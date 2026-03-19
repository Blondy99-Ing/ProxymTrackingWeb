<?php

namespace App\Observers;

use App\Models\Alert;
use App\Services\DashboardCacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AlertObserver
{
    public function __construct(private DashboardCacheService $cache) {}

    public function created(Alert $alert): void
    {
        // ── DIAGNOSTIC LOGS — retire ces lignes une fois le bug résolu ──
        Log::info('[AlertObserver::created] FIRED', [
            'alert_id'   => $alert->id,
            'alert_type' => $alert->alert_type ?? $alert->type ?? null,
            'processed'  => $alert->processed,
            'alerted_at' => (string) ($alert->alerted_at ?? 'NULL'),
            'created_at' => (string) ($alert->created_at ?? 'NULL'),
        ]);

        try {
            $versionBefore = (int) Redis::get('dash:version');

            $this->cache->publishNewAlertEvent($alert, true);

            $versionAfter = (int) Redis::get('dash:version');
            $queueLen     = (int) Redis::llen('dash:eventq:alert_new');

            Log::info('[AlertObserver::created] publishNewAlertEvent DONE', [
                'alert_id'       => $alert->id,
                'version_before' => $versionBefore,
                'version_after'  => $versionAfter,
                'version_bumped' => $versionAfter > $versionBefore,
                'queue_len'      => $queueLen,
            ]);

        } catch (\Throwable $e) {
            Log::error('[AlertObserver::created] EXCEPTION', [
                'alert_id' => $alert->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
        }
    }

    public function updated(Alert $alert): void
    {
        if ($alert->wasChanged('processed')) {
            if ($alert->processed === true) {
                try {
                    $this->cache->publishResolvedAlertEvent($alert, true);
                } catch (\Throwable $e) {
                    Log::error('[AlertObserver::updated] publishResolvedAlertEvent EXCEPTION', [
                        'alert_id' => $alert->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
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