@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@section('content')
<div class="space-y-8">

    {{-- Statistiques principales --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Utilisateurs</p>
                <p class="text-3xl font-bold mt-1 text-primary" id="stat-users">{{ $usersCount }}</p>
            </div>
            <div class="text-3xl text-primary opacity-70"><i class="fas fa-users"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Véhicules</p>
                <p class="text-3xl font-bold mt-1" id="stat-vehicles">{{ $vehiclesCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-car-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Associations</p>
                <p class="text-3xl font-bold mt-1" id="stat-associations">{{ $associationsCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-link"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider">Alertes Non-résolues</p>
                <p class="text-3xl font-bold mt-1 text-red-500" id="stat-alerts">{{ $alertsCount }}</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>

    {{-- ✅ CONFIG SIMPLE DES ALERTES (MODIFIABLE ICI) --}}
    @php
        /**
         * Tu modifies ici:
         * - label
         * - icon
         * - accent (couleur)
         * - importance (badge)
         */
        $ALERT_TYPES = [
            'stolen'       => ['label' => 'Vol',             'icon' => 'fa-mask',            'accent' => 'bg-red-100 text-red-700',     'badge' => 'bg-red-500'],
            'low_battery'  => ['label' => 'Batterie Faible', 'icon' => 'fa-battery-quarter', 'accent' => 'bg-orange-100 text-orange-700','badge' => 'bg-orange-500'],
            'geofence'     => ['label' => 'Geofence',        'icon' => 'fa-draw-polygon',    'accent' => 'bg-yellow-100 text-yellow-800','badge' => 'bg-yellow-500'],
            'safe_zone'    => ['label' => 'Safe Zone',       'icon' => 'fa-shield-alt',      'accent' => 'bg-blue-100 text-blue-700',   'badge' => 'bg-blue-500'],
            'speed'        => ['label' => 'Vitesse',         'icon' => 'fa-tachometer-alt',  'accent' => 'bg-purple-100 text-purple-700','badge' => 'bg-purple-500'],
            'offline'      => ['label' => 'Offline',         'icon' => 'fa-clock',           'accent' => 'bg-gray-100 text-gray-700',   'badge' => 'bg-gray-500'],
            'time_zone'    => ['label' => 'Time Zone',       'icon' => 'fa-calendar-alt',    'accent' => 'bg-indigo-100 text-indigo-700','badge' => 'bg-indigo-500'],
        ];
    @endphp

    {{-- Stats alertes par type (SSE) --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
        @foreach($ALERT_TYPES as $k => $meta)
            <div class="ui-card p-3 flex items-center justify-between relative overflow-hidden">
                {{-- bande couleur (importance) --}}
                <span class="absolute left-0 top-0 h-full w-1 {{ $meta['badge'] }}"></span>

                <div class="pl-2">
                    <p class="text-[10px] font-semibold text-secondary uppercase tracking-wider">{{ $meta['label'] }}</p>
                    <p class="text-xl font-bold mt-1" id="stat-alert-{{ $k }}">0</p>
                </div>
                <div class="text-xl opacity-60">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $meta['accent'] }}">
                        <i class="fas {{ $meta['icon'] }}"></i>
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Layout Flotte : Liste à gauche / Carte à droite --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

        {{-- Colonne gauche : Liste des véhicules & associations --}}
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

                <div class="flex items-center justify-between text-[11px] text-secondary mb-1">
                    <span><i class="fas fa-circle text-green-400 text-[8px] mr-1"></i> Véhicules suivis</span>
                    <span id="fleet-count">0 véhicule(s)</span>
                </div>

                {{-- Liste construite en JS --}}
                <div id="vehicleList" class="space-y-2 overflow-y-auto pr-1" style="max-height: 600px;">
                    <p class="text-sm text-secondary mt-4">Chargement de la flotte…</p>
                </div>
            </div>
        </div>

        {{-- Carte --}}
        <div class="lg:col-span-3">
            <div class="ui-card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                            Localisation de la Flotte Globale
                        </h2>
                        <p class="text-[11px] text-secondary mt-0.5">
                            Mise à jour via SSE (event: dashboard).
                        </p>
                    </div>

                    <div class="flex items-center gap-3 text-[11px] text-secondary">
                        <span id="sse-indicator" class="inline-flex items-center gap-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                            <span id="sse-label">Temps réel</span>
                        </span>
                        <span id="last-update" class="text-[11px] text-secondary"></span>
                    </div>
                </div>

                <div id="fleetMap"
                     class="rounded-xl shadow-inner"
                     style="height: 700px; border: 1px solid var(--color-border-subtle);"></div>
            </div>
        </div>
    </div>

</div>

<script>
let map;
let markersById = {};
let infoWindowsById = {};
let selectedVehicleId = null;
let dashboardSSE = null;

// ✅ data initiale (peut être vide)
let vehiclesData = @json($vehicles ?? []);

// ✅ URL template "Voir les trajets"
const TRAJETS_URL_TEMPLATE = "{{ route('trajets.index', ['vehicle_id' => '__ID__']) }}";
function trajetsUrl(id) {
    return TRAJETS_URL_TEMPLATE.replace('__ID__', encodeURIComponent(String(id)));
}

// ✅ config alertes (si tu veux aussi piloter côté JS, tout est centralisé)
const ALERT_META = @json($ALERT_TYPES);

function initFleetMap() {
    map = new google.maps.Map(document.getElementById('fleetMap'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 7
    });

    renderVehicleList(vehiclesData);
    renderMarkers(vehiclesData, true);
    initVehicleSearch();

    startDashboardSSE();
}

function startDashboardSSE() {
    const url = "{{ route('dashboard.stream') }}";
    dashboardSSE = new EventSource(url, { withCredentials: true });
    setSseIndicator('connecting');

    dashboardSSE.addEventListener('hello', () => setSseIndicator('connected'));

    dashboardSSE.addEventListener('dashboard', (e) => {
        setSseIndicator('connected');

        let payload = null;
        try { payload = JSON.parse(e.data); }
        catch (err) { console.error('SSE JSON parse error', err, e.data); return; }

        if (payload.ts) {
            const lu = document.getElementById('last-update');
            if (lu) lu.textContent = `Maj: ${payload.ts}`;
        }

        // 1) STATS GLOBAL
        if (payload.stats) {
            applyStats(payload.stats);

            const byTypeFromStats = payload.stats.alertsByType || payload.stats.alerts_by_type || null;
            if (byTypeFromStats) applyAlertTypeStats(byTypeFromStats);
        }

        // 2) ALERT SUMMARY (recommandé)
        if (payload.alerts_summary && payload.alerts_summary.by_type) {
            applyAlertTypeStats(payload.alerts_summary.by_type);

            if (typeof payload.alerts_summary.total !== 'undefined') {
                const el = document.getElementById('stat-alerts');
                if (el) el.textContent = String(payload.alerts_summary.total);
            }
        }

        // 3) FLEET (liste + map)
        const fleet = Array.isArray(payload.fleet) ? payload.fleet : [];
        vehiclesData = fleet;

        renderVehicleList(fleet);
        renderMarkers(fleet, false);
        updateStatusPills(fleet);
        updateSelectedInfoWindow(fleet);
    });

    dashboardSSE.onerror = () => setSseIndicator('reconnecting');

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && dashboardSSE) {
            dashboardSSE.close();
            dashboardSSE = null;
            setSseIndicator('paused');
        } else if (!document.hidden && !dashboardSSE) {
            startDashboardSSE();
        }
    });

    window.addEventListener('beforeunload', () => dashboardSSE && dashboardSSE.close());
}

function setSseIndicator(state) {
    const dot = document.querySelector('#sse-indicator span');
    const label = document.getElementById('sse-label');
    if (!dot || !label) return;

    const set = (cls, txt) => {
        dot.className = `inline-block w-2 h-2 rounded-full ${cls}`;
        label.textContent = txt;
    };

    if (state === 'connected') set('bg-green-500', 'Connecté');
    else if (state === 'connecting') set('bg-yellow-500', 'Connexion…');
    else if (state === 'reconnecting') set('bg-orange-500', 'Reconnexion…');
    else if (state === 'paused') set('bg-gray-400', 'En pause');
    else set('bg-gray-400', 'Temps réel');
}

function applyStats(stats) {
    const set = (id, v) => {
        const el = document.getElementById(id);
        if (el && v !== undefined && v !== null) el.textContent = String(v);
    };
    set('stat-users', stats.usersCount);
    set('stat-vehicles', stats.vehiclesCount);
    set('stat-associations', stats.associationsCount);

    if (stats.alertsCount !== undefined && stats.alertsCount !== null) {
        set('stat-alerts', stats.alertsCount);
    }
}

function applyAlertTypeStats(obj) {
    // ✅ IMPORTANT: on parcourt la config pour inclure offline et tout nouveau type
    Object.keys(ALERT_META || {}).forEach(k => {
        const el = document.getElementById('stat-alert-' + k);
        if (!el) return;
        el.textContent = (obj && obj[k] !== undefined && obj[k] !== null) ? String(obj[k]) : '0';
    });
}

function renderVehicleList(fleet) {
    const list = document.getElementById('vehicleList');
    if (!list) return;

    const fleetCount = document.getElementById('fleet-count');
    if (fleetCount) fleetCount.textContent = `${fleet.length} véhicule(s)`;

    if (!fleet.length) {
        list.innerHTML = `<p class="text-sm text-secondary mt-4">Aucun véhicule avec position connue.</p>`;
        return;
    }

    list.innerHTML = fleet.map(v => buildVehicleItemHtml(v)).join('');

    // bind click (focus map)
    list.querySelectorAll('.vehicle-item').forEach(item => {
        item.addEventListener('click', function () {
            const id = parseInt(this.dataset.id, 10);

            list.querySelectorAll('.vehicle-item').forEach(i => {
                i.classList.remove('ring-2', 'ring-[var(--color-primary)]');
            });

            this.classList.add('ring-2', 'ring-[var(--color-primary)]');
            focusVehicleOnMap(id);
        });
    });

    // refresh search filter with current query
    const searchInput = document.getElementById('vehicleSearch');
    if (searchInput) {
        const q = searchInput.value.toLowerCase().trim();
        if (q) applyVehicleFilter(q);
    }

    updateStatusPills(fleet);
}

function buildVehicleItemHtml(v) {
    const id = v.id;
    const immat = escapeHtml(v.immatriculation ?? '—');
    const brand = escapeHtml(`${v.marque ?? ''} ${v.model ?? ''}`.trim());
    const usersTxt = escapeHtml(v.users ? v.users : 'Aucun utilisateur associé');

    const profile = v.user_profile_url
        ? `<a href="${v.user_profile_url}"
              class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200 p-2"
              title="Voir les détails utilisateur"
              onclick="event.stopPropagation();"><i class="fas fa-eye"></i></a>`
        : `<button type="button"
                  class="text-gray-400 p-2 cursor-not-allowed"
                  title="Aucun utilisateur associé"
                  onclick="event.stopPropagation();"><i class="fas fa-eye-slash"></i></button>`;

    const label = `${(v.immatriculation ?? '')} ${(v.users ?? '')}`.toLowerCase();

    // ✅ lien trajets
    const trajets = `
        <a href="${trajetsUrl(id)}"
           class="inline-flex items-center gap-1 text-[10px] text-secondary hover:text-primary"
           onclick="event.stopPropagation();"
           title="Voir les trajets">
            <i class="fas fa-route text-[10px] text-primary"></i> Voir les trajets
        </a>
    `;

    return `
        <div class="vehicle-item border rounded-lg px-3 py-2.5 cursor-pointer transition-all duration-150
                    hover:shadow-md hover:border-[var(--color-primary)]
                    bg-[color:var(--color-card)]"
             id="vehicle-item-${id}"
             data-id="${id}"
             data-label="${escapeHtml(label)}">

            <div class="flex items-start gap-2">
                <div class="mt-0.5">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs
                                bg-[var(--color-sidebar-active-bg)]">
                        <i class="fas fa-car text-primary"></i>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold truncate" id="v-immat-${id}" style="color: var(--color-text);">${immat}</p>
                    <p class="text-[11px] text-secondary truncate" id="v-brand-${id}">${brand}</p>

                    <p class="text-[11px] mt-1 text-secondary line-clamp-1" id="v-users-${id}">
                        <i class="fas fa-user mr-1 text-[10px]"></i>${usersTxt}
                    </p>
                </div>

                <div class="shrink-0" id="v-profile-${id}">
                    ${profile}
                </div>
            </div>

            <div class="flex items-center justify-between mt-2">
                <span id="status-pill-${id}" class="inline-flex items-center gap-1 text-[10px] flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-secondary mb-1">
                        <i class="fas fa-power-off mr-1 text-[9px]"></i> Moteur…
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-secondary mb-1">
                        <i class="fas fa-satellite-dish mr-1 text-[9px]"></i> GPS…
                    </span>
                </span>

                ${trajets}
            </div>
        </div>
    `;
}

function initVehicleSearch() {
    const searchInput = document.getElementById('vehicleSearch');
    if (!searchInput) return;
    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        applyVehicleFilter(q);
    });
}

function applyVehicleFilter(q) {
    const list = document.getElementById('vehicleList');
    if (!list) return;
    list.querySelectorAll('.vehicle-item').forEach(item => {
        const label = (item.dataset.label || '').toLowerCase();
        item.style.display = (!q || label.includes(q)) ? '' : 'none';
    });
}

function renderMarkers(vehicles, fitBounds = false) {
    if (!map) return;

    const bounds = new google.maps.LatLngBounds();
    const newIds = new Set();

    vehicles.forEach(v => {
        if (v.lat == null || v.lon == null) return;

        const id = v.id;
        newIds.add(String(id));
        const position = { lat: parseFloat(v.lat), lng: parseFloat(v.lon) };

        let marker = markersById[id];
        if (!marker) {
            marker = new google.maps.Marker({
                position,
                map,
                title: v.immatriculation ?? '',
                icon: { url: "/assets/icons/car_icon.png", scaledSize: new google.maps.Size(40, 40) }
            });

            const infoWindow = new google.maps.InfoWindow({ content: buildInfoWindowContent(v) });

            marker.addListener('click', () => {
                selectedVehicleId = id;
                infoWindow.open(map, marker);
            });

            markersById[id] = marker;
            infoWindowsById[id] = infoWindow;
        } else {
            marker.setPosition(position);
        }

        bounds.extend(position);
    });

    // cleanup markers removed
    Object.keys(markersById).forEach(id => {
        if (!newIds.has(String(id))) {
            markersById[id].setMap(null);
            delete markersById[id];
            delete infoWindowsById[id];
        }
    });

    if (fitBounds && vehicles.length > 0) {
        map.fitBounds(bounds);
        const listener = google.maps.event.addListener(map, "idle", function () {
            if (map.getZoom() > 14) map.setZoom(14);
            google.maps.event.removeListener(listener);
        });
    }
}

function updateStatusPills(fleet) {
    fleet.forEach(v => {
        const pill = document.getElementById('status-pill-' + v.id);
        if (!pill) return;

        const engine = v.engine || {};
        const gps = v.gps || {};

        const engineCut = engine.cut;
        const gpsOnline = gps.online;

        const engineClass = engineCut ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600';
        const engineText  = engineCut ? 'Moteur coupé' : 'Moteur actif';

        const gpsClass = gpsOnline === true ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-600';
        const gpsText  = gpsOnline === true ? 'GPS en ligne' : 'GPS hors ligne';

        pill.innerHTML = `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full ${engineClass} mb-1">
                <i class="fas fa-power-off mr-1 text-[9px]"></i>${engineText}
            </span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full ${gpsClass} mb-1">
                <i class="fas fa-satellite-dish mr-1 text-[9px]"></i>${gpsText}
            </span>
        `;
    });
}

function updateSelectedInfoWindow(fleet) {
    if (selectedVehicleId == null) return;
    const v = fleet.find(x => String(x.id) === String(selectedVehicleId));
    if (!v) return;
    const iw = infoWindowsById[selectedVehicleId];
    const marker = markersById[selectedVehicleId];
    if (iw && marker) iw.setContent(buildInfoWindowContent(v));
}

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
        if (gps.online === true) { gpsLabel = 'GPS en ligne'; gpsColor = '#22c55e'; }
        else if (gps.online === false) { gpsLabel = 'GPS hors ligne'; gpsColor = '#9ca3af'; }
    }

    const profileLink = vehicle.user_profile_url
        ? `<div style="margin-top:6px;">
             <a href="${vehicle.user_profile_url}" style="color:#3b82f6;text-decoration:underline;">Voir profil utilisateur</a>
           </div>`
        : '';

    const trajetsLink = `
        <div style="margin-top:6px;">
            <a href="${trajetsUrl(vehicle.id)}" style="color:#F58220;text-decoration:underline;">
                Voir les trajets
            </a>
        </div>
    `;

    return `
        <div style="font-size:12px;min-width:220px;">
            <b>${escapeHtml(vehicle.immatriculation)}</b><br>
            Utilisateur(s): ${escapeHtml(users)}<br>

            <div style="margin-top:6px;">
                <span style="display:inline-flex;align-items:center;margin-right:10px;">
                    <i class="fas fa-power-off" style="margin-right:4px;color:${engineColor};"></i>
                    <span style="color:${engineColor};font-weight:600;">${engineLabel}</span>
                </span>
                <span style="display:inline-flex;align-items:center;">
                    <i class="fas fa-satellite-dish" style="margin-right:4px;color:${gpsColor};"></i>
                    <span style="color:${gpsColor};font-weight:600;">${gpsLabel}</span>
                </span>
            </div>

            ${trajetsLink}
            ${profileLink}
        </div>
    `;
}

function focusVehicleOnMap(vehicleId) {
    const marker = markersById[vehicleId];
    if (!marker || !map) return;

    map.setCenter(marker.getPosition());
    map.setZoom(15);

    selectedVehicleId = vehicleId;
    const iw = infoWindowsById[vehicleId];
    if (iw) iw.open(map, marker);
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (m) => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[m]));
}

function loadGoogleMaps() {
    const script = document.createElement('script');
    script.src = "https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo";
    script.async = true;
    script.defer = true;
    script.onload = () => initFleetMap();
    document.head.appendChild(script);
}

document.addEventListener('DOMContentLoaded', loadGoogleMaps);
</script>
@endsection
