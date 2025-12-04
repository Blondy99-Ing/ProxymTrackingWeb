<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service de contrôle GPS pour l'interaction avec l'API 18GPS.
 * 
 * Ce service permet :
 *  - Se connecter à l'API GPS et récupérer un token.
 *  - Envoyer des commandes aux appareils GPS (ex : ouverture/fermeture relais).
 *  - Obtenir le statut temps réel des appareils.
 *  - Normaliser les réponses du fournisseur pour l'application.
 */
class GpsControlService
{
    // ==============================
    // Endpoints du fournisseur GPS
    // ==============================
    private const GPS_API_URL = "http://apitest.18gps.net/GetDateServices.asmx";
    private const LOGIN_URL   = self::GPS_API_URL . "/loginSystem";
    private const COMMAND_URL = self::GPS_API_URL . "/GetDate";

    // Identifiants GPS (à mettre en .env pour la production)
    private string $login;
    private string $password;

    // Token GPS stocké en mémoire pour réutilisation
    private ?string $gpsToken = null;

    /**
     * Constructeur : récupère les identifiants depuis le fichier .env
     */
    public function __construct()
    {
        $this->login    = env("GPS_LOGIN", "Proxym_tracking");
        $this->password = env("GPS_PASSWORD", "proxym123");
    }

    // ==============================
    // Méthodes utilitaires (helpers)
    // ==============================

    /**
     * Retourne la date/heure ISO actuelle.
     */
    private function nowIso(): string
    {
        return now()->toISOString();
    }

    /**
     * Convertit une valeur en booléen.
     * Accepte : bool, string ("1", "true", "yes") ou int (0/1)
     */
    private function toBool($v): ?bool
    {
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return $v != 0;
        if (is_string($v)) {
            return in_array(strtolower($v), ["1", "true", "yes", "on"]);
        }
        return null;
    }

    /**
     * Analyse une chaîne de bits pour obtenir l'état ACC et huile.
     * Exemple : "10100000" => ['accState'=>true, 'oilState'=>true]
     */
    private function parseStatusBits(?string $status): array
    {
        if (!$status || strlen($status) < 3) return [];
        return [
            'accState' => $status[0] === "1",
            'oilState' => $status[2] === "1",
        ];
    }

    /**
     * Normalise la réponse du fournisseur pour l'application.
     * Retourne un tableau avec :
     *  - success : bool
     *  - gps_status : "Connected"/"Disconnected"/...
     *  - speed : vitesse
     *  - status : bitfield brut
     *  - oilState, accState : états booléens
     *  - raw : payload original pour debug
     */
    private function normalizeStatusResponse($body): array
    {
        if (!$body) {
            return ['success' => false, 'message' => "Empty response"];
        }

        $data = $body['data'] ?? $body;

        // Détection succès
        $success =
            ($body['success'] ?? null) === true ||
            ($body['success'] ?? null) === "true" ||
            ($body['code']    ?? null) === 0 ||
            ($data['success'] ?? null) === true;

        // Champs GPS
        $gpsStatus = $data['gps_status'] ?? $data['gpsStatus'] ?? $body['gps_status'] ?? "Unknown";
        $speed     = $data['speed'] ?? $data['gps_speed'] ?? $body['speed'] ?? 0;
        $statusField = $data['status'] ?? $data['powerStatus'] ?? $body['status'] ?? null;

        // États explicites
        $oilState = $this->toBool($data['oilState'] ?? $body['oilState'] ?? null);
        $accState = $this->toBool($data['accState'] ?? $body['accState'] ?? null);

        $normalized = [
            'success'    => $success,
            'gps_status' => $gpsStatus,
            'speed'      => (float)$speed,
            'status'     => $statusField,
            'oilState'   => $oilState,
            'accState'   => $accState,
            'raw'        => $data,
        ];

        // Si pas d'états explicites mais bitfield disponible
        if ($statusField && ($oilState === null || $accState === null)) {
            $bits = $this->parseStatusBits($statusField);
            $normalized['oilState'] ??= $bits['oilState'] ?? null;
            $normalized['accState'] ??= $bits['accState'] ?? null;
        }

        // Normalisation gps_status simple
        if ($normalized['gps_status'] === "1") $normalized['gps_status'] = "Connected";
        if ($normalized['gps_status'] === "0") $normalized['gps_status'] = "Disconnected";

        return $normalized;
    }

