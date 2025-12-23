<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Trajet;
use App\Models\Voiture;
use App\Models\Location;
use Carbon\Carbon;

class TrajetController extends Controller
{
    /**
     * Timezone "métier" (ce que l'utilisateur considère comme Aujourd'hui)
     */
    private string $userTz = 'Africa/Douala';

    /**
     * Timezone de stockage DB/app (souvent UTC chez toi)
     */
    private string $dbTz;

    public function __construct()
    {
        $this->dbTz = config('app.timezone', 'UTC');
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    private function normalizeTime(?string $t, string $defaultSuffix = ':00'): ?string
    {
        if (!$t) return null;

        $t = trim($t);
        if ($t === '') return null;

        // "HH:MM" => "HH:MM:00" (ou :59)
        if (preg_match('/^\d{2}:\d{2}$/', $t)) {
            return $t . $defaultSuffix;
        }

        // "HH:MM:SS" => ok
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) {
            return $t;
        }

        return null;
    }

    /**
     * Applique le filtre quick (today/yesterday/week/month/year/date/range)
     * avec conversion Africa/Douala -> timezone DB.
     */
    private function applyQuickDateFilter(Builder $query, Request $request, string $column = 'start_time'): void
    {
        $quick = $request->input('quick');
        if (!$quick) return;

        $start = null;
        $end   = null;

        switch ($quick) {
            case 'today':
                $start = Carbon::now($this->userTz)->startOfDay();
                $end   = Carbon::now($this->userTz)->endOfDay();
                break;

            case 'yesterday':
                $start = Carbon::now($this->userTz)->subDay()->startOfDay();
                $end   = Carbon::now($this->userTz)->subDay()->endOfDay();
                break;

            case 'week':
                $start = Carbon::now($this->userTz)->startOfWeek()->startOfDay();
                $end   = Carbon::now($this->userTz)->endOfWeek()->endOfDay();
                break;

            case 'month':
                $start = Carbon::now($this->userTz)->startOfMonth()->startOfDay();
                $end   = Carbon::now($this->userTz)->endOfMonth()->endOfDay();
                break;

            case 'year':
                $start = Carbon::now($this->userTz)->startOfYear()->startOfDay();
                $end   = Carbon::now($this->userTz)->endOfYear()->endOfDay();
                break;

            case 'date':
                if ($request->filled('date')) {
                    $d = Carbon::parse($request->input('date'), $this->userTz);
                    $start = $d->copy()->startOfDay();
                    $end   = $d->copy()->endOfDay();
                }
                break;

            case 'range':
                if ($request->filled('start_date')) {
                    $start = Carbon::parse($request->input('start_date'), $this->userTz)->startOfDay();
                }
                if ($request->filled('end_date')) {
                    $end = Carbon::parse($request->input('end_date'), $this->userTz)->endOfDay();
                }
                break;
        }

        if ($start) $start = $start->copy()->setTimezone($this->dbTz);
        if ($end)   $end   = $end->copy()->setTimezone($this->dbTz);

        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);
        } elseif ($start) {
            $query->where($column, '>=', $start);
        } elseif ($end) {
            $query->where($column, '<=', $end);
        }
    }

    /**
     * Applique start_date / end_date "avancés" (même si quick n'est pas range),
     * comme dans ton index actuel.
     */
    private function applyExplicitDateRange(Builder $query, Request $request, string $column = 'start_time'): void
    {
        if ($request->filled('start_date')) {
            $start = Carbon::parse($request->input('start_date'), $this->userTz)
                ->startOfDay()
                ->setTimezone($this->dbTz);
            $query->where($column, '>=', $start);
        }

        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->input('end_date'), $this->userTz)
                ->endOfDay()
                ->setTimezone($this->dbTz);
            $query->where($column, '<=', $end);
        }
    }

    /**
     * Filtre sur les heures (TIME())
     * - start_time >= HH:MM:00
     * - end_time <= HH:MM:59 (on utilise COALESCE(end_time, start_time))
     */
    private function applyTimeFilter(Builder $query, Request $request, string $columnStart = 'start_time', string $columnEnd = 'end_time'): void
    {
        $st = $this->normalizeTime($request->input('start_time'), ':00');
        $et = $this->normalizeTime($request->input('end_time'), ':59');

        if ($st) {
            $query->whereRaw("TIME($columnStart) >= ?", [$st]);
        }

        if ($et) {
            // on filtre par l'heure de fin si elle existe, sinon on prend l'heure de start_time
            $query->whereRaw("TIME(COALESCE($columnEnd, $columnStart)) <= ?", [$et]);
        }
    }

    /* =========================================================
     * 1) Index (tous les trajets)
     * ========================================================= */

    public function index(Request $request)
    {
        $query = Trajet::with('voiture');

        // ✅ Si aucune date n'est fournie, on garde ton comportement implicite :
        //    "today" seulement si rien n'est envoyé du tout.
        $hasDateSignal =
            $request->filled('quick') ||
            $request->filled('date') ||
            $request->filled('start_date') ||
            $request->filled('end_date');

        if (!$hasDateSignal) {
            $request->merge(['quick' => 'today']);
        }

        // ✅ quick + date range (timezone safe)
        $this->applyQuickDateFilter($query, $request, 'start_time');
        $this->applyExplicitDateRange($query, $request, 'start_time');

        // ✅ filtre véhicule
        $selectedVehicle = null;

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
            $selectedVehicle = Voiture::select('id', 'immatriculation')->find($request->vehicle_id);
        } elseif ($request->filled('vehicule')) {
            $query->whereHas('voiture', function ($q) use ($request) {
                $q->where('immatriculation', 'LIKE', '%' . $request->vehicule . '%');
            });
        }

        // ✅ filtre heures
        $this->applyTimeFilter($query, $request, 'start_time', 'end_time');

        // ✅ exécution
        $trajets = $query->orderBy('start_time', 'desc')->paginate(20);

        // ✅ liste véhicules (pour suggestions / search)
        $vehicles = Voiture::select('id', 'immatriculation')->orderBy('immatriculation')->get();

        return view('trajets.index', compact('trajets', 'vehicles', 'selectedVehicle'));
    }

    /* =========================================================
     * 2) ByVoiture (carte / détail)
     * ========================================================= */

    public function byVoiture($vehicle_id, Request $request)
    {
        $voiture = Voiture::findOrFail($vehicle_id);

        $focusId = $request->input('focus_trajet_id');

        // Base query
        $query = Trajet::where('vehicle_id', $vehicle_id);

        if ($focusId) {
            // ✅ Mode détail : on force uniquement ce trajet
            $trajets = (clone $query)->where('id', $focusId)->orderBy('start_time', 'desc')->get();

            if ($trajets->isEmpty()) {
                abort(404, "Trajet introuvable pour ce véhicule.");
            }
        } else {
            // ✅ IMPORTANT : ne pas forcer "today" si l'utilisateur a déjà envoyé un filtre
            $hasDateSignal =
                $request->filled('quick') ||
                $request->filled('date') ||
                $request->filled('start_date') ||
                $request->filled('end_date');

            $hasTimeSignal =
                $request->filled('start_time') ||
                $request->filled('end_time');

            if (!$hasDateSignal && !$hasTimeSignal) {
                // aucun filtre envoyé => défaut today
                $request->merge(['quick' => 'today']);
            }

            // ✅ filtres date
            $this->applyQuickDateFilter($query, $request, 'start_time');
            $this->applyExplicitDateRange($query, $request, 'start_time');

            // ✅ filtres heures
            $this->applyTimeFilter($query, $request, 'start_time', 'end_time');

            // ✅ derniers trajets
            $trajets = $query->orderBy('start_time', 'desc')->limit(20)->get();
        }

        /**
         * ✅ Stats : UNIQUEMENT depuis champs BD
         * - détail (1 trajet) => valeurs du trajet
         * - liste (n trajets) => agrégations simples BD
         */
        $totalDistance = 0.0;
        $totalDuration = 0;
        $maxSpeed      = 0.0;
        $avgSpeed      = 0.0;

        if ($trajets->count() === 1) {
            $t = $trajets->first();
            $totalDistance = (float) ($t->total_distance_km ?? 0);
            $totalDuration = (int)   ($t->duration_minutes ?? 0);
            $maxSpeed      = (float) ($t->max_speed_kmh ?? 0);
            $avgSpeed      = (float) ($t->avg_speed_kmh ?? 0);
        } elseif ($trajets->count() > 1) {
            $totalDistance = (float) $trajets->sum(fn($x) => (float) ($x->total_distance_km ?? 0));
            $totalDuration = (int)   $trajets->sum(fn($x) => (int)   ($x->duration_minutes ?? 0));
            $maxSpeed      = (float) $trajets->max('max_speed_kmh');
            $avgSpeed      = (float) $trajets->avg('avg_speed_kmh'); // ✅ moyenne simple BD
        }

        $totalDistance = round($totalDistance, 1);
        $maxSpeed      = round($maxSpeed, 1);
        $avgSpeed      = round($avgSpeed, 1);

        /**
         * Tracks depuis locations
         */
        $tracks = [];

        $maxPoints = (int) env('TRACK_MAX_POINTS', 1500);
        $maxDbRows = (int) env('TRACK_MAX_DB_ROWS', 20000);

        foreach ($trajets as $t) {
            $mac = $t->mac_id_gps ?: $voiture->mac_id_gps;
            if (empty($mac)) continue;

            $start = Carbon::parse($t->start_time);
            $end = $t->end_time ? Carbon::parse($t->end_time) : (clone $start)->addHours(3);

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
                for ($i = 0; $i < count($points); $i += $step) {
                    $reduced[] = $points[$i];
                }
                $last = end($points);
                if ($last && (empty($reduced) || $reduced[count($reduced) - 1] !== $last)) {
                    $reduced[] = $last;
                }
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

        return view('trajets.byVoiture', [
            'voiture'       => $voiture,
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
}
