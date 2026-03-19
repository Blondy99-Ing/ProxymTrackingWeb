<?php

namespace App\Http\Controllers;

use App\Models\Alert;
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

        $allowed = ['all', 'stats', 'fleet', 'alerts', 'alerts_top', 'alert_new'];
        if (!in_array($what, $allowed, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid "what" value',
                'allowed' => $allowed,
            ], 422);
        }

        // ── Nouveau cas : alert_new ───────────────────────────────────────
        // Appelé par Node.js après chaque insertion d'alerte.
        // Body JSON attendu : { "what": "alert_new", "alert_id": 123 }
        if ($what === 'alert_new') {
            $alertId = (int) $request->input('alert_id', 0);

            if ($alertId <= 0) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'alert_id manquant ou invalide',
                ], 422);
            }

            $alert = Alert::with(['voiture', 'voiture.utilisateur'])->find($alertId);

            if (!$alert) {
                return response()->json([
                    'ok'      => false,
                    'message' => "Alerte #$alertId introuvable",
                ], 404);
            }

            $this->cache->publishNewAlertEvent($alert, true);

            return response()->json([
                'ok'         => true,
                'what'       => 'alert_new',
                'alert_id'   => $alert->id,
                'alert_type' => $alert->alert_type,
            ]);
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