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
use Carbon\Carbon;

class ControlGpsController extends Controller
{
    public function __construct(private GpsControlService $gps) {}

    public function index(Request $request)
    {
        $voitures = Voiture::all();
        return view('coupure_moteur.index', compact('voitures'));
    }

    /**
     * ✅ BATCH: LIVE 18GPS
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
            ->get(['id', 'mac_id_gps'])
            ->keyBy('id');

        $out = [];

        foreach ($ids as $id) {
            $v = $voitures[$id] ?? null;
            if (!$v) {
                $out[$id] = ['success' => false, 'message' => 'VEHICLE_NOT_FOUND'];
                continue;
            }

            $mac = (string) $v->mac_id_gps;
            if ($mac === '') {
                $out[$id] = ['success' => false, 'message' => 'NO_MAC_ID'];
                continue;
            }

            $status = $this->gps->getEngineStatusFromLastLocation($mac);

            if (!($status['success'] ?? false)) {
                $out[$id] = [
                    'success' => false,
                    'message' => $status['message'] ?? 'ENGINE_STATUS_FAILED',
                    'meta' => [
                        'source'  => $status['source'] ?? null,
                        'account' => $status['account'] ?? null,
                    ],
                ];
                continue;
            }

            $engineState = $status['decoded']['engineState'] ?? 'UNKNOWN';
            $cut = ($engineState === 'CUT');

            $lastSeen = $status['location']['heart_time']
                ?? $status['location']['sys_time']
                ?? $status['datetime']
                ?? null;

            $online = null;
            if ($lastSeen) {
                try {
                    $online = \Carbon\Carbon::parse($lastSeen)->diffInMinutes(now()) <= 10;
                } catch (\Throwable) {
                    $online = null;
                }
            }

            $out[$id] = [
                'success' => true,
                'engine' => [
                    'cut' => $cut,
                    'engineState' => $engineState,
                ],
                'gps' => [
                    'online' => $online,
                    'last_seen' => $lastSeen,
                ],
                'meta' => [
                    'source'  => $status['source'] ?? null,
                    'account' => $status['account'] ?? null,
                    'user_id' => $status['user_id'] ?? null,
                ],
            ];
        }

        return response()->json(['success' => true, 'data' => $out]);
    }

    /**
     * ✅ 1 véhicule - LIVE 18GPS
     * GET /voitures/{voiture}/engine-status
     */
    public function engineStatus(Voiture $voiture)
    {
        $mac = (string) $voiture->mac_id_gps;
        if ($mac === '') {
            return response()->json(['success' => false, 'message' => 'NO_MAC_ID'], 422);
        }

        $status = $this->gps->getEngineStatusFromLastLocation($mac);

        if (!($status['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $status['message'] ?? 'ENGINE_STATUS_FAILED',
                'macid'   => $mac,
                'raw'     => $status,
            ], 502);
        }

        $engineState = $status['decoded']['engineState'] ?? 'UNKNOWN';
        $cut = ($engineState === 'CUT');

        $lastSeen = $status['location']['heart_time']
            ?? $status['location']['sys_time']
            ?? $status['datetime']
            ?? null;

        $online = null;
        if ($lastSeen) {
            try {
                $online = \Carbon\Carbon::parse($lastSeen)->diffInMinutes(now()) <= 10;
            } catch (\Throwable) {
                $online = null;
            }
        }

        return response()->json([
            'success' => true,
            'engine' => [
                'cut' => $cut,
                'engineState' => $engineState,
            ],
            'gps' => [
                'online' => $online,
                'last_seen' => $lastSeen,
            ],
            'meta' => [
                'source'  => $status['source'] ?? null,
                'account' => $status['account'] ?? null,
                'user_id' => $status['user_id'] ?? null,
            ],
        ]);
    }

