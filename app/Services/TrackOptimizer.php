<?php

namespace App\Services;

use Carbon\Carbon;

class TrackOptimizer
{
    public function __construct(private ?GoogleRoadsService $roads = null) {}

    /**
     * @param array $points [ ['lat'=>..,'lng'=>..,'t'=>..,'speed'=>..], ... ]
     */
    public function optimize(array $points, bool $isFocus): array
    {
        if (count($points) < 3) return $points;

        $cfg = config('tracks');

        if ($cfg['optimize']['filter_outliers']) {
            $points = $this->filterOutliers($points, $cfg['outliers']);
        }

        if ($cfg['optimize']['smoothing']) {
            $points = $this->smooth($points, $cfg['smooth_cfg']['window'] ?? 5);
        }

        if ($cfg['optimize']['simplify']) {
            $tol = (float) ($cfg['simplify_cfg']['tolerance_m'] ?? 6.0);
            $points = $this->douglasPeucker($points, $tol);
        }

        if ($cfg['optimize']['snap_to_roads'] && $this->roads) {
            $focusOnly = (bool) ($cfg['optimize']['snap_focus_only'] ?? true);
            if (!$focusOnly || $isFocus) {
                $points = $this->roads->snap($points);
            }
        }

        return $points;
    }

    private function filterOutliers(array $pts, array $o): array
    {
        $minMoveM = (float) ($o['min_move_m'] ?? 3);
        $maxSpeed = (float) ($o['max_speed_kmh'] ?? 140);
        $maxJumpM = (float) ($o['max_jump_m'] ?? 250);
        $maxJumpS = (int)   ($o['max_jump_s'] ?? 10);

        $maxAcc   = (float) ($o['max_acc_ms2'] ?? 6.0);
        $maxTurn  = (float) ($o['max_turn_deg'] ?? 130);
        $minSpdTurn = (float) ($o['min_speed_for_turn_kmh'] ?? 25);

        $out = [];
        $prev = null;
        $prevPrev = null;

        foreach ($pts as $p) {
            if (!$prev) { $out[] = $p; $prev = $p; continue; }

            $d = $this->haversineMeters($prev['lat'], $prev['lng'], $p['lat'], $p['lng']);
            if ($d < $minMoveM) continue; // jitter

            $dt = $this->dtSeconds($prev['t'] ?? null, $p['t'] ?? null);

            // (A) Speed / jump rule
            if ($dt !== null && $dt > 0) {
                $v = ($d / $dt) * 3.6;
                if ($v > $maxSpeed) continue;
                if ($d > $maxJumpM && $dt <= $maxJumpS) continue;

                // (B) Acceleration rule (needs prev speed)
                $prevSpeedKmh = $this->safeSpeedKmh($prev, $out);
                if ($prevSpeedKmh !== null) {
                    $v1 = $prevSpeedKmh / 3.6;
                    $v2 = $v / 3.6;
                    $acc = abs($v2 - $v1) / max(1e-3, $dt);
                    if ($acc > $maxAcc) continue;
                }

                // (C) Turn rule (needs 3 points)
                if ($prevPrev) {
                    $turn = $this->turnAngleDeg($prevPrev, $prev, $p);
                    if ($turn !== null && $turn > $maxTurn && $v > $minSpdTurn) {
                        continue;
                    }
                }
            } else {
                // dt unknown: block huge jumps
                if ($d > ($maxJumpM * 3)) continue;
            }

            $out[] = $p;
            $prevPrev = $prev;
            $prev = $p;
        }

        if (count($out) < 2) return array_slice($pts, 0, 2);
        return $out;
    }

    private function smooth(array $pts, int $window): array
    {
        $n = count($pts);
        if ($n < 5) return $pts;

        $w = max(3, $window);
        if ($w % 2 === 0) $w += 1;
        $half = intdiv($w, 2);

        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $from = max(0, $i - $half);
            $to   = min($n - 1, $i + $half);

            $sumLat = 0; $sumLng = 0; $c = 0;
            for ($j = $from; $j <= $to; $j++) {
                $sumLat += (float)$pts[$j]['lat'];
                $sumLng += (float)$pts[$j]['lng'];
                $c++;
            }

            $p = $pts[$i];
            $p['lat'] = $sumLat / max(1, $c);
            $p['lng'] = $sumLng / max(1, $c);
            $out[] = $p;
        }

