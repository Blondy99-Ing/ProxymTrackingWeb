<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\VehiclesApiController;
use App\Http\Controllers\Api\UsersApiController;
use App\Http\Controllers\Api\AlertsApiController;
use App\Http\Controllers\Api\TrajetsApiController;
use App\Http\Controllers\Api\GpsSimApiController;
use App\Http\Controllers\Api\VillesApiController;

// -----------------------------
// Versioning API
// -----------------------------
Route::prefix('v1')->group(function () {

    // -----------------------------
    // AUTH
    // -----------------------------
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    // -----------------------------
    // DASHBOARD
    // -----------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard/fleet-snapshot', [DashboardApiController::class, 'fleetSnapshot']);

        // -----------------------------
        // VEHICLES
        // -----------------------------
        Route::apiResource('vehicles', VehiclesApiController::class);

        // Moteur / engine
        Route::get('vehicles/{vehicle}/engine-status', [VehiclesApiController::class, 'getEngineStatus']);
        Route::patch('vehicles/{vehicle}/engine', [VehiclesApiController::class, 'toggleEngine']);

        // Alerts (time zone / speed zone)
        Route::put('vehicles/{vehicle}/alerts', [VehiclesApiController::class, 'defineAlerts']);

        // Geofence details
        Route::get('vehicles/{vehicle}/geofence', [VehiclesApiController::class, 'detailsVehiculeGeofence']);

        // -----------------------------
        // USERS
        // -----------------------------
        Route::apiResource('users', UsersApiController::class);

        // Alerts pour utilisateur et véhicule spécifique
        Route::put('users/{user}/vehicles/{vehicle}/alerts', [UsersApiController::class, 'defineAlertsForUserVehicle']);

        // -----------------------------
        // ALERTS
        // -----------------------------
        Route::get('alerts', [AlertsApiController::class, 'index']);
        Route::patch('alerts/{alert}/read', [AlertsApiController::class, 'markAsRead']);
        Route::patch('alerts/{alert}/unread', [AlertsApiController::class, 'markAsUnread']);
        Route::patch('alerts/{alert}/processed', [AlertsApiController::class, 'markAsProcessed']);

        // -----------------------------
        // TRAJETS
        // -----------------------------
        Route::get('trajets', [TrajetsApiController::class, 'index']);
        Route::get('vehicles/{vehicle}/trajets', [TrajetsApiController::class, 'byVehicle']);

        // -----------------------------
        // GPS SIM
        // -----------------------------
        Route::get('gps-sim', [GpsSimApiController::class, 'index']);
        Route::post('gps-sim/sync', [GpsSimApiController::class, 'syncFromAccount']);
        Route::patch('gps-sim/{sim}/sim', [GpsSimApiController::class, 'updateSim']);

        // -----------------------------
        // VILLES
        // -----------------------------
        Route::apiResource('villes', VillesApiController::class);
        Route::get('villes/geojson', [VillesApiController::class, 'geojson']);

        // -----------------------------
        // ASSOCIATIONS VEHICULE-UTILISATEUR
        // -----------------------------
        Route::get('associations', [UsersApiController::class, 'listAssociations']);
        Route::post('associations', [UsersApiController::class, 'associateVehicle']);
        Route::delete('associations/{id}', [UsersApiController::class, 'destroyAssociation']);
    });
});
