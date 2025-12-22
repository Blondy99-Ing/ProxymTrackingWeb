<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 18GPS / 18gps.net - ASMX Open API
 *
 * Endpoints:
 * - {GPS_API_URL}/loginSystem
 * - {GPS_API_URL}/GetDate (method=SendCommands / GetSendCmdList / ClearCmdList / ...)
 *
 * Notes:
 * - Le token "mds" est obligatoire
 * - errorCode=403 => token expiré => relancer loginSystem puis retry
 * - ASMX peut renvoyer du JSON encapsulé dans <string>...</string> ou {"d":"...json..."}
 */
class GpsControlService
{
    // Provider URLs
    private string $apiBaseUrl;
    private string $loginUrl;
    private string $getDateUrl;

    // Credentials
    private string $loginName;
    private string $loginPassword;

    // Device command password (souvent requis pour OPENRELAY/CLOSERELAY)
    private string $deviceCmdPassword;

    // Login params
    private string $loginType;
    private string $language;
    private string $timeZone; // parfois "8" ou "+08"
    private string $apply;
    private int $isMd5;
    private string $providerLoginUrl;

    // HTTP/Cache
    private int $httpTimeoutSeconds;
    private string $tokenCacheKey = 'gps18gps:mds_token';
    private int $tokenTtlSeconds;

    // Status decoding (locations.status)
    private int $accBitIndex;
    private int $relayBitIndex;
    private bool $relayInvert;   // si ton bit est inversé (selon câblage)
    private string $bitOrder;    // MSB (gauche->droite) ou LSB (droite->gauche)

    public function __construct()
    {
        $this->apiBaseUrl = rtrim((string) env('GPS_API_URL', 'http://apitest.18gps.net/GetDateServices.asmx'), '/');
        $this->loginUrl   = $this->apiBaseUrl . '/loginSystem';
        $this->getDateUrl = $this->apiBaseUrl . '/GetDate';

        $this->loginName     = (string) env('GPS_LOGIN_NAME', '');
        $this->loginPassword = (string) env('GPS_LOGIN_PASSWORD', '');

        // Si pas défini, on reprend le même mot de passe que le login (souvent le cas chez vous)
        $this->deviceCmdPassword = (string) env('GPS_DEVICE_CMD_PASSWORD', $this->loginPassword);

        $this->loginType        = (string) env('GPS_LOGIN_TYPE', 'ENTERPRISE');
        $this->language         = (string) env('GPS_LANGUAGE', 'en');
        $this->timeZone         = (string) env('GPS_TIMEZONE', '8');
        $this->apply            = (string) env('GPS_APPLY', 'APP');
        $this->isMd5            = (int) env('GPS_IS_MD5', 0);
        $this->providerLoginUrl = (string) env('GPS_LOGIN_URL', 'http://appzzl.18gps.net/');

        $this->tokenTtlSeconds    = (int) env('GPS_TOKEN_TTL', 1140);
        $this->httpTimeoutSeconds = (int) env('GPS_HTTP_TIMEOUT', 20);

        // Décodage statut (par défaut: comme ton code Node)
        $this->accBitIndex   = (int) env('GPS_STATUS_ACC_INDEX', 0);   // bit 0 = ACC
        $this->relayBitIndex = (int) env('GPS_STATUS_RELAY_INDEX', 2); // bit 2 = Oil/Relay
        $this->relayInvert   = (bool) env('GPS_STATUS_RELAY_INVERT', false);
        $this->bitOrder      = strtoupper((string) env('GPS_STATUS_BIT_ORDER', 'MSB')); // MSB recommandé
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    private function nowIso(): string
    {
        try { return now()->toISOString(); } catch (\Throwable) { return ''; }
    }

    /**
     * ASMX peut renvoyer:
     * - JSON direct
     * - JSON dans <string>...</string>
     * - JSON dans {"d":"{...json...}"}
     * - ou texte/xml contenant du json
     */
    private function decodeAsmxJson(string $body): ?array
    {
        $body = trim($body);

        // 1) JSON direct
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            if (isset($json['d']) && is_string($json['d'])) {
                $inner = trim($json['d']);
                $j2 = json_decode($inner, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($j2)) return $j2;
            }
            return $json;
        }

        // 2) JSON dans <string>...</string>
        if (preg_match('/<string[^>]*>(.*?)<\/string>/s', $body, $m)) {
            $inner = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $inner = trim($inner);

            $json = json_decode($inner, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) return $json;
        }

        // 3) fallback: strip tags puis JSON decode
        $stripped = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $stripped = trim($stripped);

        $json = json_decode($stripped, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) return $json;

        return null;
    }

