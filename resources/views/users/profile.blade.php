@extends('layouts.app')

@section('title', 'Profile')

@push('head')
{{-- Google Maps API avec callback --}}
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async
    defer></script>
@endpush

@section('content')
<div class="space-y-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne 1 : Informations personnelles --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="ui-card p-6 flex flex-col items-center">
                <div class="relative group">
                    <img src="{{ $user->photo ?? 'https://placehold.co/120x120/F58220/ffffff?text=JD' }}" alt="Profile"
                        id="user-profile-img"
                        class="h-32 w-32 rounded-full object-cover border-4 border-primary shadow-lg mb-4 cursor-pointer transition-transform duration-200 hover:scale-105"
                        onclick="openImageModal(this.src)">
                </div>
                <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                    {{ $user->prenom }} {{ $user->nom }}
                </h2>
                <p class="text-secondary">Propriétaire de Flotte</p>
                <button class="btn-secondary mt-4 py-2 px-4 text-sm font-normal">
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
                <div class="ui-table-container shadow-md">
                    <table id="vehiclesTable" class="ui-table w-full">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Immatriculation</th>
                                <th>Marque/Modèle</th>
                                <th>Couleur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->voitures as $vehicle)
                            <tr id="row-{{ $vehicle->id }}">
                                <td>
                                    <div class="relative group w-10 h-10">
                                        <img src="{{ $vehicle->photo ?? 'https://placehold.co/40x40' }}"
                                            class="w-10 h-10 object-cover rounded-md cursor-pointer border border-border-subtle transition-transform duration-200 hover:scale-105"
                                            onclick="openImageModal('{{ $vehicle->photo ?? '' }}')">
                                    </div>
                                </td>
                                <td>{{ $vehicle->immatriculation }}</td>
                                <td>{{ $vehicle->marque }} / {{ $vehicle->model }}</td>
                                <td>
                                    <div class="w-6 h-6 rounded" style="background-color: {{ $vehicle->couleur }};">
                                    </div>
                                </td>
                                <td class="space-x-2 whitespace-nowrap">
                                    {{-- Localiser sur la carte --}}
                                    <button type="button" onclick="zoomToVehicle({{ $vehicle->id }})"
                                        class="text-primary hover:text-primary-dark transition-colors p-1"
                                        title="Localiser sur la carte">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </button>

                                    {{-- Historique (placeholder) --}}
                                    <button type="button"
                                        class="text-secondary hover:text-red-500 transition-colors p-1"
                                        title="Historique des trajets">
                                        <i class="fas fa-history"></i>
                                    </button>

                                    {{-- Paramètres d’alertes (TimeZone / SpeedZone) --}}
                                    <button type="button"
                                        class="text-secondary hover:text-yellow-500 transition-colors p-1"
                                        title="Paramètres d’alertes" onclick="openAlertConfigModal(
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

{{-- Modal image --}}
<div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-75">
    <div class="relative bg-white rounded-lg shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden">
        <button id="closeModalBtn"
            class="absolute top-4 right-4 text-3xl font-bold text-white hover:text-primary transition-colors bg-gray-900 rounded-full h-10 w-10 flex items-center justify-center">
            &times;
        </button>
        <img id="modalImage" src="" alt="Image en grand plan" class="w-full h-auto object-contain max-h-[85vh] p-2">
    </div>
</div>

