<?php

namespace App\Http\Controllers\Gps;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HistoriqueCoupureController extends Controller
{
    /**
     * HISTORIQUE DES COMMANDES (coupure/allumage)
     *
     * GET params :
     * - q         : recherche véhicule / CmdNo (immat, marque, modèle, MAC ID)
     * - type      : all | coupure | allumage (défaut: all)
     *
     * - date_mode : period | single | range (défaut: period)
     *   - period  : period=today|week|month|year (défaut: week)
     *   - single  : date=YYYY-MM-DD
     *   - range   : from=YYYY-MM-DD & to=YYYY-MM-DD
     *
     * Tri : created_at DESC (plus récent en haut)
     */
    public function index(Request $request)
    {
        // --- Filters (sanitize) ---
        $q = trim((string) $request->query('q', ''));

        $type = strtolower((string) $request->query('type', 'all'));
        if (!in_array($type, ['all', 'coupure', 'allumage'], true)) $type = 'all';

        $dateMode = strtolower((string) $request->query('date_mode', 'period'));
        if (!in_array($dateMode, ['period', 'single', 'range'], true)) $dateMode = 'period';

        $period = strtolower((string) $request->query('period', 'week'));
        if (!in_array($period, ['today', 'week', 'month', 'year'], true)) $period = 'week';

        // --- Resolve date range ---
        [$from, $to] = $this->resolveDateRange(
            $dateMode,
            $period,
            (string) $request->query('date'),
            (string) $request->query('from'),
            (string) $request->query('to'),
        );

        // --- Query ---
        $commands = Commande::query()
            ->with([
                'vehicule:id,immatriculation,marque,model,mac_id_gps',
                'user:id,nom,prenom,email',
                'employe:id,nom,prenom,email',
            ])
            ->when($type !== 'all', fn ($qq) => $qq->where('type_commande', $type))
            ->whereBetween('created_at', [$from, $to])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($sub) use ($q) {
                    $sub->where('CmdNo', 'like', "%{$q}%")
                        ->orWhereHas('vehicule', function ($v) use ($q) {
                            $v->where('immatriculation', 'like', "%{$q}%")
                                ->orWhere('marque', 'like', "%{$q}%")
                                ->orWhere('model', 'like', "%{$q}%")
                                ->orWhere('mac_id_gps', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('coupure_moteur.coupure_historique', [
            'commands'  => $commands,

            // filtres
            'q'         => $q,
            'type'      => $type,

            'dateMode'  => $dateMode,
            'period'    => $period,
            'date'      => (string) $request->query('date', ''),
            'fromInput' => (string) $request->query('from', ''),
            'toInput'   => (string) $request->query('to', ''),

            // range effectif
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    /**
     * Résout la plage de dates [from, to] selon le mode :
     * - period : today / week / month / year
     * - single : date précise
     * - range  : from -> to
     */
    private function resolveDateRange(string $mode, string $period, string $date, string $from, string $to): array
    {
        $now = now();

        // Helpers parse
        $parseYmd = function (string $ymd): ?Carbon {
            $ymd = trim($ymd);
            if ($ymd === '') return null;
            try {
                return Carbon::createFromFormat('Y-m-d', $ymd);
            } catch (\Throwable) {
                return null;
            }
        };

        // SINGLE
        if ($mode === 'single') {
            $d = $parseYmd($date) ?? $now->copy();
            return [$d->copy()->startOfDay(), $d->copy()->endOfDay()];
        }

        // RANGE
        if ($mode === 'range') {
            $f = $parseYmd($from);
            $t = $parseYmd($to);

            // fallback intelligent si un seul est fourni
            if (!$f && !$t) {
                $f = $now->copy()->startOfWeek(Carbon::MONDAY);
                $t = $now->copy()->endOfDay();
            } elseif ($f && !$t) {
                $t = $now->copy()->endOfDay();
            } elseif (!$f && $t) {
                $f = $now->copy()->startOfWeek(Carbon::MONDAY);
            }

            if ($f->gt($t)) {
                [$f, $t] = [$t, $f];
            }

            return [$f->copy()->startOfDay(), $t->copy()->endOfDay()];
        }

        // PERIOD (default)
        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)],
        };
    }
}
