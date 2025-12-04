<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Voiture;
use App\Models\Alert;

class DashboardController extends Controller
{
    public function index()
    {
        // Statistiques
        $usersCount = User::count();
        $vehiclesCount = Voiture::count();
        $associationsCount = Voiture::has('utilisateur')->count();
        $alertsCount = Alert::where('processed', false)->count();

        // Tableau des alertes
        $alerts = Alert::with(['voiture.utilisateur'])
            ->orderBy('processed', 'asc')
            ->orderBy('alerted_at', 'desc')
            ->take(10)
            ->get()
            ->map(function($a) {
                $voiture = $a->voiture;

                $users = $voiture?->utilisateur
                    ?->map(fn($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')))
                    ->implode(', ');

                return [
                    'vehicle' => $voiture?->immatriculation ?? 'N/A',
                    'type'    => $a->type,
                    'time'    => $a->alerted_at?->format('d/m/Y H:i:s'),
                    'status'  => $a->processed ? 'Résolu' : 'Ouvert',
                    'status_color' => $a->processed ? 'bg-green-500' : 'bg-red-500',
                    'users'   => $users,
                ];
            });

        // TOUS les véhicules avec position (SEULEMENT si coordonnées valides)
        $vehicles = Voiture::with('latestLocation')->get()->filter(function($v) {
            return $v->latestLocation && $v->latestLocation->latitude && $v->latestLocation->longitude;
        })->map(function($v) {

            $lat = floatval($v->latestLocation->latitude);
            $lon = floatval($v->latestLocation->longitude);

            // LOG des coordonnées
            \Log::info("Véhicule {$v->immatriculation} -> lat: {$lat}, lon: {$lon}");

            return [
                'id' => $v->id,
                'immatriculation' => $v->immatriculation,
                'lat' => $lat,
                'lon' => $lon,
                'status' => 'En mouvement',
            ];
        });

        return view('dashboards.index', compact(
            'usersCount',
            'vehiclesCount',
            'associationsCount',
            'alertsCount',
            'alerts',
            'vehicles'
        ));
    }
}
