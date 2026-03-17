<?php

namespace App\Observers;

use App\Models\Alert;
use App\Services\DashboardCacheService;

class AlertObserver
{
    public function __construct(private DashboardCacheService $cache) {}

    public function created(Alert $alert): void
    {
        $this->cache->publishNewAlertEvent($alert, true);
    }

    public function updated(Alert $alert): void
    {
        if ($alert->wasChanged('processed')) {
            if ($alert->processed === true) {
                $this->cache->publishResolvedAlertEvent($alert, true);
            } else {
                $this->cache->rebuildAlertsTop10();
            }
            return;
        }

        if ($alert->wasChanged('alert_type') || $alert->wasChanged('read')) {
            $this->cache->rebuildAlertsTop10();
        }
    }
}