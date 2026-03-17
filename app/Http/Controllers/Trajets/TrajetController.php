<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trajet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrajetController extends Controller
{
    /**
     * Liste JSON des trajets pour le dashboard
     * Compatible avec le dashboard partenaire
     */
    public function index(Request $request)
    {
        $tz = 'Africa/Douala';

        $query = Trajet::query()
            ->with(['voiture']);

        /**
         * 1) Filtre véhicule
         * - interne = tous les véhicules
         */
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', (int) $request->vehicle_id);
        }

        /**
         * 2) Filtres date compatibles partenaire
         * quick=today|yesterday|this_week|this_month
         * ou start_date/end_date
         */
        $quick = $request->query('quick', $request->query('date_quick', 'today'));
        $now   = now($tz);

        if ($quick && $quick !== 'range') {
            switch ($quick) {
                case 'today':
                    $query->whereDate('start_time', $now->toDateString());
                    break;

                case 'yesterday':
                    $query->whereDate('start_time', $now->copy()->subDay()->toDateString());
                    break;

                case 'this_week':
                    $query->whereBetween('start_time', [
                        $now->copy()->startOfWeek(),
                        $now->copy()->endOfWeek(),
                    ]);
                    break;

                case 'this_month':
                    $query->whereBetween('start_time', [
                        $now->copy()->startOfMonth(),
                        $now->copy()->endOfMonth(),
                    ]);
                    break;
            }
        } elseif ($request->filled('start_date')) {
            $start = Carbon::parse($request->start_date, $tz)->startOfDay();
            $end   = Carbon::parse($request->end_date ?? $request->start_date, $tz)->endOfDay();

            $query->whereBetween('start_time', [$start, $end]);
        }

        /**
         * 3) Pagination
         */
        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 200);

        $trajets = $query
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->paginate($perPage);

        /**
         * 4) Réponse JSON compatible dashboard partenaire
         */
        return response()->json([
            'status' => 'success',
            'data' => collect($trajets->items())->map(function ($t) {
                return [
                    'id'                => $t->id,
                    'vehicle_id'        => $t->vehicle_id,
                    'immatriculation'   => $t->voiture?->immatriculation,
                    'driver_label'      => $t->voiture?->users_labels ?? 'Inconnu',
                    'start_time'        => $t->start_time,
                    'end_time'          => $t->end_time,
                    'duration_minutes'  => (int) ($t->duration_minutes ?? 0),
                    'total_distance_km' => round((float) ($t->total_distance_km ?? 0), 2),
                    'avg_speed_kmh'     => round((float) ($t->avg_speed_kmh ?? 0), 1),
                    'max_speed_kmh'     => round((float) ($t->max_speed_kmh ?? 0), 1),
                ];
            })->values(),
            'meta' => [
                'current_page' => $trajets->currentPage(),
                'total'        => $trajets->total(),
                'last_page'    => $trajets->lastPage(),
            ],
        ]);
    }

    /**
     * Détail JSON d’un trajet + points GPS pour la carte / replay
     * Compatible avec :
     * - /trajets/show/{voiture_id}/{trajet_id}
     * - /trajets/{vehicle_id}/detail/{trajet_id}
     */
    public function showTrajet($vehicle_id, $trajet_id, Request $request)
    {
        $trajet = Trajet::with('voiture')
            ->where('id', $trajet_id)
            ->where('vehicle_id', $vehicle_id)
            ->firstOrFail();

        $macId = $trajet->mac_id_gps ?: $trajet->voiture?->mac_id_gps;

        $points = collect();

        if ($macId) {
            $points = DB::table('locations')
                ->where('mac_id_gps', $macId)
                ->whereBetween('datetime', [
                    $trajet->start_time,
                    $trajet->end_time ?? now(),
                ])
                ->select([
                    'latitude as lat',
                    'longitude as lng',
                    'datetime as ts',
                    'speed',
                ])
                ->orderBy('datetime', 'asc')
                ->get();
        }

        /**
         * Fallback si aucun point GPS trouvé
         * Attention: dans l’interne les colonnes sont
         * start_latitude / start_longitude / end_latitude / end_longitude
         */
        if ($points->isEmpty() && $trajet->start_latitude && $trajet->start_longitude) {
            $fallback = [
                [
                    'lat'   => (float) $trajet->start_latitude,
                    'lng'   => (float) $trajet->start_longitude,
                    'ts'    => $trajet->start_time,
                    'speed' => 0,
                ],
            ];

            if ($trajet->end_latitude && $trajet->end_longitude) {
                $fallback[] = [
                    'lat'   => (float) $trajet->end_latitude,
                    'lng'   => (float) $trajet->end_longitude,
                    'ts'    => $trajet->end_time ?? now(),
                    'speed' => 0,
                ];
            }

            $points = collect($fallback);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trajet' => [
                    'id'              => $trajet->id,
                    'immatriculation' => $trajet->voiture?->immatriculation ?? '—',
                    'start_time'      => $trajet->start_time,
                    'end_time'        => $trajet->end_time,
                    'stats' => [
                        'distance'  => round((float) ($trajet->total_distance_km ?? 0), 2),
                        'duration'  => (int) ($trajet->duration_minutes ?? 0),
                        'max_speed' => round((float) ($trajet->max_speed_kmh ?? 0), 1),
                        'avg_speed' => round((float) ($trajet->avg_speed_kmh ?? 0), 1),
                    ],
                ],
                'track' => [
                    'points' => $points->values(),
                    'count'  => $points->count(),
                ],
            ],
        ]);
    }
}