{{-- Modal Paramètres d’alertes (TimeZone / SpeedZone) --}}
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

        <form id="vehicle-alerts-form" method="POST"
            action="{{ route('users.vehicle.alerts.define', ['user' => $user->id, 'voiture' => $user->voitures->first()->id ?? 0]) }}">
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
                        Exemple : 22:00 pour commencer le contrôle à 22h.
                    </p>
                </div>
                <div>
                    <label for="time_zone_end" class="block text-sm font-medium text-secondary mb-1">
                        Heure de fin (TimeZone)
                    </label>
                    <input type="time" id="time_zone_end" name="time_zone_end" class="ui-input-style">
                    <p class="text-xs text-secondary mt-1">
                        Exemple : 06:00 pour terminer le contrôle à 6h.
                    </p>
                </div>
            </div>

            {{-- SpeedZone --}}
            <div class="mb-4">
                <label for="speed_zone" class="block text-sm font-medium text-secondary mb-1">
                    Vitesse maximale autorisée (SpeedZone)
                </label>
                <input type="number" step="1" min="0" id="speed_zone" name="speed_zone" class="ui-input-style"
                    placeholder="Ex: 80">
                <p class="text-xs text-secondary mt-1">
                    Laisser vide pour ne pas modifier la vitesse actuelle.
                </p>
            </div>

            {{-- Appliquer à --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-secondary mb-1">
                    Appliquer à
                </label>
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

                {{-- Liste des véhicules sélectionnables --}}
                <div id="selectedVehiclesContainer" class="mt-3 hidden">
                    <p class="text-xs text-secondary mb-2">
                        Choisissez un ou plusieurs véhicules de cet utilisateur :
                    </p>
                    <div class="max-h-40 overflow-y-auto space-y-1 border border-border-subtle rounded-md p-2">
                        @foreach($user->voitures as $v)
                        <label class="flex items-center gap-2 text-xs text-secondary">
                            <input type="checkbox" name="selected_vehicles[]" value="{{ $v->id }}"
                                class="form-checkbox">
                            <span>
                                {{ $v->immatriculation }} — {{ $v->marque }} {{ $v->model }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
                <button type="button" id="cancelAlertConfigBtn" class="btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i>
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>
@endsection


@push('scripts')
<script>
// ===== Données des véhicules =====
let map;
let markers = [];

const vehiclesData = [
    @foreach($user->voitures as $vehicle)
    {
        id: {{ $vehicle->id }},
        immat: @json($vehicle->immatriculation),
        model: @json($vehicle->marque.' '.$vehicle->model),
        lat: {{ $vehicle->latestLocation->latitude ?? 0 }},
        lng: {{ $vehicle->latestLocation->longitude ?? 0 }},
        status: @json($vehicle->latestLocation->status ?? 'Arrêté'),
        photo_url: @json($vehicle->photo ?? 'https://placehold.co/600x400'),
    },
    @endforeach
];

// ===== Google Maps (callback initMap appelé par le script Google) =====
function initMap() {
    map = new google.maps.Map(document.getElementById('userMap'), {
        center: { lat: 4.05, lng: 9.7 },
        zoom: 11
    });
    displayVehiclesOnMap(vehiclesData, true);
}

function getCarIcon(status) {
    const color = status === 'Alerte'
        ? 'red'
        : (status === 'En Mouvement' ? 'green' : 'yellow');

    return {
        url: '/assets/icons/car_icon_' + color + '.png',
        scaledSize: new google.maps.Size(75, 75)
    };
}

function displayVehiclesOnMap(data, zoomToFit = false) {
    markers.forEach(m => m.setMap(null));
    markers = [];

    data.forEach(v => {
        const marker = new google.maps.Marker({
            position: { lat: v.lat, lng: v.lng },
            map: map,
            title: `${v.model} (${v.immat})`,
            icon: getCarIcon(v.status)
        });

        const infowindow = new google.maps.InfoWindow({
            content: `
                <div style="font-size:14px;">
                    <b>${v.model} (${v.immat})</b><br>
                    Statut: ${v.status}<br>
                    <a href="#" onclick="zoomToVehicle(${v.id});return false;" style="color:blue; text-decoration:underline;">Localiser</a>
                </div>`
        });

        marker.addListener('click', () => infowindow.open(map, marker));
        markers.push(marker);
    });

    if (zoomToFit && data.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        data.forEach(v => bounds.extend({ lat: v.lat, lng: v.lng }));
        map.fitBounds(bounds);
        const listener = google.maps.event.addListener(map, "idle", function() {
            if (map.getZoom() > 16) map.setZoom(16);
            google.maps.event.removeListener(listener);
        });
    }
}

function zoomToVehicle(vehicleId) {
    const v = vehiclesData.find(x => x.id === vehicleId);
    if (v) {
        displayVehiclesOnMap([v], true);
        const titleEl = document.getElementById('map-title');
        if (titleEl) {
            titleEl.textContent = `Suivi en Direct : ${v.model} (${v.immat})`;
        }
        const showAllBtn = document.getElementById('showAllVehiclesBtn');
        if (showAllBtn) {
            showAllBtn.classList.remove('hidden');
        }
    }
}

// ===== Modals & interactions =====
document.addEventListener('DOMContentLoaded', function() {
    // Bouton "Afficher tous"
    const showAllBtn = document.getElementById('showAllVehiclesBtn');
    if (showAllBtn) {
        showAllBtn.addEventListener('click', function() {
            displayVehiclesOnMap(vehiclesData, true);
            const titleEl = document.getElementById('map-title');
            if (titleEl) {
                titleEl.textContent = 'Carte de Suivi : Tous Mes Véhicules';
            }
            this.classList.add('hidden');
        });
    }

    // --- Modal image ---
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const closeModalBtn = document.getElementById('closeModalBtn');

    window.openImageModal = function(url) {
        if (!url) return;
        modalImage.src = url;
        imageModal.classList.remove('hidden');
        imageModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    };

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            imageModal.classList.add('hidden');
            imageModal.classList.remove('flex');
            document.body.style.overflow = '';
        });
    }

    if (imageModal) {
        imageModal.addEventListener('click', (e) => {
            if (e.target.id === 'imageModal') {
                closeModalBtn.click();
            }
        });
    }

    // --- Modal Paramètres d’alertes ---
    const alertConfigModal = document.getElementById('alertConfigModal');
    const closeAlertConfigModalBtn = document.getElementById('closeAlertConfigModalBtn');
    const cancelAlertConfigBtn = document.getElementById('cancelAlertConfigBtn');
    const alertConfigForm = document.getElementById('vehicle-alerts-form'); // ✅ ID corrigé
    const alertConfigVehicleLbl = document.getElementById('alertConfigVehicleLabel');
    const selectedVehiclesContainer = document.getElementById('selectedVehiclesContainer');

    if (alertConfigForm) {
        const scopeInputs = alertConfigForm.querySelectorAll('input[name="apply_scope"]');
        const selectedVehicleCheckboxes = selectedVehiclesContainer
            ? selectedVehiclesContainer.querySelectorAll('input[name="selected_vehicles[]"]')
            : [];

        // URL template avec placeholder pour l'ID véhicule
        const alertConfigActionTemplate = @json(
            route('users.vehicle.alerts.define', ['user' => $user->id, 'voiture' => '__VID__'])
        );

        // Fonction globale appelée par le bouton "paramètres"
        window.openAlertConfigModal = function(vehicleId, label) {
            // Remplace __VID__ par l'ID réel
            const action = alertConfigActionTemplate.replace('__VID__', vehicleId);
            alertConfigForm.action = action;

            if (alertConfigVehicleLbl) {
                alertConfigVehicleLbl.textContent = label;
            }

            // Sauvegarder l'ID du véhicule courant sur le form
            alertConfigForm.dataset.currentVehicleId = vehicleId;

            // Reset des champs
            alertConfigForm.time_zone_start.value = '';
            alertConfigForm.time_zone_end.value = '';
            alertConfigForm.speed_zone.value = '';

            // Scope par défaut : ce véhicule uniquement
            scopeInputs.forEach(i => {
                i.checked = (i.value === 'one');
            });

            // Masquer la liste de véhicules sélectionnés
            if (selectedVehiclesContainer) {
                selectedVehiclesContainer.classList.add('hidden');
            }

            // Décocher tous les véhicules
            selectedVehicleCheckboxes.forEach(cb => cb.checked = false);

            alertConfigModal.classList.remove('hidden');
            alertConfigModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        };

        // Gestion du changement de scope
        scopeInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (!selectedVehiclesContainer) return;

                if (this.value === 'selected') {
                    selectedVehiclesContainer.classList.remove('hidden');

                    // Pré-cocher le véhicule courant
                    const currentId = alertConfigForm.dataset.currentVehicleId;
                    if (currentId) {
                        selectedVehicleCheckboxes.forEach(cb => {
                            if (cb.value === String(currentId)) {
                                cb.checked = true;
                            }
                        });
                    }
                } else {
                    selectedVehiclesContainer.classList.add('hidden');
                }
            });
        });

        // Fermeture de la modale
        function closeAlertModal() {
            alertConfigModal.classList.add('hidden');
            alertConfigModal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        if (closeAlertConfigModalBtn) {
            closeAlertConfigModalBtn.addEventListener('click', closeAlertModal);
        }

        if (cancelAlertConfigBtn) {
            cancelAlertConfigBtn.addEventListener('click', (e) => {
                e.preventDefault();
                closeAlertModal();
            });
        }

        if (alertConfigModal) {
            alertConfigModal.addEventListener('click', (e) => {
                if (e.target === alertConfigModal) {
                    closeAlertModal();
                }
            });
        }
    }
});
</script>
@endpush

