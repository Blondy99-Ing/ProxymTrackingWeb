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
    private const KEY_STATS   = 'dash:stats';
    private const KEY_FLEET   = 'dash:fleet';
    private const KEY_FLEET_H = 'dash:fleet:h';
    private const KEY_ALERTS  = 'dash:alerts';     // top (du jour / non traitées)
    private const KEY_VERSION = 'dash:version';

    // ✅ canal Pub/Sub pour Node SSE
    private const CH_EVENTS = 'dash:events';

    private int $ttlStats  = 60;
    private int $ttlFleet  = 120;
    private int $ttlAlerts = 30;

    // OFFLINE si last seen > X minutes
    private int $gpsOfflineMinutes = 10;

    // =========================
    // Helpers "today"
    // =========================
    private function todayKeySuffix(): string
    {
        return now()->toDateString(); // "YYYY-MM-DD"
    }

    private function keyAlertsTotalToday(): string
    {
        return 'dash:alerts:total:' . $this->todayKeySuffix();
    }

    private function keyAlertsByTypeToday(): string
    {
        return 'dash:alerts:by_type:' . $this->todayKeySuffix();
    }

    private function todayStart(): Carbon
    {
        return now()->startOfDay();
    }

    private function todayEnd(): Carbon
    {
        return now()->endOfDay();
    }

    private function isTodayAlert(?Carbon $dt): bool
    {
        if (!$dt) return false;
        return $dt->isSameDay(now());
    }

    // =========================
    // Pub/Sub Events (pour Node SSE)
    // =========================
    private function publish(string $event, array $data): void
    {
        try {
            Redis::publish(self::CH_EVENTS, json_encode([
                'event' => $event,
                'ts'    => now()->toISOString(),
                'data'  => $data,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // no-op
        }
    }

    // =========================
    // Version (fallback legacy)
    // =========================
    public function getVersion(): int
    {
        return (int)(Redis::get(self::KEY_VERSION) ?? 0);
    }

    public function bumpVersion(): void
    {
        Redis::incr(self::KEY_VERSION);
    }

    // =========================
    // STATS (today + unprocessed)
    // =========================
    public function getStatsFromRedis(): ?array
    {
        $json = Redis::get(self::KEY_STATS);
        return $json ? json_decode($json, true) : null;
    }

    public function rebuildStats(): array
    {
        $usersCount        = User::count();
        $vehiclesCount     = Voiture::count();
        $associationsCount = Voiture::has('utilisateur')->count();

        // ✅ alertes du jour / non traitées : priorité aux compteurs Redis
        $alertsTotalKey = $this->keyAlertsTotalToday();
        $alertsByTypeKey = $this->keyAlertsByTypeToday();

        $alertsTotal = Redis::get($alertsTotalKey);
        $alertsByType = Redis::hgetall($alertsByTypeKey);

        $alertsCount = null;
        $byType = null;

        if ($alertsTotal !== null && is_array($alertsByType)) {
            $alertsCount = (int)$alertsTotal;
            $byType = [];
            foreach ($alertsByType as $k => $v) {
                $byType[(string)$k] = (int)$v;
            }
        } else {
            // fallback DB (si Redis vide)
            $alertsCount = Alert::query()
                ->where('processed', false)
                ->whereBetween('alerted_at', [$this->todayStart(), $this->todayEnd()])
                ->count();

            $rows = Alert::query()
                ->where('processed', false)
                ->whereBetween('alerted_at', [$this->todayStart(), $this->todayEnd()])
                ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
                ->groupBy('t')
                ->get();

            $byType = [];
            foreach ($rows as $r) {
                $byType[(string)$r->t] = (int)$r->c;
            }

            $this->setAlertCountersToday((int)$alertsCount, $byType);
        }

        $payload = [
            'usersCount'        => (int)$usersCount,
            'vehiclesCount'     => (int)$vehiclesCount,
            'associationsCount' => (int)$associationsCount,

            'alertsCount'       => (int)$alertsCount,
            'alertsByType'      => $byType,
            'alertsScope'       => [
                'day' => $this->todayKeySuffix(),
                'processed' => false,
            ],
        ];

        Redis::setex(self::KEY_STATS, $this->ttlStats, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion();

        // ✅ patch Node SSE
        $this->publish('stats_patch', [
            'usersCount'        => (int)$usersCount,
            'vehiclesCount'     => (int)$vehiclesCount,
            'associationsCount' => (int)$associationsCount,
            'alertsCount'       => (int)$alertsCount,
            'alertsByType'      => $byType,
            'alertsScope'       => $payload['alertsScope'],
        ]);

        return $payload;
    }

    // =========================
    // ALERT COUNTERS (today)
    // =========================
    private function setAlertCountersToday(int $total, array $byType): void
    {
        $totalKey = $this->keyAlertsTotalToday();
        $byTypeKey = $this->keyAlertsByTypeToday();

        try {
            Redis::set($totalKey, (string)$total);
            Redis::del($byTypeKey);
            if (!empty($byType)) {
                Redis::hmset($byTypeKey, array_map(fn($v) => (string)((int)$v), $byType));
            }
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function getAlertsByTypeCountersToday(): array
    {
        $byTypeKey = $this->keyAlertsByTypeToday();
        $raw = [];
        try { $raw = Redis::hgetall($byTypeKey); } catch (\Throwable $e) {}

        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $k => $v) $out[(string)$k] = (int)$v;
        }
        return $out;
    }

    // ✅ AJOUT: total du jour (pour alerts_summary dans SSE)
    public function getAlertsTotalTodayCounter(): int
    {
        $totalKey = $this->keyAlertsTotalToday();
        try {
            return (int) (Redis::get($totalKey) ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * ✅ appelé uniquement si alerte du jour ET non traitée (création)
     */
    public function onNewAlertCountersToday(string $type): void
    {
        $type = $type !== '' ? $type : 'unknown';

        $totalKey = $this->keyAlertsTotalToday();
        $byTypeKey = $this->keyAlertsByTypeToday();

        try {
            Redis::pipeline(function ($pipe) use ($type, $totalKey, $byTypeKey) {
                $pipe->incr($totalKey);
                $pipe->hincrby($byTypeKey, $type, 1);
            });
        } catch (\Throwable $e) {}

        $this->publish('stats_patch', [
            'alertsCount'  => (int)(Redis::get($totalKey) ?? 0),
            'alertsByType' => $this->getAlertsByTypeCountersToday(),
            'alertsScope'  => ['day' => $this->todayKeySuffix(), 'processed' => false],
        ]);
    }

    public function onResolveAlertCountersToday(string $type): void
    {
        $type = $type !== '' ? $type : 'unknown';

        $totalKey = $this->keyAlertsTotalToday();
        $byTypeKey = $this->keyAlertsByTypeToday();

        try {
            Redis::pipeline(function ($pipe) use ($type, $totalKey, $byTypeKey) {
                $pipe->decr($totalKey);
                $pipe->hincrby($byTypeKey, $type, -1);
            });
        } catch (\Throwable $e) {}

        $this->publish('stats_patch', [
            'alertsCount'  => (int)(Redis::get($totalKey) ?? 0),
            'alertsByType' => $this->getAlertsByTypeCountersToday(),
            'alertsScope'  => ['day' => $this->todayKeySuffix(), 'processed' => false],
        ]);
    }

    // =========================
    // FLEET
    // =========================
    public function getFleetFromRedis(): array
    {
        try {
            $all = Redis::hgetall(self::KEY_FLEET_H);
            if (is_array($all) && !empty($all)) {
                $out = [];
                foreach ($all as $vehicleId => $json) {
                    $row = json_decode($json, true);
                    if (is_array($row)) $out[] = $row;
                }
                return $out;
            }
        } catch (\Throwable $e) {}

        $json = Redis::get(self::KEY_FLEET);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function rebuildFleet(): array
    {
        $voitures = Voiture::query()
            ->with(['utilisateur:id,prenom,nom'])
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->get();

        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
            $latest = Location::query()
                ->select(['id','mac_id_gps','latitude','longitude','heart_time','sys_time','datetime','status','speed'])
                ->whereIn('mac_id_gps', $macIds)
                ->orderByDesc('datetime')
                ->get()
                ->groupBy('mac_id_gps')
                ->map(fn($g) => $g->first());

            $latestByMac = $latest->toArray();
        }

        $fleet = [];
        $hashPayload = [];

        foreach ($voitures as $v) {
            $loc = $latestByMac[$v->mac_id_gps] ?? null;
            if (!$loc) continue;

            $lat = $loc['latitude'] ?? null;
            $lon = $loc['longitude'] ?? null;
            if (!$lat || !$lon) continue;

            $users = $v->utilisateur
                ? $v->utilisateur->map(fn($u) => trim(($u->prenom ?? '').' '.($u->nom ?? '')))
                    ->filter()->implode(', ')
                : null;

            $firstUser = $v->utilisateur?->first();
            $userId = $firstUser?->id;
            $userProfileUrl = $userId ? route('users.profile', ['id' => $userId]) : null;

            $lastSeen = $loc['heart_time'] ?? $loc['sys_time'] ?? $loc['datetime'] ?? null;
            $gpsOnline = $this->isGpsOnline($lastSeen);

            $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($loc['status'] ?? null);
            $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

            $row = [
                'id'               => (int)$v->id,
                'immatriculation'  => $v->immatriculation,
                'marque'           => $v->marque,
                'model'            => $v->model,
                'users'            => $users,
                'user_id'          => $userId,
                'user_profile_url' => $userProfileUrl,
                'lat'              => (float)$lat,
                'lon'              => (float)$lon,
                'engine' => [
                    'cut' => $engineCut,
                    'engineState' => $engineDecoded['engineState'] ?? 'UNKNOWN',
                ],
                'gps' => [
                    'online'    => $gpsOnline,
                    'state'     => $gpsOnline === true ? 'ONLINE' : 'OFFLINE',
                    'last_seen' => (string)$lastSeen,
                ],
            ];

            $fleet[] = $row;
            $hashPayload[(string)$v->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        Redis::del(self::KEY_FLEET_H);
        if (!empty($hashPayload)) {
            Redis::hmset(self::KEY_FLEET_H, $hashPayload);
            Redis::expire(self::KEY_FLEET_H, $this->ttlFleet);
        }

        Redis::setex(self::KEY_FLEET, $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));

        $this->bumpVersion();
        $this->publish('fleet_rebuilt', ['count' => count($fleet)]);

        return $fleet;
    }

    public function updateVehicleFromLocation(Location $location): void
    {
        $macId = trim((string)$location->mac_id_gps);
        if ($macId === '') return;

        $voiture = Voiture::query()
            ->with(['utilisateur:id,prenom,nom'])
            ->where('mac_id_gps', $macId)
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->first();

        if (!$voiture) return;

        $lat = $location->latitude;
        $lon = $location->longitude;
        if (!$lat || !$lon) return;

        $users = $voiture->utilisateur
            ? $voiture->utilisateur->map(fn($u) => trim(($u->prenom ?? '').' '.($u->nom ?? '')))
                ->filter()->implode(', ')
            : null;

        $firstUser = $voiture->utilisateur?->first();
        $userId = $firstUser?->id;
        $userProfileUrl = $userId ? route('users.profile', ['id' => $userId]) : null;

        $lastSeen = $location->heart_time ?? $location->sys_time ?? $location->datetime ?? null;
        $gpsOnline = $this->isGpsOnline($lastSeen);

        $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($location->status ?? null);
        $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

        $row = [
            'id'               => (int)$voiture->id,
            'immatriculation'  => $voiture->immatriculation,
            'marque'           => $voiture->marque,
            'model'            => $voiture->model,
            'users'            => $users,
            'user_id'          => $userId,
            'user_profile_url' => $userProfileUrl,
            'lat'              => (float)$lat,
            'lon'              => (float)$lon,
            'engine' => [
                'cut' => $engineCut,
                'engineState' => $engineDecoded['engineState'] ?? 'UNKNOWN',
            ],
            'gps' => [
                'online'    => $gpsOnline,
                'state'     => $gpsOnline === true ? 'ONLINE' : 'OFFLINE',
                'last_seen' => (string)$lastSeen,
            ],
        ];

        Redis::hset(self::KEY_FLEET_H, (string)$voiture->id, json_encode($row, JSON_UNESCAPED_UNICODE));
        Redis::expire(self::KEY_FLEET_H, $this->ttlFleet);

        $this->bumpVersion();

        $this->publish('vehicle_patch', [
            'vehicle_id' => (int)$voiture->id,
            'vehicle'    => $row,
        ]);
    }

    private function isGpsOnline($lastSeen): ?bool
    {
        if (!$lastSeen) return null;

        try {
            $dt = Carbon::parse($lastSeen);
            return $dt->diffInMinutes(now()) <= $this->gpsOfflineMinutes;
        } catch (\Throwable) {
            return null;
        }
    }

    // =========================
    // ALERTS (table = today + non processed)
    // =========================
    public function getAlertsFromRedis(): array
    {
        $json = Redis::get(self::KEY_ALERTS);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function rebuildAlertsTop10(): array
    {
        return $this->rebuildAlerts(10);
    }

    public function rebuildAlerts(int $limit = 10): array
    {
        $alerts = Alert::with(['voiture.utilisateur'])
            ->where('processed', false)
            ->whereBetween('alerted_at', [$this->todayStart(), $this->todayEnd()])
            ->orderBy('alerted_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Alert $a) {
                $v = $a->voiture;

                $users = $v?->utilisateur
                    ?->map(fn($u) => trim(($u->prenom ?? '').' '.($u->nom ?? '')))
                    ->filter()
                    ->implode(', ');

                $type = $a->alert_type ?? 'unknown';

                return [
                    'id'           => (int)$a->id,
                    'vehicle_id'   => (int)($a->voiture_id ?? 0),
                    'vehicle'      => $v?->immatriculation ?? 'N/A',
                    'type'         => $type,
                    'users'        => $users ?: null,
                    'time'         => optional($a->alerted_at)->format('d/m/Y H:i:s'),
                    'processed'    => false,
                    'status'       => 'Ouvert',
                    'status_color' => 'bg-red-500',
                ];
            })
            ->values()
            ->toArray();

        Redis::setex(self::KEY_ALERTS, $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));

        // ✅ recale compteurs du jour (non traitées)
        $alertsCount = Alert::query()
            ->where('processed', false)
            ->whereBetween('alerted_at', [$this->todayStart(), $this->todayEnd()])
            ->count();

        $rows = Alert::query()
            ->where('processed', false)
            ->whereBetween('alerted_at', [$this->todayStart(), $this->todayEnd()])
            ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
            ->groupBy('t')
            ->get();

        $byType = [];
        foreach ($rows as $r) $byType[(string)$r->t] = (int)$r->c;

        $this->setAlertCountersToday((int)$alertsCount, $byType);

        $this->bumpVersion();

        $this->publish('stats_patch', [
            'alertsCount'  => (int)$alertsCount,
            'alertsByType' => $byType,
            'alertsScope'  => ['day' => $this->todayKeySuffix(), 'processed' => false],
        ]);

        $this->publish('alerts_top', [
            'top' => $alerts,
            'scope' => ['day' => $this->todayKeySuffix(), 'processed' => false],
        ]);

        return $alerts;
    }

    public function publishNewAlertEvent(Alert $a, bool $includeTop = true): void
    {
        if ((bool)$a->processed === true) return;
        if (!$this->isTodayAlert($a->alerted_at)) return;

        $type = (string)($a->alert_type ?? 'unknown');

        $this->onNewAlertCountersToday($type);

        $top = $includeTop ? $this->rebuildAlertsTop10() : null;

        $this->publish('alert_new', [
            'id'         => (int)$a->id,
            'type'       => $type,
            'vehicle_id' => (int)($a->voiture_id ?? 0),
            'alerted_at' => optional($a->alerted_at)->toISOString(),
            'top'        => $top,
            'scope'      => ['day' => $this->todayKeySuffix(), 'processed' => false],
        ]);
    }

    public function publishResolvedAlertEvent(Alert $a, bool $includeTop = true): void
    {
        if (!$this->isTodayAlert($a->alerted_at)) return;

        $type = (string)($a->alert_type ?? 'unknown');

        $this->onResolveAlertCountersToday($type);

        $top = $includeTop ? $this->rebuildAlertsTop10() : null;

        $this->publish('alert_processed', [
            'id'         => (int)$a->id,
            'type'       => $type,
            'vehicle_id' => (int)($a->voiture_id ?? 0),
            'top'        => $top,
            'scope'      => ['day' => $this->todayKeySuffix(), 'processed' => false],
        ]);
    }

    // =========================
    // ALL
    // =========================
    public function rebuildAll(): array
    {
        $stats  = $this->rebuildStats();
        $fleet  = $this->rebuildFleet();
        $alerts = $this->rebuildAlerts(10);

        return compact('stats','fleet','alerts');
    }
}