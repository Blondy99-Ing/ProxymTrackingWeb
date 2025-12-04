<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trajet;
use App\Models\Voiture;

class TrajetController extends Controller
{
    /**
     * Afficher tous les trajets
     */
    public function index(Request $request)
{
    $query = Trajet::with('voiture');

    /* ---------------------------
       1) Filtres rapides
    -----------------------------*/
    if ($request->quick) {
        switch ($request->quick) {
            case 'today':
                $query->whereDate('start_time', now());
                break;

            case 'yesterday':
                $query->whereDate('start_time', now()->subDay());
                break;

            case 'week':
                $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
                break;

            case 'month':
                $query->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()]);
                break;

            case 'year':
                $query->whereYear('start_time', now()->year);
                break;
        }
    }

    /* ---------------------------
       2) Plage de dates personnalisée
    -----------------------------*/
    if ($request->start_date) {
        $query->whereDate('start_time', '>=', $request->start_date);
    }

    if ($request->end_date) {
        $query->whereDate('start_time', '<=', $request->end_date);
    }

    /* ---------------------------
       3) Filtre par véhicule
    -----------------------------*/
    if ($request->vehicule) {
        $query->whereHas('voiture', function ($q) use ($request) {
            $q->where('immatriculation', 'LIKE', '%' . $request->vehicule . '%');
        });
    }

    /* ---------------------------
       4) Filtre sur HEURES
    -----------------------------*/
    if ($request->start_time) {
        $query->whereTime('start_time', '>=', $request->start_time);
    }

    if ($request->end_time) {
        $query->whereTime('start_time', '<=', $request->end_time);
    }

    /* ---------------------------
       5) Exécution + pagination
    -----------------------------*/
    $trajets = $query->orderBy('start_time', 'desc')->paginate(20);

    return view('trajets.index', compact('trajets'));
}


    /**
     * Afficher les trajets d'une voiture spécifique
     */
   public function byVoiture($vehicle_id, Request $request)
    {
        $voiture = Voiture::findOrFail($vehicle_id);

        $query = Trajet::where('vehicle_id', $vehicle_id);

        /* ---------------------------
            1. FILTRES RAPIDES
        ----------------------------*/
        $quick = $request->quick ?? 'today';

        switch ($quick) {
            case 'today':
                $query->whereDate('start_time', now());
                break;

            case 'yesterday':
                $query->whereDate('start_time', now()->subDay());
                break;

            case 'week':
                $query->whereBetween('start_time', [
                    now()->startOfWeek(), now()->endOfWeek()
                ]);
                break;

            case 'month':
                $query->whereMonth('start_time', now()->month)
                      ->whereYear('start_time', now()->year);
                break;

            case 'year':
                $query->whereYear('start_time', now()->year);
                break;

            case 'date':
                if ($request->date) {
                    $query->whereDate('start_time', $request->date);
                }
                break;

            case 'range':
                if ($request->start_date) {
                    $query->whereDate('start_time', '>=', $request->start_date);
                }

                if ($request->end_date) {
                    $query->whereDate('start_time', '<=', $request->end_date);
                }
                break;
        }

        /* ---------------------------
           2. Filtre HEURES
        ----------------------------*/
        if ($request->start_time) {
            $query->whereTime('start_time', '>=', $request->start_time);
        }

        if ($request->end_time) {
            $query->whereTime('start_time', '<=', $request->end_time);
        }

        /* ---------------------------
           3. Exécution
        ----------------------------*/
        $trajets = $query->orderBy('start_time', 'asc')->get();

        /* ---------------------------
           4. Calculs statistiques
        ----------------------------*/
        $totalDistance = $trajets->sum('total_distance_km');
        $totalDuration = $trajets->sum('duration_minutes');
        $maxSpeed      = $trajets->max('max_speed_kmh');

        if ($totalDuration > 0) {
            $avgSpeed = round($totalDistance / ($totalDuration / 60), 1);
        } else {
            $avgSpeed = 0;
        }

        return view('trajets.byVoiture', [
            'voiture'       => $voiture,
            'trajets'       => $trajets,
            'filters'       => $request->all(),
            'totalDistance' => round($totalDistance, 1),
            'totalDuration' => $totalDuration,
            'maxSpeed'      => round($maxSpeed, 1),
            'avgSpeed'      => $avgSpeed,
        ]);
    }
}
