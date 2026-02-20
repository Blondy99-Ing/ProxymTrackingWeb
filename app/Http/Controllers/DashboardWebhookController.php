<?php

namespace App\Http\Controllers;

use App\Services\DashboardCacheService;
use Illuminate\Http\Request;

class DashboardWebhookController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function refresh(Request $request)
    {
        // ✅ Protection par secret (header)
        $secret   = (string) $request->header('X-INTERNAL-SECRET');

        // ✅ CORRECTION: services.php => services.internal.webhook_secret
        $expected = (string) config('services.internal.webhook_secret');

        if (!$expected || !$secret || !hash_equals($expected, $secret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        // what = all | stats | fleet | alerts | alerts_top
        $what = (string) $request->input('what', 'all');

        if ($what === 'alerts_top') {
            $alerts = $this->cache->rebuildAlerts(10);
            return response()->json([
                'ok' => true,
                'what' => 'alerts_top',
                'alerts_count' => count($alerts),
            ]);
        }

        if ($what === 'alerts') {
            $alerts = $this->cache->rebuildAlerts(10);
            return response()->json([
                'ok' => true,
                'what' => 'alerts',
                'alerts_count' => count($alerts),
            ]);
        }

        if ($what === 'fleet') {
            $fleet = $this->cache->rebuildFleet();
            return response()->json([
                'ok' => true,
                'what' => 'fleet',
                'fleet_count' => count($fleet),
            ]);
        }

        if ($what === 'stats') {
            $stats = $this->cache->rebuildStats();
            return response()->json([
                'ok' => true,
                'what' => 'stats',
                'stats' => $stats,
            ]);
        }

        // all
        $all = $this->cache->rebuildAll();
        return response()->json(['ok' => true, 'what' => 'all', ...$all]);
    }
}