    /**
     * “Succès” provider : success=true/"true" + errorCode=200/0/""
     */
    private function isProviderSuccess(array $data): bool
    {
        $success   = $data['success'] ?? null;
        $errorCode = (string) ($data['errorCode'] ?? ($data['code'] ?? ''));

        return ($success === true || $success === 'true')
            && ($errorCode === '' || $errorCode === '200' || $errorCode === '0');
    }

    /**
     * GET wrapper robuste (ASMX)
     */
    private function getWithParams(string $url, array $params): array
    {
        try {
            $res  = Http::timeout($this->httpTimeoutSeconds)->get($url, $params);
            $body = $res->body();

            if (!$res->successful()) {
                Log::warning('[GPS] HTTP non OK', [
                    'url' => $url,
                    'status' => $res->status(),
                    'body' => $body,
                ]);

                return [
                    'success' => 'false',
                    'errorCode' => (string) $res->status(),
                    'errorDescribe' => 'HTTP error',
                    'raw' => $body,
                ];
            }

            $json = $res->json();
            if (is_array($json)) {
                if (isset($json['d']) && is_string($json['d'])) {
                    $inner = json_decode($json['d'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($inner)) return $inner;
                }
                return $json;
            }

            $decoded = $this->decodeAsmxJson($body);
            if (is_array($decoded)) return $decoded;

            return [
                'success' => 'false',
                'errorCode' => '500',
                'errorDescribe' => 'Réponse non décodable (ASMX)',
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('[GPS] Exception HTTP', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => 'false',
                'errorCode' => '500',
                'errorDescribe' => 'Exception HTTP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Appel générique GetDate?method=XXX avec gestion token + retry 403.
     */
    private function callGetDate(string $method, array $params, bool $retryOn403 = true): array
    {
        $token = $this->loginGps(false);
        if (!$token) {
            return [
                'success' => 'false',
                'errorCode' => '401',
                'errorDescribe' => 'Token (mds) indisponible (login échoué)',
                'data' => [],
            ];
        }

        $payload = array_merge($params, [
            'method' => $method,
            'mds'    => $token,
        ]);

        $data = $this->getWithParams($this->getDateUrl, $payload);

        // Token expiré -> refresh + retry 1 fois
        if ($retryOn403 && (string)($data['errorCode'] ?? '') === '403') {
            $this->resetGpsToken();
            $token2 = $this->loginGps(true);
            if ($token2) {
                $payload['mds'] = $token2;
                $data = $this->getWithParams($this->getDateUrl, $payload);
            }
        }

        return $data;
    }

    /* =========================================================
     * 1) LOGIN (mds)
     * ========================================================= */

    public function loginGps(bool $forceRefresh = false): ?string
    {
        if (!$forceRefresh) {
            $cached = Cache::get($this->tokenCacheKey);
            if (!empty($cached)) return (string) $cached;
        }

        if ($this->loginName === '' || $this->loginPassword === '') {
            Log::error('[GPS] Identifiants manquants: GPS_LOGIN_NAME / GPS_LOGIN_PASSWORD');
            return null;
        }

        $params = [
            'LoginName'     => $this->loginName,
            'LoginPassword' => $this->loginPassword,
            'LoginType'     => $this->loginType,
            'language'      => $this->language,
            'timeZone'      => $this->timeZone,
            'apply'         => $this->apply,
            'ISMD5'         => $this->isMd5,
            'loginUrl'      => $this->providerLoginUrl,
        ];

        $data  = $this->getWithParams($this->loginUrl, $params);
        $token = $data['mds'] ?? null;

        if ($this->isProviderSuccess($data) && !empty($token)) {
            Cache::put($this->tokenCacheKey, (string) $token, now()->addSeconds($this->tokenTtlSeconds));
            return (string) $token;
        }

        Log::warning('[GPS] Login échoué', [
            'errorCode' => $data['errorCode'] ?? null,
            'errorDescribe' => $data['errorDescribe'] ?? ($data['msg'] ?? null),
            'raw' => $data['raw'] ?? $data,
        ]);

        return null;
    }

    public function resetGpsToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }

    /* =========================================================
     * 2) SEND COMMANDS (2 paramètres)
     * ========================================================= */

    /**
     * ✅ EXACTEMENT 2 PARAMÈTRES (macId, command)
     */
    public function sendCommand(string $macId, string $command): array
    {
        return $this->sendCommandRaw($macId, $command, '');
    }

    /**
     * Pour les commandes qui exigent un paramètre (ex: PASSTHROUGH)
     */
    public function sendCommandWithParam(string $macId, string $command, string $param): array
    {
        return $this->sendCommandRaw($macId, $command, $param);
    }

    private function sendCommandRaw(string $macId, string $command, string $param = ''): array
    {
        $macId   = trim($macId);
        $command = strtoupper(trim($command));

        $payload = [
            'macid'    => $macId,
            'cmd'      => $command,
            'param'    => $param,
            'pwd'      => $this->deviceCmdPassword, // IMPORTANT pour OPEN/CLOSE relay
            'sendTime' => $this->nowIso(),
        ];

        return $this->callGetDate('SendCommands', $payload, true);
    }

    // Wrappers pratiques (facultatifs)
    public function openRelay(string $macId): array { return $this->sendCommand($macId, 'OPENRELAY'); }
    public function closeRelay(string $macId): array { return $this->sendCommand($macId, 'CLOSERELAY'); }

    // Compatibilité avec ton ancien test tinker
    public function cutEngine(string $macId): array { return $this->closeRelay($macId); }
    public function restoreEngine(string $macId): array { return $this->openRelay($macId); }

    public function safeOn(string $macId): array { return $this->sendCommand($macId, 'SAFEON'); }
    public function safeOff(string $macId): array { return $this->sendCommand($macId, 'SAFEOFF'); }

    public function cutOffPetrol(string $macId): array { return $this->sendCommand($macId, 'CUTOFFPETROL'); }
    public function resumePetrol(string $macId): array { return $this->sendCommand($macId, 'RESUMEPETROL'); }

    public function passThroughAscii(string $macId, string $asciiPayload): array
    {
        return $this->sendCommandWithParam($macId, 'PASSTHROUGH', '0,' . $asciiPayload);
    }

    public function passThroughHex(string $macId, string $hexPayload): array
    {
        return $this->sendCommandWithParam($macId, 'PASSTHROUGH', '1,' . $hexPayload);
    }

    /* =========================================================
     * 3) Command history (GetSendCmdList) + Clear (ClearCmdList)
     * ========================================================= */

    public function getSendCmdList(
        string $macId,
        ?string $cmdNo = null,
        ?string $startTime = null,
        ?string $endTime = null
    ): array {
        $payload = [
            'macid' => trim($macId),
            'cmdNo' => $cmdNo ? trim($cmdNo) : '',
            'startTime' => $startTime ? trim($startTime) : '',
            'endTime' => $endTime ? trim($endTime) : '',
        ];

        return $this->callGetDate('GetSendCmdList', $payload, true);
    }

    public function clearCmdList(string $macId): array
    {
        return $this->callGetDate('ClearCmdList', ['macid' => trim($macId)], true);
    }

    /* =========================================================
     * 4) STATUT MOTEUR depuis la DB (locations.status)
     * ========================================================= */

    /**
     * Convertit un statut en "bit-string" exploitable.
     * - si c'est déjà du binaire ("10100000") => ok
     * - si c'est un entier => convertit en binaire (pad)
     * - si c'est du hex "0x1F" => convertit en binaire
     */
    private function normalizeStatusToBits(?string $status): ?string
    {
        if ($status === null) return null;
        $s = trim($status);
        if ($s === '') return null;

        // déjà binaire
        if (preg_match('/^[01]{3,}$/', $s)) return $s;

        // hex
        if (preg_match('/^0x[0-9a-f]+$/i', $s)) {
            $n = hexdec(substr($s, 2));
            return str_pad(decbin($n), 16, '0', STR_PAD_LEFT);
        }

        // entier décimal
        if (preg_match('/^\d+$/', $s)) {
            $n = (int) $s;
            return str_pad(decbin($n), 16, '0', STR_PAD_LEFT);
        }

        return null;
    }

    /**
     * Lit un bit à un index selon l'ordre choisi.
     * - MSB: index 0 = 1er caractère (gauche->droite)  ✅ comme ton code Node
     * - LSB: index 0 = dernier caractère (droite->gauche)
     */
    private function getBit(?string $bits, int $index): ?bool
    {
        if (!$bits || $index < 0) return null;

        $len = strlen($bits);
        if ($len === 0) return null;

        if ($this->bitOrder === 'LSB') {
            $pos = $len - 1 - $index;
        } else {
            $pos = $index; // MSB
        }

        if ($pos < 0 || $pos >= $len) return null;

        return $bits[$pos] === '1';
    }

    /**
     * Décode le status (colonne locations.status) et renvoie l'état moteur.
     * Par défaut:
     * - bit 0 => ACC
     * - bit 2 => Relay/Oil
     */
    public function decodeEngineStatus(?string $status): array
    {
        $bits = $this->normalizeStatusToBits($status);

        $acc = $this->getBit($bits, $this->accBitIndex);
        $relay = $this->getBit($bits, $this->relayBitIndex);

        if ($relay !== null && $this->relayInvert) {
            $relay = !$relay;
        }

        // Interprétation “métier” (simple et utile)
        // - relay=false => moteur coupé (immobilizer actif)
        // - relay=true + acc=true => moteur ON
        // - relay=true + acc=false => contact OFF mais autorisation OK
        $engineState = 'UNKNOWN';
        if ($relay === false) $engineState = 'CUT';
        elseif ($relay === true && $acc === true) $engineState = 'ON';
        elseif ($relay === true && $acc === false) $engineState = 'OFF';

        return [
            'status_raw'   => $status,
            'status_bits'  => $bits,
            'accState'     => $acc,
            'relayState'   => $relay,      // oil/relay (après inversion éventuelle)
            'engineState'  => $engineState // ON | OFF | CUT | UNKNOWN
        ];
    }

    /**
     * Récupère le dernier enregistrement Location pour un mac_id_gps,
     * puis décode l'état moteur depuis la colonne status.
     */
    public function getEngineStatusFromLastLocation(string $macId): array
    {
        $loc = Location::query()
            ->where('mac_id_gps', $macId)
            ->orderByDesc('datetime')
            ->first();

        if (!$loc) {
            return [
                'success' => false,
                'message' => 'Aucune location trouvée pour ce mac_id_gps',
            ];
        }

        $decoded = $this->decodeEngineStatus($loc->status);

        return [
            'success' => true,
            'mac_id_gps' => $macId,
            'datetime' => (string) $loc->datetime,
            'speed' => (float) ($loc->speed ?? 0),
            'decoded' => $decoded,
            'location' => [
                'longitude' => (float) $loc->longitude,
                'latitude' => (float) $loc->latitude,
                'direction' => $loc->direction,
                'sys_time' => $loc->sys_time,
                'heart_time' => $loc->heart_time,
            ],
        ];
    }
























/* =========================================================
 * 5) DEVICE LIST (tous les GPS du compte)
 * ========================================================= */

/**
 * Convertit la réponse { data:[{key:{field:index}, records:[[...],[...]]}] }
 * en tableau d'objets associatifs.
 */
private function extractKeyedRecords(array $resp): array
{
    if (!$this->isProviderSuccess($resp)) {
        return [];
    }

    $block = $resp['data'][0] ?? null;
    $key = $block['key'] ?? null;
    $records = $block['records'] ?? null;

    if (!is_array($key) || !is_array($records)) {
        return [];
    }

    $out = [];
    foreach ($records as $row) {
        if (!is_array($row)) continue;

        $item = [];
        foreach ($key as $field => $idx) {
            $item[$field] = $row[$idx] ?? null;
        }
        $out[] = $item;
    }

    return $out;
}

/**
 * Liste les GPS du compte (unité courante) => sans sous-unités.
 */
public function getAccountDeviceList(): array
{
    $resp = $this->callGetDate('getDeviceList', [], true);

    if (!$this->isProviderSuccess($resp)) {
        return [];
    }

    // ✅ Cas 1: data = liste d'objets [{objectid, macid, ...}, ...]
    if (isset($resp['data']) && is_array($resp['data'])) {
        $first = $resp['data'][0] ?? null;
        if (is_array($first) && (array_key_exists('objectid', $first) || array_key_exists('macid', $first))) {
            return $resp['data'];
        }
    }

    // ✅ Cas 2: data[0] = { key:{...}, records:[[...],[...]] }
    return $this->extractKeyedRecords($resp);
}


/**
 * Liste les GPS d'une sous-unité (si tu as des subordinates).
 */
public function getSubUnitDeviceList(string $unitId, string $mapType = ''): array
{
    $payload = [
        'id' => trim($unitId),
        'mapType' => $mapType, // '' = coordonnées originales (WGS84 souvent)
    ];

    $resp = $this->callGetDate('getDeviceListByCustomId', $payload, true);
    return $this->extractKeyedRecords($resp);
}

/**
 * Version RAW (utile pour debug si ça renvoie [])
 */
public function getAccountDeviceListRaw(): array
{
    return $this->callGetDate('getDeviceList', [], true);
}

public function getSubUnitDeviceListRaw(string $unitId, string $mapType = ''): array
{
    $payload = ['id' => trim($unitId), 'mapType' => $mapType];
    return $this->callGetDate('getDeviceListByCustomId', $payload, true);
}


}
