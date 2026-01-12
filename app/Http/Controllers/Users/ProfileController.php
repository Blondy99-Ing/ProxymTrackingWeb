<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GpsControlService;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ProfileController extends Controller
{
    public function show(int $id, GpsControlService $gps)
    {
        $user = User::with(['voitures.latestLocation'])->findOrFail($id);
        $vehiclesCount = $user->voitures->count();

        // Cache liste devices (évite API à chaque refresh)
        $deviceList = Cache::remember('gps18gps:device_list', now()->addSeconds(30), function () use ($gps) {
            $list = $gps->getAccountDeviceList();

            // Normaliser si jamais le service renvoie autre chose
            if (!is_array($list)) return [];

            // Si ton service renvoie un format {success:false,...}, adapte ici
            if (isset($list['success']) && $list['success'] === false) return [];

            return $list;
        });

        // Index par macid
        $devicesByMac = collect($deviceList)->keyBy(function ($d) {
            return (string) ($d['macid'] ?? $d['mac_id_gps'] ?? $d['mac_id'] ?? '');
        });

        $thresholdMinutes = (int) env('GPS_ONLINE_THRESHOLD_MINUTES', 10);

        $user->voitures->transform(function ($v) use ($gps, $devicesByMac, $thresholdMinutes) {

            // 1) Engine state depuis latestLocation.status (DB)
            $statusRaw = optional($v->latestLocation)->status;
            $decoded = $gps->decodeEngineStatus($statusRaw);
            $v->engine_state = $decoded['engineState'] ?? 'UNKNOWN';

            // 2) GPS online/offline (API getDeviceList heart_time -> sinon fallback DB)
            $device = $devicesByMac->get((string) $v->mac_id_gps);
            $apiHeart = is_array($device) ? ($device['heart_time'] ?? null) : null;

            $dbHeart = optional($v->latestLocation)->heart_time;

            $v->gps_state = $this->computeGpsOnlineState($apiHeart, $dbHeart, $thresholdMinutes);

            // 3) Geofence coords (depuis geofence_zone : [[lng,lat],...])
            $coords = [];
            if (!empty($v->geofence_zone)) {
                $tmp = json_decode($v->geofence_zone, true);
                if (is_array($tmp)) {
                    foreach ($tmp as $pt) {
                        if (!is_array($pt) || count($pt) < 2) continue;
                        $coords[] = [(float) $pt[0], (float) $pt[1]]; // [lng,lat]
                    }
                }
            }
            $v->geofence_coords = $coords;

            return $v;
        });

        return view('users.profile', compact('user', 'vehiclesCount'));
    }

    private function computeGpsOnlineState($apiHeartTime, $dbHeartTime, int $thresholdMinutes): string
    {
        $now = now();

        // 1) heart_time API (souvent timestamp en ms)
        if (!is_null($apiHeartTime) && $apiHeartTime !== '') {

            if (is_numeric($apiHeartTime)) {
                $heartMs = (int) $apiHeartTime;
                $diffMin = (($now->timestamp * 1000) - $heartMs) / 60000;
                return ($diffMin <= $thresholdMinutes) ? 'ONLINE' : 'OFFLINE';
            }

            try {
                $t = Carbon::parse($apiHeartTime);
                return ($t->diffInMinutes($now) <= $thresholdMinutes) ? 'ONLINE' : 'OFFLINE';
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 2) fallback DB heart_time
        if (!empty($dbHeartTime)) {
            try {
                $t = Carbon::parse($dbHeartTime);
                return ($t->diffInMinutes($now) <= $thresholdMinutes) ? 'ONLINE' : 'OFFLINE';
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return 'UNKNOWN';
    }
}
