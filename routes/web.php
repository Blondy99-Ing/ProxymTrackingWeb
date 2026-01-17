<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\AgenceAuthController;
use App\Http\Controllers\Voitures\VoitureController;
use App\Http\Controllers\Associations\AssociationController;
use App\Http\Controllers\VehiclesController;
use App\Http\Controllers\Employes\EmployeController;
use App\Http\Controllers\Villes\VilleController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Users\ProfileController;
use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\Trajets\TrajetController;
use App\Http\Controllers\Gps\ControlGpsController;
use App\Http\Controllers\Gps\HistoriqueCoupureController;
use App\Http\Controllers\GpsSimController;





//Route::get('/', function () {
//    return view('welcome');
//});








//Route::get('login', function () {
//    return view('auth.login');  // Vue de la page de connexion
//})->name('login');

Route::post('login', [AgenceAuthController::class, 'authenticate'])->name('login');

Route::middleware(['auth:web'])->group(function () {
    // Route pour la déconnexion
    Route::post('logout', [AgenceAuthController::class, 'logout'])->name('logout');

    // Routes protégées par authentification

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    // 🔁 API JSON pour le dashboard temps réel
Route::get('/api/fleet-snapshot', [DashboardController::class, 'fleetSnapshot'])
    ->name('fleet.snapshot');

    // Afficher les utilisateurs 
Route::prefix('tracking')->group(function() {
    Route::get('users', [TrackingUserController::class, 'index'])->name('tracking.users');
    Route::post('users', [TrackingUserController::class, 'store'])->name('tracking.users.store');
    Route::put('users/{trackingUser}', [TrackingUserController::class, 'update'])->name('tracking.users.update');
    Route::delete('users/{trackingUser}', [TrackingUserController::class, 'destroy'])->name('tracking.users.destroy');
});




// Liste des véhicules
Route::prefix('tracking')->name('tracking.')->group(function() {
    Route::get('vehicles', [VoitureController::class, 'index'])->name('vehicles');
    Route::post('vehicles', [VoitureController::class, 'store'])->name('vehicles.store');
    Route::put('vehicles/{voiture}', [VoitureController::class, 'update'])->name('vehicles.update');
    Route::delete('vehicles/{voiture}', [VoitureController::class, 'destroy'])->name('vehicles.destroy');
});








// Formulaire pour associer
Route::get('/association', [AssociationController::class, 'index'])->name('association.index');

// Enregistrer l'association
Route::post('/association', [AssociationController::class, 'associerVoitureAUtilisateur'])->name('association.store');

Route::delete('/associations/{id}', [AssociationController::class, 'destroy'])->name('association.destroy');


//alerts
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::post('/alerts/turnoff/{voiture}', [AlertController::class, 'turnOff'])->name('alerts.turnoff');
Route::post('/alerts/{alert}/mark-as-read', [AlertController::class, 'markAsRead'])->name('alerts.markAsRead');
Route::post('/alerts/{alert}/mark-as-unread', [AlertController::class, 'markAsUnread'])->name('alerts.markAsUnread');

// Vehicles Routes
Route::get('/vehicles/create', [VehiclesController::class, 'create'])->name('vehicles.create');
Route::post('/vehicles/store', [VehiclesController::class, 'store'])->name('vehicles.store');

//details d'un vehicule specifique
Route::get('/voitures/{id}/geofence', [VoitureController::class, 'detailsVehiculeGeofence'])
    ->name('tracking.vehicles.geofence');

Route::prefix('voitures')->group(function () {
         Route::get('engine/action', [ControlGpsController::class, 'index'])
            ->name('engine.action.index');
      
        Route::get('engine-status/batch', [ControlGpsController::class, 'engineStatusBatch'])
            ->name('voitures.engineStatusBatch');

        Route::get('{voiture}/engine-status', [ControlGpsController::class, 'engineStatus'])
            ->name('voitures.engineStatus');

        Route::post('{voiture}/toggle-engine', [ControlGpsController::class, 'toggleEngine'])
            ->name('voitures.toggleEngine');
    });
  
 Route::get('/engine/actions/history', [\App\Http\Controllers\Gps\HistoriqueCoupureController::class, 'index'])
    ->name('engine.action.history');



   





// 1. Route to show the page
Route::get('/add-vehicle', function () {
    return view('vehicles.create');
})->name('vehicles.add');

// 2. Route to save the vehicle (form POST)
Route::post('/save-vehicle', [\App\Http\Controllers\VehicleController::class, 'store'])->name('vehicles.save');


Route::prefix('employes')->name('employes.')->group(function () {
    Route::get('/', [EmployeController::class, 'index'])->name('index');        // Liste des employés
    Route::post('/', [EmployeController::class, 'store'])->name('store');       // Ajouter un employé
    Route::get('/{employe}/edit', [EmployeController::class, 'edit'])->name('edit'); // Formulaire édition
    Route::put('/{employe}', [EmployeController::class, 'update'])->name('update'); // Mettre à jour
    Route::delete('/{employe}', [EmployeController::class, 'destroy'])->name('destroy'); // Supprimer
});





Route::get('/users/{id}/profile', [ProfileController::class, 'show'])
    ->name('users.profile');

// Paramètre de vehicule
// Mettre à jour les paramètres d'alertes (time zone / speed zone) d'un véhicule
Route::put(
    '/users/{user}/vehicles/{voiture}/define',
    [VoitureController::class, 'defineAlertsForUserVehicle']
)->name('users.vehicle.alerts.define');

 // definition du time zone et du speed zone
Route::put('vehicles/{voiture}/alerts', [VoitureController::class, 'defineAlertsForVehicle'])
    ->name('tracking.vehicles.alerts.define');



    // Liste de toutes les alertes (JSON)
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');

// Marquer une alerte comme lue
Route::patch('/alerts/{id}/processed', [AlertController::class, 'markAsProcessed'])->name('alerts.processed');

// Vue HTML des alertes
Route::get('/alerts/view', function () {
    return view('alerts.index'); // le nom du blade fourni
})->name('alerts.view');


//trajets
Route::get('/trajets', [TrajetController::class, 'index'])->name('trajets.index');
Route::get('/voitures/{id}/trajets', [TrajetController::class, 'byVoiture'])->name('voitures.trajets');



// route pour la gestion des SIM dans les GPS
Route::get('/gps-sim', [GpsSimController::class, 'index'])->name('gps_sim.index');
Route::post('/gps-sim/sync', [GpsSimController::class, 'syncFromAccount'])->name('gps_sim.sync');
Route::patch('/gps-sim/{simGps}/sim', [GpsSimController::class, 'updateSim'])->name('gps_sim.sim.update');Route::get('/sim-gps/search', [VoitureController::class, 'searchSimGps'])
    ->name('sim_gps.search');


//route ville 
Route::prefix('villes')->name('villes.')->group(function () {
    Route::get('/', [VilleController::class, 'index'])->name('index');
    Route::post('/', [VilleController::class, 'store'])->name('store');

    Route::put('/{ville}', [VilleController::class, 'update'])->name('update');
    Route::delete('/{ville}', [VilleController::class, 'destroy'])->name('destroy');


    // ✅ flux GeoJSON pour la carte
    Route::get('/geojson', [VilleController::class, 'geojson'])->name('geojson');
});











// ✅ SSE global dashboard : stats + fleet + dernières alertes
//Route::get('/sse/dashboard', [DashboardController::class, 'dashboardStream'])
//    ->name('dashboard.stream');
//Route::get('/dashboard', [DashboardController::class,'index'])->name('dashboard');
Route::get('/dashboard/stream', [DashboardController::class,'dashboardStream'])->name('dashboard.stream');
Route::get('/dashboard/rebuild-cache', [DashboardController::class,'rebuildCache'])->name('dashboard.rebuild');

// (optionnel) endpoint pour forcer rebuild si tu veux tester rapidement
Route::post('/dashboard/rebuild', [DashboardController::class, 'rebuildDashboardCache'])
    ->name('dashboard.rebuild');



// ✅ SSE page alertes : liste alertes (utile si page alertes est ouverte seule)
Route::get('/sse/alerts', [AlertController::class, 'alertsStream'])
    ->name('alerts.stream');


});




Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';







