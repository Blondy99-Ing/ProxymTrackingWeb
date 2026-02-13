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
use App\Http\Controllers\Auth\VerifyLoginController;
use App\Http\Controllers\Trajets\TrajetReplayController;





//Route::get('/', function () {
//    return view('welcome');
//});








//Route::get('login', function () {
//    return view('auth.login');  // Vue de la page de connexion
//})->name('login');

Route::post('login', [AgenceAuthController::class, 'authenticate'])->name('login');

Route::middleware(['auth:web'])->group(function () {

    // ✅ ADMIN + CALL_CENTER
    Route::middleware(['role:admin,call_center'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/api/fleet-snapshot', [DashboardController::class, 'fleetSnapshot'])
            ->name('fleet.snapshot');

        // Alertes (lecture + mark)
        Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::post('/alerts/{alert}/mark-as-read', [AlertController::class, 'markAsRead'])->name('alerts.markAsRead');
        Route::post('/alerts/{alert}/mark-as-unread', [AlertController::class, 'markAsUnread'])->name('alerts.markAsUnread');

        // SSE
        Route::get('/dashboard/stream', [DashboardController::class,'dashboardStream'])->name('dashboard.stream');
        Route::get('/sse/alerts', [AlertController::class, 'alertsStream'])->name('alerts.stream');

        // Trajets
        Route::get('/trajets', [TrajetController::class, 'index'])->name('trajets.index');
        Route::get('/voitures/{id}/trajets', [TrajetController::class, 'byVoiture'])->name('voitures.trajets');
        Route::get('/trajets/{trajet}/points', [TrajetReplayController::class, 'points'])
    ->name('trajets.points');

        // Exemple: détails geofence en lecture
        Route::get('/voitures/{id}/geofence', [VoitureController::class, 'detailsVehiculeGeofence'])
            ->name('tracking.vehicles.geofence');




            //route personnaliser 
            Route::get('/users/{id}/profile', [ProfileController::class, 'show'])
    ->name('users.profile');
    
        // Afficher les utilisateurs 
Route::prefix('tracking')->group(function() {
    Route::get('users', [TrackingUserController::class, 'index'])->name('tracking.users');
});

//route ville 
Route::prefix('villes')->name('villes.')->group(function () {
    Route::get('/', [VilleController::class, 'index'])->name('index');
    // ✅ flux GeoJSON pour la carte
    Route::get('/geojson', [VilleController::class, 'geojson'])->name('geojson');
});

// Liste des véhicules
Route::prefix('tracking')->name('tracking.')->group(function() {
    Route::get('vehicles', [VoitureController::class, 'index'])->name('vehicles');
});

// Formulaire pour associer
Route::get('/association', [AssociationController::class, 'index'])->name('association.index');

// Vue HTML des alertes
Route::get('/alerts/view', function () {
    return view('alerts.index'); // le nom du blade fourni
})->name('alerts.view');
// Marquer une alerte comme lue
Route::patch('/alerts/{id}/processed', [AlertController::class, 'markAsProcessed'])->name('alerts.processed');

// route pour la gestion des SIM dans les GPS
Route::get('/gps-sim', [GpsSimController::class, 'index'])->name('gps_sim.index');
//vue de coupure sans couper
 Route::get('engine/action', [ControlGpsController::class, 'index'])
            ->name('engine.action.index');

// Mettre à jour les paramètres d'alertes (time zone / speed zone) d'un véhicule
Route::put(
    '/users/{user}/vehicles/{voiture}/define',
    [VoitureController::class, 'defineAlertsForUserVehicle']
)->name('users.vehicle.alerts.define');

//statut du moteur
Route::get('{voiture}/engine-status', [ControlGpsController::class, 'engineStatus'])
            ->name('voitures.engineStatus');
Route::get('engine-status/batch', [ControlGpsController::class, 'engineStatusBatch'])
            ->name('voitures.engineStatusBatch');
Route::post('{voiture}/toggle-engine', [ControlGpsController::class, 'toggleEngine'])
            ->name('voitures.toggleEngine');


    });





    // ✅ ADMIN ONLY
    Route::middleware(['role:admin'])->group(function () {

        // Employés CRUD
        Route::prefix('employes')->name('employes.')->group(function () {
            Route::get('/', [EmployeController::class, 'index'])->name('index');
            Route::post('/', [EmployeController::class, 'store'])->name('store');
            Route::put('/{employe}', [EmployeController::class, 'update'])->name('update');
            Route::delete('/{employe}', [EmployeController::class, 'destroy'])->name('destroy');
        });

        // Actions sensibles (engine + turnoff)
        Route::post('/alerts/turnoff/{voiture}', [AlertController::class, 'turnOff'])->name('alerts.turnoff');

        Route::post('voitures/{voiture}/toggle-engine', [ControlGpsController::class, 'toggleEngine'])
            ->name('voitures.toggleEngine');

        // (optionnel) rebuild cache
        Route::post('/dashboard/rebuild', [DashboardController::class, 'rebuildDashboardCache'])
            ->name('dashboard.rebuild');

            // Enregistrer l'association
Route::post('/association', [AssociationController::class, 'associerVoitureAUtilisateur'])->name('association.store');
//supprimer une association
Route::delete('/associations/{id}', [AssociationController::class, 'destroy'])->name('association.destroy');
    });

 Route::get('/engine/actions/history', [\App\Http\Controllers\Gps\HistoriqueCoupureController::class, 'index'])
    ->name('engine.action.history');

// syntroniser les gps du compte 
Route::post('/gps-sim/sync', [GpsSimController::class, 'syncFromAccount'])->name('gps_sim.sync');
//metre à jours les SIM
Route::patch('/gps-sim/{simGps}/sim', [GpsSimController::class, 'updateSim'])->name('gps_sim.sim.update');

// Liste des véhicules
Route::prefix('tracking')->name('tracking.')->group(function() {
    Route::post('vehicles', [VoitureController::class, 'store'])->name('vehicles.store');
    Route::put('vehicles/{voiture}', [VoitureController::class, 'update'])->name('vehicles.update');
    Route::delete('vehicles/{voiture}', [VoitureController::class, 'destroy'])->name('vehicles.destroy');
});
 // definition du time zone et du speed zone
Route::put('vehicles/{voiture}/alerts', [VoitureController::class, 'defineAlertsForVehicle'])
    ->name('tracking.vehicles.alerts.define');
Route::get('/sim-gps/search', [VoitureController::class, 'searchSimGps'])
    ->name('sim_gps.search');




    Route::prefix('tracking')->group(function() {
    Route::get('users', [TrackingUserController::class, 'index'])->name('tracking.users');
    Route::post('users', [TrackingUserController::class, 'store'])->name('tracking.users.store');
    Route::put('users/{trackingUser}', [TrackingUserController::class, 'update'])->name('tracking.users.update');
    Route::delete('users/{trackingUser}', [TrackingUserController::class, 'destroy'])->name('tracking.users.destroy');
});

});










