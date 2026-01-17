<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\User;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class DashboardCacheService
{
    // =========================
    // Redis keys
    // =========================
    private const KEY_STATS   = 'dash:stats';
    private const KEY_FLEET   = 'dash:fleet';
    private const KEY_ALERTS  = 'dash:alerts';
    private const KEY_VERSION = 'dash:version';

    // TTL en secondes (tu peux ajuster)
    private int $ttlStats  = 60;
    private int $ttlFleet  = 60;
    private int $ttlAlerts = 30;

    // ==========================================
    // Version (déclencheur SSE)
    // ==========================================
    public function getVersion(): int
    {
        return (int) (Redis::get(self::KEY_VERSION) ?? 0);
    }

    public function bumpVersion(): void
    {
        // incr atomique
        Redis::incr(self::KEY_VERSION);
        // optionnel: TTL sur version (pas obligatoire)
        // Redis::expire(self::KEY_VERSION, 3600);
    }

    // ==========================================
    // STATS
    // ==========================================
    public function getStatsFromRedis(): ?array
    {
        $json = Redis::get(self::KEY_STATS);
        return $json ? json_decode($json, true) : null;
    }

    public function rebuildStats(): array
    {
        $usersCount         = User::count();
        $vehiclesCount      = Voiture::count();
        $associationsCount  = Voiture::has('utilisateur')->count();

        // ✅ total alertes non traitées
        $alertsCount = Alert::where('processed', false)->count();

        // ✅ alertes non traitées par type
        // ATTENTION: pas de colonne "type" dans la DB => on utilise alert_type (la vraie colonne)
        $rows = Alert::query()
            ->where('processed', false)
            ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
            ->groupBy('t')
            ->get();

        $alertsByType = [];
        foreach ($rows as $r) {
            $alertsByType[(string) $r->t] = (int) $r->c;
        }

        $payload = [
            'usersCount'        => (int) $usersCount,
            'vehiclesCount'     => (int) $vehiclesCount,
            'associationsCount' => (int) $associationsCount,
            'alertsCount'       => (int) $alertsCount,
            'alertsByType'      => $alertsByType,
        ];

        Redis::setex(self::KEY_STATS, $this->ttlStats, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion();

        return $payload;
    }

    // ==========================================
    // FLEET
    // ==========================================
    public function getFleetFromRedis(): array
    {
        $json = Redis::get(self::KEY_FLEET);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function rebuildFleet(): array
    {
        $vehicles = Voiture::with(['latestLocation', 'utilisateur'])->get();

        $fleet = $vehicles
            ->filter(function ($v) {
                $loc = $v->latestLocation;
                return $loc && $loc->latitude && $loc->longitude;
            })
            ->map(function ($v) {
                $loc = $v->latestLocation;

                $usersCollection = $v->utilisateur;
                $users = $usersCollection
                    ? $usersCollection->map(fn($u) => trim(($u->prenom ?? '').' '.($u->nom ?? '')))
                        ->filter()
                        ->implode(', ')
                    : null;

                $firstUser = $usersCollection?->first();
                $userId = $firstUser?->id;
                $userProfileUrl = $userId ? route('users.profile', ['id' => $userId]) : null;

                $lat = (float) $loc->latitude;
                $lon = (float) $loc->longitude;

                $online = $this->isGpsOnline($loc);
                $lastSeen = (string)($loc->heart_time ?? $loc->sys_time ?? $loc->datetime);

                return [
                    'id'              => $v->id,
                    'immatriculation' => $v->immatriculation,
                    'marque'          => $v->marque,
                    'model'           => $v->model,
                    'users'           => $users,
                    'user_id'         => $userId,
                    'user_profile_url'=> $userProfileUrl,
                    'lat'             => $lat,
                    'lon'             => $lon,

                    // tu peux brancher ton decodeEngineStatus ici si tu veux,
                    // pour le moment on laisse des valeurs simples
                    'engine' => [
                        'cut' => null,
                        'engineState' => 'UNKNOWN',
                    ],
                    'gps' => [
                        'online'    => $online,
                        'last_seen' => $lastSeen,
                    ],
                ];
            })
            ->values()
            ->toArray();

        Redis::setex(self::KEY_FLEET, $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion();

        return $fleet;
    }

    private function isGpsOnline($loc): ?bool
    {
        $last = $loc->heart_time ?? $loc->sys_time ?? $loc->datetime;
        if (!$last) return null;

        try {
            $dt = Carbon::parse($last);
            return $dt->diffInMinutes(now()) <= 10;
        } catch (\Throwable) {
            return null;
        }
    }

    // ==========================================
    // ALERTS (TOP 10)
    // ==========================================
    public function getAlertsFromRedis(): array
    {
        $json = Redis::get(self::KEY_ALERTS);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function rebuildAlerts(int $limit = 10): array
    {
        // Tu m’as dit: alertes créées par NodeJS mais enregistrées dans la même DB.
        // Donc ici on lit la DB et on met dans Redis.

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

                // IMPORTANT: la vraie colonne = alert_type
                $type = $a->alert_type ?? 'unknown';

                return [
                    'id'           => $a->id,
                    'vehicle'      => $v?->immatriculation ?? 'N/A',
                    'type'         => $type,
                    'users'        => $users ?: null,
                    'time'         => optional($a->alerted_at)->format('d/m/Y H:i:s'),
                    'processed'    => (bool) $a->processed,
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

    // ==========================================
    // Rebuild all
    // ==========================================
    public function rebuildAll(): array
    {
        $stats  = $this->rebuildStats();
        $fleet  = $this->rebuildFleet();
        $alerts = $this->rebuildAlerts(10);

        return [
            'stats'  => $stats,
            'fleet'  => $fleet,
            'alerts' => $alerts,
        ];
    }
}
