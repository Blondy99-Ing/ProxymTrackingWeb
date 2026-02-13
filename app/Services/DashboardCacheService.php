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
    private const KEY_FLEET   = 'dash:fleet';          // JSON array (fallback / rebuild complet)
    private const KEY_FLEET_H = 'dash:fleet:h';        // Redis HASH: vehicle_id => JSON item (temps réel)
    private const KEY_ALERTS  = 'dash:alerts';
    private const KEY_VERSION = 'dash:version';

    private int $ttlStats  = 60;
    private int $ttlFleet  = 120; // plus long car maj en "patch"
    private int $ttlAlerts = 30;

    // OFFLINE si last seen > X minutes
    private int $gpsOfflineMinutes = 10;

    // =========================
    // Version
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
    // STATS
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

        // non traitées
        $alertsCount = Alert::where('processed', false)->count();

        // par type (IMPORTANT : colonne réelle = alert_type)
        $rows = Alert::query()
            ->where('processed', false)
            ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
            ->groupBy('t')
            ->get();

        $alertsByType = [];
        foreach ($rows as $r) {
            $alertsByType[(string)$r->t] = (int)$r->c;
        }

        $payload = [
            'usersCount'        => (int)$usersCount,
            'vehiclesCount'     => (int)$vehiclesCount,
            'associationsCount' => (int)$associationsCount,
            'alertsCount'       => (int)$alertsCount,
            'alertsByType'      => $alertsByType,
        ];

        Redis::setex(self::KEY_STATS, $this->ttlStats, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion();

        return $payload;
    }

    // =========================
    // FLEET
    // =========================
    public function getFleetFromRedis(): array
    {
        // ✅ priorité au hash temps réel (ultra rapide)
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
        } catch (\Throwable $e) {
            // fallback JSON
        }

        $json = Redis::get(self::KEY_FLEET);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    /**
     * ✅ rebuild complet (à utiliser rarement)
     * - Optimisé: join sur dernière location par mac_id_gps
     * - Construit aussi le HASH temps réel (KEY_FLEET_H)
     */
    public function rebuildFleet(): array
    {
        // 1) récupérer voitures + utilisateurs (light)
        $voitures = Voiture::query()
            ->with(['utilisateur:id,prenom,nom'])
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->get();

        // 2) dernière location par mac_id_gps (subquery)
        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
            // prend la dernière location (par datetime ou sys_time)
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
                'id'              => (int)$v->id,
                'immatriculation' => $v->immatriculation,
                'marque'          => $v->marque,
                'model'           => $v->model,
                'users'           => $users,
                'user_id'         => $userId,
                'user_profile_url'=> $userProfileUrl,
                'lat'             => (float)$lat,
                'lon'             => (float)$lon,
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

        // write hash (temps réel)
        Redis::del(self::KEY_FLEET_H);
        if (!empty($hashPayload)) {
            Redis::hmset(self::KEY_FLEET_H, $hashPayload);
            Redis::expire(self::KEY_FLEET_H, $this->ttlFleet);
        }

        // write json fallback
        Redis::setex(self::KEY_FLEET, $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));

        $this->bumpVersion();
        return $fleet;
    }

    /**
     * ✅ Temps réel: mise à jour 1 véhicule seulement
     * appelé par LocationObserver::created()
     */
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
            'id'              => (int)$voiture->id,
            'immatriculation' => $voiture->immatriculation,
            'marque'          => $voiture->marque,
            'model'           => $voiture->model,
            'users'           => $users,
            'user_id'         => $userId,
            'user_profile_url'=> $userProfileUrl,
            'lat'             => (float)$lat,
            'lon'             => (float)$lon,
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

        // (optionnel) ne pas maintenir KEY_FLEET json à chaque update 1 véhicule (coûteux)
        // => on le refresh seulement via rebuildFleet()

        $this->bumpVersion();
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
    // ALERTS
    // =========================
    public function getAlertsFromRedis(): array
    {
        $json = Redis::get(self::KEY_ALERTS);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    // alias pour ton Observer
    public function rebuildAlertsTop10(): array
    {
        return $this->rebuildAlerts(10);
    }

    public function rebuildAlerts(int $limit = 10): array
    {
        $alerts = Alert::with(['voiture.utilisateur'])
            ->orderBy('processed', 'asc')
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
                    'id'           => $a->id,
                    'vehicle'      => $v?->immatriculation ?? 'N/A',
                    'type'         => $type,
                    'users'        => $users ?: null,
                    'time'         => optional($a->alerted_at)->format('d/m/Y H:i:s'),
                    'processed'    => (bool)$a->processed,
                    'status'       => $a->processed ? 'Résolu' : 'Ouvert',
                    'status_color' => $a->processed ? 'bg-green-500' : 'bg-red-500',
                ];
            })
            ->values()
            ->toArray();

        Redis::setex(self::KEY_ALERTS, $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion();

        return $alerts;
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
