<?php

namespace App\Http\Controllers\HistoriquePositions;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoriquePosition\HistoriquePositionFilterRequest;
use App\Services\HistoriquePositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HistoriquePositionController extends Controller
{
    public function __construct(
        private readonly HistoriquePositionService $service
    ) {
    }

    public function index(HistoriquePositionFilterRequest $request): View
    {
        $filters = $request->validated();

        $vehicles = $this->service->listVehicles($filters);

        $selectedHistory = null;

        if ($request->wantsHistory()) {
            $vehicleId = (int) $filters['vehicle_id'];
            $mode = $filters['mode'] ?? 'exact';

            $selectedHistory = $mode === 'range'
                ? $this->service->getTrackInRange($vehicleId, $filters)
                : $this->service->getPositionAtTime($vehicleId, $filters);
        }

        return view('historique_positions.index', [
            'filters' => $filters,
            'vehicles' => $vehicles,
            'selectedHistory' => $selectedHistory,
        ]);
    }

    public function data(HistoriquePositionFilterRequest $request, int $vehicleId): JsonResponse
    {
        $filters = $request->validated();
        $filters['vehicle_id'] = $vehicleId;

        if (!$request->wantsHistory()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Les filtres de date/heure sont incomplets.',
            ], 422);
        }

        $mode = $filters['mode'] ?? 'exact';

        $data = $mode === 'range'
            ? $this->service->getTrackInRange($vehicleId, $filters)
            : $this->service->getPositionAtTime($vehicleId, $filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}