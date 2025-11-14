<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\AgenceAuthController;
use App\Http\Controllers\Voitures\VoitureController;
use App\Http\Controllers\Associations\AssociationController;
use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\VehiclesController;



Route::get('/', function () {
    return view('welcome');
});

//Route::get('login', function () {
//    return view('auth.login');  // Vue de la page de connexion
//})->name('login');

Route::post('login', [AgenceAuthController::class, 'authenticate']);

//Route::middleware(['auth.agence'])->group(function () {
    // Route pour la déconnexion
    Route::post('logout', [AgenceAuthController::class, 'logout'])->name('logout');

    // Routes protégées par authentification

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Afficher les utilisateurs de l'agence
   Route::get('/tracking_users', [TrackingUserController::class, 'index'])->name('tracking.users');


//});



// Enregistrer un nouvel agent swap
Route::post('/tracking_users', [TrackingUserController::class, 'store'])->name('tracking.users.store');

// Modifier un utilisateur (Formulaire)
Route::get('/tracking_users/{trackingUser}/edit', [TrackingUserController::class, 'edit'])->name('tracking.users.edit');

// Mettre à jour un utilisateur
Route::put('/tracking_users/{trackingUser}', [TrackingUserController::class, 'update'])->name('tracking.users.update');


// Supprimer un agent swap
Route::delete('/tracking_users/{trackingUser}', [TrackingUserController::class, 'destroy'])->name('tracking.users.destroy');






// Liste des véhicules
Route::get('/tracking_vehicles', [VoitureController::class, 'index'])->name('tracking.vehicles');

// Enregistrer ou mettre à jour un véhicule
Route::post('/tracking_vehicles', [VoitureController::class, 'store'])->name('tracking.vehicles.store');

// Supprimer un véhicule
Route::delete('/tracking_vehicles/{vehicles}', [VoitureController::class, 'destroy'])->name('tracking.vehicles.destroy');

// Modifier un véhicule
Route::get('/tracking_vehicles/{vehicles}/edit', [VoitureController::class, 'edit'])->name('tracking.vehicles.edit');






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




<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\AgenceAuthController; // Votre contrôleur d'authentification personnalisé
use App\Http\Controllers\Voitures\VoitureController;
use App\Http\Controllers\Associations\AssociationController;
use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\VehiclesController;
use App\Http\Controllers\VehicleController; // Assurez-vous que c'est le bon contrôleur pour les véhicules
use App\Http\Controllers\ReportController; // Ajouté pour corriger l'erreur reports.index


// ----------------------------------------------------------------------
// 1. ROUTES PUBLIQUES (NON AUTHENTIFIÉES)
// ----------------------------------------------------------------------

// Page d'accueil (public si l'utilisateur n'est pas connecté)
Route::get('/', function () {
    return view('welcome');
});

// Route de connexion personnalisée
// Note: Laravel Breeze ajoute déjà les routes GET/POST login, mais si vous utilisez AgenceAuthController,
// assurez-vous de commenter ou supprimer le require __DIR__.'/auth.php'; si vous gérez TOUT manuellement.
//Route::post('login', [AgenceAuthController::class, 'authenticate'])->name('login.attempt');


// ----------------------------------------------------------------------
// 2. ROUTES PROTÉGÉES (ACCESSIBLE APRÈS CONNEXION)
// ----------------------------------------------------------------------

