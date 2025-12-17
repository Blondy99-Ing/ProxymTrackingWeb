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



// route creation des ville
Route::get('/villes', [VilleController::class, 'index'])->name('villes.index');
Route::post('/villes', [VilleController::class, 'store'])->name('villes.store');




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
        Route::get('engine-status/batch', [ControlGpsController::class, 'engineStatusBatch'])
            ->name('voitures.engineStatusBatch');

        Route::get('{voiture}/engine-status', [ControlGpsController::class, 'engineStatus'])
            ->name('voitures.engineStatus');

        Route::post('{voiture}/toggle-engine', [ControlGpsController::class, 'toggleEngine'])
            ->name('voitures.toggleEngine');
    });


    // API JSON pour les positions de la flotte
Route::get('/api/fleet-positions', [DashboardController::class, 'fleetPositions'])
    ->name('fleet.positions');

// couper le moteur
Route::get('/voitures/{id}/engine-status', [VoitureController::class, 'getEngineStatus'])
    ->name('voitures.engineStatus');

Route::post('/voitures/{id}/toggle-engine', [VoitureController::class, 'toggleEngine'])
    ->name('voitures.toggleEngine');



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












});




Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';