    /**
     * ✅ Toggle - décide sur statut LIVE 18GPS (IMPORTANT)
     * POST /voitures/{voiture}/toggle-engine
     */
public function toggleEngine(Request $request, Voiture $voiture)
{
    $mac = (string) $voiture->mac_id_gps;
    if ($mac === '') {
        return response()->json(['success' => false, 'message' => 'NO_MAC_ID'], 422);
    }

    // ✅ action demandée par le frontend (PRIORITAIRE)
    $action = strtolower(trim((string) $request->input('action', ''))); // cut | restore

    if (!in_array($action, ['cut', 'restore'], true)) {
        // fallback (si jamais front n'envoie rien) : on tente live provider
        // ⚠️ sans changer le service, on peut juste vider le cache avant lecture
        Cache::forget("gps18gps:engine_status:tracking:{$mac}");
        Cache::forget("gps18gps:engine_status:mobility:{$mac}");

        $statusLive = $this->gps->getEngineStatusFromLastLocation($mac);
        $engineState = $statusLive['decoded']['engineState'] ?? 'UNKNOWN';
        $currentlyCut = ($engineState === 'CUT');
        $action = $currentlyCut ? 'restore' : 'cut';
    }

    // (1) se caler sur le bon compte si connu
    $accDb = $this->getAccountFromDb($mac);
    if ($accDb) {
        $this->gps->setAccount($accDb);
    }

    // (2) envoi provider
    $providerResp = $action === 'cut'
        ? $this->gps->cutEngine($mac)
        : $this->gps->restoreEngine($mac);

    $parsed = $this->parseSendCommandResponse($providerResp);

    // ✅ file saturée → clear + retry 1 fois
    if (!$parsed['ok'] && strtoupper((string)$parsed['returnMsg']) === 'CMD_EXCEEDLENGTH') {
        $this->gps->clearCmdList($mac);

        $providerResp = $action === 'cut'
            ? $this->gps->cutEngine($mac)
            : $this->gps->restoreEngine($mac);

        $parsed = $this->parseSendCommandResponse($providerResp);
    }

    // ✅ mauvais compte → switch + retry 1 fois
    if (!$parsed['ok'] && $this->isWrongAccountMsg($parsed['returnMsg'] ?? '')) {
        $current = $this->gps->getAccount();
        $other = ($current === 'tracking') ? 'mobility' : 'tracking';

        $this->upsertAccountForMac($mac, $other);

        $this->gps->setAccount($other);
        $this->gps->resetGpsToken();

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
    $typeCommande = $action === 'cut' ? 'COUPURE' : 'ALLUMAGE';

    Commande::updateOrCreate(
        ['CmdNo' => $cmdNo],
        [
            'user_id'     => null,
            'employe_id'  => $employeId,
            'vehicule_id' => $voiture->id,
            'status'      => 'SEND_OK',
            'type_commande' => $typeCommande,

        ]
    );

    // ✅ IMPORTANT: vider le cache engine_status (sans modifier le service)
    Cache::forget("gps18gps:engine_status:tracking:{$mac}");
    Cache::forget("gps18gps:engine_status:mobility:{$mac}");

    // ✅ lecture live immédiate (souvent OK en 1–2s si le tracker remonte vite)
    $after = $this->gps->getEngineStatusFromLastLocation($mac);

    return response()->json([
        'success' => true,
        'message' => $action === 'cut' ? 'Commande coupure envoyée (SEND_OK)' : 'Commande allumage envoyée (SEND_OK)',
        'cmd_no' => $cmdNo,
        'return_msg' => $parsed['returnMsg'],
        'requested_action' => $action,
        'engine' => [
            'cut' => ($action === 'cut'),
        ],
        // ✅ utile pour mettre à jour vite côté UI
        'status_after' => $after,
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
            return ['ok' => false, 'cmdNo' => null, 'returnMsg' => null, 'message' => $msg];
        }

        $row = $resp['data'][0] ?? null;
        if (!is_array($row)) {
            return ['ok' => false, 'cmdNo' => null, 'returnMsg' => null, 'message' => 'Commande non confirmée (data vide)'];
        }

        $returnMsgRaw = (string) ($row['ReturnMsg'] ?? $row['returnMsg'] ?? '');
        $returnMsg = strtoupper(trim($returnMsgRaw));
        $cmdNo = trim((string) ($row['CmdNo'] ?? $row['cmdNo'] ?? ''));

        if ($returnMsg !== 'SEND_OK') {
            return [
                'ok' => false,
                'cmdNo' => null,
                'returnMsg' => $returnMsgRaw,
                'message' => $returnMsgRaw !== '' ? $returnMsgRaw : 'Commande refusée',
            ];
        }

        if ($cmdNo === '') {
            return ['ok' => false, 'cmdNo' => null, 'returnMsg' => $returnMsg, 'message' => 'SEND_OK mais CmdNo manquant'];
        }

        return ['ok' => true, 'cmdNo' => $cmdNo, 'returnMsg' => $returnMsg, 'message' => 'SEND_OK'];
    }

    private function currentEmployeId(): ?int
    {
        try {
            $id = Auth::guard('employe')->id();
            if ($id) return (int) $id;
        } catch (\Throwable) {}

        return Auth::check() ? (int) Auth::id() : null;
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

        SimGps::query()->updateOrCreate(
            ['mac_id' => $macId],
            ['account_name' => $account]
        );

        Cache::forget("gps18gps:macid_account:" . $macId);
    }

    private function isWrongAccountMsg(string $returnMsg): bool
    {
        $msg = trim((string) $returnMsg);
        if ($msg === '') return false;

        if (str_contains($msg, '不属于本账号') || str_contains($msg, '不存在')) return true;

        $low = strtolower($msg);
        return str_contains($low, 'not belong') || str_contains($low, 'does not belong') || str_contains($low, 'not exist');
    }
}