// Le middleware 'auth' garantit que seul un utilisateur connecté peut accéder à ces routes.
// Utiliser 'auth:web' est l'approche la plus sûre si vous avez défini le guard 'web' pour les employés.
Route::middleware(['auth:web'])->group(function () {

    // Route pour la déconnexion
    Route::post('logout', [AgenceAuthController::class, 'logout'])->name('logout');

    // DASHBOARD (Route de base après connexion)
    // J'ai utilisé 'index' de DashboardController pour le point d'entrée
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');


    // ------------------- GESTION DES EMPLOYES (TRACKING USERS) -------------------

    // Afficher tous les employés
    Route::get('/tracking_users', [TrackingUserController::class, 'index'])->name('tracking.users');
    // Enregistrer un nouvel employé (POST)
    Route::post('/tracking_users', [TrackingUserController::class, 'store'])->name('tracking.users.store');
    // Modifier un employé (Formulaire GET)
    Route::get('/tracking_users/{trackingUser}/edit', [TrackingUserController::class, 'edit'])->name('tracking.users.edit');
    // Mettre à jour un employé (PUT/PATCH)
    Route::put('/tracking_users/{trackingUser}', [TrackingUserController::class, 'update'])->name('tracking.users.update');
    // Supprimer un employé
    Route::delete('/tracking_users/{trackingUser}', [TrackingUserController::class, 'destroy'])->name('tracking.users.destroy');


    // ------------------- GESTION DES VÉHICULES -------------------

    // Liste des véhicules (Note: J'utilise 'tracking.vehicles' pour la liste)
    Route::get('/tracking_vehicles', [VoitureController::class, 'index'])->name('tracking.vehicles');
    // Formulaire d'ajout (Si vous utilisez un formulaire séparé)
    Route::get('/vehicles/create', [VehiclesController::class, 'create'])->name('vehicles.create');
    // Enregistrer ou mettre à jour un véhicule (J'ai regroupé le store)
    Route::post('/tracking_vehicles', [VoitureController::class, 'store'])->name('tracking.vehicles.store');
    // Route de sauvegarde (Nettoyage de la duplication)
    Route::post('/save-vehicle', [VehicleController::class, 'store'])->name('vehicles.save'); // Utilisez soit VoitureController, soit VehicleController, pas les deux !
    // Modifier un véhicule (Formulaire GET)
    Route::get('/tracking_vehicles/{vehicles}/edit', [VoitureController::class, 'edit'])->name('tracking.vehicles.edit');
    // Supprimer un véhicule
    Route::delete('/tracking_vehicles/{vehicles}', [VoitureController::class, 'destroy'])->name('tracking.vehicles.destroy');


    // ------------------- GESTION DES ASSOCIATIONS -------------------

    Route::get('/association', [AssociationController::class, 'index'])->name('association.index');
    Route::post('/association', [AssociationController::class, 'associerVoitureAUtilisateur'])->name('association.store');
    Route::delete('/associations/{id}', [AssociationController::class, 'destroy'])->name('association.destroy');


    // ------------------- GESTION DES ALERTES -------------------

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/turnoff/{voiture}', [AlertController::class, 'turnOff'])->name('alerts.turnoff');
    Route::post('/alerts/{alert}/mark-as-read', [AlertController::class, 'markAsRead'])->name('alerts.markAsRead');
    Route::post('/alerts/{alert}/mark-as-unread', [AlertController::class, 'markAsUnread'])->name('alerts.markAsUnread');
    
    // ------------------- REPORTS (CORRECTION DE L'ERREUR) -------------------
    // Ajoutez cette route pour corriger l'erreur Route [reports.index] not defined.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // ------------------- SETTINGS (PARAMÈTRES) -------------------
    Route::get('/settings', function () {
        return view('settings.index'); // Assurez-vous que cette vue existe
    })->name('settings.index');


    // ------------------- ROUTES BREEZE PAR DÉFAUT (Profile) -------------------
    // Ces routes sont ajoutées par Breeze pour la gestion du profil utilisateur
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// ----------------------------------------------------------------------
// 3. AUTHENTIFICATION BREEZE PAR DÉFAUT
// ----------------------------------------------------------------------

// Sauf si vous avez entièrement réécrit les formulaires de connexion/inscription de Breeze,
// il est préférable de conserver ce require qui charge les routes auth/register/password-reset
// de Laravel Breeze, en s'assurant qu'il est cohérent avec AgenceAuthController.
require __DIR__.'/auth.php';

// Route:get('/dashboard') de Breeze (supprimée car remplacée par la vôtre dans le groupe 'auth')
















<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>Connexion Employé - ProxyM Tracking</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
:root { --color-primary: #F58220; --font-family: 'Orbitron', sans-serif; }
.font-orbitron { font-family: var(--font-family); }
.light-mode { --color-bg:#f3f4f6; --color-card:#fff; --color-text:#111827; --color-input-bg:#fff; --color-input-border:#d1d5db; --color-secondary-text:#6b7280; color: var(--color-text);}
.light-mode .card-shadow { box-shadow:0 10px 30px rgba(0,0,0,0.1); border-color:#e5e7eb; background-color:var(--color-card);}
.light-mode .input-style { background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text);}
.light-mode .text-primary{color:var(--color-primary);}
.light-mode .text-secondary{color:var(--color-secondary-text);}
.dark-mode { --color-bg:#121212; --color-card:#1f2937; --color-text:#f3f4f6; --color-input-bg:#374151; --color-input-border:#4b5563; --color-secondary-text:#9ca3af; color: var(--color-text);}
.dark-mode .card-shadow { box-shadow:0 15px 40px rgba(0,0,0,0.5); border-color:#374151; background-color:var(--color-card);}
.dark-mode .input-style { background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text);}
.dark-mode .text-primary { color: var(--color-primary); }
.dark-mode .text-secondary { color: var(--color-secondary-text); }
.input-style:focus { border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px rgba(245,130,32,0.4);}
.btn-primary { background-color:var(--color-primary); transition:0.2s;}
.btn-primary:hover { background-color:#e06d12; transform:translateY(-1px);}
.toggle-switch { width:48px;height:24px;background:#4b5563;border-radius:9999px;position:relative;cursor:pointer;transition:0.4s;}
.toggle-switch.toggled { background: var(--color-primary);}
.toggle-switch::after { content:''; position:absolute; top:2px; left:2px;width:20px;height:20px;background:#fff;border-radius:9999px;transition:0.4s;}
.toggle-switch.toggled::after { transform: translateX(24px);}
</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 light-mode bg-image" id="theme-container">

<div class="w-full max-w-md mx-auto z-content">
    <!-- Toggle Mode -->
    <div class="flex justify-end mb-4">
        <span id="mode-label" class="text-sm mr-2 pt-0.5 text-secondary font-orbitron hidden md:block">Mode Clair</span>
        <div id="theme-toggle" class="toggle-switch"></div>
    </div>

    <!-- Carte Connexion -->
    <div class="card-shadow p-8 md:p-10 rounded-xl border">
        <header class="text-center mb-8">
            <div class="font-orbitron text-xl md:text-2xl font-extrabold">
                PROXYM <span class="text-primary">TRACKING</span>
            </div>
            <h1 class="font-orbitron text-2xl md:text-3xl font-bold mt-4">Connexion Employé</h1>
            <p class="text-sm text-secondary mt-1">Connectez-vous pour accéder à votre espace.</p>
        </header>

        <!-- Formulaire Dynamique -->
        <form id="login-form" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium font-orbitron">Email</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="username"
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="votre.email@agence.com">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium font-orbitron">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="••••••••">
            </div>
            <div class="block">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" name="remember" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                    <span class="ms-2 text-sm text-secondary">Se souvenir de moi</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-6 pt-2">
                <a class="underline text-sm text-secondary hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" href="/forgot-password">
                    Mot de passe oublié ?
                </a>
                <button type="submit" class="ms-3 btn-primary text-white px-5 py-2 rounded-lg font-orbitron font-bold text-sm shadow-md shadow-orange-500/50">
                    Connexion
                </button>
            </div>
        </form>

        <div id="login-message" class="mt-6 p-4 rounded-lg hidden text-sm font-semibold font-orbitron" role="alert"></div>
    </div>
</div>

<script>
const themeContainer = document.getElementById('theme-container');
const themeToggle = document.getElementById('theme-toggle');
const modeLabel = document.getElementById('mode-label');
const loginForm = document.getElementById('login-form');
const loginMessage = document.getElementById('login-message');

// --- Thème ---
function setTheme(theme){
    if(theme==='dark'){
        themeContainer.classList.replace('light-mode','dark-mode');
        themeToggle.classList.add('toggled'); modeLabel.textContent='Mode Sombre';
    } else{
        themeContainer.classList.replace('dark-mode','light-mode');
        themeToggle.classList.remove('toggled'); modeLabel.textContent='Mode Clair';
    }
    localStorage.setItem('theme',theme);
}
themeToggle.addEventListener('click',()=>{setTheme(themeContainer.classList.contains('dark-mode')?'light':'dark');});
document.addEventListener('DOMContentLoaded',()=>setTheme(localStorage.getItem('theme')||'light'));

// --- Messages ---
function displayLoginMessage(msg,type='info'){
    const theme = themeContainer.classList.contains('dark-mode')?'dark':'light';
    loginMessage.textContent = msg; loginMessage.style.display='block';
    loginMessage.className='mt-6 p-4 rounded-lg text-sm font-semibold font-orbitron';
    if(type==='success') loginMessage.classList.add(...(theme==='dark'?['bg-green-700','text-green-100']:['bg-green-100','text-green-800']));
    else if(type==='error') loginMessage.classList.add(...(theme==='dark'?['bg-red-700','text-red-100']:['bg-red-100','text-red-800']));
    else loginMessage.classList.add(...(theme==='dark'?['bg-blue-700','text-blue-100']:['bg-blue-100','text-blue-800']));
}

// --- Soumission Login ---
loginForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    loginMessage.style.display='none';
    const formData = new FormData(loginForm);

    try {
        const response = await fetch('/login', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: formData
        });

        const data = await response.json();

        if(response.ok && data.success){
            displayLoginMessage("✅ Connexion réussie ! Redirection en cours...", 'success');
            loginForm.reset();
            setTimeout(()=>{ window.location.href = data.redirect || '/dashboard'; }, 1500);
        } else if(data.errors){
            const messages = Object.values(data.errors).flat().join(' ');
            displayLoginMessage("❌ " + messages, 'error');
        } else {
            displayLoginMessage("❌ Identifiants incorrects.", 'error');
        }
    } catch(err){
        displayLoginMessage("❌ Une erreur est survenue lors de la connexion.", 'error');
    }
});
</script>
</body>
</html>

















@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-8 p-4 md:p-8">

    {{-- Bande de navigation secondaire --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4" style="border-color: var(--color-border-subtle);">
        <h1 class="text-3xl font-bold font-orbitron" style="color: var(--color-text);">Gestion des Véhicules</h1>
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('tracking.vehicles') }}" class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                <i class="fas fa-car mr-2"></i> Véhicules
            </a>
        </div>
    </div>

    {{-- Bouton pour basculer le formulaire --}}
    <div class="flex justify-end">
        <button id="toggle-form" class="btn-primary">
            <i class="fas fa-plus mr-2"></i>
            @if(isset($voitureEdit))
                Modifier le véhicule
            @else
                Ajouter un véhicule
            @endif
        </button>
    </div>

    {{-- Formulaire conditionnel pour ajout / modification --}}
    <div id="vehicle-form" class="ui-card mt-4 max-h-0 overflow-hidden opacity-0 transition-all duration-500 ease-in-out @if($errors->any() || isset($voitureEdit)) is-error-state @endif">
        <h2 class="text-xl font-bold font-orbitron mb-6">
            @if(isset($voitureEdit))
                Modifier un Véhicule
            @else
                Ajouter un Véhicule
            @endif
        </h2>

        {{-- Affichage des erreurs --}}
        @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong class="font-bold">Erreurs de validation:</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Formulaire --}}
        <form action="@if(isset($voitureEdit)) {{ route('tracking.vehicles.update', $voitureEdit->id) }} @else {{ route('tracking.vehicles.store') }} @endif" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if(isset($voitureEdit))
                @method('PUT')
            @endif

            {{-- Champs de base --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="immatriculation" class="block text-sm font-medium text-secondary">Immatriculation</label>
                    <input type="text" class="ui-input-style mt-1" id="immatriculation" name="immatriculation" placeholder="ABC-123-XY"
                        value="{{ old('immatriculation', $voitureEdit->immatriculation ?? '') }}" required>
                </div>
                <div>
                    <label for="model" class="block text-sm font-medium text-secondary">Modèle</label>
                    <input type="text" class="ui-input-style mt-1" id="model" name="model" placeholder="SUV, Berline, etc."
                        value="{{ old('model', $voitureEdit->model ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="couleur" class="block text-sm font-medium text-secondary">Couleur</label>
                    <input type="text" class="ui-input-style mt-1" id="couleur" name="couleur" placeholder="Noir, Blanc, Rouge..."
                        value="{{ old('couleur', $voitureEdit->couleur ?? '') }}" required>
                </div>
                <div>
                    <label for="marque" class="block text-sm font-medium text-secondary">Marque</label>
                    <input type="text" class="ui-input-style mt-1" id="marque" name="marque" placeholder="Toyota, Renault..."
                        value="{{ old('marque', $voitureEdit->marque ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mac_id_gps" class="block text-sm font-medium text-secondary">Numéro GPS</label>
                    <input type="text" class="ui-input-style mt-1" id="mac_id_gps" name="mac_id_gps" placeholder="GPS-XXXX-XXXX"
                        value="{{ old('mac_id_gps', $voitureEdit->mac_id_gps ?? '') }}" required>
                </div>
                <div>
                    <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                    <input type="file" class="ui-input-style mt-1" id="photo" name="photo">
                    @if(isset($voitureEdit) && $voitureEdit->photo)
                        <img src="{{ asset('storage/' . $voitureEdit->photo) }}" class="h-10 w-10 object-cover rounded mt-2">
                    @endif
                </div>
            </div>

            {{-- Géofence --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <div>
                    <label for="geofence_latitude" class="block text-sm font-medium text-secondary">Latitude</label>
                    <input type="number" step="0.000001" id="geofence_latitude" name="geofence_latitude"
                        value="{{ old('geofence_latitude', $voitureEdit->geofence_latitude ?? 4.0500) }}" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="geofence_longitude" class="block text-sm font-medium text-secondary">Longitude</label>
                    <input type="number" step="0.000001" id="geofence_longitude" name="geofence_longitude"
                        value="{{ old('geofence_longitude', $voitureEdit->geofence_longitude ?? 9.7000) }}" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="geofence_radius" class="block text-sm font-medium text-secondary">Rayon (mètres)</label>
                    <input type="range" id="geofence_radius_slider" min="100" max="10000" step="100" value="{{ old('geofence_radius', $voitureEdit->geofence_radius ?? 1000) }}" class="mt-1 w-full">
                    <input type="number" id="geofence_radius" name="geofence_radius" min="100" max="100000000" step="100" value="{{ old('geofence_radius', $voitureEdit->geofence_radius ?? 1000) }}" class="ui-input-style mt-1">
                    <div class="flex justify-between text-xs text-secondary">
                        <span>100m</span>
                        <span>100000m</span>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-secondary mb-2">Choisir l'emplacement du véhicule</label>
                <div id="map" class="h-96 w-full rounded border border-gray-300"></div>
            </div>

            <button type="submit" class="btn-primary w-full mt-4">
                <i class="fas fa-save mr-2"></i>
                @if(isset($voitureEdit))
                    Mettre à jour le véhicule
                @else
                    Enregistrer le véhicule
                @endif
            </button>
        </form>
    </div>

    {{-- Liste des véhicules --}}
    <div class="ui-card mt-6">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste des Véhicules</h2>
        <div class="ui-table-container shadow-md">
            <table id="example" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>GPS</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    <tr>
                        <td>{{ $voiture->immatriculation }}</td>
                        <td>{{ $voiture->model }}</td>
                        <td>{{ $voiture->marque }}</td>
                        <td>{{ $voiture->couleur }}</td>
                        <td>{{ $voiture->mac_id_gps }}</td>
                        <td>
                            @if($voiture->photo)
                            <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo" class="h-10 w-10 object-cover rounded">
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('tracking.vehicles', ['edit' => $voiture->id]) }}" class="btn-secondary btn-edit">
                                <i class="fas fa-edit mr-2"></i> 
                            </a>
                            <form action="{{ route('tracking.vehicles.destroy', $voiture->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-delete" onclick="return confirm('Voulez-vous vraiment supprimer ce véhicule ?');">
                                    <i class="fas fa-trash mr-2"></i> 
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Scripts --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // FORMULAIRE
    const form = document.getElementById('vehicle-form');
    const toggleBtn = document.getElementById('toggle-form');

    const latInput   = document.getElementById('geofence_latitude');
    const lngInput   = document.getElementById('geofence_longitude');
    const radiusInput = document.getElementById('geofence_radius');
    const radiusSlider = document.getElementById('geofence_radius_slider');

    const initialLat = parseFloat(latInput.value);
    const initialLng = parseFloat(lngInput.value);
    const initialRadius = parseInt(radiusInput.value);

    // ========= CORRECTION : DÉLAY POUR ÉVITER LA MULTIPLICATION DE LA CARTE =========
    let mapInitialized = false;
    let map, circle, marker;

    function initMap() {
        if (mapInitialized) return; // Empêche la création multiple
        mapInitialized = true;

        map = L.map('map');
        map.setView([initialLat, initialLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        circle = L.circle([initialLat, initialLng], {
            radius: initialRadius,
            color: '#4361ee',
            fillColor: '#4895ef',
            fillOpacity: 0.3,
            weight: 2
        }).addTo(map);

        marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);

        marker.on('drag', e => updateFields(e.latlng.lat, e.latlng.lng, parseInt(radiusInput.value)));
        map.on('click', e => updateFields(e.latlng.lat, e.latlng.lng, parseInt(radiusInput.value)));

        // Correction d'affichage
        setTimeout(() => map.invalidateSize(), 200);
    }

    function updateFields(lat, lng, radius) {
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
        radiusInput.value = radius;
        radiusSlider.value = radius;

        circle.setLatLng([lat, lng]);
        circle.setRadius(radius);
        marker.setLatLng([lat, lng]);
    }

    radiusInput.addEventListener('input', () =>
        updateFields(parseFloat(latInput.value), parseFloat(lngInput.value), parseInt(radiusInput.value))
    );

    radiusSlider.addEventListener('input', () =>
        updateFields(parseFloat(latInput.value), parseFloat(lngInput.value), parseInt(radiusSlider.value))
    );

    // ========= OUVERTURE DU FORMULAIRE =========
    toggleBtn.addEventListener('click', () => {
        const isHidden = form.classList.contains('max-h-0');

        if (isHidden) {
            form.classList.remove('max-h-0', 'opacity-0');
            form.classList.add('max-h-[2000px]', 'opacity-100');

            // Initialise la map SEULEMENT APRÈS l'animation (sinon bugs)
            setTimeout(() => {
                initMap();
                map.invalidateSize();
            }, 500);

        } else {
            form.classList.remove('max-h-[2000px]', 'opacity-100');
            form.classList.add('max-h-0', 'opacity-0');
        }
    });

    // Si on est en MODE ÉDIT → ouvrir directement le formulaire et charger la carte
    @if(isset($voitureEdit))
        initMap();
        setTimeout(() => map.invalidateSize(), 400);
    @endif

    // ========= DATATABLES =========
    const table = document.getElementById('example');
    if (table && window.jQuery && typeof $.fn.DataTable !== 'undefined') {
        $(table).DataTable({
            language: {
                url: "/datatables/i18n/fr-FR.json"
            }
        });
    }
});
</script>

@endsection
