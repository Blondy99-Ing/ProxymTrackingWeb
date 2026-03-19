<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Location;
use App\Models\User;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class DashboardCacheService
{
    private const KEY_STATS          = 'dash:stats';
    private const KEY_FLEET          = 'dash:fleet';
    private const KEY_FLEET_H        = 'dash:fleet:h';
    private const KEY_ALERTS         = 'dash:alerts';
    private const KEY_VERSION        = 'dash:version';
    private const KEY_DEBOUNCE       = 'dash:debounce';
    private const KEY_FLEET_RESET    = 'dash:fleet:reset';
    private const KEY_DIRTY_VEHICLES = 'dash:dirty:vehicles';
    private const KEY_DIRTY_ALERTS   = 'dash:dirty:alerts';
    private const KEY_DIRTY_STATS    = 'dash:dirty:stats';

    private const CH_EVENTS = 'dash:events';

    private const KEY_ALERT_NEW_QUEUE       = 'dash:eventq:alert_new';
    private const KEY_ALERT_PROCESSED_QUEUE = 'dash:eventq:alert_processed';

    private int $ttlStats  = 900;
    private int $ttlFleet  = 600;
    private int $ttlAlerts = 600;

    private int $gpsOfflineMinutes = 10;
    private float $movingThreshold = 5.0;
    private int $eventQueueMaxLen = 200;

    private function publish(string $event, array $data): void
    {
        try {
            Redis::publish(self::CH_EVENTS, json_encode([
                'event' => $event,
                'ts'    => now()->toISOString(),
                'data'  => $data,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
        }
    }

    private function pushEventToQueue(string $key, array $payload): void
    {
        try {
            Redis::pipeline(function ($pipe) use ($key, $payload) {
                $pipe->rpush($key, json_encode($payload, JSON_UNESCAPED_UNICODE));
                $pipe->ltrim($key, -$this->eventQueueMaxLen, -1);
                $pipe->expire($key, 300);
            });
        } catch (\Throwable $e) {
        }
    }

    public function getVersion(): int
    {
        return (int) (Redis::get(self::KEY_VERSION) ?? 0);
    }

    public function bumpVersion(): void
    {
        Redis::incr(self::KEY_VERSION);
    }

    public function bumpVersionDebounced(int $seconds = 1): void
    {
        $ok = Redis::set(self::KEY_DEBOUNCE, '1', 'EX', $seconds, 'NX');
        if ($ok) {
            $this->bumpVersion();
        }
    }

    public function getStatsFromRedis(): ?array
    {
        $json = Redis::get(self::KEY_STATS);
        return $json ? json_decode($json, true) : null;
    }

    public function getAlertsFromRedis(): array
    {
        $json = Redis::get(self::KEY_ALERTS);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function getFleetFromRedis(): array
    {
        try {
            $all = Redis::hgetall(self::KEY_FLEET_H);

            if (is_array($all) && !empty($all)) {
                $out = [];
                foreach ($all as $vehicleId => $json) {
                    $row = json_decode($json, true);
                    if (is_array($row)) {
                        $out[] = $this->applyDynamicLiveStatusOnRow($row);
                    }
                }
                return $out;
            }
        } catch (\Throwable $e) {
        }

        $json = Redis::get(self::KEY_FLEET);
        $fleet = $json ? (json_decode($json, true) ?: []) : [];

        if (!is_array($fleet)) {
            return [];
        }

        return array_map(
            fn($row) => is_array($row) ? $this->applyDynamicLiveStatusOnRow($row) : $row,
            $fleet
        );
    }

    public function getFleetVehicleRowFromRedis(int $vehicleId): ?array
    {
        try {
            $json = Redis::hget(self::KEY_FLEET_H, (string) $vehicleId);
            if (!$json) {
                return null;
            }

            $row = json_decode($json, true);
            return is_array($row) ? $this->applyDynamicLiveStatusOnRow($row) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function consumeDirtyVehicleRows(): array
    {
        $ids = Redis::smembers(self::KEY_DIRTY_VEHICLES);

        if (empty($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            Redis::del(self::KEY_DIRTY_VEHICLES);
            return [];
        }

        $rows = Redis::pipeline(function ($pipe) use ($ids) {
            foreach ($ids as $id) {
                $pipe->hget(self::KEY_FLEET_H, (string) $id);
            }
            $pipe->del(self::KEY_DIRTY_VEHICLES);
        });

        $out = [];
        $countRows = count($ids);

        for ($i = 0; $i < $countRows; $i++) {
            $json = $rows[$i] ?? null;
            if (!$json) {
                continue;
            }

            $row = json_decode($json, true);
            if (is_array($row)) {
                $out[] = $this->applyDynamicLiveStatusOnRow($row);
            }
        }

        return $out;
    }

    public function consumeDirtyAlerts(): ?array
    {
        $flag = Redis::get(self::KEY_DIRTY_ALERTS);
        if (!$flag) {
            return null;
        }

        $alerts = $this->getAlertsFromRedis();
        Redis::del(self::KEY_DIRTY_ALERTS);

        return $alerts;
    }

    public function consumeDirtyStats(): ?array
    {
        $flag = Redis::get(self::KEY_DIRTY_STATS);
        if (!$flag) {
            return null;
        }

        $stats = $this->getStatsFromRedis();
        Redis::del(self::KEY_DIRTY_STATS);

        return $stats;
    }

    public function consumeNewAlertEvent(): ?array
    {
        $json = Redis::lpop(self::KEY_ALERT_NEW_QUEUE);

        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public function consumeProcessedAlertEvent(): ?array
    {
        $json = Redis::lpop(self::KEY_ALERT_PROCESSED_QUEUE);

        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public function rebuildAlertsTop10(): array
    {
        return $this->rebuildAlerts(10);
    }

    public function markFleetResetDirty(): void
    {
        Redis::setex(self::KEY_FLEET_RESET, 60, '1');
    }

    public function consumeFleetReset(): bool
    {
        $flag = Redis::get(self::KEY_FLEET_RESET);
        if (!$flag) {
            return false;
        }

        Redis::del(self::KEY_FLEET_RESET);

        return true;
    }

    private function markVehiclesDirty(array $vehicleIds): void
    {
        $vehicleIds = array_values(array_unique(array_map('intval', $vehicleIds)));
        if (empty($vehicleIds)) {
            return;
        }

        Redis::pipeline(function ($pipe) use ($vehicleIds) {
            foreach ($vehicleIds as $id) {
                $pipe->sadd(self::KEY_DIRTY_VEHICLES, (string) $id);
            }
            $pipe->expire(self::KEY_DIRTY_VEHICLES, $this->ttlFleet);
        });
    }

    private function markAlertsDirty(): void
    {
        Redis::setex(self::KEY_DIRTY_ALERTS, 60, '1');
    }

    private function markStatsDirty(): void
    {
        Redis::setex(self::KEY_DIRTY_STATS, 60, '1');
    }

    public function rebuildStats(): array
    {
        $usersCount        = User::count();
        $vehiclesCount     = Voiture::count();
        $associationsCount = Voiture::has('utilisateur')->count();

        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $baseOpenToday = Alert::query()
            ->whereNotNull('alert_type')
            ->where('alert_type', '!=', '')
            ->where(function ($q) {
                $q->where('processed', false)->orWhereNull('processed');
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('alerted_at', [$start, $end])
                    ->orWhere(function ($qq) use ($start, $end) {
                        $qq->whereNull('alerted_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });

        $alertsCount = (clone $baseOpenToday)->count();

        $alertsByType = [
            'stolen'      => 0,
            'low_battery' => 0,
            'geofence'    => 0,
            'safe_zone'   => 0,
            'speed'       => 0,
            'offline'     => 0,
            'time_zone'   => 0,
            'engine_on'   => 0,
            'engine_off'  => 0,
            'other'       => 0,
            'unknown'     => 0,
        ];

        $rows = (clone $baseOpenToday)->get(['alert_type']);

        foreach ($rows as $row) {
            $norm = $this->normalizeAlertType((string) $row->alert_type);

            if (!array_key_exists($norm, $alertsByType)) {
                $alertsByType[$norm] = 0;
            }

            $alertsByType[$norm]++;
        }

        $payload = [
            'usersCount'        => (int) $usersCount,
            'vehiclesCount'     => (int) $vehiclesCount,
            'associationsCount' => (int) $associationsCount,
            'alertsCount'       => (int) $alertsCount,
            'alertsByType'      => $alertsByType,
        ];

        Redis::setex(self::KEY_STATS, $this->ttlStats, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $this->markStatsDirty();
        $this->bumpVersionDebounced(1);
        $this->publish('stats_patch', $payload);

        return $payload;
    }

    public function rebuildFleet(): array
    {
        $voitures = Voiture::query()
            ->with(['utilisateur:id,prenom,nom,phone'])
            ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps'])
            ->get();

        if ($voitures->isEmpty()) {
            Redis::pipeline(function ($pipe) {
                $pipe->del(self::KEY_FLEET_H);
                $pipe->setex(self::KEY_FLEET, $this->ttlFleet, json_encode([], JSON_UNESCAPED_UNICODE));
                $pipe->del(self::KEY_DIRTY_VEHICLES);
            });

            $this->markFleetResetDirty();
            $this->bumpVersionDebounced(1);
            $this->publish('fleet_rebuilt', ['count' => 0]);

            return [];
        }

        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
            $sub = Location::query()
                ->selectRaw('MAX(id) as max_id, mac_id_gps')
                ->whereIn('mac_id_gps', $macIds)
                ->groupBy('mac_id_gps');

            $latestRows = Location::query()
                ->joinSub($sub, 't', function ($join) {
                    $join->on('locations.id', '=', 't.max_id');
                })
                ->select('locations.*')
                ->get();

            foreach ($latestRows as $loc) {
                $latestByMac[(string) $loc->mac_id_gps] = $loc->toArray();
            }
        }

        $fleet = [];
        $hashPayload = [];
        $dirtyIds = [];

        foreach ($voitures as $voiture) {
            $loc = $latestByMac[(string) $voiture->mac_id_gps] ?? null;
            if (!$loc) {
                continue;
            }

            $row = $this->buildVehicleRow($voiture, $loc, null);
            if (!$row) {
                continue;
            }

            $fleet[] = $row;
            $hashPayload[(string) $voiture->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
            $dirtyIds[] = (int) $voiture->id;
        }

        Redis::pipeline(function ($pipe) use ($hashPayload, $fleet) {
            $pipe->del(self::KEY_FLEET_H);

            if (!empty($hashPayload)) {
                $pipe->hmset(self::KEY_FLEET_H, $hashPayload);
                $pipe->expire(self::KEY_FLEET_H, $this->ttlFleet);
            }

            $pipe->setex(self::KEY_FLEET, $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));
        });

        $this->markVehiclesDirty($dirtyIds);
        $this->markFleetResetDirty();
        $this->bumpVersionDebounced(1);

        $this->publish('fleet_rebuilt', ['count' => count($fleet)]);

        return $fleet;
    }

    public function updateVehicleFromLocation(Location $location, bool $bump = true): void
    {
        $this->updateFleetBatchFromLocations([$location], $bump);
    }

    public function updateFleetBatchFromLocations(iterable $locations, bool $bump = true): void
    {
        $latestByMac = [];

        foreach ($locations as $location) {
            if (!$location instanceof Location) {
                continue;
            }

            $mac = trim((string) ($location->mac_id_gps ?? ''));
            if ($mac === '') {
                continue;
            }

            $current = $latestByMac[$mac] ?? null;
            if (!$current || (int) $location->id > (int) $current->id) {
                $latestByMac[$mac] = $location;
            }
        }

        if (empty($latestByMac)) {
            return;
        }

        $macs = array_keys($latestByMac);

        $voitures = Voiture::query()
            ->whereIn('mac_id_gps', $macs)
            ->with(['utilisateur:id,prenom,nom,phone'])
            ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps'])
            ->get();

        if ($voitures->isEmpty()) {
            return;
        }

        $hashPayload = [];
        $dirtyIds = [];
        $publishedRows = [];

        foreach ($voitures as $voiture) {
            $mac = trim((string) $voiture->mac_id_gps);
            $location = $latestByMac[$mac] ?? null;

            if (!$location) {
                continue;
            }

            $incomingLocId = (int) ($location->id ?? 0);
            if ($incomingLocId > 0 && !$this->isNewerLocIdThanCached((int) $voiture->id, $incomingLocId)) {
                continue;
            }

            $existingRow = $this->getFleetVehicleRowFromRedis((int) $voiture->id);
            $row = $this->buildVehicleRow($voiture, $location->toArray(), $existingRow);

            if (!$row) {
                continue;
            }

            $hashPayload[(string) $voiture->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
            $dirtyIds[] = (int) $voiture->id;
            $publishedRows[] = $row;
        }

        if (empty($hashPayload)) {
            return;
        }

        Redis::pipeline(function ($pipe) use ($hashPayload) {
            $pipe->hmset(self::KEY_FLEET_H, $hashPayload);
            $pipe->expire(self::KEY_FLEET_H, $this->ttlFleet);
        });

        $this->markVehiclesDirty($dirtyIds);

        if ($bump) {
            $this->bumpVersionDebounced(1);
        }

        foreach ($publishedRows as $row) {
            $this->publish('vehicle_patch', [
                'vehicle_id' => (int) ($row['id'] ?? 0),
                'vehicle'    => $row,
            ]);
        }
    }

    public function rebuildAlerts(int $limit = 10): array
    {
        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $alerts = Alert::query()
            ->with(['voiture', 'voiture.utilisateur'])
            ->whereNotNull('alert_type')
            ->where('alert_type', '!=', '')
            ->where(function ($q) {
                $q->where('processed', 0)->orWhereNull('processed');
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('alerted_at', [$start, $end])
                    ->orWhere(function ($qq) use ($start, $end) {
                        $qq->whereNull('alerted_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->orderByDesc('alerted_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Alert $a) {
                $payload = $this->buildRealtimeAlertPayload($a);

                return [
                    'id' => (int) ($payload['id'] ?? 0),
                    'vehicle_id' => (int) ($payload['vehicle_id'] ?? 0),
                    'vehicle' => $payload['vehicle'] ?? null,
                    'type' => $payload['type'] ?? 'unknown',
                    'type_label' => $payload['type_label'] ?? $this->alertTypeLabel($payload['type'] ?? 'unknown'),
                    'users' => $payload['user']['full_name'] ?? ($payload['driver'] ?? null),
                    'time' => $payload['created_at'] ?? null,
                    'processed' => (bool) ($payload['is_processed'] ?? false),
                    'status' => 'Ouvert',
                    'status_color' => 'bg-red-500',
                    'raw_type' => $a->alert_type,
                    'message' => $payload['message'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'location' => $payload['location'] ?? null,
                    'lat' => $payload['lat'] ?? null,
                    'lng' => $payload['lng'] ?? null,
                    'speed' => $payload['speed'] ?? null,
                    'driver' => $payload['driver'] ?? 'Non assigné',
                    'user' => $payload['user'] ?? null,
                    'immatriculation' => $payload['immatriculation'] ?? '—',
                    'created_at' => $payload['created_at'] ?? '—',
                    'is_processed' => (bool) ($payload['is_processed'] ?? false),
                    'is_read' => (bool) ($payload['is_read'] ?? false),
                    'scope' => $payload['scope'] ?? [
                        'day' => now()->toDateString(),
                        'processed' => false,
                    ],
                ];
            })
            ->values()
            ->toArray();

        Redis::setex(self::KEY_ALERTS, $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));

        $this->markAlertsDirty();
        $this->rebuildStats();

        $this->publish('alerts_top', [
            'top' => $alerts,
            'scope' => [
                'day' => now()->toDateString(),
                'processed' => false,
            ],
        ]);

        return $alerts;
    }

    public function publishNewAlertEvent(Alert $alert, bool $includeTop = true): void
    {
        if ((bool) $alert->processed === true) {
            return;
        }

        if (!$this->isTodayAlert($alert->alerted_at ?? $alert->created_at)) {
            return;
        }

        if ($includeTop) {
            $this->rebuildAlerts(10);
        } else {
            $this->markAlertsDirty();
            $this->rebuildStats();
        }

        $payload = $this->buildRealtimeAlertPayload($alert);

        // Push to queue BEFORE bumping version so the stream loop always finds the
        // event waiting when it wakes up and sees the new version.
        $this->pushEventToQueue(self::KEY_ALERT_NEW_QUEUE, $payload);
        $this->publish('alert_new', $payload);

        // Use a hard bump (not debounced) so that even if rebuildAlerts/rebuildStats
        // already fired a debounced bump within the same second, the stream loop is
        // guaranteed to detect a version change and consume the alert.new queue entry.
        $this->bumpVersion();
    }

    public function publishResolvedAlertEvent(Alert $alert, bool $includeTop = true): void
    {
        if (!$this->isTodayAlert($alert->alerted_at ?? $alert->created_at)) {
            return;
        }

        if ($includeTop) {
            $this->rebuildAlerts(10);
        } else {
            $this->markAlertsDirty();
            $this->rebuildStats();
        }

        $payload = $this->buildRealtimeAlertPayload($alert);
        $payload['is_processed'] = true;

        $this->pushEventToQueue(self::KEY_ALERT_PROCESSED_QUEUE, $payload);
        $this->publish('alert_processed', $payload);

        // Hard bump — same reason as publishNewAlertEvent.
        $this->bumpVersion();
    }

    private function buildRealtimeAlertPayload(Alert $alert): array
    {
        $alert->loadMissing([
            'voiture',
            'voiture.utilisateur',
        ]);

        $voiture = $alert->voiture;
        $user = $voiture?->utilisateur?->first();

        $fullName = trim((string) (($user->nom ?? '') . ' ' . ($user->prenom ?? '')));
        if ($fullName === '') {
            $fullName = trim((string) (($user->prenom ?? '') . ' ' . ($user->nom ?? '')));
        }

        $phone = trim((string) ($user->phone ?? ''));
        $type = $this->normalizeAlertType((string) ($alert->alert_type ?? $alert->type ?? ''));

        return [
            'id' => (int) $alert->id,
            'type' => $type,
            'alert_type' => $type,
            'type_label' => $this->alertTypeLabel($type),

            'vehicle_id' => (int) ($alert->voiture_id ?? 0),
            'voiture_id' => (int) ($alert->voiture_id ?? 0),

            'vehicle' => [
                'id' => (int) ($alert->voiture_id ?? 0),
                'label' => $voiture
                    ? trim(($voiture->immatriculation ?? '—') . ' (' . ($voiture->marque ?? 'Véhicule') . ')')
                    : '—',
                'immatriculation' => $voiture?->immatriculation ?? '—',
                'marque' => $voiture?->marque ?? null,
                'model' => $voiture?->model ?? null,
            ],

            'immatriculation' => $voiture?->immatriculation ?? '—',
            'message' => $alert->message,
            'description' => $alert->message,
            'location' => $alert->location ?? $alert->message,
            'lat' => $alert->lat ?? null,
            'lng' => $alert->lng ?? null,
            'speed' => $alert->speed ?? null,

            'driver' => $fullName ?: 'Non assigné',

            'user' => [
                'id' => $user?->id,
                'nom' => $user?->nom,
                'prenom' => $user?->prenom,
                'full_name' => $fullName ?: 'Non assigné',
                'phone' => $phone ?: null,
                'call_url' => $phone ? ('tel:' . preg_replace('/\s+/', '', $phone)) : null,
            ],

            'created_at' => ($alert->alerted_at ?? $alert->created_at)
                ? Carbon::parse($alert->alerted_at ?? $alert->created_at)->format('d/m/Y H:i:s')
                : '—',

            'alerted_at' => ($alert->alerted_at ?? $alert->created_at)
                ? Carbon::parse($alert->alerted_at ?? $alert->created_at)->toISOString()
                : null,

            'is_processed' => (bool) ($alert->processed ?? false),
            'is_read' => (bool) ($alert->read ?? false),

            'scope' => [
                'day' => now()->toDateString(),
                'processed' => false,
            ],
        ];
    }

    public function refreshOfflineStatusesFromRedis(): array
    {
        $fleet = $this->getFleetFromRedis();
        if (!is_array($fleet) || empty($fleet)) {
            return ['updated' => 0, 'changed' => 0];
        }

        $changed = 0;
        $hashPayload = [];
        $dirtyIds = [];

        foreach ($fleet as $vehicle) {
            if (!is_array($vehicle)) {
                continue;
            }

            $oldVehicle = $vehicle;
            $vehicle = $this->applyDynamicLiveStatusOnRow($vehicle);

            if ($vehicle !== $oldVehicle) {
                $changed++;

                if (isset($vehicle['id'])) {
                    $hashPayload[(string) $vehicle['id']] = json_encode($vehicle, JSON_UNESCAPED_UNICODE);
                    $dirtyIds[] = (int) $vehicle['id'];
                }
            }
        }

        if ($changed > 0) {
            Redis::pipeline(function ($pipe) use ($hashPayload) {
                if (!empty($hashPayload)) {
                    $pipe->hmset(self::KEY_FLEET_H, $hashPayload);
                    $pipe->expire(self::KEY_FLEET_H, $this->ttlFleet);
                }
            });

            $this->markVehiclesDirty($dirtyIds);
            $this->bumpVersionDebounced(1);
        }

        return ['updated' => count($fleet), 'changed' => $changed];
    }

    public function rebuildAll(): array
    {
        $stats  = $this->rebuildStats();
        $fleet  = $this->rebuildFleet();
        $alerts = $this->rebuildAlerts(10);

        return compact('stats', 'fleet', 'alerts');
    }

    private function buildVehicleRow(Voiture $voiture, array $locationData, ?array $existingRow = null): ?array
    {
        $lat = $locationData['latitude'] ?? null;
        $lon = $locationData['longitude'] ?? null;

        if ($lat === null || $lon === null) {
            return null;
        }

        $firstUser = $voiture->utilisateur?->first();

        $users = $voiture->utilisateur
            ?->map(fn($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')))
            ->filter()
            ->implode(', ');

        $driverLabel = $users ?: 'Non associé';
        $userId = $firstUser?->id;
        $userProfileUrl = $userId ? route('users.profile', ['id' => $userId]) : null;

        $lastSeen = $locationData['heart_time'] ?? $locationData['sys_time'] ?? $locationData['datetime'] ?? null;
        $gpsOnline = $this->isGpsOnline($lastSeen);

        $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($locationData['status'] ?? null);
        $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

        $previousLiveStatus = (array) ($existingRow['live_status'] ?? []);
        $liveStatus = $this->buildLiveStatusFromLocation($locationData, $previousLiveStatus);

        return [
            'id'              => (int) $voiture->id,
            'immatriculation' => $voiture->immatriculation,
            'marque'          => $voiture->marque,
            'model'           => $voiture->model,
            'mac_id_gps'      => $voiture->mac_id_gps,

            'driver' => [
                'label' => $driverLabel,
                'id'    => $userId,
            ],

            'users'            => $users,
            'user_id'          => $userId,
            'user_profile_url' => $userProfileUrl,

            'lat' => (float) $lat,
            'lon' => (float) $lon,

            'engine' => [
                'cut'         => $engineCut,
                'engineState' => $engineDecoded['engineState'] ?? 'UNKNOWN',
            ],

            'gps' => [
                'online'    => $gpsOnline,
                'state'     => $gpsOnline === true ? 'ONLINE' : 'OFFLINE',
                'last_seen' => $lastSeen ? (string) $lastSeen : null,
            ],

            'live_status' => $liveStatus,
            'loc_id'      => (int) ($locationData['id'] ?? 0),
        ];
    }

    private function isNewerLocIdThanCached(int $vehicleId, int $incomingLocId): bool
    {
        try {
            $json = Redis::hget(self::KEY_FLEET_H, (string) $vehicleId);
            if (!$json) {
                return true;
            }

            $row = json_decode($json, true);
            $cachedLocId = (int) ($row['loc_id'] ?? 0);

            return $incomingLocId >= $cachedLocId;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function applyDynamicLiveStatusOnRow(array $vehicle): array
    {
        $oldLiveStatus = (array) ($vehicle['live_status'] ?? []);
        if (!empty($oldLiveStatus)) {
            $newLiveStatus = $this->recomputeOfflineLiveStatusFromRedis($oldLiveStatus);
            $vehicle['live_status'] = $newLiveStatus;

            $vehicle['gps']['online'] = $newLiveStatus['is_online'] ?? null;
            $vehicle['gps']['state'] = ($newLiveStatus['is_online'] ?? null) === true ? 'ONLINE' : 'OFFLINE';
            $vehicle['gps']['last_seen'] = (string) (
                $newLiveStatus['heart_time']
                ?? $newLiveStatus['datetime']
                ?? $newLiveStatus['sys_time']
                ?? ($vehicle['gps']['last_seen'] ?? '')
            );
        }

        return $vehicle;
    }

    private function isGpsOnline($lastSeen): ?bool
    {
        $ms = $this->toMs($lastSeen);
        if (!$ms) {
            return null;
        }

        $diffMs = now()->getTimestampMs() - $ms;
        return $diffMs <= ($this->gpsOfflineMinutes * 60 * 1000);
    }

    private function durationHuman(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($days > 0) {
            return "{$days}j {$hours}h {$minutes}min";
        }
        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        }
        if ($minutes > 0) {
            return "{$minutes}min" . ($secs > 0 ? " {$secs}s" : '');
        }

        return "{$secs}s";
    }

    private function toMs($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $n = (int) $value;
            if ($n <= 0) {
                return null;
            }
            if ($n >= 1000000000000) {
                return $n;
            }
            if ($n >= 1000000000) {
                return $n * 1000;
            }
        }

        if (is_string($value)) {
            $s = trim((string) $value);
            if ($s === '') {
                return null;
            }
            if (is_numeric($s)) {
                return $this->toMs((int) $s);
            }

            try {
                return Carbon::parse($s)->getTimestampMs();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function msToDateTime(?int $ms): ?string
    {
        if (!$ms || $ms <= 0) {
            return null;
        }

        try {
            return Carbon::createFromTimestampMs($ms)
                ->setTimezone(config('app.timezone'))
                ->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildLiveStatusFromLocation(array $location, ?array $previousLiveStatus = null): array
    {
        $offlineThresholdMinutes = $this->gpsOfflineMinutes;
        $offlineThresholdMs = $offlineThresholdMinutes * 60 * 1000;

        $speedRaw = $location['speed'] ?? $location['su'] ?? null;
        $speed = is_numeric($speedRaw) ? (float) $speedRaw : null;

        $heartMs = $this->toMs($location['heart_time'] ?? null);
        $gpsMs   = $this->toMs($location['datetime'] ?? null);
        $sysMs   = $this->toMs($location['sys_time'] ?? null);

        $nowMs = now()->getTimestampMs();
        $onlineRefMs = $heartMs ?: $gpsMs ?: $sysMs;
        $isOnline = $onlineRefMs ? (($nowMs - $onlineRefMs) < $offlineThresholdMs) : false;

        $prevMovementState = (string) ($previousLiveStatus['movement_state'] ?? '');
        $prevStoppedSinceMs = isset($previousLiveStatus['stopped_since_ms']) ? (int) $previousLiveStatus['stopped_since_ms'] : null;
        $prevOfflineSinceMs = isset($previousLiveStatus['offline_since_ms']) ? (int) $previousLiveStatus['offline_since_ms'] : null;

        $movementState = 'UNKNOWN';
        $connectivityState = 'UNKNOWN';
        $uiStatus = 'UNKNOWN';
        $isMoving = null;

        $stoppedSinceMs = $prevStoppedSinceMs;
        $offlineSinceMs = $prevOfflineSinceMs;

        if ($isOnline === false) {
            $movementState = 'OFFLINE';
            $connectivityState = 'OFFLINE';
            $uiStatus = 'OFFLINE';
            $isMoving = null;

            if (!$offlineSinceMs) {
                $offlineSinceMs = $onlineRefMs ?: $nowMs;
            }
        } else {
            $offlineSinceMs = null;

            if ($speed !== null && $speed >= $this->movingThreshold) {
                $movementState = 'MOVING';
                $connectivityState = 'ONLINE_MOVING';
                $uiStatus = 'ONLINE_MOVING';
                $isMoving = true;
                $stoppedSinceMs = null;
            } elseif ($speed !== null && $speed >= 0) {
                $movementState = 'STOPPED';
                $connectivityState = 'ONLINE_STATIONARY';
                $uiStatus = 'ONLINE_STOPPED';
                $isMoving = false;

                if (!$stoppedSinceMs || $prevMovementState !== 'STOPPED') {
                    $stoppedSinceMs = $gpsMs ?: $sysMs ?: $onlineRefMs ?: $nowMs;
                }
            }
        }

        $stoppedSinceSeconds = $stoppedSinceMs ? max(0, (int) floor(($nowMs - $stoppedSinceMs) / 1000)) : null;
        $offlineSinceSeconds = $offlineSinceMs ? max(0, (int) floor(($nowMs - $offlineSinceMs) / 1000)) : null;

        return [
            'ui_status' => $uiStatus,
            'movement_state' => $movementState,
            'connectivity_state' => $connectivityState,
            'is_online' => $isOnline,
            'is_moving' => $isMoving,
            'speed' => $speed,
            'speed_raw' => $speedRaw,
            'moving_threshold' => $this->movingThreshold,
            'stopped_since_ms' => $stoppedSinceMs,
            'stopped_since_seconds' => $stoppedSinceSeconds,
            'stopped_since_human' => $this->durationHuman($stoppedSinceSeconds),
            'offline_since_ms' => $offlineSinceMs,
            'offline_since_seconds' => $offlineSinceSeconds,
            'offline_since_human' => $this->durationHuman($offlineSinceSeconds),
            'datetime' => $this->msToDateTime($gpsMs),
            'heart_time' => $this->msToDateTime($heartMs),
            'sys_time' => $this->msToDateTime($sysMs),
            'heart_time_ms' => $heartMs,
            'datetime_ms' => $gpsMs,
            'sys_time_ms' => $sysMs,
            'updated_at_ms' => $nowMs,
            'offline_threshold_minutes' => $offlineThresholdMinutes,
        ];
    }

    private function recomputeOfflineLiveStatusFromRedis(array $liveStatus): array
    {
        $offlineThresholdMinutes = (int) ($liveStatus['offline_threshold_minutes'] ?? $this->gpsOfflineMinutes);
        $offlineThresholdMs = $offlineThresholdMinutes * 60 * 1000;
        $nowMs = now()->getTimestampMs();

        $heartMs = isset($liveStatus['heart_time_ms']) ? (int) $liveStatus['heart_time_ms'] : null;
        $datetimeMs = isset($liveStatus['datetime_ms']) ? (int) $liveStatus['datetime_ms'] : null;
        $sysMs = isset($liveStatus['sys_time_ms']) ? (int) $liveStatus['sys_time_ms'] : null;

        $onlineRefMs = $heartMs ?: $datetimeMs ?: $sysMs;
        $isOnline = $onlineRefMs ? (($nowMs - $onlineRefMs) < $offlineThresholdMs) : false;

        $offlineSinceMs = isset($liveStatus['offline_since_ms']) ? (int) $liveStatus['offline_since_ms'] : null;
        $movementState = (string) ($liveStatus['movement_state'] ?? 'UNKNOWN');

        if ($isOnline === false) {
            if (!$offlineSinceMs) {
                $offlineSinceMs = $onlineRefMs ?: $nowMs;
            }

            $offlineSinceSeconds = max(0, (int) floor(($nowMs - $offlineSinceMs) / 1000));
            $liveStatus['ui_status'] = 'OFFLINE';
            $liveStatus['movement_state'] = 'OFFLINE';
            $liveStatus['connectivity_state'] = 'OFFLINE';
            $liveStatus['is_online'] = false;
            $liveStatus['is_moving'] = null;
            $liveStatus['offline_since_ms'] = $offlineSinceMs;
            $liveStatus['offline_since_seconds'] = $offlineSinceSeconds;
            $liveStatus['offline_since_human'] = $this->durationHuman($offlineSinceSeconds);
        } else {
            $liveStatus['is_online'] = true;
            $liveStatus['offline_since_ms'] = null;
            $liveStatus['offline_since_seconds'] = null;
            $liveStatus['offline_since_human'] = null;

            if ($movementState === 'STOPPED') {
                $liveStatus['ui_status'] = 'ONLINE_STOPPED';
                $liveStatus['connectivity_state'] = 'ONLINE_STATIONARY';
                $liveStatus['is_moving'] = false;
            } elseif ($movementState === 'MOVING') {
                $liveStatus['ui_status'] = 'ONLINE_MOVING';
                $liveStatus['connectivity_state'] = 'ONLINE_MOVING';
                $liveStatus['is_moving'] = true;
            }
        }

        $stoppedSinceMs = isset($liveStatus['stopped_since_ms']) ? (int) $liveStatus['stopped_since_ms'] : null;
        if ($stoppedSinceMs && ($liveStatus['movement_state'] ?? null) === 'STOPPED') {
            $stoppedSinceSeconds = max(0, (int) floor(($nowMs - $stoppedSinceMs) / 1000));
            $liveStatus['stopped_since_seconds'] = $stoppedSinceSeconds;
            $liveStatus['stopped_since_human'] = $this->durationHuman($stoppedSinceSeconds);
        }

        $liveStatus['updated_at_ms'] = $nowMs;

        return $liveStatus;
    }

    private function normalizeAlertType(?string $t): string
    {
        $t = strtolower(trim((string) $t));
        if ($t === '') {
            return 'unknown';
        }

        return match ($t) {
            'overspeed', 'speeding', 'speed' => 'speed',
            'geo_fence', 'geofence', 'geofence_enter', 'geofence_exit', 'geofence_breach' => 'geofence',
            'safezone', 'safe-zone', 'safe_zone' => 'safe_zone',
            'battery_low', 'lowbattery', 'low_battery' => 'low_battery',
            'timezone', 'time_zone', 'time-zone' => 'time_zone',
            'unauthorized', 'offline' => 'offline',
            'engine_on' => 'engine_on',
            'engine_off' => 'engine_off',
            'other' => 'other',
            default => $t,
        };
    }

    private function alertTypeLabel(string $type): string
    {
        return match ($type) {
            'stolen'      => 'Vol',
            'low_battery' => 'Batterie faible',
            'geofence'    => 'GeoFence',
            'safe_zone'   => 'Safe Zone',
            'speed'       => 'Survitesse',
            'offline'     => 'Offline',
            'time_zone'   => 'Time Zone',
            'engine_on'   => 'Moteur ON',
            'engine_off'  => 'Moteur OFF',
            'other'       => 'Autres',
            default       => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    private function isTodayAlert($date): bool
    {
        // If no date is available we give the benefit of the doubt and treat the
        // alert as today's — better to trigger a false notification than to
        // silently swallow a real one.
        if (!$date) {
            return true;
        }

        try {
            return Carbon::parse($date)->isToday();
        } catch (\Throwable $e) {
            return true;
        }
    }
}