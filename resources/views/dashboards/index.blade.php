@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@section('content')
<div class="space-y-8">

    {{-- Statistiques --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Utilisateurs Actifs</p>
                <p class="text-3xl font-bold mt-1 text-primary">{{ $usersCount }}</p>
            </div>
            <div class="text-3xl text-primary opacity-70"><i class="fas fa-users"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Véhicules de la Flotte</p>
                <p class="text-3xl font-bold mt-1">{{ $vehiclesCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-car-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Associations Actives</p>
                <p class="text-3xl font-bold mt-1">{{ $associationsCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-link"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider">Alertes Non-résolues</p>
                <p class="text-3xl font-bold mt-1 text-red-500">{{ $alertsCount }}</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>

    {{-- Layout Flotte : Liste à gauche / Carte à droite --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

        {{-- 🧭 Colonne gauche : Liste des véhicules & associations --}}
        <div class="lg:col-span-1">
            <div class="ui-card h-full flex flex-col gap-3" style="padding: 1.25rem;">

                <div class="flex items-center justify-between mb-1">
                    <div>
                        <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                            Flotte & Associations
                        </h2>
                        <p class="text-[11px] text-secondary mt-0.5">
                            Cliquez sur un véhicule pour le centrer sur la carte.
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center"
                         style="background: rgba(245,130,32,0.12);">
                        <i class="fas fa-car-side text-primary text-sm"></i>
                    </div>
                </div>

                {{-- Barre de recherche --}}
                <div class="relative mb-2">
                    <span class="absolute inset-y-0 left-2 flex items-center text-secondary text-xs">
                        <i class="fas fa-search"></i>
                    </span>
                    <input
                        id="vehicleSearch"
                        type="text"
                        class="ui-input-style pl-8 text-xs"
                        placeholder="Rechercher immatriculation ou utilisateur..."
                    />
                </div>

                {{-- Légende / résumé --}}
                <div class="flex items-center justify-between text-[11px] text-secondary mb-1">
                    <span>
                        <i class="fas fa-circle text-green-400 text-[8px] mr-1"></i> Véhicules suivis
                    </span>
                    <span>{{ count($vehicles) }} véhicule(s)</span>
                </div>

                {{-- Liste scrollable --}}
                <div id="vehicleList" class="space-y-2 overflow-y-auto pr-1"
                     style="max-height: 600px;">
                    @forelse($vehicles as $v)
                        <div
                            class="vehicle-item border rounded-lg px-3 py-2.5 cursor-pointer transition-all duration-150
                                   hover:shadow-md hover:border-[var(--color-primary)]
                                   bg-[color:var(--color-card)] group"
                            data-id="{{ $v['id'] }}"
                            data-label="{{ strtolower(($v['immatriculation'] ?? '').' '.($v['users'] ?? '')) }}"
                        >
                            <div class="flex items-start gap-2">
                                {{-- Avatar / icône véhicule --}}
                                <div class="mt-0.5">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs
                                                bg-[var(--color-sidebar-active-bg)]">
                                        <i class="fas fa-car text-primary"></i>
                                    </div>
                                </div>

                                {{-- Infos principales --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold truncate" style="color: var(--color-text);">
                                        {{ $v['immatriculation'] }}
                                    </p>
                                    @if(!empty($v['marque']) || !empty($v['model']))
                                        <p class="text-[11px] text-secondary truncate">
                                            {{ $v['marque'] ?? '' }} {{ $v['model'] ?? '' }}
                                        </p>
                                    @endif
                                    <p class="text-[11px] mt-1 text-secondary line-clamp-1">
                                        <i class="fas fa-user mr-1 text-[10px]"></i>
                                        {{ $v['users'] ?: 'Aucun utilisateur associé' }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-2">
                                {{-- 🟢 Badges Moteur / GPS remplis par JS --}}
                                <span id="status-pill-{{ $v['id'] }}"
                                      class="inline-flex items-center gap-1 text-[10px] flex-wrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full
                                                 bg-gray-100 dark:bg-gray-700 text-secondary mb-1">
                                        <i class="fas fa-power-off mr-1 text-[9px]"></i> Moteur…
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full
                                                 bg-gray-100 dark:bg-gray-700 text-secondary mb-1">
                                        <i class="fas fa-satellite-dish mr-1 text-[9px]"></i> GPS…
                                    </span>
                                </span>

                                <span class="inline-flex items-center gap-1 text-[10px] text-secondary">
                                    <i class="fas fa-location-arrow text-[10px] text-primary"></i>
                                    Voir sur carte
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-secondary mt-4">
                            Aucun véhicule avec position connue.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 🗺️ Colonne droite : Carte (large) --}}
        <div class="lg:col-span-3">
            <div class="ui-card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                            Localisation de la Flotte Globale
                        </h2>
                        <p class="text-[11px] text-secondary mt-0.5">
                            Mise à jour automatique toutes les 10 secondes.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-secondary">
                        <span class="inline-flex items-center gap-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-[var(--color-primary)]"></span>
                            Véhicule
                        </span>
                    </div>
                </div>

                <div id="fleetMap"
                     class="rounded-xl shadow-inner"
                     style="height: 700px; border: 1px solid var(--color-border-subtle);">
                </div>
            </div>
        </div>
    </div>

    {{-- Tableau alertes --}}
    <div class="ui-card">
        <h2 class="text-xl font-orbitron font-bold mb-4">Historique des Dernières Alertes</h2>
        <div class="ui-table-container">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th>Type</th>
                        <th>Utilisateur</th>
                        <th>Heure</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alerts as $alert)
                        <tr>
                            <td>{{ $alert['vehicle'] }}</td>
                            <td>{{ $alert['type'] }}</td>
                            <td>{{ $alert['users'] ?? '-' }}</td>
                            <td>{{ $alert['time'] }}</td>
                            <td>
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $alert['status_color'] }}" style="color:white;">
                                    {{ $alert['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- JS Fleet + Google Maps + Statuts moteur/GPS --}}
<script>
let map;
let markersById = {};
let infoWindowsById = {};
let vehiclesData = @json($vehicles); // contient déjà engine + gps

// 1) Init carte
function initFleetMap() {
    map = new google.maps.Map(document.getElementById('fleetMap'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 7
    });

    // 1er rendu
    renderMarkers(vehiclesData, true);
    applySnapshotToUI(vehiclesData);
    initVehicleListInteractions();

    // 🔁 Mise à jour temps réel toutes les 10 secondes
    setInterval(refreshSnapshot, 10000);
}

// 2) Rendu / mise à jour markers
function renderMarkers(vehicles, fitBounds = false) {
    const bounds = new google.maps.LatLngBounds();

    vehicles.forEach(v => {
        if (v.lat == null || v.lon == null) return;

        const lat = parseFloat(v.lat);
        const lon = parseFloat(v.lon);
        const position = { lat: lat, lng: lon };

        let marker = markersById[v.id];

        if (!marker) {
            marker = new google.maps.Marker({
                position: position,
                map: map,
                title: v.immatriculation,
                icon: {
                    url: "/assets/icons/car_icon.png",
                    scaledSize: new google.maps.Size(40, 40)
                }
            });

            const infoWindow = new google.maps.InfoWindow({
                content: buildInfoWindowContent(v)
            });

            marker.addListener('click', () => infoWindow.open(map, marker));

            markersById[v.id] = marker;
            infoWindowsById[v.id] = infoWindow;
        } else {
            marker.setPosition(position);
        }

        bounds.extend(position);
    });

    if (fitBounds && vehicles.length > 0) {
        map.fitBounds(bounds);
        const listener = google.maps.event.addListener(map, "idle", function () {
            if (map.getZoom() > 14) map.setZoom(14);
            google.maps.event.removeListener(listener);
        });
    }
}

// 3) Construit l’infoWindow à partir du snapshot
function buildInfoWindowContent(vehicle) {
    const users = vehicle.users || '-';
    const engine = vehicle.engine || {};
    const gps = vehicle.gps || {};

    let engineLabel = 'Moteur…';
    let engineColor = '#6b7280';
    let gpsLabel = 'GPS…';
    let gpsColor = '#6b7280';

    if (typeof engine.cut !== 'undefined') {
        engineLabel = engine.cut ? 'Moteur coupé' : 'Moteur actif';
        engineColor = engine.cut ? '#ef4444' : '#22c55e';
    }

    if (typeof gps.online !== 'undefined') {
        if (gps.online === true) {
            gpsLabel = 'GPS en ligne';
            gpsColor = '#22c55e';
        } else if (gps.online === false) {
            gpsLabel = 'GPS hors ligne';
            gpsColor = '#9ca3af';
        }
    }

    return `
        <div style="font-size:12px;">
            <b>${vehicle.immatriculation}</b><br>
            Utilisateur(s): ${users}<br>
            <span style="display:inline-flex;align-items:center;margin-top:4px;">
                <i class="fas fa-power-off" style="margin-right:4px;color:${engineColor};"></i>
                <span style="color:${engineColor};font-weight:600;">${engineLabel}</span>
            </span><br>
            <span style="display:inline-flex;align-items:center;margin-top:2px;">
                <i class="fas fa-satellite-dish" style="margin-right:4px;color:${gpsColor};"></i>
                <span style="color:${gpsColor};font-weight:600;">${gpsLabel}</span>
            </span>
        </div>
    `;
}

// 4) Applique le snapshot sur la liste + infoWindows
function applySnapshotToUI(snapshot) {
    snapshot.forEach(v => {
        const pill = document.getElementById('status-pill-' + v.id);
        if (pill) {
            const engine = v.engine || {};
            const gps = v.gps || {};

            const engineCut = engine.cut;
            const gpsOnline = gps.online;

            const engineClass = engineCut
                ? 'bg-red-100 text-red-600'
                : 'bg-green-100 text-green-600';

            const engineText = engineCut ? 'Moteur coupé' : 'Moteur actif';

            const gpsClass = gpsOnline === true
                ? 'bg-green-100 text-green-600'
                : 'bg-gray-100 text-gray-600';

            const gpsText = gpsOnline === true
                ? 'GPS en ligne'
                : 'GPS hors ligne';

            pill.innerHTML = `
                <span class="inline-flex items-center px-2 py-0.5 rounded-full ${engineClass} mb-1">
                    <i class="fas fa-power-off mr-1 text-[9px]"></i>${engineText}
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full ${gpsClass} mb-1">
                    <i class="fas fa-satellite-dish mr-1 text-[9px]"></i>${gpsText}
                </span>
            `;
        }

        const marker = markersById[v.id];
        const infoWindow = infoWindowsById[v.id];
        if (marker && infoWindow) {
            infoWindow.setContent(buildInfoWindowContent(v));
        }
    });
}

// 5) Appel temps réel du snapshot (position + statuts)
function refreshSnapshot() {
    fetch("{{ route('fleet.snapshot') }}", {
        headers: { 'Accept': 'application/json' }
    })
        .then(res => res.json())
        .then(data => {
            vehiclesData = data;
            renderMarkers(data, false);
            applySnapshotToUI(data);
        })
        .catch(err => console.error("Erreur snapshot flotte :", err));
}

// 6) Focus véhicule depuis la liste
function focusVehicleOnMap(vehicleId) {
    const marker = markersById[vehicleId];
    if (!marker || !map) return;

    map.setCenter(marker.getPosition());
    map.setZoom(15);

    const iw = infoWindowsById[vehicleId];
    if (iw) iw.open(map, marker);
}

// 7) Interactions liste + recherche
function initVehicleListInteractions() {
    const listContainer = document.getElementById('vehicleList');
    const searchInput = document.getElementById('vehicleSearch');

    if (!listContainer || !searchInput) return;

    // Recherche texte (immat + user)
    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();

        listContainer.querySelectorAll('.vehicle-item').forEach(item => {
            const label = item.dataset.label || '';
            item.style.display = (!q || label.includes(q)) ? '' : 'none';
        });
    });

    // Clic sur véhicule → focus sur carte
    listContainer.querySelectorAll('.vehicle-item').forEach(item => {
        item.addEventListener('click', function () {
            const id = parseInt(this.dataset.id, 10);

            listContainer.querySelectorAll('.vehicle-item').forEach(i => {
                i.classList.remove('ring-2', 'ring-[var(--color-primary)]', 'bg-[var(--color-sidebar-active-bg)]');
            });

            this.classList.add('ring-2', 'ring-[var(--color-primary)]');
            focusVehicleOnMap(id);
        });
    });
}

// 8) Chargement dynamique Google Maps
function loadGoogleMaps() {
    const script = document.createElement('script');
    script.src = "https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo";
    script.async = true;
    script.defer = true;
    script.onload = () => {
        initFleetMap();
    };
    document.head.appendChild(script);
}

document.addEventListener('DOMContentLoaded', loadGoogleMaps);
</script>

@endsection
