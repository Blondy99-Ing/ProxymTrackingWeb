<?php

namespace App\Observers;

use App\Models\Alert;
use App\Services\DashboardCacheService;

class AlertObserver
{
    public function __construct(private DashboardCacheService $cache) {}

    public function created(Alert $alert): void
    {
        /**
         * ✅ Règle:
         * - On ne veut que les alertes "du jour" et "non traitées".
         * - L'effet sonore = seulement sur augmentation => event alert_new.
         *
         * Donc:
         * - Si ce n'est pas today OU si processed=true => on ne fait rien.
         * - Sinon: publishNewAlertEvent() gère:
         *   - incr compteurs today
         *   - refresh top10 (today + non traité)
         *   - publish alert_new + stats_patch + alerts_top (selon ton service)
         */
        $this->cache->publishNewAlertEvent($alert, true);
    }

    public function updated(Alert $alert): void
    {
        /**
         * ✅ Règles:
         * - Si l'alerte devient traitée (false -> true) => stats diminuent, PAS de son
         * - Si alert_type change sur une alerte non traitée today => on recale les compteurs / top
         * - read change ne doit pas déclencher rebuildStats (inutile)
         */

        // 1) Transition processed: false -> true (résolution)
        if ($alert->wasChanged('processed') && $alert->processed === true) {
            $this->cache->publishResolvedAlertEvent($alert, true);
            return;
        }

        // 2) Cas: processed repasse à false (réouverture) => augmentation (son)
        // (si tu ne veux pas ça, supprime ce bloc)
        if ($alert->wasChanged('processed') && $alert->processed === false) {
            $this->cache->publishNewAlertEvent($alert, true);
            return;
        }

        // 3) Changement de type sur une alerte encore non traitée
        // => le plus safe est de resynchroniser top + compteurs today
        // (sans jouer de son)
        if ($alert->wasChanged('alert_type')) {
            // resync cohérent today/unprocessed
            $this->cache->rebuildAlertsTop10(); // ça recale aussi compteurs today dans ton service
            $this->cache->rebuildStats();       // stats today via compteurs redis (rapide)
            return;
        }

        // 4) read change => pas besoin de stats; au pire, refresh top si ton tableau affiche read
        if ($alert->wasChanged('read')) {
            // Si ton tableau n'affiche pas read, tu peux enlever cette ligne aussi.
            $this->cache->rebuildAlertsTop10();
            return;
        }
    }
}