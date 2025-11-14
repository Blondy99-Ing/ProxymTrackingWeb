<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\AgenceAuthController;
use App\Http\Controllers\Voitures\VoitureController;
use App\Http\Controllers\Associations\AssociationController;
use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\VehiclesController;
use App\Http\Controllers\Employes\EmployeController;




Route::get('/', function () {
    return view('welcome');
});




Route::get('/', function () {
    return view('welcome');
});



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

});




Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
