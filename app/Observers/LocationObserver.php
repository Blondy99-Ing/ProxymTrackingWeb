<?php

namespace App\Observers;

use App\Models\Location;
use App\Services\DashboardCacheService;

class LocationObserver
{
    public function __construct(private DashboardCacheService $cache) {}

    /**
     * ✅ Lorsqu'une location arrive:
     * - update redis uniquement pour LE véhicule concerné
     * - publish vehicle_patch (dans le cache service)
     */
    public function created(Location $location): void
    {
        $this->cache->updateVehicleFromLocation($location);
    }
}