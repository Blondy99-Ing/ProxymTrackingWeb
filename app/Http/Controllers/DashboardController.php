<?php

namespace App\Http\Controllers;

use App\Services\DashboardCacheService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function index()
    {
        // ✅ Stats (si absent => rebuild)
        $stats = $this->cache->getStatsFromRedis();
        if (!$stats) $stats = $this->cache->rebuildStats();

        // ✅ Fleet (si vide => rebuild)
        $vehicles = $this->cache->getFleetFromRedis();
        if (empty($vehicles)) $vehicles = $this->cache->rebuildFleet();

        // ✅ Alerts (si vide => rebuild)
        $alerts = $this->cache->getAlertsFromRedis();
        if (empty($alerts)) $alerts = $this->cache->rebuildAlerts(10);

        // ✅ Garantit alertsCount/alertsByType (fallback)
        if (!isset($stats['alertsCount']) || !isset($stats['alertsByType'])) {
            $stats = $this->cache->rebuildStats();
        }

        return view('dashboards.index', [
            'usersCount'        => (int)($stats['usersCount'] ?? 0),
            'vehiclesCount'     => (int)($stats['vehiclesCount'] ?? 0),
            'associationsCount' => (int)($stats['associationsCount'] ?? 0),
            'alertsCount'       => (int)($stats['alertsCount'] ?? 0),

            'vehicles'          => $vehicles,
            'alerts'            => $alerts,
        ]);
    }

    /**
     * ⚠️ SSE Laravel = fallback (polling version)
     * Ton vrai temps réel performant = Node SSE (Redis Pub/Sub).
     */
    public function dashboardStream(): StreamedResponse
    {
        return response()->stream(function () {

            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);

            try { session()->save(); } catch (\Throwable $e) {}
            if (function_exists('session_write_close')) @session_write_close();

            echo "event: hello\n";
            echo "data: {\"ok\":true}\n\n";
            $this->flushNow();

            echo "event: dashboard\n";
            echo "data: " . $this->buildPayload() . "\n\n";
            $this->flushNow();

            $lastVersion = $this->cache->getVersion();

            while (!connection_aborted()) {
                $v = $this->cache->getVersion();

                if ($v !== $lastVersion) {
                    $lastVersion = $v;

                    echo "event: dashboard\n";
                    echo "data: " . $this->buildPayload() . "\n\n";
                    $this->flushNow();
                } else {
                    echo ": ping\n\n";
                    $this->flushNow();
                }

                usleep(200000); // 200ms
            }

        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=UTF-8',
            'Cache-Control'     => 'no-cache, no-store, must-revalidate',
            'Pragma'            => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function rebuildCache()
    {
        $all = $this->cache->rebuildAll();

        return response()->json([
            'ok'      => true,
            'ts'      => now()->toDateTimeString(),
            'version' => $this->cache->getVersion(),
            ...$all,
        ]);
    }

    // ✅ CORRIGÉ: ajoute alerts_summary (compteurs du jour toujours frais)
    private function buildPayload(): string
    {
        $stats  = $this->cache->getStatsFromRedis() ?? [];
        $fleet  = $this->cache->getFleetFromRedis();
        $alerts = $this->cache->getAlertsFromRedis();

        $alertsSummary = [
            'total' => $this->cache->getAlertsTotalTodayCounter(),
            'by_type' => $this->cache->getAlertsByTypeCountersToday(),
            'scope' => [
                'day' => now()->toDateString(),
                'processed' => false,
            ],
        ];

        return json_encode([
            'ts'     => now()->toDateTimeString(),
            'stats'  => $stats,
            'fleet'  => $fleet,
            'alerts' => $alerts,
            'alerts_summary' => $alertsSummary, // ✅
        ], JSON_UNESCAPED_UNICODE);
    }

    private function flushNow(): void
    {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_flush(); }
        }
        @flush();
    }
}