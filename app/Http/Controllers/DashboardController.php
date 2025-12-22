<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Voiture;
use App\Models\Alert;
use App\Services\GpsControlService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private GpsControlService $gps)
    {
    }

    public function index()
    {
        // Statistiques
        $usersCount         = User::count();
        $vehiclesCount      = Voiture::count();
        $associationsCount  = Voiture::has('utilisateur')->count();
        $alertsCount        = Alert::where('processed', false)->count();

        // Tableau des alertes
        $alerts = Alert::with(['voiture.utilisateur'])
            ->orderBy('processed', 'asc')
            ->orderBy('alerted_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($a) {
                $voiture = $a->voiture;

                $users = $voiture?->utilisateur
                    ?->map(fn ($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')))
                    ->implode(', ');

                return [
                    'vehicle'      => $voiture?->immatriculation ?? 'N/A',
                    'type'         => $a->type,
                    'time'         => $a->alerted_at?->format('d/m/Y H:i:s'),
                    'status'       => $a->processed ? 'Résolu' : 'Ouvert',
                    'status_color' => $a->processed ? 'bg-green-500' : 'bg-red-500',
                    'users'        => $users,
                ];
            });

        // 📌 Snapshot complet flotte : position + statut moteur + statut GPS
        $vehicles = $this->buildFleetSnapshot();

        return view('dashboards.index', compact(
            'usersCount',
            'vehiclesCount',
            'associationsCount',
            'alertsCount',
            'alerts',
            'vehicles'
        ));
    }

    // 🔁 API temps réel : appelée par le JS du dashboard toutes les 10s
    public function fleetSnapshot()
    {
        $snapshot = $this->buildFleetSnapshot();
        return response()->json($snapshot);
    }

    /**
     * Construit la liste des véhicules avec :
     * - dernière position
     * - associations utilisateur
     * - statut moteur (CUT / ACTIVE)
     * - statut GPS (online/offline)
     */
    private function buildFleetSnapshot()
    {
        return Voiture::with(['latestLocation', 'utilisateur'])
            ->get()
            ->filter(function ($v) {
                return $v->latestLocation
                    && $v->latestLocation->latitude
                    && $v->latestLocation->longitude;
            })
            ->map(function ($v) {
                $loc = $v->latestLocation;

                $lat = floatval($loc->latitude);
                $lon = floatval($loc->longitude);

                $users = $v->utilisateur
                    ? $v->utilisateur
                        ->map(fn ($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')))
                        ->filter()
                        ->implode(', ')
                    : null;

                // 💡 Décodage moteur via le même service que ControlGpsController
                $decoded = $this->gps->decodeEngineStatus($loc->status ?? '');
                $engineState = $decoded['engineState'] ?? 'UNKNOWN';
                $cut = ($engineState === 'CUT');

                // 💡 Online/offline
                $online = $this->isGpsOnline($loc);
                $lastSeen = (string)($loc->heart_time ?? $loc->sys_time ?? $loc->datetime);

                return [
                    'id'              => $v->id,
                    'immatriculation' => $v->immatriculation,
                    'marque'          => $v->marque,
                    'model'           => $v->model,
                    'users'           => $users,
                    'lat'             => $lat,
                    'lon'             => $lon,
                    'status'          => 'En mouvement', // statut "logique" de la voiture

                    'engine' => [
                        'cut'         => $cut,
                        'engineState' => $engineState,
                    ],
                    'gps' => [
                        'online'    => $online,
                        'last_seen' => $lastSeen,
                    ],
                ];
            })
            ->values();
    }

    private function isGpsOnline($loc): ?bool
    {
        $last = $loc->heart_time ?? $loc->sys_time ?? $loc->datetime;
        if (!$last) return null;

        try {
            $dt = Carbon::parse($last);
            // 🔟 On considère "online" si dernière trame < 10 minutes
            return $dt->diffInMinutes(now()) <= 10;
        } catch (\Throwable) {
            return null;
        }
    }
}
