<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trajet;
use App\Models\Voiture;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class TrajetController extends Controller
{
    /**
     * Tous les trajets (tous véhicules)
     */
    public function index(Request $request)
    {
        $query = Trajet::with('voiture');

        /**
         * 1) Dates :
         * - priorité à start_date/end_date (si renseignés)
         * - sinon quick (si renseigné)
         * - sinon aucun filtre date
         */
        $this->applyIndexDateFilters($query, $request, 'start_time');

        /**
         * 2) Véhicule (priorité vehicle_id)
         */
        $selectedVehicle = null;

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
            $selectedVehicle = Voiture::select('id', 'immatriculation')->find($request->vehicle_id);
        } elseif ($request->filled('vehicule')) {
            $query->whereHas('voiture', function ($q) use ($request) {
                $q->where('immatriculation', 'LIKE', '%' . $request->vehicule . '%');
            });
        }

        /**
         * 3) Filtre heures (robuste)
         */
        $this->applyTimeOfDayFilter($query, $request, 'start_time');

        /**
         * 4) Pagination
         */
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

        // Liste véhicules (switch véhicule depuis la vue)
        $vehicles = Voiture::select('id', 'immatriculation')
            ->orderBy('immatriculation')
            ->get();

        $perPage = 20;

        /**
         * Focus trajet
         */
        $focusId = $request->query('focus_trajet_id');
        $focusTrajet = null;

        if ($focusId) {
            $focusTrajet = Trajet::where('vehicle_id', $id)
                ->where('id', $focusId)
                ->first();
        }

        /**
         * ✅ Si on arrive via "Détails" et qu'aucun filtre date n'est fourni,
         * on force une URL stable sur la date du trajet focus
         */
        $hasAnyExplicitDateFilter =
            $request->filled('quick') ||
            $request->filled('date') ||
            $request->filled('start_date') ||
            $request->filled('end_date');

        if ($focusTrajet && !$hasAnyExplicitDateFilter) {
            $params = $request->query();
            $params['quick'] = 'date';
            $params['date']  = Carbon::parse($focusTrajet->start_time)->toDateString();

            return redirect()->to(
                route('voitures.trajets', ['id' => $id] + $params) . '#trajet-' . $focusId
            );
        }

        /**
         * ✅ MODE DETAIL : afficher uniquement le trajet focus (sans filtrer par quick/time etc.)
         * - utile quand on clique "Détails"
         */
        $mode = $request->query('mode'); // 'detail' ou null

        if ($mode === 'detail' && $focusTrajet) {
            $shown = collect([$focusTrajet]);

            // paginator "fake" pour garder la vue compatible (count(), links() optionnel)
            $trajets = new LengthAwarePaginator(
                $shown,
                1,   // total
                1,   // perPage
                1,   // currentPage
                [
                    'path'  => url()->current(),
                    'query' => $request->query(),
                ]
            );

            [$totalDistance, $totalDuration, $maxSpeed, $avgSpeed] = $this->statsFromDbFields($shown);
            $tracks = $this->buildTracks($shown, $voiture, $focusId);

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
                'focusTrajet'   => $focusTrajet,
                'mode'          => $mode,
            ]);
        }

        /**
         * ✅ Query de base (liste filtrée)
         */
        $query = Trajet::where('vehicle_id', $id);

        /**
         * ✅ Filtres dates (par défaut today si rien)
         */
        $this->applyByVoitureDateFilters($query, $request, 'start_time');

        /**
         * ✅ Filtres heures
         */
        $this->applyTimeOfDayFilter($query, $request, 'start_time');

        /**
         * ✅ Si focus => tomber sur la page qui contient ce trajet (dans l’ensemble filtré)
         * IMPORTANT : en mode liste uniquement
         */
        if ($focusTrajet) {
            $existsInFilteredSet = (clone $query)->where('id', $focusTrajet->id)->exists();

            if ($existsInFilteredSet) {
                $ordered = (clone $query)
                    ->orderByDesc('start_time')
                    ->orderByDesc('id');

                $beforeCount = (clone $ordered)
                    ->where(function ($q) use ($focusTrajet) {
                        $q->where('start_time', '>', $focusTrajet->start_time)
                          ->orWhere(function ($q) use ($focusTrajet) {
                              $q->where('start_time', '=', $focusTrajet->start_time)
                                ->where('id', '>', $focusTrajet->id);
                          });
                    })
                    ->count();

                $targetPage  = intdiv($beforeCount, $perPage) + 1;
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
         * ✅ Pagination (tableau)
         */
        $trajets = (clone $query)
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        $shown = $trajets->getCollection();

        /**
         * ✅ Stats cohérentes avec CE QUI EST AFFICHÉ
         */
        [$totalDistance, $totalDuration, $maxSpeed, $avgSpeed] = $this->statsFromDbFields($shown);

        /**
         * ✅ Tracks depuis locations
         */
        $tracks = $this->buildTracks($shown, $voiture, $focusId);

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
            'focusTrajet'   => $focusTrajet,
            'mode'          => $mode,
        ]);
    }


    /**
     * Dates pour INDEX:
     * - start_date/end_date prioritaire
     * - sinon quick
     * - sinon rien
     */
    private function applyIndexDateFilters($query, Request $request, string $column): void
    {
        $hasRange = $request->filled('start_date') || $request->filled('end_date');

        if ($hasRange) {
            if ($request->filled('start_date')) {
                $query->whereDate($column, '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate($column, '<=', $request->end_date);
            }
            return;
        }

        if ($request->filled('quick')) {
            switch ($request->quick) {
                case 'today':
                    $query->whereDate($column, now());
                    break;
                case 'yesterday':
                    $query->whereDate($column, now()->subDay());
                    break;
                case 'week':
                    $query->whereBetween($column, [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()]);
                    break;
                case 'year':
                    $query->whereYear($column, now()->year);
                    break;
            }
        }
    }


    /**
     * Dates pour BYVOITURE:
     * - si quick vide mais date/range renseigné => on respecte date/range
     * - sinon défaut today
     */
    private function applyByVoitureDateFilters($query, Request $request, string $column): void
    {
        $quick = $request->input('quick');
        $quick = is_string($quick) ? trim($quick) : $quick;
        if ($quick === '') $quick = null;

        if (!$quick) {
            if ($request->filled('date')) {
                $quick = 'date';
            } elseif ($request->filled('start_date') || $request->filled('end_date')) {
                $quick = 'range';
            } else {
                $quick = 'today';
            }
        }

        switch ($quick) {
            case 'today':
                $query->whereDate($column, now());
                break;

            case 'yesterday':
                $query->whereDate($column, now()->subDay());
                break;

            case 'week':
                $query->whereBetween($column, [now()->startOfWeek(), now()->endOfWeek()]);
                break;

            case 'month':
                $query->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()]);
                break;

            case 'year':
                $query->whereYear($column, now()->year);
                break;

            case 'date':
                if ($request->filled('date')) {
                    $query->whereDate($column, $request->date);
                }
                break;

            case 'range':
                if ($request->filled('start_date')) {
                    $query->whereDate($column, '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $query->whereDate($column, '<=', $request->end_date);
                }
                break;
        }
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
     * Tracks exacts depuis locations
     * - En focus, on autorise plus de points/rows => tracé plus fidèle.
     */
    private function buildTracks($trajetsCollection, Voiture $voiture, $focusId = null): array
    {
        $tracks = [];

        foreach ($trajetsCollection as $t) {

            $isFocusTrip = $focusId && ((int)$t->id === (int)$focusId);

            $maxPoints = (int) ($isFocusTrip
                ? env('TRACK_MAX_POINTS_FOCUS', 20000)
                : env('TRACK_MAX_POINTS', 1500)
            );

            $maxDbRows = (int) ($isFocusTrip
                ? env('TRACK_MAX_DB_ROWS_FOCUS', 120000)
                : env('TRACK_MAX_DB_ROWS', 20000)
            );

            $mac = $t->mac_id_gps ?: $voiture->mac_id_gps;
            if (empty($mac)) continue;

            $start = Carbon::parse($t->start_time);
            $end   = $t->end_time ? Carbon::parse($t->end_time) : (clone $start)->addHours(3);

            $startQ = (clone $start)->subMinutes(2);
            $endQ   = (clone $end)->addMinutes(2);

            $locs = Location::query()
                ->select(['latitude', 'longitude', 'datetime', 'speed'])
                ->where('mac_id_gps', $mac)
                ->whereBetween('datetime', [$startQ, $endQ])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
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

            $points = [];
            $prevKey = null;

            foreach ($locs as $l) {
                $lat = (float) $l->latitude;
                $lng = (float) $l->longitude;

                $key = number_format($lat, 6) . ',' . number_format($lng, 6);
                if ($key === $prevKey) continue;
                $prevKey = $key;

                $points[] = [
                    'lat'   => $lat,
                    'lng'   => $lng,
                    't'     => $l->datetime ? Carbon::parse($l->datetime)->format('Y-m-d H:i:s') : null,
                    'speed' => (float) ($l->speed ?? 0),
                ];
            }

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
