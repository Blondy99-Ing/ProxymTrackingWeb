<?php

namespace App\Http\Controllers;

use App\Services\DashboardCacheService;
use Illuminate\Http\Request;

class DashboardWebhookController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function refresh(Request $request)
    {
        $secret = (string) $request->header('X-INTERNAL-SECRET');
        $expected = (string) config('services.internal.webhook_secret');

        if (!$expected || !$secret || !hash_equals($expected, $secret)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $what = strtolower((string) $request->input('what', 'all'));

        $allowed = ['all', 'stats', 'fleet', 'alerts', 'alerts_top'];
        if (!in_array($what, $allowed, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid "what" value',
                'allowed' => $allowed,
            ], 422);
        }

        if ($what === 'alerts_top') {
            $alerts = $this->cache->rebuildAlertsTop10();

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

        $all = $this->cache->rebuildAll();

        return response()->json([
            'ok' => true,
            'what' => 'all',
            ...$all,
        ]);
    }
}