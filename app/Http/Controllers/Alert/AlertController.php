<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlertController extends Controller
{
    /**
     * Types de base qu'on veut voir dans les petites cartes.
     * Si un nouveau type apparaît, il sera aussi compté dynamiquement.
     */
    private array $statsTypes = [
        'stolen',
        'low_battery',
        'geofence',
        'safe_zone',
        'speed',
        'offline',
        'time_zone',
        'engine_on',
        'engine_off',
        'other',
        'unknown',
    ];

    /**
     * GET /alerts
     * JSON only
     * Interne = on montre tous les types d'alertes non vides
     */
    public function index(Request $request)
    {
        $tz = 'Africa/Douala';

        $query = Alert::query()
            ->with(['voiture.utilisateur', 'processedBy'])
            ->whereNotNull('alert_type')
            ->where('alert_type', '!=', '');

        $this->applyFilters($query, $request, $tz);

        $stats = $this->computeStats(clone $query);

        $perPage = (int) $request->query('per_page', 50);
        $perPage = min(max($perPage, 1), 200);

        $alerts = $query
            ->orderByDesc('alerted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => collect($alerts->items())->map(fn (Alert $a) => $this->formatAlert($a))->values(),
            'meta'   => [
                'current_page' => $alerts->currentPage(),
                'total'        => $alerts->total(),
                'last_page'    => $alerts->lastPage(),
            ],
            'stats'  => $stats,
        ]);
    }

    /**
     * PATCH /alerts/{id}/processed
     */
    public function markAsProcessed(Request $request, $id)
    {
        $data = $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        $alert = Alert::with(['voiture', 'processedBy'])->findOrFail($id);

        $alert->processed = true;
        $alert->processed_by = Auth::id();
        $alert->commentaire = $data['commentaire'] ?? null;
        $alert->save();

        $alert->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Alerte marquée comme traitée',
            'data'    => [
                'id'                => (int) $alert->id,
                'is_processed'      => true,
                'processed_by'      => $alert->processed_by,
                'commentaire'       => $alert->commentaire,
                'processed_by_name' => optional($alert->processedBy)->name,
            ],
        ]);
    }

    private function applyFilters($query, Request $request, string $tz): void
    {
        if ($request->filled('vehicle_id')) {
            $query->where('voiture_id', (int) $request->vehicle_id);
        }

        if ($request->filled('q')) {
            $term = '%' . trim((string) $request->q) . '%';

            $query->where(function ($q) use ($term) {
                $q->where('message', 'like', $term)
                  ->orWhere('alert_type', 'like', $term)
                  ->orWhereHas('voiture', function ($vq) use ($term) {
                      $vq->where('immatriculation', 'like', $term)
                         ->orWhere('marque', 'like', $term)
                         ->orWhere('model', 'like', $term);
                  })
                  ->orWhereHas('voiture.utilisateur', function ($uq) use ($term) {
                      $uq->where('nom', 'like', $term)
                         ->orWhere('prenom', 'like', $term)
                         ->orWhere('phone', 'like', $term);
                  });
            });
        }

        if ($request->filled('alert_type') && $request->alert_type !== 'all') {
            $requestedType = $this->normalizeType($request->alert_type);

            match ($requestedType) {
                'speed' => $query->whereIn('alert_type', ['speed', 'overspeed', 'speeding']),

                'geofence' => $query->whereIn('alert_type', [
                    'geofence', 'geo_fence', 'geofence_enter', 'geofence_exit', 'geofence_breach'
                ]),

                'safe_zone' => $query->whereIn('alert_type', [
                    'safe_zone', 'safezone', 'safe-zone'
                ]),

                'low_battery' => $query->whereIn('alert_type', [
                    'low_battery', 'battery_low', 'lowbattery'
                ]),

                'time_zone' => $query->whereIn('alert_type', [
                    'time_zone', 'timezone', 'time-zone'
                ]),

                'offline' => $query->whereIn('alert_type', [
                    'offline', 'unauthorized'
                ]),

                default => $query->where('alert_type', $requestedType),
            };
        }

        $quick = $request->get('quick') ?: $request->get('date_quick', 'today');
        $now = now($tz);

        if ($quick && $quick !== 'range') {
            match ($quick) {
                'today' => $query->where(function ($q) use ($now) {
                    $q->whereDate('alerted_at', $now->toDateString())
                      ->orWhere(function ($qq) use ($now) {
                          $qq->whereNull('alerted_at')
                             ->whereDate('created_at', $now->toDateString());
                      });
                }),

                'yesterday' => $query->where(function ($q) use ($now) {
                    $d = $now->copy()->subDay()->toDateString();
                    $q->whereDate('alerted_at', $d)
                      ->orWhere(function ($qq) use ($d) {
                          $qq->whereNull('alerted_at')
                             ->whereDate('created_at', $d);
                      });
                }),

                'this_week' => $query->where(function ($q) use ($now) {
                    $from = $now->copy()->startOfWeek();
                    $to   = $now->copy()->endOfWeek();
                    $q->whereBetween('alerted_at', [$from, $to])
                      ->orWhere(function ($qq) use ($from, $to) {
                          $qq->whereNull('alerted_at')
                             ->whereBetween('created_at', [$from, $to]);
                      });
                }),

                'this_month' => $query->where(function ($q) use ($now) {
                    $from = $now->copy()->startOfMonth();
                    $to   = $now->copy()->endOfMonth();
                    $q->whereBetween('alerted_at', [$from, $to])
                      ->orWhere(function ($qq) use ($from, $to) {
                          $qq->whereNull('alerted_at')
                             ->whereBetween('created_at', [$from, $to]);
                      });
                }),

                default => null,
            };
        } else {
            if ($request->filled('date_from')) {
                $from = Carbon::parse($request->date_from, $tz)->startOfDay();
                $to   = Carbon::parse($request->date_to ?: $request->date_from, $tz)->endOfDay();

                $query->where(function ($q) use ($from, $to) {
                    $q->whereBetween('alerted_at', [$from, $to])
                      ->orWhere(function ($qq) use ($from, $to) {
                          $qq->whereNull('alerted_at')
                             ->whereBetween('created_at', [$from, $to]);
                      });
                });
            }

            if ($request->filled('hour_from') || $request->filled('hour_to')) {
                $hourFrom = $request->input('hour_from');
                $hourTo   = $request->input('hour_to');

                $columnExpr = "COALESCE(alerted_at, created_at)";

                if ($hourFrom && $hourTo) {
                    if ($hourFrom <= $hourTo) {
                        $query->whereRaw("TIME($columnExpr) >= ? AND TIME($columnExpr) <= ?", [$hourFrom, $hourTo]);
                    } else {
                        $query->where(function ($q) use ($columnExpr, $hourFrom, $hourTo) {
                            $q->whereRaw("TIME($columnExpr) >= ?", [$hourFrom])
                              ->orWhereRaw("TIME($columnExpr) <= ?", [$hourTo]);
                        });
                    }
                } elseif ($hourFrom) {
                    $query->whereRaw("TIME($columnExpr) >= ?", [$hourFrom]);
                } elseif ($hourTo) {
                    $query->whereRaw("TIME($columnExpr) <= ?", [$hourTo]);
                }
            }
        }
    }

    private function computeStats($query): array
    {
        $rows = $query->get(['alert_type']);

        $byType = array_fill_keys($this->statsTypes, 0);

        foreach ($rows as $row) {
            $type = $this->normalizeType($row->alert_type);

            if (!array_key_exists($type, $byType)) {
                $byType[$type] = 0;
            }

            $byType[$type]++;
        }

        return ['by_type' => $byType];
    }

    private function formatAlert(Alert $a): array
    {
        $voiture = $a->voiture;

        $driver = 'Non assigné';
        if ($voiture && $voiture->utilisateur && $voiture->utilisateur->count() > 0) {
            $u = $voiture->utilisateur->first();
            $driver = trim(($u->nom ?? '') . ' ' . ($u->prenom ?? ''));
            if ($driver === '') {
                $driver = $u->phone ?? 'Non assigné';
            }
        }

        $type = $this->normalizeType($a->alert_type ?? $a->type);

        return [
            'id'           => (int) $a->id,
            'type'         => $type,
            'type_label'   => $this->typeLabel($type),
            'message'      => $a->message,
            'is_read'      => (bool) ($a->read ?? false),
            'is_processed' => (bool) ($a->processed ?? false),
            'created_at'   => ($a->alerted_at ?? $a->created_at)
                ? Carbon::parse($a->alerted_at ?? $a->created_at)->format('d/m/Y H:i:s')
                : '—',

            'vehicle' => [
                'id'    => $a->voiture_id,
                'label' => $voiture
                    ? trim(($voiture->immatriculation ?? '—') . ' (' . ($voiture->marque ?? 'Véhicule') . ')')
                    : '—',
            ],

            'driver'      => $driver ?: 'Non assigné',
            'location'    => $a->location ?? $a->message,
            'description' => $a->message,
            'lat'         => $a->lat ?? null,
            'lng'         => $a->lng ?? null,
            'speed'       => $a->speed ?? null,
            'raw_type'    => $a->alert_type,
        ];
    }

    private function normalizeType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return match ($type) {
            'overspeed', 'speeding' => 'speed',

            'geo_fence',
            'geofence_enter',
            'geofence_exit',
            'geofence_breach' => 'geofence',

            'safezone',
            'safe-zone' => 'safe_zone',

            'battery_low',
            'lowbattery' => 'low_battery',

            'timezone',
            'time-zone' => 'time_zone',

            'unauthorized' => 'offline',

            '' => 'unknown',

            default => $type,
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'stolen'      => 'Vol',
            'low_battery' => 'Batterie faible',
            'geofence'    => 'Géofence',
            'safe_zone'   => 'Zone sûre',
            'speed'       => 'Survitesse',
            'offline'     => 'Hors ligne',
            'time_zone'   => 'Time zone',
            'engine_on'   => 'Moteur ON',
            'engine_off'  => 'Moteur OFF',
            'other'       => 'Autre',
            default       => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}