    /**
     * Envoi d'une requête GET avec Laravel HTTP client
     */
    private function httpGet(string $url, array $params)
    {
        return Http::timeout(15)->get($url, $params)->json();
    }

    // ==============================
    // API publique
    // ==============================

    /**
     * Login sur l'API GPS et récupération du token.
     * Le token est mis en cache en mémoire pour réutilisation.
     * Retourne : string|null
     */
    public function loginGps(): ?string
    {
        if ($this->gpsToken) {
            Log::info("🔑 Token GPS existant utilisé", [$this->gpsToken]);
            return $this->gpsToken;
        }

        $params = [
            "LoginName"     => $this->login,
            "LoginPassword" => $this->password,
            "LoginType"     => "ENTERPRISE",
            "language"      => "en",
            "timeZone"      => 8,
            "apply"         => "APP",
            "ISMD5"         => 0,
            "loginUrl"      => "http://appzzl.18gps.net/",
        ];

        try {
            Log::info("🔑 Connexion à l'API GPS...");
            $data = $this->httpGet(self::LOGIN_URL, $params);
            Log::info("📡 Réponse login GPS", $data);

            if (($data['success'] ?? null) == "true" && isset($data['mds'])) {
                $this->gpsToken = $data['mds'];
                Log::info("✅ Login GPS réussi", ['token' => $this->gpsToken]);
                return $this->gpsToken;
            }

            Log::error("❌ Échec login GPS", $data);
            return null;

        } catch (\Exception $e) {
            Log::error("🔥 Erreur login GPS", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Envoi d'une commande à un appareil GPS (ex : OPENRELAY / CLOSERELAY)
     */
    public function sendGpsCommand(string $macId, string $command, string $param = "", string $pwd = "proxym123"): ?array
    {
        $token = $this->loginGps();
        if (!$token) return null;

        $params = [
            "method"  => "SendCommands",
            "macid"   => $macId,
            "cmd"     => $command,
            "param"   => $param,
            "pwd"     => $pwd,
            "sendTime"=> $this->nowIso(),
            "mds"     => $token,
        ];

        try {
            Log::info("📡 Envoi commande GPS", ['cmd' => $command, 'macid' => $macId]);
            $data = $this->httpGet(self::COMMAND_URL, $params);
            Log::info("✅ Réponse commande GPS", $data);
            return $data;
        } catch (\Exception $e) {
            Log::error("🔥 Erreur commande GPS", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Récupération du statut temps réel d'un appareil GPS
     */
    public function getRealtimeStatusByMac(string $macId): array
    {
        $token = $this->loginGps();
        if (!$token)
            return ['success' => false, 'message' => 'Token manquant'];

        $common = ["macid" => $macId, "mds" => $token];
        $methods = ["GetDeviceStatus", "GetNowData", "GetBitStatus"];
        $lastErr = null;

        foreach ($methods as $method) {
            try {
                Log::info("🔎 Récupération statut GPS", ['method' => $method, 'mac' => $macId]);
                $data = $this->httpGet(self::COMMAND_URL, array_merge($common, ["method" => $method]));

                $normalized = $this->normalizeStatusResponse($data);

                if ($normalized['success']) return $normalized;

                $lastErr = $normalized['message'] ?? "Échec fournisseur";

            } catch (\Exception $e) {
                $lastErr = $e->getMessage();
            }
        }

        return [
            "success" => false,
            "message" => $lastErr ?? "Toutes les méthodes ont échoué",
        ];
    }

    /**
     * Réinitialisation du token GPS en mémoire
     */
    public function resetGpsToken(): void
    {
        $this->gpsToken = null;
    }
}
