<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleRoadsService
{
    /**
     * Snap points to road using Google Roads API (SnapToRoads)
     * Input points: [ ['lat'=>..., 'lng'=>..., 't'=>..., 'speed'=>...], ... ]
     */
    public function snap(array $points): array
    {
        $key = config('services.google_roads.key');
        if (!$key || count($points) < 2) return $points;

        $chunkSize   = max(2, (int) config('services.google_roads.chunk', 100));
        $interpolate = (bool) config('services.google_roads.interpolate', true);
        $timeout     = (int) config('services.google_roads.timeout', 15);
        $retries     = (int) config('services.google_roads.retries', 2);

        $chunks = array_chunk($points, $chunkSize);
        $snappedAll = [];

        foreach ($chunks as $ci => $chunk) {
            $path = collect($chunk)->map(fn($p) => $p['lat'].','.$p['lng'])->implode('|');

            $resp = Http::retry($retries, 250)
                ->timeout($timeout)
                ->get('https://roads.googleapis.com/v1/snapToRoads', [
                    'path' => $path,
                    'interpolate' => $interpolate ? 'true' : 'false',
                    'key' => $key,
                ]);

            if (!$resp->ok()) {
                $snappedAll = array_merge($snappedAll, $chunk);
                continue;
            }

            $json = $resp->json();
            $snapped = $json['snappedPoints'] ?? [];
            if (!$snapped) {
                $snappedAll = array_merge($snappedAll, $chunk);
                continue;
            }

            $out = [];

            if (!$interpolate) {
                foreach ($snapped as $sp) {
                    $idx = $sp['originalIndex'] ?? null;
                    if ($idx === null) continue;

                    $src = $chunk[$idx] ?? null;
                    if (!$src) continue;

                    $out[] = [
                        'lat'   => (float) ($sp['location']['latitude'] ?? $src['lat']),
                        'lng'   => (float) ($sp['location']['longitude'] ?? $src['lng']),
                        't'     => $src['t'] ?? null,
                        'speed' => (float) ($src['speed'] ?? 0),
                    ];
                }
            } else {
                // Interpolated: project time/speed from nearest raw point
                foreach ($snapped as $sp) {
                    $lat = (float) ($sp['location']['latitude'] ?? 0);
                    $lng = (float) ($sp['location']['longitude'] ?? 0);

                    $nearest = $this->nearest($lat, $lng, $chunk);

                    $out[] = [
                        'lat'   => $lat,
                        'lng'   => $lng,
                        't'     => $nearest['t'] ?? null,
                        'speed' => (float) ($nearest['speed'] ?? 0),
                    ];
                }
            }

            $out = $this->dedupe($out);

            // avoid duplicate boundary point between chunks
            if ($ci > 0 && count($snappedAll) && count($out)) {
                $last = end($snappedAll);
                $first = $out[0];
                if ($this->same($last, $first)) array_shift($out);
            }

            $snappedAll = array_merge($snappedAll, $out);
        }

        return $this->dedupe($snappedAll);
    }

    private function nearest(float $lat, float $lng, array $src): array
    {
        $best = $src[0] ?? [];
        $bestD = INF;

        foreach ($src as $p) {
            $d = ($p['lat']-$lat)*($p['lat']-$lat) + ($p['lng']-$lng)*($p['lng']-$lng);
            if ($d < $bestD) { $bestD = $d; $best = $p; }
        }
        return $best;
    }

    private function dedupe(array $pts): array
    {
        $out = [];
        $prev = null;
        foreach ($pts as $p) {
            if (!$prev) { $out[] = $p; $prev = $p; continue; }
            if ($this->same($prev, $p)) continue;
            $out[] = $p; $prev = $p;
        }
        return $out;
    }

    private function same(array $a, array $b): bool
    {
        return round((float)$a['lat'], 6) === round((float)$b['lat'], 6)
            && round((float)$a['lng'], 6) === round((float)$b['lng'], 6);
    }
}