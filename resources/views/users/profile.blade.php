{{-- resources/views/users/profile.blade.php --}}
@extends('layouts.app')

@section('title', 'Profile')

@push('head')
    {{-- Google Maps API avec callback --}}
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async defer></script>
@endpush

@php
    // ✅ Payload JS propre (évite les @foreach dans le JS et les erreurs de parsing)
    $vehiclesPayload = ($user->voitures ?? collect())->map(function ($v) {
        $tzStart = $v->time_zone_start ? substr((string)$v->time_zone_start, 0, 5) : null; // "HH:MM"
        $tzEnd   = $v->time_zone_end   ? substr((string)$v->time_zone_end,   0, 5) : null;

        return [
            'id' => $v->id,
            'immat' => $v->immatriculation,
            'model' => trim(($v->marque ?? '').' '.($v->model ?? '')),
            'lat' => (float) optional($v->latestLocation)->latitude,
            'lng' => (float) optional($v->latestLocation)->longitude,
            'gps_state' => $v->gps_state ?? 'UNKNOWN',
            'engine_state' => $v->engine_state ?? 'UNKNOWN',
            'geofence_coords' => $v->geofence_coords ?? [], // [[lng,lat],...]

            // ✅ Valeurs actuelles alertes
            'time_zone_start' => $tzStart,
            'time_zone_end'   => $tzEnd,
            'speed_zone'      => $v->speed_zone !== null ? (int)$v->speed_zone : null,

            // ✅ Photos (si ton modèle expose photo_url)
            'photo_url' => $v->photo_url ?? null,
        ];
    })->values();

    // ✅ user photo url
    $userPhotoUrl = $user->photo_url ?? null;

    // ✅ URL update user (même pattern que ta liste: PUT /tracking/users/{id})
    $userUpdateUrl = url('tracking/users/'.$user->id);
@endphp

