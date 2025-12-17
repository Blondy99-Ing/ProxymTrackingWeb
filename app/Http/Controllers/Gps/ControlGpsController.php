<?php

namespace App\Http\Controllers\Gps;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Location;
use App\Models\Voiture;
use App\Services\GpsControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ControlGpsController extends Controller
{
    public function __construct(private GpsControlService $gps)
    {
    }

    /**
     * ✅ Batch : retourne les statuts moteur + GPS online/offline pour une liste de véhicules.
     * GET /voitures/engine-status/batch?ids=1,2,3
     */
    public function engineStatusBatch(Request $request)
    {
        $idsRaw = (string) $request->query('ids', '');
        $ids = collect(explode(',', $idsRaw))
            ->map(fn($v) => (int) trim($v))
            ->filter(fn($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun id fourni',
                'data' => []
            ], 422);
        }

        // ✅ Sécurité: uniquement les voitures liées au user connecté (pivot association_user_voitures)
        $voitures = Voiture::query()
            ->whereIn('id', $ids->all())
            ->whereHas('user', fn($q) => $q->where('users.id', Auth::id()))
            ->get(['id', 'mac_id_gps']);

        $macs = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $lastLocationsByMac = $this->fetchLastLocationsByMac($macs);

        $out = [];
        foreach ($voitures as $v) {
            $mac = (string) $v->mac_id_gps;
            $loc = $lastLocationsByMac[$mac] ?? null;

            if (!$loc) {
                $out[$v->id] = [
                    'success' => false,
                    'message' => 'Aucune location trouvée',
                    'engine' => [
                        'cut' => null,
                        'engineState' => 'UNKNOWN',
                    ],
                    'gps' => [
                        'online' => null,
                        'last_seen' => null,
                    ],
                ];
                continue;
            }

            $decoded = $this->gps->decodeEngineStatus($loc->status);

            $cut = ($decoded['engineState'] ?? 'UNKNOWN') === 'CUT';
            $online = $this->isGpsOnline($loc);

            $out[$v->id] = [
                'success' => true,
                'engine' => [
                    'cut' => $cut,
                    'engineState' => $decoded['engineState'] ?? 'UNKNOWN',
                    'accState' => $decoded['accState'] ?? null,
                    'relayState' => $decoded['relayState'] ?? null,
                    'status_bits' => $decoded['status_bits'] ?? null,
                ],
                'gps' => [
                    'online' => $online,
                    'last_seen' => (string) ($loc->heart_time ?? $loc->sys_time ?? $loc->datetime),
                ],
                'datetime' => (string) $loc->datetime,
                'speed' => (float) ($loc->speed ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $out,
        ]);
    }

    /**
     * ✅ Statut 1 véhicule (si tu veux garder un endpoint simple)
     * GET /voitures/{voiture}/engine-status
     */
    public function engineStatus(Voiture $voiture)
    {
        $this->authorizeVehicle($voiture);

        $mac = (string) $voiture->mac_id_gps;
        if ($mac === '') {
            return response()->json([
                'success' => false,
                'message' => 'mac_id_gps manquant sur ce véhicule'
            ], 422);
        }

        $loc = Location::query()
            ->where('mac_id_gps', $mac)
            ->orderByDesc('datetime')
            ->first();

        if (!$loc) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune location trouvée'
            ], 404);
        }

        $decoded = $this->gps->decodeEngineStatus($loc->status);

        return response()->json([
            'success' => true,
            'engine' => [
                'cut' => (($decoded['engineState'] ?? 'UNKNOWN') === 'CUT'),
                'engineState' => $decoded['engineState'] ?? 'UNKNOWN',
                'accState' => $decoded['accState'] ?? null,
                'relayState' => $decoded['relayState'] ?? null,
            ],
            'gps' => [
                'online' => $this->isGpsOnline($loc),
                'last_seen' => (string) ($loc->heart_time ?? $loc->sys_time ?? $loc->datetime),
            ],
            'datetime' => (string) $loc->datetime,
        ]);
    }

    /**
     * ✅ Toggle : coupe si pas coupé, rétablit si coupé.
     * POST /voitures/{voiture}/toggle-engine
     *
     * ✅ Enregistre dans commands UNIQUEMENT si succès provider
     * ❌ Si échec provider: pas d'insert, message d'erreur JSON
     */
    public function toggleEngine(Request $request, Voiture $voiture)
{
    $this->authorizeVehicle($voiture);

    $mac = (string) $voiture->mac_id_gps;
    if ($mac === '') {
        return response()->json([
            'success' => false,
            'message' => 'mac_id_gps manquant sur ce véhicule'
        ], 422);
    }

    // ✅ employe_id obligatoire (user_id doit rester null)
    $employeId = $this->currentEmployeId();
    if (!$employeId) {
        return response()->json([
            'success' => false,
            'message' => "Impossible d'identifier l'employé connecté (employe_id)."
        ], 401);
    }

    // état courant depuis la dernière location
    $loc = \App\Models\Location::query()
        ->where('mac_id_gps', $mac)
        ->orderByDesc('datetime')
        ->first();

    $decoded = $this->gps->decodeEngineStatus($loc?->status);
    $currentlyCut = (($decoded['engineState'] ?? 'UNKNOWN') === 'CUT');

    $action = $currentlyCut ? 'restore' : 'cut';

    // appel provider
    $providerResp = $action === 'cut'
        ? $this->gps->cutEngine($mac)       // CLOSERELAY
        : $this->gps->restoreEngine($mac);  // OPENRELAY

    // ✅ Ici: on accepte SEULEMENT si ReturnMsg=SEND_OK
    $parsed = $this->parseSendCommandResponse($providerResp);

    if (!$parsed['ok']) {
        return response()->json([
            'success' => false,
            'message' => $parsed['message'],  // <-- message clair pour le frontend
            'provider' => $providerResp,
        ], 422);
    }


    $type = $action === 'cut' ? 'coupure_moteur' : 'allumage_moteur';
    $cmdNo = $parsed['cmdNo'];

    // ✅ Enregistrer UNIQUEMENT les succès
    // ✅ user_id = null ; employe_id = employé connecté
    Commande::updateOrCreate(
        ['CmdNo' => $cmdNo],
        [
            'user_id'     => null,
            'employe_id'  => $employeId,
            'vehicule_id' => $voiture->id,
            'status'      => 'SEND_OK',
            'type_commande'  => $type,
        ]
    );

    return response()->json([
        'success' => true,
        'message' => $action === 'cut' ? 'Véhicule coupé (commande OK)' : 'Véhicule rétabli (commande OK)',
        'action'  => $action,
        'cmd_no'  => $cmdNo,
        'engine'  => [
            'cut' => ($action === 'cut'),
        ],
    ]);
}

    /* ===================== Helpers ===================== */

    private function authorizeVehicle(Voiture $voiture): void
    {
        $ok = $voiture->user()->where('users.id', Auth::id())->exists();
        abort_unless($ok, 403, 'Accès interdit à ce véhicule');
    }

    private function providerOk(array $data): bool
    {
        $success = $data['success'] ?? null;
        $errorCode = (string) ($data['errorCode'] ?? ($data['code'] ?? ''));

        return ($success === true || $success === 'true')
            && ($errorCode === '' || $errorCode === '200' || $errorCode === '0');
    }

    private function extractCmdNo(array $data): ?string
    {
        // provider peut renvoyer cmdNo dans plusieurs formes
        $candidates = [
            $data['cmdNo'] ?? null,
            $data['CmdNo'] ?? null,
            $data['cmdno'] ?? null,
            $data['data']['cmdNo'] ?? null,
            $data['data']['CmdNo'] ?? null,
        ];

        foreach ($candidates as $c) {
            $c = is_string($c) ? trim($c) : '';
            if ($c !== '') return $c;
        }
        return null;
    }

    /**
     * ✅ Récupère la dernière location par mac_id_gps en 1 requête.
     */
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
                'locations.speed',
                'locations.heart_time',
                'locations.sys_time',
            ]);

        return $rows->keyBy('mac_id_gps')->all();
    }

    /**
     * Déduit ONLINE/OFFLINE depuis heart_time/sys_time/datetime.
     * Tu peux ajuster le seuil (ex: 10 min).
     */
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







    private function parseSendCommandResponse(array $resp): array
{
    $success = $resp['success'] ?? null;
    $errorCode = (string) ($resp['errorCode'] ?? ($resp['code'] ?? ''));

    // 1) check global success
    $globalOk = ($success === true || $success === 'true')
        && ($errorCode === '' || $errorCode === '200' || $errorCode === '0');

    if (!$globalOk) {
        $msg = (string) (
            $resp['errorDescribe']
            ?? $resp['msg']
            ?? $resp['message']
            ?? 'Commande GPS échouée'
        );

        return ['ok' => false, 'cmdNo' => null, 'message' => $msg];
    }

    // 2) check provider data[0].ReturnMsg == SEND_OK
    $row = $resp['data'][0] ?? null;
    if (!is_array($row)) {
        return ['ok' => false, 'cmdNo' => null, 'message' => 'Commande non confirmée (data vide).'];
    }

    $returnMsg = strtoupper(trim((string)($row['ReturnMsg'] ?? '')));
    $cmdNo     = trim((string)($row['CmdNo'] ?? ''));

    if ($returnMsg !== 'SEND_OK') {
        $m = $returnMsg !== '' ? $returnMsg : 'Commande refusée par le provider';
        return ['ok' => false, 'cmdNo' => null, 'message' => $m];
    }

    if ($cmdNo === '') {
        return ['ok' => false, 'cmdNo' => null, 'message' => 'SEND_OK mais CmdNo manquant.'];
    }

    return ['ok' => true, 'cmdNo' => $cmdNo, 'message' => 'SEND_OK'];
}

private function currentEmployeId(): ?int
{
    // ✅ si tu utilises un guard "employe"
    try {
        $id = Auth::guard('employe')->id();
        if ($id) return (int) $id;
    } catch (\Throwable $e) {
        // guard non configuré -> fallback
    }

    // ✅ fallback : si ton auth actuel est celui des employés
    return Auth::check() ? (int) Auth::id() : null;
}

}
