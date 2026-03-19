<?php

namespace App\Http\Controllers;

use App\Services\DashboardCacheService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function index()
    {
        $stats = $this->cache->getStatsFromRedis() ?: $this->cache->rebuildStats();

        $vehicles = $this->cache->getFleetFromRedis();
        if (empty($vehicles)) {
            $vehicles = $this->cache->rebuildFleet();
        }

        $alerts = $this->cache->getAlertsFromRedis();
        if (empty($alerts)) {
            $alerts = $this->cache->rebuildAlerts(10);
        }

        return view('dashboards.index', [
            'usersCount'        => (int) ($stats['usersCount'] ?? 0),
            'vehiclesCount'     => (int) ($stats['vehiclesCount'] ?? 0),
            'associationsCount' => (int) ($stats['associationsCount'] ?? 0),
            'alertsCount'       => (int) ($stats['alertsCount'] ?? 0),
            'alertStats'        => (array) ($stats['alertsByType'] ?? []),
            'vehicles'          => is_array($vehicles) ? array_values($vehicles) : [],
            'alerts'            => is_array($alerts) ? array_values($alerts) : [],
        ]);
    }

    public function fleetSnapshot()
    {
        $fleet = $this->cache->getFleetFromRedis();

        if (empty($fleet)) {
            $fleet = $this->cache->rebuildFleet();
        }

        return response()->json([
            'status' => 'success',
            'data'   => is_array($fleet) ? array_values($fleet) : [],
        ]);
    }

    public function dashboardStream(): StreamedResponse
    {
        return response()->stream(function () {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            @ini_set('max_execution_time', '0');

            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }

            try {
                session()->save();
            } catch (\Throwable $e) {
            }

            if (function_exists('session_write_close')) {
                @session_write_close();
            }

            echo "event: hello\n";
            echo "data: {\"ok\":true}\n\n";
            $this->flushNow();

            echo "event: dashboard.init\n";
            echo "data: " . $this->buildInitPayload() . "\n\n";
            $this->flushNow();

            $lastVersion = $this->cache->getVersion();

            while (!connection_aborted()) {
                $version = $this->cache->getVersion();
                $anythingSent = false;

                // ── Alert queues are drained on EVERY cycle, unconditionally. ──
                // They must NOT be gated behind the version check because multiple
                // version bumps can happen between two loop iterations (fleet patch
                // + alert creation within the same 800 ms window). When the loop
                // wakes up it absorbs all version increments at once into
                // $lastVersion, and a subsequent cycle sees no version change —
                // leaving queued alert.new events stranded forever.
                $drainLimit = 20;
                while ($drainLimit-- > 0) {
                    $newAlert = $this->cache->consumeNewAlertEvent();
                    if ($newAlert === null) break;
                    echo "event: alert.new\n";
                    echo "data: " . json_encode($newAlert, JSON_UNESCAPED_UNICODE) . "\n\n";
                    $anythingSent = true;
                }

                $drainLimit = 20;
                while ($drainLimit-- > 0) {
                    $processedAlert = $this->cache->consumeProcessedAlertEvent();
                    if ($processedAlert === null) break;
                    echo "event: alert.processed\n";
                    echo "data: " . json_encode($processedAlert, JSON_UNESCAPED_UNICODE) . "\n\n";
                    $anythingSent = true;
                }

                // ── Fleet / stats events remain version-gated (they are cheap ──
                // to batch and don't need sub-second latency).
                if ($version !== $lastVersion) {
                    $lastVersion = $version;

                    if ($this->cache->consumeFleetReset()) {
                        echo "event: fleet.reset\n";
                        echo "data: " . json_encode([
                            'fleet' => $this->cache->getFleetFromRedis(),
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        $anythingSent = true;
                    } else {
                        $vehicles = $this->cache->consumeDirtyVehicleRows();
                        foreach ($vehicles as $row) {
                            echo "event: vehicle.updated\n";
                            echo "data: " . json_encode([
                                'vehicle' => $row,
                            ], JSON_UNESCAPED_UNICODE) . "\n\n";
                            $anythingSent = true;
                        }
                    }

                    $alerts = $this->cache->consumeDirtyAlerts();
                    if ($alerts !== null) {
                        echo "event: alerts.updated\n";
                        echo "data: " . json_encode([
                            'alerts' => $alerts,
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        $anythingSent = true;
                    }

                    $stats = $this->cache->consumeDirtyStats();
                    if ($stats !== null) {
                        echo "event: stats.updated\n";
                        echo "data: " . json_encode([
                            'stats' => $stats,
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                        $anythingSent = true;
                    }
                }

                if ($anythingSent) {
                    $this->flushNow();
                } else {
                    echo "event: heartbeat\n";
                    echo "data: " . json_encode([
                        'ts' => now()->toDateTimeString(),
                    ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    $this->flushNow();
                }

                usleep(800000);
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

    private function buildInitPayload(): string
    {
        $stats = $this->cache->getStatsFromRedis() ?: [];
        $fleet = $this->cache->getFleetFromRedis();
        $alerts = $this->cache->getAlertsFromRedis();

        return json_encode([
            'ts'     => now()->toDateTimeString(),
            'stats'  => is_array($stats) ? $stats : [],
            'fleet'  => is_array($fleet) ? array_values($fleet) : [],
            'alerts' => is_array($alerts) ? array_values($alerts) : [],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function flushNow(): void
    {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
        }

        @flush();
    }
}