@section('content')
<div class="space-y-8">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne 1 : Informations personnelles --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="ui-card p-6 flex flex-col items-center">
                <div class="relative group">
                    <img
                        src="{{ $userPhotoUrl ?: 'https://placehold.co/120x120/F58220/ffffff?text=JD' }}"
                        alt="Profile"
                        id="user-profile-img"
                        class="h-32 w-32 rounded-full object-cover border-4 border-primary shadow-lg mb-4 cursor-pointer transition-transform duration-200 hover:scale-105"
                        onclick="openImageModal(this.src)"
                    >
                </div>

                <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                    {{ $user->prenom }} {{ $user->nom }}
                </h2>
                <p class="text-secondary">{{ $user->role?->name ?? 'Utilisateur' }}</p>

                <button type="button" id="openProfileEditModalBtn" class="btn-secondary mt-4 py-2 px-4 text-sm font-normal">
                    <i class="fas fa-edit mr-2"></i> Mettre à jour les informations
                </button>
            </div>

            {{-- Détails du compte --}}
            <div class="ui-card p-6 space-y-4">
                <h3 class="text-lg font-semibold border-b pb-2" style="border-color: var(--color-border-subtle);">
                    Détails du Compte
                </h3>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Prénom:</span>
                    <span class="text-primary font-semibold">{{ $user->prenom }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Nom:</span>
                    <span class="text-semibold">{{ $user->nom }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Téléphone:</span>
                    <span class="text-semibold">{{ $user->phone }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Email:</span>
                    <span class="text-semibold">{{ $user->email }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Identifiant:</span>
                    <span class="text-semibold">{{ $user->user_unique_id }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Ville:</span>
                    <span class="text-semibold">{{ $user->ville }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Quartier:</span>
                    <span class="text-semibold">{{ $user->quartier }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">CIN / Passport:</span>
                    <span class="text-semibold">{{ $user->cin ?? '-' }}</span>
                </div>
                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Statut:</span>
                    <span class="font-semibold text-green-500">Actif</span>
                </div>
            </div>
        </div>

        {{-- Colonne 2/3 : Carte + tableau véhicules --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Carte --}}
            <div class="ui-card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);" id="map-title">
                        Carte de Suivi : Tous Mes Véhicules
                    </h2>

                    {{-- ✅ Bouton Afficher Tous --}}
                    <button id="showAllVehiclesBtn" class="btn-secondary py-1 px-3 text-sm font-normal hidden">
                        <i class="fas fa-list-ul mr-1"></i> Afficher Tous
                    </button>
                </div>

                <div id="userMap" class="rounded-lg shadow-inner" style="height:450px;"></div>
            </div>

            {{-- Tableau des véhicules --}}
            <div class="ui-card p-6">
                <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
                    Véhicules Associés ({{ $vehiclesCount }})
                </h2>

                <div class="ui-table-container shadow-md overflow-x-auto">
                    <table id="vehiclesTable" class="ui-table w-full">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Immatriculation</th>
                                <th>Marque/Modèle</th>
                                <th>GPS</th>
                                <th>Moteur</th>
                                <th>Couleur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($user->voitures as $vehicle)
                                @php
                                    $gpsState = $vehicle->gps_state ?? 'UNKNOWN';
                                    $engState = $vehicle->engine_state ?? 'UNKNOWN';
                                    $vehiclePhoto = $vehicle->photo_url ?? null;
                                @endphp

                                <tr id="row-{{ $vehicle->id }}">
                                    <td>
                                        <div class="relative group w-10 h-10">
                                            <img
                                                src="{{ $vehiclePhoto ?: 'https://placehold.co/40x40' }}"
                                                class="w-10 h-10 object-cover rounded-md cursor-pointer border border-border-subtle transition-transform duration-200 hover:scale-105"
                                                onclick="openImageModal('{{ $vehiclePhoto ?: '' }}')"
                                                alt="Photo véhicule"
                                            >
                                        </div>
                                    </td>

                                    <td>{{ $vehicle->immatriculation }}</td>
                                    <td>{{ $vehicle->marque }} / {{ $vehicle->model }}</td>

                                    {{-- GPS state --}}
                                    <td>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                                            {{ $gpsState === 'ONLINE' ? 'bg-green-100 text-green-700' : ($gpsState === 'OFFLINE' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') }}">
                                            {{ $gpsState }}
                                        </span>
                                    </td>

                                    {{-- Engine state --}}
                                    <td>
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                                            {{ $engState === 'ON' ? 'bg-green-100 text-green-700' : ($engState === 'OFF' ? 'bg-yellow-100 text-yellow-700' : ($engState === 'CUT' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
                                            {{ $engState }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="w-6 h-6 rounded" style="background-color: {{ $vehicle->couleur }};"></div>
                                    </td>

                                    <td class="space-x-2 whitespace-nowrap">
                                        {{-- Localiser sur la carte --}}
                                        <button type="button"
                                            onclick="zoomToVehicle({{ $vehicle->id }})"
                                            class="text-primary hover:text-primary-dark transition-colors p-1"
                                            title="Localiser sur la carte">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>

                                        {{-- Historique --}}
                                        <a href="{{ route('trajets.index', ['vehicle_id' => $vehicle->id]) }}"
                                           class="text-secondary hover:text-red-500 transition-colors p-1 inline-flex"
                                           title="Historique des trajets">
                                            <i class="fas fa-history"></i>
                                        </a>

                                        {{-- Paramètres d’alertes --}}
                                        <button type="button"
                                            class="text-secondary hover:text-yellow-500 transition-colors p-1"
                                            title="Paramètres d’alertes"
                                            onclick="openAlertConfigModal(
                                                {{ $vehicle->id }},
                                                '{{ addslashes($vehicle->immatriculation.' - '.$vehicle->marque.' '.$vehicle->model) }}'
                                            )">
                                            <i class="fas fa-sliders-h"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ================= MODALE IMAGE (profil + voitures) ================= --}}
<div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-75">
    <div class="relative bg-white rounded-lg shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden">
        <button id="closeImageModalBtn"
            class="absolute top-4 right-4 text-3xl font-bold text-white hover:text-primary transition-colors bg-gray-900 rounded-full h-10 w-10 flex items-center justify-center">
            &times;
        </button>
        <img id="modalImage" src="" alt="Image en grand plan" class="w-full h-auto object-contain max-h-[85vh] p-2">
    </div>
</div>

{{-- ================= MODALE EDIT PROFIL ================= --}}
<div id="profileEditModal"
     class="fixed inset-0 bg-black bg-opacity-75 hidden z-[9999] flex items-center justify-center transition-opacity duration-300">
    <div class="bg-card rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card">

        <button id="closeProfileEditModalBtn"
                class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 class="text-xl font-bold font-orbitron mb-6" style="color: var(--color-text);">
            Modifier mes informations
        </h2>

        <form id="profileEditForm"
              action="{{ $userUpdateUrl }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-4">
            @csrf
            @method('PUT')

            {{-- IMPORTANT: ton controller update exige role_id => on le renvoie --}}
            <input type="hidden" name="role_id" value="{{ $user->role_id }}">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="edit_nom" name="nom" class="ui-input-style mt-1" required value="{{ $user->nom }}">
                </div>
                <div>
                    <label for="edit_prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="edit_prenom" name="prenom" class="ui-input-style mt-1" required value="{{ $user->prenom }}">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="edit_phone" name="phone" class="ui-input-style mt-1" required value="{{ $user->phone }}">
                </div>
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="edit_email" name="email" class="ui-input-style mt-1" required value="{{ $user->email }}">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_ville" class="block text-sm font-medium text-secondary">Ville</label>
                    <input type="text" id="edit_ville" name="ville" class="ui-input-style mt-1" value="{{ $user->ville }}">
                </div>
                <div>
                    <label for="edit_quartier" class="block text-sm font-medium text-secondary">Quartier</label>
                    <input type="text" id="edit_quartier" name="quartier" class="ui-input-style mt-1" value="{{ $user->quartier }}">
                </div>
            </div>

            {{-- Photo --}}
            <div class="space-y-2">
                <label class="block text-sm font-medium text-secondary">Photo</label>

                <label for="edit_photo" class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                    Choisir un fichier
                </label>
                <input type="file" class="hidden" id="edit_photo" name="photo" accept="image/*">

                <div id="edit_file_name" class="text-xs text-secondary italic">
                    {{ $userPhotoUrl ? 'Laisser vide pour conserver la photo actuelle' : 'Aucun fichier sélectionné' }}
                </div>

                <img id="edit_preview"
                     src="{{ $userPhotoUrl ?: '#' }}"
                     alt="Aperçu"
                     class="mt-2 h-24 w-24 object-cover rounded-full {{ $userPhotoUrl ? '' : 'hidden' }} border border-border-subtle">
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
                <button type="button" id="cancelProfileEditBtn" class="btn-secondary">Annuler</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ================= MODALE PARAMÈTRES D’ALERTES ================= --}}
<div id="alertConfigModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black bg-opacity-50">
    <div class="bg-card rounded-2xl shadow-2xl w-full max-w-xl p-6 relative ui-card">

        <button type="button" id="closeAlertConfigModalBtn"
            class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
            Paramètres d’alertes du véhicule
        </h2>

        <p class="text-sm text-secondary mb-4">
            <span class="font-medium">Véhicule ciblé :</span>
            <span id="alertConfigVehicleLabel" class="font-semibold text-primary"></span>
        </p>

        <form id="vehicle-alerts-form" method="POST" action="#">
            @csrf
            @method('PUT')

            {{-- TimeZone --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="time_zone_start" class="block text-sm font-medium text-secondary mb-1">
                        Heure de début (TimeZone)
                    </label>
                    <input type="time" id="time_zone_start" name="time_zone_start" class="ui-input-style">

                    <p class="text-xs text-secondary mt-1">
                        Valeur actuelle :
                        <span id="current_time_zone_start" class="font-semibold text-primary">—</span>
                    </p>
                </div>

                <div>
                    <label for="time_zone_end" class="block text-sm font-medium text-secondary mb-1">
                        Heure de fin (TimeZone)
                    </label>
                    <input type="time" id="time_zone_end" name="time_zone_end" class="ui-input-style">

                    <p class="text-xs text-secondary mt-1">
                        Valeur actuelle :
                        <span id="current_time_zone_end" class="font-semibold text-primary">—</span>
                    </p>
                </div>
            </div>

            {{-- SpeedZone --}}
            <div class="mb-4">
                <label for="speed_zone" class="block text-sm font-medium text-secondary mb-1">
                    Vitesse maximale autorisée (SpeedZone)
                </label>
                <input type="number" step="1" min="0" id="speed_zone" name="speed_zone"
                    class="ui-input-style" placeholder="Ex: 80">

                <p class="text-xs text-secondary mt-1">
                    Valeur actuelle :
                    <span id="current_speed_zone" class="font-semibold text-primary">—</span>
                </p>
            </div>

            {{-- Appliquer à --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-secondary mb-1">Appliquer à</label>
                <div class="flex flex-col gap-2 mt-1">
                    <label class="inline-flex items-center gap-2 text-sm text-secondary">
                        <input type="radio" name="apply_scope" value="one" class="form-radio" checked>
                        <span>Ce véhicule uniquement</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-secondary">
                        <input type="radio" name="apply_scope" value="all" class="form-radio">
                        <span>Tous les véhicules de cet utilisateur</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-secondary">
                        <input type="radio" name="apply_scope" value="selected" class="form-radio">
                        <span>Véhicules sélectionnés</span>
                    </label>
                </div>

                <div id="selectedVehiclesContainer" class="mt-3 hidden">
                    <p class="text-xs text-secondary mb-2">Choisissez un ou plusieurs véhicules :</p>
                    <div class="max-h-40 overflow-y-auto space-y-1 border border-border-subtle rounded-md p-2">
                        @foreach($user->voitures as $v)
                            <label class="flex items-center gap-2 text-xs text-secondary">
                                <input type="checkbox" name="selected_vehicles[]" value="{{ $v->id }}" class="form-checkbox">
                                <span>{{ $v->immatriculation }} — {{ $v->marque }} {{ $v->model }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
                <button type="button" id="cancelAlertConfigBtn" class="btn-secondary">Annuler</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
/**
 * ✅ Exigences:
 * - Tous les véhicules: markers visibles, PAS de geofence
 * - Clic sur marker: centre + geofence + InfoWindow (GPS/Moteur)
 * - Localiser depuis tableau: idem
 * - Modale alertes: pré-remplir avec valeurs actuelles + afficher "Valeur actuelle"
 */

let map;
let markersById = {};
let geofencePolygon = null;
let infoWindow = null;
let selectedVehicleId = null;

const carIconUrl = @json(asset('assets/icons/car_icon.png'));
const geofenceStroke = '#F58220';

// ✅ Data JS safe
const vehiclesData = @json($vehiclesPayload);

// callback Google Maps
function initMap() {
    map = new google.maps.Map(document.getElementById('userMap'), {
        center: { lat: 4.05, lng: 9.7 },
        zoom: 11
    });

    infoWindow = new google.maps.InfoWindow();

    renderAllVehicles();
    fitAllVehicles();
    clearGeofence(); // ✅ jamais de geofence au début
}

function getCarIcon() {
    return {
        url: carIconUrl,
        scaledSize: new google.maps.Size(60, 60),
        anchor: new google.maps.Point(30, 58) // bas-centre
    };
}

function renderAllVehicles() {
    Object.values(markersById).forEach(m => m.setMap(null));
    markersById = {};

    vehiclesData.forEach(v => {
        if (!v.lat || !v.lng || v.lat === 0 || v.lng === 0) return;

        const marker = new google.maps.Marker({
            position: { lat: v.lat, lng: v.lng },
            map,
            title: `${v.model} (${v.immat})`,
            icon: getCarIcon()
        });

        marker.addListener('click', () => focusVehicle(v.id, true));
        markersById[v.id] = marker;
    });
}

function fitAllVehicles() {
    const valid = vehiclesData.filter(v => v.lat && v.lng && v.lat !== 0 && v.lng !== 0);
    if (!valid.length) return;

    const bounds = new google.maps.LatLngBounds();
    valid.forEach(v => bounds.extend({ lat: v.lat, lng: v.lng }));
    map.fitBounds(bounds);

    google.maps.event.addListenerOnce(map, "idle", function() {
        if (map.getZoom() > 16) map.setZoom(16);
    });
}

function clearGeofence() {
    if (geofencePolygon) {
        geofencePolygon.setMap(null);
        geofencePolygon = null;
    }
}

function showGeofenceForVehicle(v) {
    clearGeofence();

    const coords = v.geofence_coords || [];
    if (!coords.length) return;

    const path = coords.map(pt => ({ lng: pt[0], lat: pt[1] })); // [lng,lat] => {lng,lat}
    if (path.length < 3) return;

    geofencePolygon = new google.maps.Polygon({
        paths: path,
        strokeColor: geofenceStroke,
        strokeOpacity: 0.9,
        strokeWeight: 2,
        fillColor: geofenceStroke,
        fillOpacity: 0.15,
        map
    });
}

function badgeHtml(label, value) {
    let bg = '#eee', fg = '#333';

    if (label === 'GPS') {
        if (value === 'ONLINE') { bg = '#DCFCE7'; fg = '#166534'; }
        else if (value === 'OFFLINE') { bg = '#FEE2E2'; fg = '#991B1B'; }
        else { bg = '#F3F4F6'; fg = '#374151'; }
    }

    if (label === 'MOTEUR') {
        if (value === 'ON') { bg = '#DCFCE7'; fg = '#166534'; }
        else if (value === 'OFF') { bg = '#FEF9C3'; fg = '#854D0E'; }
        else if (value === 'CUT') { bg = '#FEE2E2'; fg = '#991B1B'; }
        else { bg = '#F3F4F6'; fg = '#374151'; }
    }

    return `<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:${bg};color:${fg};font-weight:700;font-size:12px;">${value}</span>`;
}

function buildInfoContent(v) {
    return `
        <div style="font-size:14px;line-height:1.4;min-width:220px">
            <div style="font-weight:800;margin-bottom:6px;">${v.model} (${v.immat})</div>
            <div>GPS : ${badgeHtml('GPS', v.gps_state)}</div>
            <div>Moteur : ${badgeHtml('MOTEUR', v.engine_state)}</div>
        </div>
    `;
}

function focusVehicle(vehicleId) {
    const v = vehiclesData.find(x => x.id === vehicleId);
    const marker = markersById[vehicleId];
    if (!v || !marker) return;

    selectedVehicleId = vehicleId;

    map.panTo(marker.getPosition());
    if (map.getZoom() < 15) map.setZoom(15);

    showGeofenceForVehicle(v);

    infoWindow.setContent(buildInfoContent(v));
    infoWindow.open(map, marker);

    const titleEl = document.getElementById('map-title');
    if (titleEl) titleEl.textContent = `Suivi : ${v.model} (${v.immat})`;

    const showAllBtn = document.getElementById('showAllVehiclesBtn');
    if (showAllBtn) showAllBtn.classList.remove('hidden');
}

// Localiser depuis le tableau
window.zoomToVehicle = function(vehicleId) {
    focusVehicle(vehicleId);
};

document.addEventListener('DOMContentLoaded', function() {

    // Bouton "Afficher tous"
    const showAllBtn = document.getElementById('showAllVehiclesBtn');
    if (showAllBtn) {
        showAllBtn.addEventListener('click', function() {
            selectedVehicleId = null;
            clearGeofence();
            if (infoWindow) infoWindow.close();

            fitAllVehicles();

            const titleEl = document.getElementById('map-title');
            if (titleEl) titleEl.textContent = 'Carte de Suivi : Tous Mes Véhicules';

            this.classList.add('hidden');
        });
    }

    /* =========================
       MODALE IMAGE (profil/voiture)
    ========================== */
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const closeImageModalBtn = document.getElementById('closeImageModalBtn');

    window.openImageModal = function(url) {
        if (!url) return;
        modalImage.src = url;
        imageModal.classList.remove('hidden');
        imageModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    };

    function closeImageModal() {
        imageModal.classList.add('hidden');
        imageModal.classList.remove('flex');
        document.body.style.overflow = '';
        modalImage.src = '';
    }

    closeImageModalBtn && closeImageModalBtn.addEventListener('click', closeImageModal);
    imageModal && imageModal.addEventListener('click', (e) => {
        if (e.target === imageModal) closeImageModal();
    });

    /* =========================
       MODALE EDIT PROFIL
    ========================== */
    const profileEditModal = document.getElementById('profileEditModal');
    const openProfileEditModalBtn = document.getElementById('openProfileEditModalBtn');
    const closeProfileEditModalBtn = document.getElementById('closeProfileEditModalBtn');
    const cancelProfileEditBtn = document.getElementById('cancelProfileEditBtn');

    const editPhotoInput = document.getElementById('edit_photo');
    const editFileName = document.getElementById('edit_file_name');
    const editPreview = document.getElementById('edit_preview');

    function openProfileModal() {
        profileEditModal.classList.remove('hidden');
        profileEditModal.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
        setTimeout(() => profileEditModal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
    }

    function closeProfileModal() {
        profileEditModal.firstElementChild.classList.add('scale-95', 'opacity-0');
        document.body.style.overflow = '';
        setTimeout(() => {
            profileEditModal.classList.add('hidden');
            profileEditModal.classList.remove('opacity-100');
        }, 200);
    }

    openProfileEditModalBtn && openProfileEditModalBtn.addEventListener('click', openProfileModal);
    closeProfileEditModalBtn && closeProfileEditModalBtn.addEventListener('click', closeProfileModal);
    cancelProfileEditBtn && cancelProfileEditBtn.addEventListener('click', closeProfileModal);

    profileEditModal && profileEditModal.addEventListener('click', (e) => {
        if (e.target === profileEditModal) closeProfileModal();
    });

    editPhotoInput && editPhotoInput.addEventListener('change', function() {
        const file = this.files && this.files[0];
        if (!file) {
            editFileName.textContent = 'Aucun fichier sélectionné';
            editPreview.classList.add('hidden');
            editPreview.src = '#';
            return;
        }

        editFileName.textContent = 'Fichier : ' + file.name;

        const reader = new FileReader();
        reader.onload = (e) => {
            editPreview.src = e.target.result;
            editPreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    /* =========================
       MODALE PARAMÈTRES D’ALERTES (pré-remplissage)
    ========================== */
    const alertConfigModal = document.getElementById('alertConfigModal');
    const closeAlertConfigModalBtn = document.getElementById('closeAlertConfigModalBtn');
    const cancelAlertConfigBtn = document.getElementById('cancelAlertConfigBtn');
    const alertConfigForm = document.getElementById('vehicle-alerts-form');
    const alertConfigVehicleLbl = document.getElementById('alertConfigVehicleLabel');
    const selectedVehiclesContainer = document.getElementById('selectedVehiclesContainer');

    const currentTzStartEl = document.getElementById('current_time_zone_start');
    const currentTzEndEl   = document.getElementById('current_time_zone_end');
    const currentSpeedEl   = document.getElementById('current_speed_zone');

    if (alertConfigForm) {
        const scopeInputs = alertConfigForm.querySelectorAll('input[name="apply_scope"]');

        const selectedVehicleCheckboxes = selectedVehiclesContainer
            ? selectedVehiclesContainer.querySelectorAll('input[name="selected_vehicles[]"]')
            : [];

        // ✅ template action: users.vehicle.alerts.define
        const alertConfigActionTemplate = @json(
            route('users.vehicle.alerts.define', ['user' => $user->id, 'voiture' => '__VID__'])
        );

        window.openAlertConfigModal = function(vehicleId, label) {
            alertConfigForm.action = alertConfigActionTemplate.replace('__VID__', vehicleId);
            if (alertConfigVehicleLbl) alertConfigVehicleLbl.textContent = label || '';

            alertConfigForm.dataset.currentVehicleId = vehicleId;

            // ✅ récupérer valeurs actuelles du véhicule
            const v = vehiclesData.find(x => x.id === vehicleId);

            const tzStart = (v && v.time_zone_start) ? v.time_zone_start : '';
            const tzEnd   = (v && v.time_zone_end)   ? v.time_zone_end   : '';
            const speed   = (v && v.speed_zone !== null && v.speed_zone !== undefined) ? String(v.speed_zone) : '';

            // ✅ pré-remplir inputs
            alertConfigForm.time_zone_start.value = tzStart;
            alertConfigForm.time_zone_end.value   = tzEnd;
            alertConfigForm.speed_zone.value      = speed;

            // ✅ afficher valeurs actuelles
            if (currentTzStartEl) currentTzStartEl.textContent = tzStart || '—';
            if (currentTzEndEl)   currentTzEndEl.textContent   = tzEnd   || '—';
            if (currentSpeedEl)   currentSpeedEl.textContent   = speed   || '—';

            // scope par défaut = one
            scopeInputs.forEach(i => i.checked = (i.value === 'one'));

            if (selectedVehiclesContainer) selectedVehiclesContainer.classList.add('hidden');
            selectedVehicleCheckboxes.forEach(cb => cb.checked = false);

            alertConfigModal.classList.remove('hidden');
            alertConfigModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        };

        scopeInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (!selectedVehiclesContainer) return;

                if (this.value === 'selected') {
                    selectedVehiclesContainer.classList.remove('hidden');

                    const currentId = alertConfigForm.dataset.currentVehicleId;
                    if (currentId) {
                        selectedVehicleCheckboxes.forEach(cb => {
                            if (cb.value === String(currentId)) cb.checked = true;
                        });
                    }
                } else {
                    selectedVehiclesContainer.classList.add('hidden');
                }
            });
        });

        function closeAlertModal() {
            alertConfigModal.classList.add('hidden');
            alertConfigModal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        closeAlertConfigModalBtn && closeAlertConfigModalBtn.addEventListener('click', closeAlertModal);
        cancelAlertConfigBtn && cancelAlertConfigBtn.addEventListener('click', (e) => { e.preventDefault(); closeAlertModal(); });

        alertConfigModal && alertConfigModal.addEventListener('click', (e) => {
            if (e.target === alertConfigModal) closeAlertModal();
        });
    }
});
</script>
@endpush
