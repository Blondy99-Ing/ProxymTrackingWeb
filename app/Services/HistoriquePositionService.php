<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Trajet;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HistoriquePositionService
{
    private string $tz = 'Africa/Douala';

    public function listVehicles(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = min(max($perPage, 1), 100);

        return Voiture::query()
            ->with(['utilisateur:id,nom,prenom,phone,email'])
            ->when(!empty($filters['search']), function (Builder $query) use ($filters) {
                $term = trim((string) $filters['search']);

                $query->where(function (Builder $q) use ($term) {
                    $q->where('immatriculation', 'like', "%{$term}%")
                        ->orWhere('marque', 'like', "%{$term}%")
                        ->orWhere('model', 'like', "%{$term}%")
                        ->orWhereHas('utilisateur', function (Builder $uq) use ($term) {
                            $uq->where('nom', 'like', "%{$term}%")
                                ->orWhere('prenom', 'like', "%{$term}%")
                                ->orWhere('phone', 'like', "%{$term}%");
                        });
                });
            })
            ->orderBy('immatriculation')
            ->paginate($perPage);
    }

    public function getPositionAtTime(int $vehicleId, array $filters = []): array
    {
        $vehicle = $this->findVehicle($vehicleId);
        $macId = trim((string) ($vehicle->mac_id_gps ?? ''));

        [$targetAt, $start, $end] = $this->buildExactWindow($filters);

        $points = $this->fetchPoints($macId, $start, $end);
        [$nearest, $previous, $next] = $this->resolveNearestPoint($macId, $targetAt);

        return [
            'mode' => 'exact',
            'view' => 'position',

            'vehicle' => $this->transformVehicle($vehicle),

            'window' => [
                'timezone' => $this->tz,
                'start' => $start?->format('Y-m-d H:i:s'),
                'end' => $end?->format('Y-m-d H:i:s'),
                'target_at' => $targetAt?->format('Y-m-d H:i:s'),
            ],

            'track' => [
                'count' => $points->count(),
                'points' => $points->map(fn ($point) => $this->transformPoint($point))->values(),
            ],

            'position_at_time' => $nearest ? $this->transformPoint($nearest) : null,

            'context_points' => [
                'previous' => $previous ? $this->transformPoint($previous) : null,
                'next' => $next ? $this->transformPoint($next) : null,
            ],

            'trajets' => collect(),
        ];
    }

    public function getTrackInRange(int $vehicleId, array $filters = []): array
    {
        $vehicle = $this->findVehicle($vehicleId);
        $macId = trim((string) ($vehicle->mac_id_gps ?? ''));

        [$start, $end] = $this->buildRangeWindow($filters);

        $points = $this->fetchPoints($macId, $start, $end);
        $trajets = $this->fetchTrajets($vehicleId, $start, $end);

        return [
            'mode' => 'range',
            'view' => 'trajet',

            'vehicle' => $this->transformVehicle($vehicle),

            'window' => [
                'timezone' => $this->tz,
                'start' => $start?->format('Y-m-d H:i:s'),
                'end' => $end?->format('Y-m-d H:i:s'),
                'target_at' => null,
            ],

            'track' => [
                'count' => $points->count(),
                'points' => $points->map(fn ($point) => $this->transformPoint($point))->values(),
            ],

            'position_at_time' => null,

            'context_points' => [
                'previous' => null,
                'next' => null,
            ],

            'trajets' => $trajets->map(fn ($trajet) => $this->transformTrajet($trajet))->values(),
        ];
    }

    private function findVehicle(int $vehicleId): Voiture
    {
        return Voiture::with(['utilisateur:id,nom,prenom,phone,email'])->findOrFail($vehicleId);
    }

    private function buildExactWindow(array $filters): array
    {
        $targetDate = $filters['target_at'] ?? null;
        $targetTime = $filters['target_time'] ?? null;

        if (!$targetDate || !$targetTime) {
            return [null, null, null];
        }

        $normalizedTime = strlen($targetTime) === 5 ? ($targetTime . ':00') : $targetTime;

        $targetAt = Carbon::parse($targetDate . ' ' . $normalizedTime, $this->tz);

        return [
            $targetAt,
            $targetAt->copy()->subMinutes(15),
            $targetAt->copy()->addMinutes(15),
        ];
    }

    private function buildRangeWindow(array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $startTime = $filters['start_time'] ?? '00:00';
        $endTime = $filters['end_time'] ?? '23:59';

        if (!$startDate || !$endDate) {
            return [null, null];
        }

        $start = Carbon::parse($startDate . ' ' . $startTime, $this->tz);
        $end = Carbon::parse($endDate . ' ' . $endTime, $this->tz);

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function fetchPoints(string $macId, ?Carbon $start, ?Carbon $end): Collection
    {
        if ($macId === '' || !$start || !$end) {
            return collect();
        }

        return Location::query()
            ->where('mac_id_gps', $macId)
            ->whereBetween('datetime', [$start, $end])
            ->orderBy('datetime', 'asc')
            ->get([
                'id',
                'latitude',
                'longitude',
                'datetime',
                'speed',
                'status',
                'direction',
                'mac_id_gps',
            ]);
    }

    private function fetchTrajets(int $vehicleId, ?Carbon $start, ?Carbon $end): Collection
    {
        if (!$start || !$end) {
            return collect();
        }

        return Trajet::query()
            ->with('voiture')
            ->where('vehicle_id', $vehicleId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', [$start, $end])
                    ->orWhereBetween('end_time', [$start, $end])
                    ->orWhere(function ($qq) use ($start, $end) {
                        $qq->where('start_time', '<=', $start)
                            ->where('end_time', '>=', $end);
                    });
            })
            ->orderBy('start_time', 'asc')
            ->get();
    }

    private function resolveNearestPoint(string $macId, ?Carbon $targetAt): array
    {
        if ($macId === '' || !$targetAt) {
            return [null, null, null];
        }

        $previous = Location::query()
            ->where('mac_id_gps', $macId)
            ->where('datetime', '<=', $targetAt)
            ->orderBy('datetime', 'desc')
            ->first([
                'id',
                'latitude',
                'longitude',
                'datetime',
                'speed',
                'status',
                'direction',
                'mac_id_gps',
            ]);

        $next = Location::query()
            ->where('mac_id_gps', $macId)
            ->where('datetime', '>=', $targetAt)
            ->orderBy('datetime', 'asc')
            ->first([
                'id',
                'latitude',
                'longitude',
                'datetime',
                'speed',
                'status',
                'direction',
                'mac_id_gps',
            ]);

        $nearest = $this->pickNearestPoint($previous, $next, $targetAt);

        return [$nearest, $previous, $next];
    }

    private function pickNearestPoint($previous, $next, Carbon $targetAt)
    {
        if (!$previous && !$next) {
            return null;
        }

        if ($previous && !$next) {
            return $previous;
        }

        if (!$previous && $next) {
            return $next;
        }

        $prevDiff = abs(Carbon::parse($previous->datetime)->diffInSeconds($targetAt, false));
        $nextDiff = abs(Carbon::parse($next->datetime)->diffInSeconds($targetAt, false));

        return $prevDiff <= $nextDiff ? $previous : $next;
    }

    private function transformVehicle(Voiture $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'voiture_unique_id' => $vehicle->voiture_unique_id,
            'immatriculation' => $vehicle->immatriculation,
            'marque' => $vehicle->marque,
            'model' => $vehicle->model,
            'mac_id_gps' => $vehicle->mac_id_gps,
            'owner' => $this->transformOwner($vehicle),
        ];
    }

    private function transformPoint($point): array
    {
        return [
            'id' => $point->id,
            'lat' => (float) $point->latitude,
            'lng' => (float) $point->longitude,
            'datetime' => Carbon::parse($point->datetime)->timezone($this->tz)->format('Y-m-d H:i:s'),
            'speed' => (float) ($point->speed ?? 0),
            'status' => $point->status,
            'direction' => $point->direction,
            'mac_id_gps' => $point->mac_id_gps,
        ];
    }

    private function transformTrajet($trajet): array
    {
        return [
            'id' => $trajet->id,
            'vehicle_id' => $trajet->vehicle_id,
            'start_time' => $trajet->start_time
                ? Carbon::parse($trajet->start_time)->timezone($this->tz)->format('Y-m-d H:i:s')
                : null,
            'end_time' => $trajet->end_time
                ? Carbon::parse($trajet->end_time)->timezone($this->tz)->format('Y-m-d H:i:s')
                : null,
            'duration_minutes' => (int) ($trajet->duration_minutes ?? 0),
            'total_distance_km' => round((float) ($trajet->total_distance_km ?? 0), 2),
            'avg_speed_kmh' => round((float) ($trajet->avg_speed_kmh ?? 0), 1),
            'max_speed_kmh' => round((float) ($trajet->max_speed_kmh ?? 0), 1),
            'start_latitude' => $trajet->start_latitude ? (float) $trajet->start_latitude : null,
            'start_longitude' => $trajet->start_longitude ? (float) $trajet->start_longitude : null,
            'end_latitude' => $trajet->end_latitude ? (float) $trajet->end_latitude : null,
            'end_longitude' => $trajet->end_longitude ? (float) $trajet->end_longitude : null,
        ];
    }

    private function transformOwner(Voiture $vehicle): ?array
    {
        $owner = $vehicle->utilisateur->first();

        if (!$owner) {
            return null;
        }

        return [
            'id' => $owner->id,
            'nom' => $owner->nom,
            'prenom' => $owner->prenom,
            'nom_complet' => trim(($owner->prenom ?? '') . ' ' . ($owner->nom ?? '')),
            'phone' => $owner->phone,
            'email' => $owner->email,
        ];
    }
}