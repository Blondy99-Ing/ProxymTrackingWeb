<?php

namespace App\Http\Controllers;

use App\Models\SimGps;
use App\Services\GpsControlService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class GpsSimController extends Controller
{
    /**
     * Liste des GPS + SIM + Statut moteur/GPS
     */
    public function index(Request $request, GpsControlService $gps): View
    {
        $q = trim((string) $request->get('q', ''));

        $items = SimGps::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('mac_id', 'like', "%{$q}%")
                        ->orWhere('objectid', 'like', "%{$q}%")
                        ->orWhere('sim_number', 'like', "%{$q}%")
                        ->orWhere('account_name', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->appends(['q' => $q]);

        // 🧠 Pour chaque GPS, on calcule engine_state + gps_online depuis la table locations
        $items->getCollection()->transform(function (SimGps $item) use ($gps) {

            $engineState = null;
            $engineCut   = null;
            $gpsOnline   = null;
            $gpsLastSeen = null;

            if (!empty($item->mac_id)) {
                try {
                    $status = $gps->getEngineStatusFromLastLocation($item->mac_id);

                    if ($status['success'] ?? false) {
                        $decoded     = $status['decoded'] ?? [];
                        $engineState = $decoded['engineState'] ?? 'UNKNOWN';
                        $engineCut   = ($engineState === 'CUT');

                        $last = $status['location']['heart_time']
                            ?? $status['location']['sys_time']
                            ?? $status['datetime']
                            ?? null;

                        if ($last) {
                            $gpsLastSeen = $last;

                            try {
                                $dt        = Carbon::parse($last);
                                $gpsOnline = $dt->diffInMinutes(now()) <= 10;
                            } catch (\Throwable $e) {
                                $gpsOnline = null;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[GPS_SIM] Erreur getEngineStatusFromLastLocation', [
                        'mac_id' => $item->mac_id,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

            $item->engine_state  = $engineState;   // CUT / ON / OFF / UNKNOWN / null
            $item->engine_cut    = $engineCut;     // bool|null
            $item->gps_online    = $gpsOnline;     // bool|null
            $item->gps_last_seen = $gpsLastSeen;   // string|null

            return $item;
        });

        return view('gps_sim.index', compact('items', 'q'));
    }

    /**
     * ✅ Mettre à jour la SIM d’un GPS
     */
    public function updateSim(Request $request, SimGps $simGps): RedirectResponse
    {
        $validated = $request->validate([
            'sim_number' => ['nullable', 'string', 'max:30'],
        ]);

        $sim = trim((string) ($validated['sim_number'] ?? ''));
        $simGps->sim_number = $sim === '' ? null : $sim;
        $simGps->save();

        return back()->with('success', "SIM mise à jour pour {$simGps->mac_id} ✅");
    }

    /**
     * ✅ Sync MULTI-COMPTES (tracking puis mobility)
     * - récupère les GPS du compte
     * - insère uniquement les nouveaux
     * - remplit account_name à l'ajout
     * - backfill account_name uniquement si NULL
     */
    public function syncFromAccount(Request $request, GpsControlService $gps): RedirectResponse
    {
        $accounts = ['tracking', 'mobility'];

        $summary = [];
        $anyOk = false;

        foreach ($accounts as $account) {
            try {
                // 1) switch identifiants du service + token isolé
                $gps->setAccount($account);

                $raw     = $gps->getAccountDeviceListRaw();
                $devices = $this->extractDevicesUltraRobust($raw);

                Log::info('[GPS_SIM] Sync RAW', [
                    'account'       => $account,
                    'raw_top_keys'   => is_array($raw) ? array_keys($raw) : null,
                    'devices_count'  => count($devices),
                ]);

                if (count($devices) === 0) {
                    $rawLower = is_array($raw) ? array_change_key_case($raw, CASE_LOWER) : [];
                    $code     = $rawLower['errorcode'] ?? $rawLower['code'] ?? null;
                    $desc     = $rawLower['errordescribe'] ?? $rawLower['msg'] ?? $rawLower['message'] ?? null;

                    $msg = "{$account}: aucun GPS récupéré";
                    if ($code !== null) $msg .= " (code={$code})";
                    if (!empty($desc)) $msg .= " - {$desc}";

                    $summary[] = "❌ {$msg}";
                    continue;
                }

                $now = now();

                // build rows dédoublonné
                $rowsByMac = [];
                foreach ($devices as $d) {
                    if (!is_array($d)) continue;
                    $dLower = array_change_key_case($d, CASE_LOWER);

                    $mac = trim((string)($dLower['macid'] ?? $dLower['mac_id'] ?? ''));
                    if ($mac === '') continue;

                    $rowsByMac[$mac] = [
                        'mac_id'       => $mac,
                        'objectid'     => $dLower['objectid'] ?? null,
                        'sim_number'   => null,
                        'account_name' => $account, // ✅ IMPORTANT
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }

                if (count($rowsByMac) === 0) {
                    $summary[] = "❌ {$account}: liste provider OK mais aucun macid valide.";
                    continue;
                }

                $macIds = array_keys($rowsByMac);

                // 2) backfill account_name uniquement si NULL (ne modifie pas les existants déjà renseignés)
                $filled = SimGps::query()
                    ->whereIn('mac_id', $macIds)
                    ->whereNull('account_name')
                    ->update(['account_name' => $account]);

                // 3) déjà existants
                $existing = SimGps::query()
                    ->whereIn('mac_id', $macIds)
                    ->pluck('mac_id')
                    ->all();

                $existingSet = array_flip($existing);

                // 4) nouveaux uniquement
                $newRows = [];
                foreach ($rowsByMac as $mac => $row) {
                    if (!isset($existingSet[$mac])) $newRows[] = $row;
                }

                foreach (array_chunk($newRows, 500) as $chunk) {
                    SimGps::query()->insert($chunk);
                }

                $anyOk = true;

                $summary[] = "✅ {$account}: reçus=" . count($macIds)
                    . " | nouveaux=" . count($newRows)
                    . " | backfill_account=" . (int)$filled;

            } catch (\Throwable $e) {
                Log::error('[GPS_SIM] Erreur sync', [
                    'account' => $account,
                    'error'   => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);

                $summary[] = "❌ {$account}: erreur - " . $e->getMessage();
                // on continue sur l'autre compte
            }
        }

        if (!$anyOk) {
            return back()->with('error', "Sync échoué. " . implode(" | ", $summary));
        }

        return back()->with('success', "Sync terminé ✅ | " . implode(" | ", $summary));
    }

    /**
     * Ultra-robust extractor (inchangé)
     */
    private function extractDevicesUltraRobust($raw): array
    {
        if (!is_array($raw)) return [];

        $rawLower = array_change_key_case($raw, CASE_LOWER);

        $candidate = null;
        foreach (['rows', 'data', 'result', 'list', 'devices'] as $k) {
            if (isset($rawLower[$k]) && is_array($rawLower[$k])) {
                $candidate = $rawLower[$k];
                break;
            }
        }
        if ($candidate === null) $candidate = $raw;

        if (is_array($candidate)) {
            $candLower = array_change_key_case($candidate, CASE_LOWER);
            if (isset($candLower['rows']) && is_array($candLower['rows'])) $candidate = $candLower['rows'];
            if (isset($candLower['data']) && is_array($candLower['data'])) $candidate = $candLower['data'];
        }

        $out = [];

        // key/records
        if (is_array($candidate)
            && isset($candidate['key'], $candidate['records'])
            && is_array($candidate['key'])
            && is_array($candidate['records'])
        ) {
            foreach ($candidate['records'] as $row) {
                if (!is_array($row)) continue;

                $item = [];
                foreach ($candidate['key'] as $field => $idx) {
                    $item[$field] = $row[$idx] ?? null;
                }

                $itemLower = array_change_key_case($item, CASE_LOWER);
                if (!empty($itemLower['macid'])) $out[] = $item;
            }
            return $out;
        }

        // liste directe
        if (is_array($candidate)) {
            foreach ($candidate as $v) {
                if (!is_array($v)) continue;
                $vLower = array_change_key_case($v, CASE_LOWER);
                if (!empty($vLower['macid']) || !empty($vLower['objectid'])) $out[] = $v;
            }
        }

        return $out;
    }
}
