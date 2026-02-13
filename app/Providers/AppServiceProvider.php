<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Alert;
use App\Models\Location;
use App\Observers\AlertObserver;
use App\Observers\LocationObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GoogleRoadsService::class);
        $this->app->singleton(\App\Services\TrackOptimizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         // Vérifiez si l'agence est authentifiée dans la session
        if (session()->has('agence')) {
            // Récupérer l'ID de l'agence stocké dans la session
            $agenceId = session('agence')->id;

            // Récupérer toutes les informations de l'agence dans la base de données
            $agence = Agence::find($agenceId);

            // Partager toutes les informations de l'agence dans toutes les vues
            View::share('agence', $agence);
        }

        Alert::observe(AlertObserver::class);
        Location::observe(LocationObserver::class);
    }
}
