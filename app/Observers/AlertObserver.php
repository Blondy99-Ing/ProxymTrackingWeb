<?php

namespace App\Observers;

use App\Models\Alert;
use App\Services\DashboardCacheService;

class AlertObserver
{
    public function __construct(private DashboardCacheService $cache) {}

    public function created(Alert $alert): void
    {
        $this->cache->rebuildAlertsTop10();
        $this->cache->rebuildStats();
    }

    public function updated(Alert $alert): void
    {
        if ($alert->wasChanged(['processed', 'read', 'alert_type'])) {
            $this->cache->rebuildAlertsTop10();
            $this->cache->rebuildStats();
        }
    }
}
