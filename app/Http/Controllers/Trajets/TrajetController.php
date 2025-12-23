<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trajet;
use App\Models\Voiture;
use App\Models\Location;
use Carbon\Carbon;

class TrajetController extends Controller
{
    /**
     * Tous les trajets (tous véhicules)
     */
    public function index(Request $request)
    {
        $query = Trajet::with('voiture');

        // 1) Filtres rapides
        if ($request->filled('quick')) {
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

        // 2) Plage dates
        if ($request->filled('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        // 3) Véhicule (priorité vehicle_id)
        $selectedVehicle = null;

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
            $selectedVehicle = Voiture::select('id', 'immatriculation')->find($request->vehicle_id);
        } elseif ($request->filled('vehicule')) {
            $query->whereHas('voiture', function ($q) use ($request) {
                $q->where('immatriculation', 'LIKE', '%' . $request->vehicule . '%');
            });
        }

        // 4) Filtre heures (robuste)
        $this->applyTimeOfDayFilter($query, $request, 'start_time');

        // 5) Pagination
        $trajets = $query
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $vehicles = Voiture::select('id', 'immatriculation')
            ->orderBy('immatriculation')
            ->get();

        return view('trajets.index', compact('trajets', 'vehicles', 'selectedVehicle'));
    }


    /**
     * Trajets d’un véhicule + focus trajet + tracks
     *
     * Route: /voitures/{id}/trajets  name: voitures.trajets
     */
    public function byVoiture($id, Request $request)
    {
        $voiture = Voiture::findOrFail($id);

        $vehicles = Voiture::select('id', 'immatriculation')
            ->orderBy('immatriculation')
            ->get();

        $perPage = 20;

        // Focus demandé ?
        $focusId = $request->query('focus_trajet_id');
        $focusTrajet = null;

        if ($focusId) {
            $focusTrajet = Trajet::where('vehicle_id', $id)->where('id', $focusId)->first();
        }

        /**
         * ✅ 1) Si focusId existe MAIS aucun filtre date n’a été donné,
         * on force une URL "fiable" : quick=date&date=<date du trajet focus>.
         * => comme ça tu "tombes" bien dans la bonne période.
         */
        $hasAnyDateFilter =
            $request->filled('quick') ||
            $request->filled('date') ||
            $request->filled('start_date') ||
            $request->filled('end_date');

        if ($focusTrajet && !$hasAnyDateFilter) {
            $params = $request->query();
            $params['quick'] = 'date';
            $params['date']  = Carbon::parse($focusTrajet->start_time)->toDateString();

            // on garde focus_trajet_id pour centrer ensuite
            return redirect()->to(
                route('voitures.trajets', ['id' => $id] + $params) . '#trajet-' . $focusId
            );
        }

        /**
         * ✅ 2) Construire la requête (toujours véhicule)
         * IMPORTANT: focus NE FILTRE PAS la query.
         */
        $query = Trajet::where('vehicle_id', $id);

        // 2.1) filtres date
        $quick = $request->input('quick', 'today');

        switch ($quick) {
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
            case 'date':
                if ($request->filled('date')) {
                    $query->whereDate('start_time', $request->date);
                }
                break;
            case 'range':
                if ($request->filled('start_date')) {
                    $query->whereDate('start_time', '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $query->whereDate('start_time', '<=', $request->end_date);
                }
                break;
        }

        // 2.2) filtres heures (robuste)
        $this->applyTimeOfDayFilter($query, $request, 'start_time');

        /**
         * ✅ 3) Si focusId est présent, on doit aller sur LA PAGE
         * qui contient ce trajet (sinon tu ne "tombes" pas dessus).
         */
        if ($focusTrajet) {
            $existsInFilteredSet = (clone $query)->where('id', $focusTrajet->id)->exists();

            if ($existsInFilteredSet) {
                // même ordre que la pagination
                $ordered = (clone $query)
                    ->orderByDesc('start_time')
                    ->orderByDesc('id');

                // combien de trajets avant lui ?
                $beforeCount = (clone $ordered)
                    ->where(function ($q) use ($focusTrajet) {
                        $q->where('start_time', '>', $focusTrajet->start_time)
                          ->orWhere(function ($q) use ($focusTrajet) {
                              $q->where('start_time', '=', $focusTrajet->start_time)
                                ->where('id', '>', $focusTrajet->id);
                          });
                    })
                    ->count();

                $targetPage = intdiv($beforeCount, $perPage) + 1;

                $currentPage = (int) $request->query('page', 1);

                if ($currentPage !== $targetPage) {
                    $params = $request->query();
                    $params['page'] = $targetPage;

                    return redirect()->to(
                        route('voitures.trajets', ['id' => $id] + $params) . '#trajet-' . $focusId
                    );
                }
            }
        }

        /**
         * ✅ 4) Pagination (affichage tableau)
         */
        $trajets = (clone $query)
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        $shown = $trajets->getCollection();

        /**
         * ✅ 5) Stats depuis BD (cohérentes AVEC le tableau affiché)
         */
        [$totalDistance, $totalDuration, $maxSpeed, $avgSpeed] = $this->statsFromDbFields($shown);

        /**
         * ✅ 6) Tracks exacts depuis locations (pour trajets visibles)
         */
        $tracks = $this->buildTracks($shown, $voiture);

        return view('trajets.byVoiture', [
            'voiture'       => $voiture,
            'vehicles'      => $vehicles,
            'trajets'       => $trajets,
            'tracks'        => $tracks,
            'filters'       => $request->all(),
            'totalDistance' => $totalDistance,
            'totalDuration' => $totalDuration,
            'maxSpeed'      => $maxSpeed,
            'avgSpeed'      => $avgSpeed,
            'focusId'       => $focusId,
        ]);
    }


    /**
     * Filtre heures robuste (gère 22:00->06:00)
     */
    private function applyTimeOfDayFilter($query, Request $request, string $column): void
    {
        $startT = $request->input('start_time');
        $endT   = $request->input('end_time');

        if ($startT && $endT) {
            if ($startT <= $endT) {
                $query->whereTime($column, '>=', $startT)
                      ->whereTime($column, '<=', $endT);
            } else {
                // intervalle nuit
                $query->where(function ($q) use ($column, $startT, $endT) {
                    $q->whereTime($column, '>=', $startT)
                      ->orWhereTime($column, '<=', $endT);
                });
            }
            return;
        }

        if ($startT) $query->whereTime($column, '>=', $startT);
        if ($endT)   $query->whereTime($column, '<=', $endT);
    }

    /**
     * Stats BD cohérentes avec le tableau affiché
     */
    private function statsFromDbFields($trajetsCollection): array
    {
        $totalDistance = 0.0;
        $totalDuration = 0;
        $maxSpeed      = 0.0;
        $avgSpeed      = 0.0;

        if ($trajetsCollection->count() === 1) {
            $t = $trajetsCollection->first();
            $totalDistance = (float) ($t->total_distance_km ?? 0);
            $totalDuration = (int)   ($t->duration_minutes ?? 0);
            $maxSpeed      = (float) ($t->max_speed_kmh ?? 0);
            $avgSpeed      = (float) ($t->avg_speed_kmh ?? 0);
        } elseif ($trajetsCollection->count() > 1) {
            $totalDistance = (float) $trajetsCollection->sum(fn($x) => (float) ($x->total_distance_km ?? 0));
            $totalDuration = (int)   $trajetsCollection->sum(fn($x) => (int)   ($x->duration_minutes ?? 0));
            $maxSpeed      = (float) $trajetsCollection->max('max_speed_kmh');

            // moyenne pondérée par durée
            $sumWeighted = 0.0;
            $sumWeight   = 0.0;

            foreach ($trajetsCollection as $tr) {
                $dur = (float) ($tr->duration_minutes ?? 0);
                $spd = (float) ($tr->avg_speed_kmh ?? 0);
                if ($dur > 0) {
                    $sumWeighted += ($spd * $dur);
                    $sumWeight   += $dur;
                }
            }
            $avgSpeed = ($sumWeight > 0)
                ? ($sumWeighted / $sumWeight)
                : (float) $trajetsCollection->avg('avg_speed_kmh');
        }

        return [
            round($totalDistance, 1),
            (int) $totalDuration,
            round($maxSpeed, 1),
            round($avgSpeed, 1),
        ];
    }

    /**
     * Tracks exacts depuis locations (chemin)
     */
    private function buildTracks($trajetsCollection, Voiture $voiture): array
    {
        $tracks = [];

        $maxPoints = (int) env('TRACK_MAX_POINTS', 1500);
        $maxDbRows = (int) env('TRACK_MAX_DB_ROWS', 20000);

        foreach ($trajetsCollection as $t) {

            $mac = $t->mac_id_gps ?: $voiture->mac_id_gps;
            if (empty($mac)) continue;

            $start = Carbon::parse($t->start_time);
            $end   = $t->end_time ? Carbon::parse($t->end_time) : (clone $start)->addHours(3);

            $locs = Location::query()
                ->select(['latitude', 'longitude', 'datetime', 'speed'])
                ->where('mac_id_gps', $mac)
                ->whereBetween('datetime', [$start, $end])
                ->orderBy('datetime', 'asc')
                ->limit($maxDbRows)
                ->get();

            if ($locs->count() < 2) {
                $tracks[] = [
                    'trajet_id'  => $t->id,
                    'start_time' => $start->format('Y-m-d H:i:s'),
                    'end_time'   => $end->format('Y-m-d H:i:s'),
                    'start' => ['lat' => (float) $t->start_latitude, 'lng' => (float) $t->start_longitude],
                    'end'   => ['lat' => (float) $t->end_latitude,   'lng' => (float) $t->end_longitude],
                    'points' => [],
                ];
                continue;
            }

            $points = $locs->map(function ($l) {
                return [
                    'lat'   => (float) $l->latitude,
                    'lng'   => (float) $l->longitude,
                    't'     => $l->datetime ? Carbon::parse($l->datetime)->format('Y-m-d H:i:s') : null,
                    'speed' => (float) ($l->speed ?? 0),
                ];
            })->values()->all();

            if (count($points) > $maxPoints) {
                $step = (int) ceil(count($points) / $maxPoints);
                $reduced = [];
                for ($i = 0; $i < count($points); $i += $step) $reduced[] = $points[$i];
                $last = end($points);
                if ($last && (empty($reduced) || $reduced[count($reduced) - 1] !== $last)) $reduced[] = $last;
                $points = $reduced;
            }

            $tracks[] = [
                'trajet_id'  => $t->id,
                'start_time' => $start->format('Y-m-d H:i:s'),
                'end_time'   => $end->format('Y-m-d H:i:s'),
                'start' => ['lat' => (float) $points[0]['lat'], 'lng' => (float) $points[0]['lng']],
                'end'   => ['lat' => (float) $points[count($points) - 1]['lat'], 'lng' => (float) $points[count($points) - 1]['lng']],
                'points' => $points,
            ];
        }

        return $tracks;
    }
}
