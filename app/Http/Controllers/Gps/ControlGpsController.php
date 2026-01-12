<?php

namespace App\Http\Controllers\Gps;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Location;
use App\Models\SimGps;
use App\Models\Voiture;
use App\Services\GpsControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ControlGpsController extends Controller
{
    public function __construct(private GpsControlService $gps) {}

    /**
     * PAGE INDEX – Liste + Form
     */
    public function index(Request $request)
    {
        $voitures = Voiture::all();
        return view('coupure_moteur.index', compact('voitures'));
    }

    /**
     * ✅ BATCH: 1 appel pour tous les statuts (moteur + gps)
     * GET /voitures/engine-status/batch?ids=1,2,3
     */
    public function engineStatusBatch(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun id fourni',
                'data' => []
            ], 422);
        }

        $voitures = Voiture::query()
            ->whereIn('id', $ids->all())
            ->get(['id', 'mac_id_gps']);

        $voituresById = $voitures->keyBy('id');

        $macs = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();
        $lastLocationsByMac = $this->fetchLastLocationsByMac($macs);

        $out = [];
        foreach ($ids as $id) {
            if (!isset($voituresById[$id])) {
                $out[$id] = ['success' => false, 'message' => 'VEHICLE_NOT_FOUND'];
                continue;
            }

            $v = $voituresById[$id];
            $mac = (string) $v->mac_id_gps;

            if ($mac === '') {
                $out[$id] = ['success' => false, 'message' => 'NO_MAC_ID'];
                continue;
            }

            $loc = $lastLocationsByMac[$mac] ?? null;
            if (!$loc) {
                $out[$id] = ['success' => false, 'message' => 'NO_LOCATION'];
                continue;
            }

            $decoded = $this->gps->decodeEngineStatus($loc->status);
            $cut = (($decoded['engineState'] ?? 'UNKNOWN') === 'CUT');

            $out[$id] = [
                'success' => true,
                'engine' => [
                    'cut' => $cut,
                    'engineState' => $decoded['engineState'] ?? 'UNKNOWN',
                ],
                'gps' => [
                    'online' => $this->isGpsOnline($loc),
                    'last_seen' => (string) ($loc->heart_time ?? $loc->sys_time ?? $loc->datetime),
                ],
            ];
        }

        return response()->json(['success' => true, 'data' => $out]);
    }

    /**
     * ✅ 1 véhicule
     * GET /voitures/{voiture}/engine-status
     */
    public function engineStatus(Voiture $voiture)
    {
        $mac = (string) $voiture->mac_id_gps;
        if ($mac === '') {
            return response()->json(['success' => false, 'message' => 'NO_MAC_ID'], 422);
        }

        $loc = Location::query()
            ->where('mac_id_gps', $mac)
            ->orderByDesc('datetime')
            ->first();

        if (!$loc) {
            return response()->json(['success' => false, 'message' => 'NO_LOCATION'], 404);
        }

        $decoded = $this->gps->decodeEngineStatus($loc->status);
        $cut = (($decoded['engineState'] ?? 'UNKNOWN') === 'CUT');

        return response()->json([
            'success' => true,
            'engine' => [
                'cut' => $cut,
                'engineState' => $decoded['engineState'] ?? 'UNKNOWN',
            ],
            'gps' => [
                'online' => $this->isGpsOnline($loc),
                'last_seen' => (string) ($loc->heart_time ?? $loc->sys_time ?? $loc->datetime),
            ],
        ]);
    }

    /**
     * ✅ Toggle + log DB seulement si SEND_OK
     * POST /voitures/{voiture}/toggle-engine
     */
    public function toggleEngine(Request $request, Voiture $voiture)
    {
        $mac = (string) $voiture->mac_id_gps;
        if ($mac === '') {
            return response()->json(['success' => false, 'message' => 'NO_MAC_ID'], 422);
        }

        // (1) on tente de se caler sur le bon compte via sim_gps.account_name
        $accDb = $this->getAccountFromDb($mac);
        if ($accDb) {
            $this->gps->setAccount($accDb);
            // optionnel : si tu suspectes des tokens “croisés”
            // $this->gps->resetGpsToken();
        }

        // état actuel (DB locations)
        $loc = Location::query()->where('mac_id_gps', $mac)->orderByDesc('datetime')->first();
        $decoded = $this->gps->decodeEngineStatus($loc?->status);
        $currentlyCut = (($decoded['engineState'] ?? 'UNKNOWN') === 'CUT');

        $action = $currentlyCut ? 'restore' : 'cut';

        // (2) envoi provider
        $providerResp = $action === 'cut'
            ? $this->gps->cutEngine($mac)
            : $this->gps->restoreEngine($mac);

        $parsed = $this->parseSendCommandResponse($providerResp);

        // ✅ si file saturée → clear + retry 1 fois
        if (!$parsed['ok'] && strtoupper((string)$parsed['returnMsg']) === 'CMD_EXCEEDLENGTH') {
            $this->gps->clearCmdList($mac);

            $providerResp = $action === 'cut'
                ? $this->gps->cutEngine($mac)
                : $this->gps->restoreEngine($mac);

            $parsed = $this->parseSendCommandResponse($providerResp);
        }

        // ✅ si device pas dans le compte → switch tracking/mobility + retry 1 fois
        if (!$parsed['ok'] && $this->isWrongAccountMsg($parsed['returnMsg'] ?? '')) {
            $current = $this->gps->getAccount();
            $other = ($current === 'tracking') ? 'mobility' : 'tracking';

            // on met à jour sim_gps.account_name pour éviter que le service rebascule mal ensuite
            $this->upsertAccountForMac($mac, $other);

            $this->gps->setAccount($other);
            $this->gps->resetGpsToken(); // force un login du bon compte

            $providerResp = $action === 'cut'
                ? $this->gps->cutEngine($mac)
                : $this->gps->restoreEngine($mac);

            $parsed = $this->parseSendCommandResponse($providerResp);
        }

        if (!$parsed['ok']) {
            return response()->json([
                'success' => false,
                'message' => $parsed['message'],
                'return_msg' => $parsed['returnMsg'],
                'provider' => $providerResp,
            ], 422);
        }

        $cmdNo = $parsed['cmdNo'];
        $employeId = $this->currentEmployeId();

        Commande::updateOrCreate(
            ['CmdNo' => $cmdNo],
            [
                'user_id'     => null,
                'employe_id'  => $employeId,
                'vehicule_id' => $voiture->id,
                'status'      => 'SEND_OK',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $action === 'cut' ? 'Commande coupure OK' : 'Commande allumage OK',
            'cmd_no' => $cmdNo,
            'return_msg' => $parsed['returnMsg'],
            'engine' => ['cut' => ($action === 'cut')],
        ]);
    }

    /* ====================== Helpers ====================== */

    private function parseSendCommandResponse(array $resp): array
    {
        $success = $resp['success'] ?? null;
        $errorCode = (string) ($resp['errorCode'] ?? ($resp['code'] ?? ''));

        $globalOk = ($success === true || $success === 'true')
            && ($errorCode === '' || $errorCode === '200' || $errorCode === '0');

        if (!$globalOk) {
            $msg = (string) ($resp['errorDescribe'] ?? $resp['msg'] ?? $resp['message'] ?? 'Commande échouée');
            return [
                'ok' => false,
                'cmdNo' => null,
                'returnMsg' => null,
                'message' => $msg,
            ];
        }

        $row = $resp['data'][0] ?? null;
        if (!is_array($row)) {
            return [
                'ok' => false,
                'cmdNo' => null,
                'returnMsg' => null,
                'message' => 'Commande non confirmée (data vide)',
            ];
        }

        $returnMsg = strtoupper(trim((string) ($row['ReturnMsg'] ?? $row['returnMsg'] ?? '')));
        $cmdNo = trim((string) ($row['CmdNo'] ?? $row['cmdNo'] ?? ''));

        if ($returnMsg !== 'SEND_OK') {
            // ex: CMD_EXCEEDLENGTH / CMD_NOT_SUPPORT / ou message chinois…
            return [
                'ok' => false,
                'cmdNo' => null,
                'returnMsg' => (string) ($row['ReturnMsg'] ?? $row['returnMsg'] ?? null),
                'message' => (string) ($row['ReturnMsg'] ?? $row['returnMsg'] ?? 'Commande refusée'),
            ];
        }

        if ($cmdNo === '') {
            return [
                'ok' => false,
                'cmdNo' => null,
                'returnMsg' => $returnMsg,
                'message' => 'SEND_OK mais CmdNo manquant',
            ];
        }

        return [
            'ok' => true,
            'cmdNo' => $cmdNo,
            'returnMsg' => $returnMsg,
            'message' => 'SEND_OK',
        ];
    }

    private function currentEmployeId(): ?int
    {
        try {
            $id = Auth::guard('employe')->id();
            if ($id) return (int) $id;
        } catch (\Throwable) {}

        return Auth::check() ? (int) Auth::id() : null;
    }

    private function fetchLastLocationsByMac(array $macIds): array
    {
        if (empty($macIds)) return [];

        $sub = Location::query()
            ->selectRaw('mac_id_gps, MAX(`datetime`) as max_dt')
            ->whereIn('mac_id_gps', $macIds)
            ->groupBy('mac_id_gps');

        $rows = Location::query()
            ->joinSub($sub, 't', function ($join) {
                $join->on('locations.mac_id_gps', '=', 't.mac_id_gps')
                    ->on('locations.datetime', '=', 't.max_dt');
            })
            ->get([
                'locations.mac_id_gps',
                'locations.datetime',
                'locations.status',
                'locations.heart_time',
                'locations.sys_time',
            ]);

        return $rows->keyBy('mac_id_gps')->all();
    }

    private function isGpsOnline($loc): ?bool
    {
        $last = $loc->heart_time ?? $loc->sys_time ?? $loc->datetime;
        if (!$last) return null;

        try {
            $dt = \Carbon\Carbon::parse($last);
            return $dt->diffInMinutes(now()) <= 10;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getAccountFromDb(string $macId): ?string
    {
        $acc = SimGps::query()->where('mac_id', $macId)->value('account_name');
        $acc = strtolower(trim((string) $acc));
        return in_array($acc, ['tracking', 'mobility'], true) ? $acc : null;
    }

    private function upsertAccountForMac(string $macId, string $account): void
    {
        $account = strtolower(trim($account));
        if (!in_array($account, ['tracking', 'mobility'], true)) return;

        // upsert minimal (si tu veux éviter insert, fais un update simple)
        SimGps::query()->updateOrCreate(
            ['mac_id' => $macId],
            ['account_name' => $account]
        );

        // flush cache de résolution compte (même clé que dans ton service)
        Cache::forget("gps18gps:macid_account:" . $macId);
    }

    private function isWrongAccountMsg(string $returnMsg): bool
    {
        $msg = trim((string) $returnMsg);
        if ($msg === '') return false;

        // message chinois observé
        if (str_contains($msg, '不属于本账号') || str_contains($msg, '不存在')) return true;

        // variantes possibles en anglais
        $low = strtolower($msg);
        return str_contains($low, 'not belong') || str_contains($low, 'does not belong') || str_contains($low, 'not exist');
    }
}
