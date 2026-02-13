<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\SimGps;
use App\Services\GpsControlService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; // ✅ NEW
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GpsWatchOfflineCommand extends Command
{
    protected $signature = 'gps:watch-offline
        {--limit=500 : Nombre max de devices à traiter}
        {--chunk=100 : Taille des paquets DB}
        {--sleep=0 : Pause en ms entre devices}
        {--threshold=25 : Seuil offline en minutes (override)}
        {--debug=1 : 1=logs debug, 0=silence}';

    protected $description = 'Met à jour gps_device_status / gps_offline_historique et crée les alertes offline';

    // ✅ NEW: on déclenche le webhook Laravel "what=alerts" uniquement si une alerte OFFLINE a réellement été créée
    private bool $dashboardAlertsDirty = false;

    public function handle(GpsControlService $gps): int
    {
        $limit     = max(1, (int) $this->option('limit'));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $sleepMs   = max(0, (int) $this->option('sleep'));
        $threshold = max(1, (int) $this->option('threshold'));

        $this->dbg('[GPS_OFFLINE] start', compact('limit', 'chunkSize', 'sleepMs', 'threshold'));

        $rows = SimGps::query()
            ->select(['mac_id', 'objectid', 'account_name'])
            ->whereNotNull('mac_id')
            ->where('mac_id', '<>', '')
            ->limit($limit)
            ->get();

        $summary = [
            'total' => $rows->count(),
            'processed' => 0,
            'status_upserts' => 0,
            'offline_started' => 0,
            'offline_ended' => 0,
            'alerts_created' => 0,
            'skipped_no_objectid' => 0,
            'skipped_no_record' => 0,
            'skipped_missing_times' => 0,
            'skipped_no_voiture' => 0,
            'failed' => 0,
        ];

        if ($rows->isEmpty()) {
            $this->info('Aucun device trouvé dans sim_gps');
            return self::SUCCESS;
        }

        // Grouper par compte (tracking/mobility)
        $groups = $rows->groupBy(function ($r) {
            $a = strtolower(trim((string) $r->account_name));
            return in_array($a, ['tracking', 'mobility'], true) ? $a : 'tracking';
        });

        foreach ($groups as $account => $items) {
            $account = $this->normalizeAccount($account);

            $gps->setAccount($account);
            $token = $gps->loginGps(false);

            if (!$token) {
                $this->err('[GPS_OFFLINE] login failed', ['account' => $account, 'count' => $items->count()]);
                $summary['failed'] += $items->count();
                continue;
            }

            foreach ($items->chunk($chunkSize) as $chunk) {
                foreach ($chunk as $sim) {
                    $summary['processed']++;

                    $macId  = trim((string) $sim->mac_id);
                    $userId = trim((string) ($sim->objectid ?? ''));

                    if ($userId === '') {
                        $summary['skipped_no_objectid']++;
                        $this->dbg('[GPS_OFFLINE] skip objectid missing', [
                            'mac_id' => $macId,
                            'account' => $account,
                        ]);
                        $this->upsertStatusUnknown($macId, $account, 'objectid missing');
                        continue;
                    }

                    try {
                        // Dernier record provider
                        $record = $gps->getLatestLocationByUserId($userId, null, null);

                        if (!$record || !is_array($record)) {
                            $summary['skipped_no_record']++;
                            $this->dbg('[GPS_OFFLINE] skip no provider record', [
                                'mac_id' => $macId,
                                'account' => $account,
                                'user_id' => $userId,
                            ]);
                            $this->upsertStatusUnknown($macId, $account, 'no provider record');
                            continue;
                        }

                        // Debug structure record
                        $this->dbg('[GPS_OFFLINE] provider record keys', [
                            'mac_id' => $macId,
                            'account' => $account,
                            'user_id' => $userId,
                            'record_keys' => array_keys($record),
                            'server_time' => $record['server_time'] ?? null,
                            'heart_time' => $record['heart_time'] ?? null,
                            'datetime' => $record['datetime'] ?? null,
                            'speed' => $record['su'] ?? $record['speed'] ?? null,
                        ]);

                        // Compute connectivity (OFFLINE/ONLINE_*)
                        $conn = $gps->computeConnectivityFromLatestRecord($record, $threshold);

                        $state    = (string)($conn['state'] ?? 'UNKNOWN');
                        $isOnline = $conn['is_online'] ?? null; // true/false/null

                        // offline_since_at = heart_time (dernier heartbeat)
                        $lastSeenAt   = $this->parseNullableDateTime($conn['offline_since_at'] ?? null);
                        $lastServerAt = $this->parseNullableDateTime($conn['server_time_at'] ?? null);

                        $offlineSecondsGap = isset($conn['offline_time_seconds'])
                            ? (int) $conn['offline_time_seconds']
                            : null;

                        // started_at = last_seen_at + threshold minutes
                        $offlineStartedAt = null;
                        if ($state === 'OFFLINE' && $lastSeenAt) {
                            $offlineStartedAt = $lastSeenAt->copy()->addMinutes($threshold);
                        }

                        // ✅ Durée réelle depuis started_at jusqu'au server_time
                        $offlineDurationSinceStarted = null;
                        if ($offlineStartedAt && $lastServerAt) {
                            $diff = $offlineStartedAt->diffInSeconds($lastServerAt, false); // peut être négatif
                            $offlineDurationSinceStarted = max(0, (int) $diff);
                        }

                        $this->dbg('[GPS_OFFLINE] connectivity computed', [
                            'mac_id' => $macId,
                            'account' => $account,
                            'user_id' => $userId,
                            'state' => $state,
                            'is_online' => $isOnline,
                            'reason' => $conn['reason'] ?? null,
                            'last_seen_at' => $lastSeenAt?->toDateTimeString(),
                            'last_server_at' => $lastServerAt?->toDateTimeString(),
                            'offline_seconds_gap(server-heart)' => $offlineSecondsGap,
                            'offline_started_at(last_seen+threshold)' => $offlineStartedAt?->toDateTimeString(),
                            'offline_duration_since_started' => $offlineDurationSinceStarted,
                        ]);

                        // Lire status précédent
                        $prev = DB::table('gps_device_status')->where('mac_id_gps', $macId)->first();
                        $prevState = $prev?->state ? (string)$prev->state : null;

                        $wasOffline = ($prevState === 'OFFLINE');
                        $isOffline  = ($state === 'OFFLINE');

                        // Upsert gps_device_status
                        $changed = ($prevState !== $state) || (($prev?->is_online ?? null) !== $isOnline);
                        $now = now();

                        $payload = [
                            'mac_id_gps' => $macId,
                            'account_name' => $account,
                            'state' => $state,
                            'is_online' => $isOnline,
                            'last_seen_at' => $lastSeenAt?->toDateTimeString(),
                            'last_server_at' => $lastServerAt?->toDateTimeString(),
                            'offline_started_at' => $offlineStartedAt?->toDateTimeString(),
                            'offline_seconds' => $offlineDurationSinceStarted, // ✅ durée réelle depuis started_at
                            'threshold_minutes' => $threshold,
                            'last_change_at' => $changed ? $now->toDateTimeString() : ($prev?->last_change_at ?? $now->toDateTimeString()),
                            'updated_at' => $now->toDateTimeString(),
                        ];

                        if (!$prev) {
                            $payload['created_at'] = $now->toDateTimeString();
                            DB::table('gps_device_status')->insert($payload);
                        } else {
                            DB::table('gps_device_status')->where('mac_id_gps', $macId)->update($payload);
                        }

                        $summary['status_upserts']++;

                        // OFFLINE START (transition)
                        if ($isOffline && !$wasOffline) {
                            // Besoin des dates clés pour ouvrir event + alerte
                            if (!$offlineStartedAt || !$lastSeenAt || !$lastServerAt) {
                                $summary['skipped_missing_times']++;
                                $this->dbg('[GPS_OFFLINE] cannot open offline event / alert (missing times)', [
                                    'mac_id' => $macId,
                                    'account' => $account,
                                    'offline_started_at' => $offlineStartedAt?->toDateTimeString(),
                                    'last_seen_at' => $lastSeenAt?->toDateTimeString(),
                                    'last_server_at' => $lastServerAt?->toDateTimeString(),
                                ]);
                            } else {
                                $opened = $this->openOfflineEventIfNotExists(
                                    macId: $macId,
                                    account: $account,
                                    startedAt: $offlineStartedAt,
                                    lastSeenAt: $lastSeenAt,
                                    lastServerAt: $lastServerAt,
                                    threshold: $threshold,
                                    meta: [
                                        'state' => $state,
                                        'offline_seconds_gap' => $offlineSecondsGap,
                                        'offline_duration_since_started' => $offlineDurationSinceStarted,
                                        'user_id' => $userId,
                                    ]
                                );

                                if ($opened) $summary['offline_started']++;

                                // ✅ Alerte offline avec durée en J/H/Min/S
                                $createdAlert = $this->createOfflineAlertIfPossible(
                                    macId: $macId,
                                    startedAt: $offlineStartedAt,
                                    lastSeenAt: $lastSeenAt,
                                    lastServerAt: $lastServerAt,
                                    offlineSeconds: $offlineDurationSinceStarted,
                                    thresholdMin: $threshold
                                );

                                if ($createdAlert) {
                                    $summary['alerts_created']++;
                                } else {
                                    $summary['skipped_no_voiture']++;
                                }
                            }
                        }

                        // OFFLINE END (transition => online)
                        $becameOnline = in_array($state, ['ONLINE_MOVING', 'ONLINE_STATIONARY'], true);

                        if ($becameOnline && $wasOffline) {
                            $closed = $this->closeOfflineEventIfOpen(
                                macId: $macId,
                                endedAt: $lastServerAt ?: now(),
                                meta: [
                                    'ended_state' => $state,
                                    'user_id' => $userId,
                                ]
                            );

                            if ($closed) $summary['offline_ended']++;
                        }

                    } catch (\Throwable $e) {
                        $summary['failed']++;

                        $this->err('[GPS_OFFLINE] exception per device', [
                            'mac_id' => $macId,
                            'account' => $account,
                            'user_id' => $userId,
                            'error' => $e->getMessage(),
                        ]);

                        $this->upsertStatusUnknown($macId, $account, 'exception: ' . $e->getMessage());
                    }

                    if ($sleepMs > 0) usleep($sleepMs * 1000);
                }
            }
        }

        $this->info('✅ gps:watch-offline terminé');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // ✅ NEW: déclenche webhook Laravel (what=alerts) une seule fois si une alerte OFFLINE a été réellement créée
        if ($this->dashboardAlertsDirty) {
            $this->notifyDashboard('alerts');
        }

        return self::SUCCESS;
    }

    /* ==========================================================
     * Helpers
     * ========================================================== */

    private function dbg(string $message, array $context = []): void
    {
        if ((int) $this->option('debug') !== 1) return;
        Log::debug($message, $context);
    }

    private function err(string $message, array $context = []): void
    {
        Log::error($message, $context);
        $this->error($message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    private function normalizeAccount(?string $account): string
    {
        $a = strtolower(trim((string) $account));
        return in_array($a, ['tracking', 'mobility'], true) ? $a : 'tracking';
    }

    private function parseNullableDateTime($value): ?Carbon
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        if ($s === '' || $s === '0' || $s === '0000-00-00 00:00:00') return null;

        try {
            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * ✅ Format durée en "Xj XXh XXmin XXs"
     */
    private function formatDurationDhms(?int $seconds): ?string
    {
        if ($seconds === null) return null;

        $seconds = max(0, (int) $seconds);

        $days = intdiv($seconds, 86400);
        $seconds %= 86400;

        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;

        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        return sprintf('%dj %02dh %02dmin %02ds', $days, $hours, $minutes, $seconds);
    }

    private function upsertStatusUnknown(string $macId, string $account, string $reason): void
    {
        $now = now()->toDateTimeString();
        $prev = DB::table('gps_device_status')->where('mac_id_gps', $macId)->first();

        $payload = [
            'mac_id_gps' => $macId,
            'account_name' => $account,
            'state' => 'UNKNOWN',
            'is_online' => null,
            'last_seen_at' => null,
            'last_server_at' => null,
            'offline_started_at' => null,
            'offline_seconds' => null,
            'threshold_minutes' => (int) $this->option('threshold'),
            'last_change_at' => $now,
            'updated_at' => $now,
        ];

        try {
            if (!$prev) {
                $payload['created_at'] = $now;
                DB::table('gps_device_status')->insert($payload);
            } else {
                DB::table('gps_device_status')->where('mac_id_gps', $macId)->update($payload);
            }
        } catch (\Throwable $e) {
            Log::error('[GPS_OFFLINE] upsertStatusUnknown failed', [
                'mac_id' => $macId,
                'account' => $account,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }

        $this->dbg('[GPS_OFFLINE] status UNKNOWN set', [
            'mac_id' => $macId,
            'account' => $account,
            'reason' => $reason,
        ]);
    }

    // ✅ NEW: appelle le webhook Laravel qui invalide/rebuild Redis + SSE
    private function notifyDashboard(string $what): bool
    {
        $url = (string) env('DASH_WEBHOOK_URL', '');
        $secret = (string) config('services.dashboard_webhook_secret', '');

        if ($url === '' || $secret === '') {
            $this->dbg('[DASH_WEBHOOK] missing url/secret', compact('what', 'url'));
            return false;
        }

        try {
            $res = Http::timeout(10)
                ->withHeaders(['X-DASH-SECRET' => $secret])
                ->acceptJson()
                ->post($url, ['what' => $what]);

            $ok = $res->successful() && (bool) data_get($res->json(), 'ok', false);

            $this->dbg('[DASH_WEBHOOK] called', [
                'what' => $what,
                'status' => $res->status(),
                'ok' => $ok,
                'body' => $res->json(),
            ]);

            return $ok;
        } catch (\Throwable $e) {
            $this->err('[DASH_WEBHOOK] failed', [
                'what' => $what,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function openOfflineEventIfNotExists(
        string $macId,
        string $account,
        Carbon $startedAt,
        Carbon $lastSeenAt,
        Carbon $lastServerAt,
        int $threshold,
        array $meta = []
    ): bool {
        try {
            $exists = DB::table('gps_offline_historique')
                ->where('mac_id_gps', $macId)
                ->whereNull('ended_at')
                ->exists();

            if ($exists) {
                $this->dbg('[GPS_OFFLINE] offline event already open', [
                    'mac_id' => $macId,
                    'account' => $account,
                ]);
                return false;
            }

            DB::table('gps_offline_historique')->insert([
                'mac_id_gps' => $macId,
                'account_name' => $account,
                'started_at' => $startedAt->toDateTimeString(),
                'detected_at' => now()->toDateTimeString(),
                'ended_at' => null,
                'duration_seconds' => null,
                'last_seen_at' => $lastSeenAt->toDateTimeString(),
                'last_server_at' => $lastServerAt->toDateTimeString(),
                'threshold_minutes' => $threshold,
                'meta' => !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

            $this->dbg('[GPS_OFFLINE] offline event OPENED', [
                'mac_id' => $macId,
                'account' => $account,
                'started_at' => $startedAt->toDateTimeString(),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->err('[GPS_OFFLINE] openOfflineEvent failed', [
                'mac_id' => $macId,
                'account' => $account,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function closeOfflineEventIfOpen(string $macId, Carbon $endedAt, array $meta = []): bool
    {
        try {
            $row = DB::table('gps_offline_historique')
                ->where('mac_id_gps', $macId)
                ->whereNull('ended_at')
                ->orderByDesc('id')
                ->first();

            if (!$row) return false;

            $startedAt = $this->parseNullableDateTime($row->started_at) ?: null;
            $duration = $startedAt ? $startedAt->diffInSeconds($endedAt) : null;

            $oldMeta = [];
            if (!empty($row->meta)) {
                $tmp = json_decode((string) $row->meta, true);
                if (is_array($tmp)) $oldMeta = $tmp;
            }
            $newMeta = array_merge($oldMeta, $meta);

            DB::table('gps_offline_historique')->where('id', $row->id)->update([
                'ended_at' => $endedAt->toDateTimeString(),
                'duration_seconds' => $duration,
                'meta' => !empty($newMeta) ? json_encode($newMeta, JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => now()->toDateTimeString(),
            ]);

            $this->dbg('[GPS_OFFLINE] offline event CLOSED', [
                'mac_id' => $macId,
                'ended_at' => $endedAt->toDateTimeString(),
                'duration_seconds' => $duration,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->err('[GPS_OFFLINE] closeOfflineEvent failed', [
                'mac_id' => $macId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * ✅ Crée l'alerte offline dans alerts :
     * - resolve voiture_id depuis sim_gps.voiture_id OU voitures.mac_id_gps OU voitures.mac_id
     * - évite doublons (±2 minutes autour started_at)
     * - message inclut Durée: Xj XXh XXmin XXs
     */
    private function createOfflineAlertIfPossible(
        string $macId,
        Carbon $startedAt,
        ?Carbon $lastSeenAt,
        ?Carbon $lastServerAt,
        ?int $offlineSeconds,
        int $thresholdMin
    ): bool {
        $voiture = $this->resolveVoitureByMacId($macId);

        if (!$voiture || empty($voiture['id'])) {
            $this->dbg('[OFFLINE_ALERT] voiture not resolved', ['mac_id' => $macId]);
            return false;
        }

        $voitureId = (int) $voiture['id'];
        $immat = $voiture['immatriculation'] ?? null;

        $durationText = $this->formatDurationDhms($offlineSeconds);

        $msgParts = [];
        $msgParts[] = "🚨 GPS OFFLINE";
        $msgParts[] = $immat ? "Véhicule: {$immat}" : "MAC_ID: {$macId}";
        $msgParts[] = "Début offline: " . $startedAt->format('d/m/Y H:i:s');
        if ($lastSeenAt)   $msgParts[] = "Dernier heartbeat: " . $lastSeenAt->format('d/m/Y H:i:s');
        if ($lastServerAt) $msgParts[] = "Server time: " . $lastServerAt->format('d/m/Y H:i:s');
        $msgParts[] = "Seuil: {$thresholdMin}min";
        if ($durationText) $msgParts[] = "Durée offline: {$durationText}";

        $message = implode(' , ', $msgParts);

        // anti-doublon : ±2 minutes autour started_at
        $from = $startedAt->copy()->subMinutes(2);
        $to   = $startedAt->copy()->addMinutes(2);

        try {
            $exists = Alert::query()
                ->where('voiture_id', $voitureId)
                ->where('alert_type', 'offline')
                ->whereBetween('alerted_at', [$from, $to])
                ->exists();

            if ($exists) {
                $this->dbg('[OFFLINE_ALERT] duplicate avoided', [
                    'voiture_id' => $voitureId,
                    'mac_id' => $macId,
                    'window' => [$from->toDateTimeString(), $to->toDateTimeString()],
                ]);
                return true;
            }

            Alert::create([
                'voiture_id' => $voitureId,
                'alert_type' => 'offline',
                'message' => $message,
                'alerted_at' => $startedAt,
                'sent' => false,
                'read' => false,
                'processed' => false,
            ]);

            // ✅ NEW: on marque "dirty" uniquement quand on a réellement créé une alerte
            $this->dashboardAlertsDirty = true;

            $this->dbg('[OFFLINE_ALERT] created', [
                'voiture_id' => $voitureId,
                'mac_id' => $macId,
                'alerted_at' => $startedAt->toDateTimeString(),
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('[OFFLINE_ALERT] create failed', [
                'voiture_id' => $voitureId,
                'mac_id' => $macId,
                'alert_type' => 'offline',
                'alerted_at' => $startedAt->toDateTimeString(),
                'error' => $e->getMessage(),
                'code' => method_exists($e, 'getCode') ? $e->getCode() : null,
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);

            return false;
        }
    }

    /**
     * Résolution voiture via :
     * 1) sim_gps.voiture_id (si existe)
     * 2) voitures.mac_id_gps (si existe)
     * 3) voitures.mac_id (si existe)
     */
    private function resolveVoitureByMacId(string $macId): ?array
    {
        try {
            $macId = trim($macId);
            if ($macId === '') return null;

            $voitureId = null;

            if (Schema::hasTable('sim_gps') && Schema::hasColumn('sim_gps', 'voiture_id')) {
                $voitureId = DB::table('sim_gps')->where('mac_id', $macId)->value('voiture_id');
            }

            if (!$voitureId && Schema::hasTable('voitures')) {
                if (Schema::hasColumn('voitures', 'mac_id_gps')) {
                    $voitureId = DB::table('voitures')->where('mac_id_gps', $macId)->value('id');
                } elseif (Schema::hasColumn('voitures', 'mac_id')) {
                    $voitureId = DB::table('voitures')->where('mac_id', $macId)->value('id');
                }
            }

            if (!$voitureId) return null;

            $select = ['id'];
            if (Schema::hasColumn('voitures', 'immatriculation')) $select[] = 'immatriculation';
            if (Schema::hasColumn('voitures', 'marque')) $select[] = 'marque';
            if (Schema::hasColumn('voitures', 'model')) $select[] = 'model';

            $row = DB::table('voitures')->select($select)->where('id', $voitureId)->first();

            if (!$row) {
                return ['id' => (int) $voitureId];
            }

            return [
                'id' => (int) ($row->id ?? $voitureId),
                'immatriculation' => $row->immatriculation ?? null,
                'marque' => $row->marque ?? null,
                'model' => $row->model ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('[GPS_OFFLINE] resolveVoitureByMacId failed', [
                'mac_id' => $macId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