        return $out;
    }

    /**
     * Douglas–Peucker simplification (meters tolerance)
     */
    private function douglasPeucker(array $pts, float $tolM): array
    {
        if (count($pts) < 3) return $pts;

        $keep = array_fill(0, count($pts), false);
        $keep[0] = true;
        $keep[count($pts)-1] = true;

        $this->dpMark($pts, 0, count($pts)-1, $tolM, $keep);

        $out = [];
        foreach ($pts as $i => $p) {
            if ($keep[$i]) $out[] = $p;
        }
        return $out;
    }

    private function dpMark(array $pts, int $i0, int $i1, float $tolM, array &$keep): void
    {
        if ($i1 <= $i0 + 1) return;

        $maxD = 0.0;
        $idx = -1;

        $a = $pts[$i0];
        $b = $pts[$i1];

        for ($i = $i0 + 1; $i < $i1; $i++) {
            $p = $pts[$i];
            $d = $this->perpDistanceMeters($p, $a, $b);
            if ($d > $maxD) { $maxD = $d; $idx = $i; }
        }

        if ($idx !== -1 && $maxD > $tolM) {
            $keep[$idx] = true;
            $this->dpMark($pts, $i0, $idx, $tolM, $keep);
            $this->dpMark($pts, $idx, $i1, $tolM, $keep);
        }
    }

    /**
     * Approx perpendicular distance in meters using local projection.
     */
    private function perpDistanceMeters(array $p, array $a, array $b): float
    {
        // project lat/lng to meters around point a
        $ax = 0.0; $ay = 0.0;
        $bx = $this->lngMeters((float)$b['lng']-(float)$a['lng'], (float)$a['lat']);
        $by = $this->latMeters((float)$b['lat']-(float)$a['lat']);
        $px = $this->lngMeters((float)$p['lng']-(float)$a['lng'], (float)$a['lat']);
        $py = $this->latMeters((float)$p['lat']-(float)$a['lat']);

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len2 = $dx*$dx + $dy*$dy;
        if ($len2 <= 1e-9) return sqrt($px*$px + $py*$py);

        $t = (($px-$ax)*$dx + ($py-$ay)*$dy) / $len2;
        $t = max(0.0, min(1.0, $t));

        $cx = $ax + $t*$dx;
        $cy = $ay + $t*$dy;

        $ex = $px - $cx;
        $ey = $py - $cy;

        return sqrt($ex*$ex + $ey*$ey);
    }

    private function dtSeconds(?string $t1, ?string $t2): ?int
    {
        if (!$t1 || !$t2) return null;
        try {
            return Carbon::parse($t2)->diffInSeconds(Carbon::parse($t1));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function safeSpeedKmh(array $prev, array $out): ?float
    {
        if (isset($prev['speed']) && is_numeric($prev['speed']) && (float)$prev['speed'] > 0) {
            return (float)$prev['speed'];
        }

        // fallback: estimate from last two kept points
        $n = count($out);
        if ($n < 2) return null;
        $a = $out[$n-2];
        $b = $out[$n-1];
        $dt = $this->dtSeconds($a['t'] ?? null, $b['t'] ?? null);
        if (!$dt || $dt <= 0) return null;
        $d = $this->haversineMeters($a['lat'], $a['lng'], $b['lat'], $b['lng']);
        return ($d/$dt)*3.6;
    }

    private function turnAngleDeg(array $a, array $b, array $c): ?float
    {
        $abx = $this->lngMeters((float)$b['lng']-(float)$a['lng'], (float)$a['lat']);
        $aby = $this->latMeters((float)$b['lat']-(float)$a['lat']);
        $bcx = $this->lngMeters((float)$c['lng']-(float)$b['lng'], (float)$b['lat']);
        $bcy = $this->latMeters((float)$c['lat']-(float)$b['lat']);

        $abLen = sqrt($abx*$abx + $aby*$aby);
        $bcLen = sqrt($bcx*$bcx + $bcy*$bcy);
        if ($abLen < 1e-6 || $bcLen < 1e-6) return null;

        $dot = ($abx*$bcx + $aby*$bcy) / ($abLen*$bcLen);
        $dot = max(-1.0, min(1.0, $dot));
        $ang = acos($dot) * (180.0 / M_PI);
        return $ang;
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLam = deg2rad($lon2 - $lon1);

        $a = sin($dPhi/2)**2 + cos($phi1)*cos($phi2)*sin($dLam/2)**2;
        return 2*$R*atan2(sqrt($a), sqrt(1-$a));
    }

    private function latMeters(float $dLat): float
    {
        return $dLat * 111320.0;
    }

    private function lngMeters(float $dLng, float $lat): float
    {
        return $dLng * 111320.0 * cos(deg2rad($lat));
    }
}