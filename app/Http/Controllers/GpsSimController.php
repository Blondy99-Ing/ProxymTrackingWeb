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
                        ->orWhere('sim_number', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->appends(['q' => $q]);

        // 🧠 Pour chaque GPS, on calcule engine_state + gps_online depuis la table locations via le service
        $items->getCollection()->transform(function (SimGps $item) use ($gps) {

            // valeurs par défaut (-> N/A si rien trouvé)
            $engineState = null;
            $engineCut   = null;
            $gpsOnline   = null;
            $gpsLastSeen = null;

            // Si pas de MAC ID => impossible de chercher
            if (!empty($item->mac_id)) {
                try {
                    $status = $gps->getEngineStatusFromLastLocation($item->mac_id);

                    if ($status['success'] ?? false) {
                        $decoded     = $status['decoded'] ?? [];
                        $engineState = $decoded['engineState'] ?? 'UNKNOWN';
                        $engineCut   = ($engineState === 'CUT');

                        // Dernier "seen" (heart_time > sys_time > datetime)
                        $last = $status['location']['heart_time']
                            ?? $status['location']['sys_time']
                            ?? $status['datetime']
                            ?? null;

                        if ($last) {
                            $gpsLastSeen = $last;

                            try {
                                $dt        = Carbon::parse($last);
                                // même logique que dans ControlGpsController::isGpsOnline()
                                $gpsOnline = $dt->diffInMinutes(now()) <= 10;
                            } catch (\Throwable $e) {
                                $gpsOnline = null;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // On log juste en debug, on ne casse pas la page
                    Log::warning('[GPS_SIM] Erreur getEngineStatusFromLastLocation', [
                        'mac_id' => $item->mac_id,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

            // On ajoute des "attributs dynamiques" utilisés dans la vue
            $item->engine_state  = $engineState;   // CUT / ON / OFF / UNKNOWN / null
            $item->engine_cut    = $engineCut;     // bool|null
            $item->gps_online    = $gpsOnline;     // bool|null
            $item->gps_last_seen = $gpsLastSeen;   // string|null

            return $item;
        });

        return view('gps_sim.index', compact('items', 'q'));
    }

    /**
     * ✅ Mettre à jour (ajouter/modifier) la SIM d’un GPS (via modale)
     */
    public function updateSim(Request $request, SimGps $simGps): RedirectResponse
    {
        $validated = $request->validate([
            'sim_number' => ['nullable', 'string', 'max:30'],
            // Si tu veux strict digits: 'regex:/^[0-9+\s-]{6,30}$/'
        ]);

        $sim = trim((string) ($validated['sim_number'] ?? ''));
        $simGps->sim_number = $sim === '' ? null : $sim;
        $simGps->save();

        return back()->with('success', "SIM mise à jour pour {$simGps->mac_id} ✅");
    }

    /**
     * ✅ Sync depuis le compte 18GPS
     * - insère UNIQUEMENT les nouveaux mac_id
     * - ne modifie PAS les existants
     */
    public function syncFromAccount(Request $request, GpsControlService $gps): RedirectResponse
    {
        try {
            $raw     = $gps->getAccountDeviceListRaw();
            $devices = $this->extractDevicesUltraRobust($raw);

            Log::info('[GPS_SIM] Sync RAW', [
                'raw_top_keys'  => is_array($raw) ? array_keys($raw) : null,
                'devices_count' => count($devices),
            ]);

            if (count($devices) === 0) {
                $rawLower = is_array($raw) ? array_change_key_case($raw, CASE_LOWER) : [];
                $code     = $rawLower['errorcode'] ?? $rawLower['code'] ?? null;
                $desc     = $rawLower['errordescribe'] ?? $rawLower['msg'] ?? $rawLower['message'] ?? null;

                $msg = "Aucun GPS récupéré depuis le provider";
                if ($code !== null) $msg .= " (code={$code})";
                if (!empty($desc)) $msg .= " - {$desc}";
                if (isset($rawLower['data']) && is_array($rawLower['data'])) $msg .= " | data_count=" . count($rawLower['data']);
                if (isset($rawLower['rows']) && is_array($rawLower['rows'])) $msg .= " | rows_count=" . count($rawLower['rows']);

                return back()->with('error', $msg . '.');
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
                    'mac_id'     => $mac,
                    'objectid'   => $dLower['objectid'] ?? null,
                    'sim_number' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($rowsByMac) === 0) {
                return back()->with('error', "Liste provider OK mais aucun macid valide trouvé.");
            }

            $macIds = array_keys($rowsByMac);

            // déjà existants
            $existing = SimGps::query()
                ->whereIn('mac_id', $macIds)
                ->pluck('mac_id')
                ->all();

            $existingSet = array_flip($existing);

            // nouveaux uniquement
            $newRows = [];
            foreach ($rowsByMac as $mac => $row) {
                if (!isset($existingSet[$mac])) $newRows[] = $row;
            }

            $newCount = count($newRows);

            if ($newCount === 0) {
                return back()->with(
                    'success',
                    "Sync terminé ✅ Aucun nouveau GPS à ajouter. Total reçus: " . count($macIds)
                );
            }

            foreach (array_chunk($newRows, 500) as $chunk) {
                SimGps::query()->insert($chunk);
            }

            return back()->with(
                'success',
                "Sync terminé ✅ Nouveaux GPS ajoutés: {$newCount}. Total reçus: " . count($macIds)
            );

        } catch (\Throwable $e) {
            Log::error('[GPS_SIM] Erreur sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', "Erreur Sync: " . $e->getMessage());
        }
    }

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