/*
|--------------------------------------------------------------------------
| Forgot password via OTP (SMS/Email) - Option A (page reset dédiée)
|--------------------------------------------------------------------------
| - POST forgot-password/send   : envoie OTP (ouvre la modale OTP sur login)
| - POST forgot-password/resend : renvoie OTP
| - POST forgot-password/verify : vérifie OTP -> redirige vers page reset /reset-password/{token}
| - GET  reset-password/{token} : affiche le formulaire nouveau mdp
| - POST reset-password         : enregistre le nouveau mdp
*/

Route::middleware('guest')->group(function () {

    // Envoi OTP
    Route::post('forgot-password/send', [VerifyLoginController::class, 'sendForgotOtp'])
        ->name('password.otp.send');

    // Renvoyer OTP
    Route::post('forgot-password/resend', [VerifyLoginController::class, 'resendForgotOtp'])
        ->name('password.otp.resend');

    // Vérifier OTP => génère resetToken et redirige vers reset-password/{token}
    Route::post('forgot-password/verify', [VerifyLoginController::class, 'verifyForgotOtp'])
        ->name('password.otp.verify');

    // Page reset (Option A)
    Route::get('reset-password/{token}', [VerifyLoginController::class, 'showResetForm'])
        ->name('password.reset');

    // Soumission nouveau mot de passe
    // ✅ NOS routes reset OTP (pas de conflit)
    Route::get('otp-reset-password/{token}', [VerifyLoginController::class, 'showResetForm'])
        ->name('otp.password.reset');

    Route::post('otp-reset-password', [VerifyLoginController::class, 'resetPassword'])
        ->name('otp.password.store');
});





Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';







