{{-- resources/views/dashboards/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@section('content')
<div class="space-y-8">

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
        'stolen'      => ['label' => 'Vol',             'icon' => 'fa-mask',            'accent' => 'bg-red-100 text-red-700',       'badge' => 'bg-red-500'],
        'low_battery' => ['label' => 'Batterie Faible', 'icon' => 'fa-battery-quarter','accent' => 'bg-orange-100 text-orange-700',  'badge' => 'bg-orange-500'],
        'geofence'    => ['label' => 'Geofence',        'icon' => 'fa-draw-polygon',    'accent' => 'bg-yellow-100 text-yellow-800', 'badge' => 'bg-yellow-500'],
        'safe_zone'   => ['label' => 'Safe Zone',       'icon' => 'fa-shield-alt',      'accent' => 'bg-blue-100 text-blue-700',     'badge' => 'bg-blue-500'],
        'speed'       => ['label' => 'Vitesse',         'icon' => 'fa-tachometer-alt',  'accent' => 'bg-purple-100 text-purple-700', 'badge' => 'bg-purple-500'],
        'offline'     => ['label' => 'Offline',         'icon' => 'fa-clock',           'accent' => 'bg-gray-100 text-gray-700',     'badge' => 'bg-gray-500'],
        'time_zone'   => ['label' => 'Time Zone',       'icon' => 'fa-calendar-alt',    'accent' => 'bg-indigo-100 text-indigo-700', 'badge' => 'bg-indigo-500'],
    ];

    // ✅ OPTIONAL: route AJAX pour retrouver l’utilisateur du véhicule
    // Crée une route Laravel qui renvoie un JSON:
    // GET vehicles/{vehicle}/callcenter  -> { user: { nom, prenom, phone } }
    // et nomme-la: vehicles.callcenter
    $CC_LOOKUP_TEMPLATE = \Illuminate\Support\Facades\Route::has('vehicles.callcenter')
        ? route('vehicles.callcenter', ['vehicle' => '__ID__'])
        : null;
    @endphp

    {{-- ✅ STATISTIQUES --}}
    <div class="dashboard-stats-sticky">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-9 gap-3">

            {{-- Véhicules --}}
            <div class="ui-card p-3 flex items-center justify-between relative overflow-hidden">
                <span class="absolute left-0 top-0 h-full w-1 bg-[var(--color-primary)]"></span>

                <div class="pl-2">
                    <p class="text-[10px] font-semibold text-secondary uppercase tracking-wider">Véhicules</p>
                    <p class="text-xl font-bold mt-1" id="stat-vehicles">{{ $vehiclesCount }}</p>
                </div>

                <div class="text-xl opacity-60">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg"
                        style="background: rgba(245,130,32,0.12); color: var(--color-primary);">
                        <i class="fas fa-car-alt"></i>
                    </span>
                </div>
            </div>

            {{-- Alertes Non-résolues --}}
            <div class="ui-card p-3 flex items-center justify-between relative overflow-hidden">
                <span class="absolute left-0 top-0 h-full w-1 bg-red-500"></span>

                <div class="pl-2">
                    <p class="text-[10px] font-semibold text-secondary uppercase tracking-wider">Alertes Non-résolues</p>
                    <p class="text-xl font-bold mt-1 text-red-500" id="stat-alerts">{{ $alertsCount }}</p>
                </div>

                <div class="text-xl opacity-60">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                </div>
            </div>

            {{-- Alertes par type --}}
            @foreach($ALERT_TYPES as $k => $meta)
                <div class="ui-card p-3 flex items-center justify-between relative overflow-hidden">
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
    </div>

    {{-- Layout Flotte : Liste à gauche / Carte à droite --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

        {{-- Colonne gauche : Liste --}}
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
                    <input id="vehicleSearch" type="text" class="ui-input-style pl-8 text-xs"
                        placeholder="Rechercher immatriculation ou utilisateur..." />
                </div>

                <div class="flex items-center justify-between text-[11px] text-secondary mb-1">
                    <span><i class="fas fa-circle text-green-400 text-[8px] mr-1"></i> Véhicules suivis</span>
                    <span id="fleet-count">0 véhicule(s)</span>
                </div>

                {{-- Liste construite en JS --}}
                <div id="vehicleList" class="space-y-2 overflow-y-auto pr-1" style="max-height: 850px;">
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

                <div id="fleetMap" class="rounded-xl shadow-inner"
                    style="height: 850px; border: 1px solid var(--color-border-subtle);"></div>
            </div>
        </div>
    </div>

</div>

{{-- ✅ Audio (effets sonores) --}}
<audio id="audio-alert-actif" preload="auto">
    <source src="{{ asset('assets/song/alert_actif.mp3') }}" type="audio/mpeg">
</audio>
<audio id="audio-alert-passif" preload="auto">
    <source src="{{ asset('assets/song/alert_passif.mp3') }}" type="audio/mpeg">
</audio>

{{-- ✅ POPUP Call-Center (top-center) --}}
<div id="cc-toast-wrap" class="fixed top-4 left-1/2 -translate-x-1/2 z-[9999] pointer-events-none"
     style="width: min(720px, calc(100vw - 1.5rem));">
</div>

<style>
.cc-toast-enter { transform: translateY(-8px); opacity: 0; }
.cc-toast-in    { transform: translateY(0);   opacity: 1; transition: all .18s ease-out; }
.cc-toast-out   { transform: translateY(-8px); opacity: 0; transition: all .18s ease-in; }
</style>

<script>
let map;
let markersById = {};
let infoWindowsById = {};
let selectedVehicleId = null;
let dashboardSSE = null;

// ✅ data initiale
let vehiclesData = @json($vehicles ?? []);

// ✅ URL template "Voir les trajets"
const TRAJETS_URL_TEMPLATE = "{{ route('trajets.index', ['vehicle_id' => '__ID__']) }}";
function trajetsUrl(id) {
    return TRAJETS_URL_TEMPLATE.replace('__ID__', encodeURIComponent(String(id)));
}

// ✅ config alertes
const ALERT_META = @json($ALERT_TYPES);

// ✅ OPTIONAL: route lookup call-center par véhicule
const CC_LOOKUP_TEMPLATE = @json($CC_LOOKUP_TEMPLATE);

// =====================
// 🔊 Effets sonores
// =====================
const PASSIF_TYPES = new Set(['speed', 'safe_zone', 'time_zone', 'offline']);

let audioUnlocked = false;
function unlockAudioOnce() {
    if (audioUnlocked) return;
    audioUnlocked = true;

    const a1 = document.getElementById('audio-alert-actif');
    const a2 = document.getElementById('audio-alert-passif');

    const tryPlay = (a) => {
        if (!a) return Promise.resolve();
        a.muted = true;
        const p = a.play();
        if (p && typeof p.then === 'function') {
            return p.then(() => { a.pause(); a.currentTime = 0; a.muted = false; }).catch(() => {});
        }
        return Promise.resolve();
    };

    tryPlay(a1).finally(() => tryPlay(a2)).finally(() => {
        if (a1) a1.muted = false;
        if (a2) a2.muted = false;
    });
}
['click','touchstart','keydown'].forEach(evt => {
    window.addEventListener(evt, unlockAudioOnce, { once: true, passive: true });
});

function playAlertSound(type) {
    if (!audioUnlocked) return;
    const t = String(type || '').toLowerCase();
    const el = document.getElementById(PASSIF_TYPES.has(t) ? 'audio-alert-passif' : 'audio-alert-actif');
    if (!el) return;
    try {
        el.currentTime = 0;
        const p = el.play();
        if (p && typeof p.catch === 'function') p.catch(() => {});
    } catch (e) {}
}

// =====================
// 📣 POPUP Call-Center
// =====================
let ccFirstSnapshot = true;         // pas de popup sur le 1er event SSE
let lastSeenAlertId = 0;            // anti-refresh: on déclenche seulement si alert.id > lastSeenAlertId
let userCacheByVehicleId = {};      // cache lookup user par vehicle_id

function ccCloseToast(el) {
    if (!el) return;
    el.classList.remove('cc-toast-in');
    el.classList.add('cc-toast-out');
    setTimeout(() => { try { el.remove(); } catch(e) {} }, 220);
}

function ccPushToast({ type, immatriculation, userDisplayName, phone, scriptText, durationMs = 26000 }) {
    const wrap = document.getElementById('cc-toast-wrap');
    if (!wrap) return;

    const meta = (ALERT_META && ALERT_META[type]) ? ALERT_META[type] : {
        label: String(type || 'Alerte'),
        badge: 'bg-red-500',
        accent: 'bg-red-100 text-red-700',
        icon: 'fa-bell'
    };

    const title = meta.label || String(type || 'Alerte');
    const badge = meta.badge || 'bg-red-500';
    const accent = meta.accent || 'bg-red-100 text-red-700';
    const icon = meta.icon || 'fa-bell';

    const safeImmat = escapeHtml(immatriculation || '—');
    const safeUser = escapeHtml(userDisplayName || 'Aucun utilisateur associé');
    const safePhone = escapeHtml(phone || '—');
    const safeScript = escapeHtml(scriptText || '');

    const el = document.createElement('div');
    el.className = `pointer-events-auto ui-card p-4 shadow-lg border border-[color:var(--color-border-subtle)] cc-toast-enter`;
    el.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="shrink-0">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl ${accent}">
                    <i class="fas ${icon}"></i>
                </span>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-white text-[10px] font-bold ${badge}">
                            ${escapeHtml(title)}
                        </span>
                        <span class="text-[11px] text-secondary truncate">
                            <b class="text-[color:var(--color-text)]">${safeImmat}</b>
                            <span class="mx-1">•</span>
                            ${safeUser}
                        </span>
                    </div>

                    <button type="button"
                            class="text-secondary hover:text-[color:var(--color-text)]"
                            style="padding:6px;border-radius:10px;"
                            title="Fermer"
                            onclick="(function(btn){ const root=btn.closest('.ui-card'); if(root){ ccCloseToast(root); } })(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div class="rounded-xl px-3 py-2 bg-[color:var(--color-sidebar-active-bg)]">
                        <div class="text-[10px] uppercase font-semibold text-secondary">Téléphone</div>
                        <div class="text-[12px] font-semibold" style="color: var(--color-text);">
                            ${safePhone}
                        </div>
                    </div>

                    <div class="md:col-span-2 rounded-xl px-3 py-2 bg-[color:var(--color-sidebar-active-bg)]">
                        <div class="text-[10px] uppercase font-semibold text-secondary">Script Call Center</div>
                        <div class="text-[12px] mt-0.5 leading-5" style="color: var(--color-text);">
                            ${safeScript}
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex items-center gap-2">
                    <button type="button"
                            class="ui-btn-primary text-[11px] px-3 py-2 rounded-xl"
                            onclick="(function(){
                                const t=${JSON.stringify((scriptText||'') + (phone ? (' (Tel: '+phone+')') : ''))};
                                if(navigator.clipboard && navigator.clipboard.writeText){
                                    navigator.clipboard.writeText(t).catch(()=>{});
                                }
                            })()">
                        <i class="fas fa-copy mr-1"></i> Copier le script
                    </button>

                    <button type="button"
                            class="text-[11px] px-3 py-2 rounded-xl border"
                            style="border-color: var(--color-border-subtle); color: var(--color-text);"
                            onclick="(function(btn){ const root=btn.closest('.ui-card'); if(root){ ccCloseToast(root); } })(this)">
                        Ok
                    </button>

                    <span class="ml-auto text-[10px] text-secondary">Auto-fermeture ~ ${Math.round(durationMs/1000)}s</span>
                </div>
            </div>
        </div>
    `;

    wrap.appendChild(el);

    requestAnimationFrame(() => {
        el.classList.remove('cc-toast-enter');
        el.classList.add('cc-toast-in');
    });

    setTimeout(() => ccCloseToast(el), durationMs);
}

function normalizeImmat(v) {
    const s = String(v || '').trim();
    return s ? s.toUpperCase() : null;
}

function buildFleetIndex(fleet) {
    const byId = {};
    const byImmat = {};
    (Array.isArray(fleet) ? fleet : []).forEach(v => {
        if (v && v.id != null) byId[String(v.id)] = v;
        const imm = normalizeImmat(v?.immatriculation);
        if (imm) byImmat[imm] = v;
    });
    return { byId, byImmat };
}

/**
 * ✅ IMPORTANT: utilisateur = trouvé "à partir du véhicule"
 * On supporte plusieurs shapes possibles dans le payload fleet:
 * - vehicle.primary_user
 * - vehicle.user
 * - vehicle.driver
 * - vehicle.chauffeur
 */
function getUserFromVehicle(vehicle) {
    if (!vehicle) return null;
    return vehicle.primary_user || vehicle.user || vehicle.driver || vehicle.chauffeur || null;
}

function formatUserName(user) {
    if (!user) return 'Aucun utilisateur associé';
    const full = `${user.prenom || ''} ${user.nom || ''}`.trim();
    return full || 'Utilisateur';
}

function extractPhoneFromUser(user) {
    const p = user?.phone;
    return p ? String(p) : '—';
}

// Lookup AJAX optionnel si le fleet ne contient pas phone
async function lookupCallCenterUser(vehicleId) {
    const vid = vehicleId != null ? String(vehicleId) : null;
    if (!vid) return null;

    if (userCacheByVehicleId[vid]) return userCacheByVehicleId[vid];

    if (!CC_LOOKUP_TEMPLATE) return null; // route pas disponible

    const url = String(CC_LOOKUP_TEMPLATE).replace('__ID__', encodeURIComponent(vid));

    try {
        const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        if (!res.ok) return null;
        const json = await res.json();
        const u = json?.user || null;
        if (u) userCacheByVehicleId[vid] = u;
        return u;
    } catch (e) {
        return null;
    }
}

function buildCallCenterScript(type, immat, userName, phone, raw) {
    const t = String(type || '').toLowerCase();
    const dir = String(raw?.direction || raw?.geo_direction || '').toLowerCase(); // enter/exit
    const zone = raw?.zone_name || raw?.geofence_name || raw?.safe_zone_name || null;
    const speed = raw?.speed || raw?.speed_kmh || null;
    const when = raw?.time || raw?.alerted_at || null;
    const whenTxt = when ? ` (à ${String(when)})` : '';

    if (t === 'geofence') {
        const action = dir === 'enter' ? 'est revenue dans sa zone' : (dir === 'exit' ? 'est sortie de sa zone' : 'a déclenché une alerte Geofence');
        return `La voiture immatriculée ${immat} associée à ${userName} ${action}${zone ? ` (zone: ${zone})` : ''}${whenTxt}. Merci d’appeler au ${phone} pour confirmer la situation et vérifier la trajectoire.`;
    }
    if (t === 'safe_zone') {
        const action = dir === 'enter' ? 'est entrée dans la zone sûre' : (dir === 'exit' ? 'est sortie de la zone sûre' : 'a déclenché une alerte Safe Zone');
        return `Alerte Safe Zone : la voiture ${immat} (${userName}) ${action}${zone ? ` (zone: ${zone})` : ''}${whenTxt}. Appelez le ${phone} pour confirmer la mission et la position actuelle.`;
    }
    if (t === 'speed') {
        const sp = speed ? `${speed} km/h` : 'une vitesse élevée';
        return `Alerte Vitesse : la voiture ${immat} associée à ${userName} roule à ${sp}${whenTxt}. Merci d’appeler au ${phone} pour demander de ralentir et confirmer les conditions de route.`;
    }
    if (t === 'offline') {
        return `Alerte Offline : le GPS de la voiture ${immat} (${userName}) ne répond plus${whenTxt}. Contactez le ${phone} pour vérifier l’état du GPS, la batterie et la couverture réseau.`;
    }
    if (t === 'time_zone') {
        return `Alerte Time Zone : la voiture ${immat} associée à ${userName} est active hors plage horaire autorisée${whenTxt}. Merci d’appeler le ${phone} pour vérifier la raison et valider l’autorisation.`;
    }
    if (t === 'low_battery') {
        return `Alerte Batterie Faible : la voiture ${immat} (${userName}) signale un niveau de batterie bas${whenTxt}. Appelez le ${phone} pour planifier un passage recharge/swap et éviter une immobilisation.`;
    }
    if (t === 'stolen') {
        return `Alerte Vol : la voiture ${immat} associée à ${userName} a déclenché une alerte critique${whenTxt}. Appelez immédiatement le ${phone}. Si non joignable, escaladez selon la procédure sécurité.`;
    }
    return `Alerte ${t} : la voiture ${immat} associée à ${userName} a déclenché une alerte${whenTxt}. Contact: ${phone}.`;
}

/**
 * ✅ Déclencheur popup/son sur NOUVELLE ALERTE (pas sur compteur)
 * On utilise payload.alerts (liste des dernières alertes) et alert.id comme vérité.
 *
 * Attendu côté SSE (idéal):
 * payload.alerts = [{ id, type, vehicle_id, immatriculation, ... }]
 */
async function handleIncomingAlerts(payload, fleet) {
    const alerts = Array.isArray(payload?.alerts) ? payload.alerts : [];
    if (!alerts.length) return;

    // 1er snapshot SSE = on init seulement (pas popup)
    if (ccFirstSnapshot) {
        const maxId = Math.max(...alerts.map(a => Number(a?.id || 0)));
        if (Number.isFinite(maxId)) lastSeenAlertId = maxId;
        ccFirstSnapshot = false;
        return;
    }

    // on prend toutes les alertes strictement nouvelles (id > lastSeenAlertId)
    const fresh = alerts
        .filter(a => Number(a?.id || 0) > Number(lastSeenAlertId || 0))
        .sort((a,b) => Number(a?.id||0) - Number(b?.id||0));

    if (!fresh.length) return;

    // on déclenche pour la plus récente (1 popup par event SSE)
    const a = fresh[fresh.length - 1];
    lastSeenAlertId = Math.max(Number(lastSeenAlertId || 0), Number(a?.id || 0));

    const type = String(a?.type || '').toLowerCase();
    if (!type) return;

    const { byId, byImmat } = buildFleetIndex(fleet);

    const aVehicleId = a?.vehicle_id ?? a?.voiture_id ?? a?.vehicle?.id ?? a?.voiture?.id ?? null;
    const aImmat = normalizeImmat(a?.immatriculation ?? a?.vehicle_immatriculation ?? a?.plate ?? a?.voiture?.immatriculation);

    let vehicle = null;
    if (aVehicleId != null && byId[String(aVehicleId)]) vehicle = byId[String(aVehicleId)];
    else if (aImmat && byImmat[aImmat]) vehicle = byImmat[aImmat];

    // si on ne retrouve pas le véhicule, on joue juste le son (pas de confusion)
    if (!vehicle) {
        playAlertSound(type);
        return;
    }

    // utilisateur depuis véhicule (pas l’inverse)
    let user = getUserFromVehicle(vehicle);

    // si pas de user/phone dans fleet, on tente le lookup AJAX (optionnel)
    if ((!user || !user.phone) && aVehicleId != null) {
        const looked = await lookupCallCenterUser(aVehicleId);
        if (looked) user = looked;
    }

    const immat = aImmat || vehicle?.immatriculation || '—';
    const userName = formatUserName(user);
    const phone = extractPhoneFromUser(user);

    // SON + POPUP ensemble
    playAlertSound(type);

    const scriptText = buildCallCenterScript(type, immat, userName, phone, a);

    ccPushToast({
        type,
        immatriculation: immat,
        userDisplayName: userName,
        phone,
        scriptText
    });
}

// =====================
// Map / SSE
// =====================

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

    dashboardSSE.addEventListener('dashboard', async (e) => {
        setSseIndicator('connected');

        let payload = null;
        try {
            payload = JSON.parse(e.data);
        } catch (err) {
            console.error('SSE JSON parse error', err, e.data);
            return;
        }

        if (payload.ts) {
            const lu = document.getElementById('last-update');
            if (lu) lu.textContent = `Maj: ${payload.ts}`;
        }

        // 1) Fleet
        const fleet = Array.isArray(payload.fleet) ? payload.fleet : [];
        vehiclesData = fleet;

        renderVehicleList(fleet);
        renderMarkers(fleet, false);
        updateStatusPills(fleet);
        updateSelectedInfoWindow(fleet);

        // 2) Stats
        if (payload.stats) {
            applyStats(payload.stats);
            const byTypeFromStats = payload.stats.alertsByType || payload.stats.alerts_by_type || null;
            if (byTypeFromStats) applyAlertTypeStats(byTypeFromStats);
        }
        if (payload.alerts_summary && payload.alerts_summary.by_type) {
            applyAlertTypeStats(payload.alerts_summary.by_type);
            if (typeof payload.alerts_summary.total !== 'undefined') {
                const el = document.getElementById('stat-alerts');
                if (el) el.textContent = String(payload.alerts_summary.total);
            }
        }

        // 3) ✅ NOUVELLES ALERTES (popup/son) sur alert.id
        await handleIncomingAlerts(payload, fleet);
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

    list.querySelectorAll('.vehicle-item').forEach(item => {
        item.addEventListener('click', function() {
            const id = parseInt(this.dataset.id, 10);

            list.querySelectorAll('.vehicle-item').forEach(i => {
                i.classList.remove('ring-2', 'ring-[var(--color-primary)]');
            });

            this.classList.add('ring-2', 'ring-[var(--color-primary)]');
            focusVehicleOnMap(id);
        });
    });

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

    const profile = v.user_profile_url ?
        `<a href="${v.user_profile_url}"
              class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200 p-2"
              title="Voir les détails utilisateur"
              onclick="event.stopPropagation();"><i class="fas fa-eye"></i></a>` :
        `<button type="button"
                  class="text-gray-400 p-2 cursor-not-allowed"
                  title="Aucun utilisateur associé"
                  onclick="event.stopPropagation();"><i class="fas fa-eye-slash"></i></button>`;

    const label = `${(v.immatriculation ?? '')} ${(v.users ?? '')}`.toLowerCase();

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
    searchInput.addEventListener('input', function() {
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
                icon: {
                    url: "/assets/icons/car_icon.png",
                    scaledSize: new google.maps.Size(40, 40)
                }
            });

            const infoWindow = new google.maps.InfoWindow({
                content: buildInfoWindowContent(v)
            });

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

    Object.keys(markersById).forEach(id => {
        if (!newIds.has(String(id))) {
            markersById[id].setMap(null);
            delete markersById[id];
            delete infoWindowsById[id];
        }
    });

    if (fitBounds && vehicles.length > 0) {
        map.fitBounds(bounds);
        const listener = google.maps.event.addListener(map, "idle", function() {
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
        const engineText = engineCut ? 'Moteur coupé' : 'Moteur actif';

        const gpsClass = gpsOnline === true ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-600';
        const gpsText = gpsOnline === true ? 'GPS en ligne' : 'GPS hors ligne';

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
        if (gps.online === true) {
            gpsLabel = 'GPS en ligne';
            gpsColor = '#22c55e';
        } else if (gps.online === false) {
            gpsLabel = 'GPS hors ligne';
            gpsColor = '#9ca3af';
        }
    }

    const profileLink = vehicle.user_profile_url ?
        `<div style="margin-top:6px;">
             <a href="${vehicle.user_profile_url}" style="color:#3b82f6;text-decoration:underline;">Voir profil utilisateur</a>
           </div>` :
        '';

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
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[m]));
}

function loadGoogleMaps() {
    const script = document.createElement('script');
    script.src = "https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}";
    script.async = true;
    script.defer = true;
    script.onload = () => initFleetMap();
    document.head.appendChild(script);
}

document.addEventListener('DOMContentLoaded', loadGoogleMaps);
</script>
@endsection