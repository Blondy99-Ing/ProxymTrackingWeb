@extends('layouts.app')

@section('title', 'Trajets sur carte')

@push('head')
<script
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap&libraries=places,geometry"
    async defer>
</script>

<style>
    #map {
        width: 100%;
        height: calc(100vh - 320px);
        min-height: 520px;
        border-radius: 14px;
    }

    .filter-bar {
        width: 100%;
        padding: 14px;
        border-radius: 14px;
        background: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        box-shadow: none;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid var(--color-border-subtle);
        background: var(--color-card);
        color: var(--color-text);
        transition: .2s;
    }
    .back-btn:hover {
        transform: translateY(-1px);
        border-color: var(--color-primary);
        color: var(--color-primary);
    }

    .chip {
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 12px;
        border-radius:999px;
        border:1px solid var(--color-border-subtle);
        background: var(--color-card);
        color: var(--color-text);
        font-size: 12px;
        white-space: nowrap;
    }

    .trip-row-focus {
        outline: 2px solid rgba(245,130,32,.35);
        background: rgba(245,130,32,.06);
    }

    /* ---- MAP MODE CARTE ---- */
    .map-shell{ position: relative; }

    .map-overlay{
        position:absolute;
        top:14px;
        left:14px;
        z-index: 10;
        width: min(420px, calc(100% - 28px));
    }

    .overlay-card{
        border-radius:16px;
        background: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        padding: 12px;
        box-shadow: 0 12px 30px rgba(0,0,0,.12);
    }

    .overlay-title{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        margin-bottom: 8px;
    }

    .overlay-actions{
        display:flex;
        gap:8px;
        flex-wrap: wrap;
    }

    .map-mini-btn{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding: 8px 10px;
        border-radius: 12px;
        border: 1px solid var(--color-border-subtle);
        background: transparent;
        color: var(--color-text);
        cursor:pointer;
        font-size: 12px;
        transition:.15s;
        user-select:none;
    }
    .map-mini-btn:hover{
        transform: translateY(-1px);
        border-color: var(--color-primary);
        color: var(--color-primary);
    }
    .map-mini-btn.active{
        border-color: var(--color-primary);
        color: var(--color-primary);
        background: rgba(245,130,32,.08);
    }

    .kv{
        font-size: 12px;
        color: var(--color-text);
        line-height: 1.35;
        margin-top: 8px;
    }
    .kv .muted{
        color: var(--color-text-secondary, #6b7280);
        font-size: 12px;
    }

    .poi-list{
        margin-top: 10px;
        display:flex;
        flex-direction:column;
        gap:8px;
        max-height: 220px;
        overflow:auto;
        padding-right:4px;
    }

    .poi-item{
        border: 1px solid var(--color-border-subtle);
        border-radius: 14px;
        padding: 10px;
        background: rgba(255,255,255,.02);
    }
    .poi-item b{ font-size: 13px; }

    .poi-meta{
        margin-top: 4px;
        font-size: 12px;
        color: var(--color-text-secondary, #6b7280);
        display:flex;
        gap:10px;
        flex-wrap:wrap;
    }
</style>
@endpush

@section('content')
@php
    $filters = $filters ?? [];

    // ✅ retour vers index en conservant les filtres (sans focus/page/mode)
    $backFilters = $filters;
    unset($backFilters['focus_trajet_id'], $backFilters['page'], $backFilters['mode']);

    $pageCount = ($trajets instanceof \Illuminate\Pagination\AbstractPaginator)
        ? $trajets->count()
        : (is_countable($trajets) ? count($trajets) : 0);

    $mode = $mode ?? request('mode');
@endphp

<div class="max-w-7xl mx-auto p-2 space-y-4">

    <!-- HEADER -->
    <header class="flex items-center justify-between gap-3 flex-wrap">
        <div class="space-y-2">
            <h1 class="text-2xl md:text-3xl font-orbitron font-bold" style="color: var(--color-text);">
                Trajets de <span class="text-primary">{{ $voiture->immatriculation }}</span>
            </h1>

            <div class="flex flex-wrap gap-2">
                @if(!empty($focusId))
                    <div class="chip">
                        <i class="fas fa-route text-primary"></i>
                        <span>Trajet sélectionné : <b>#{{ $focusId }}</b></span>
                    </div>
                @endif

                @if(!empty($mode) && $mode === 'detail')
                    <div class="chip">
                        <i class="fas fa-crosshairs text-primary"></i>
                        <span>Mode : <b>Détail</b> (1 trajet)</span>
                    </div>
                @else
                    <div class="chip">
                        <i class="fas fa-list text-primary"></i>
                        <span>Affichage : <b>{{ $pageCount }}</b> trajet(s) (page)</span>
                    </div>
                @endif
            </div>
        </div>

        <a href="{{ route('trajets.index', $backFilters) }}" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Retour</span>
        </a>
    </header>

    {{-- ✅ INFOS DETAILLEES DU TRAJET (si focus) --}}
    @if(!empty($focusTrajet))
        <div id="trajet-{{ $focusTrajet->id }}" class="ui-card p-4 border border-border-subtle rounded-2xl">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div class="space-y-2">
                    <div class="text-sm text-secondary">
                        Trajet <b>#{{ $focusTrajet->id }}</b>
                        @if(!empty($mode) && $mode==='detail')
                            <span class="ml-2 chip"><i class="fas fa-crosshairs text-primary"></i> Mode détail</span>
                        @endif
                    </div>

                    <div class="text-sm">
                        <b>Départ :</b>
                        {{ \Carbon\Carbon::parse($focusTrajet->start_time)->format('d/m/Y H:i') }}
                        —
                        <b>Arrivée :</b>
                        {{ $focusTrajet->end_time ? \Carbon\Carbon::parse($focusTrajet->end_time)->format('d/m/Y H:i') : 'N/A' }}
                    </div>

                    <div class="text-xs text-secondary">
                        <b>Coord. départ :</b>
                        long {{ number_format($focusTrajet->start_longitude, 6) }},
                        lat {{ number_format($focusTrajet->start_latitude, 6) }}
                        —
                        <b>Coord. arrivée :</b>
                        long {{ number_format($focusTrajet->end_longitude, 6) }},
                        lat {{ number_format($focusTrajet->end_latitude, 6) }}
                    </div>

                    <div class="text-xs text-secondary">
                        <b>Distance :</b> {{ number_format($focusTrajet->total_distance_km ?? 0, 2) }} km
                        —
                        <b>Durée :</b> {{ (int)($focusTrajet->duration_minutes ?? 0) }} min
                        —
                        <b>Vit. moy :</b> {{ number_format($focusTrajet->avg_speed_kmh ?? 0, 1) }} km/h
                        —
                        <b>Vit. max :</b> {{ number_format($focusTrajet->max_speed_kmh ?? 0, 1) }} km/h
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if(empty($mode) || $mode !== 'detail')
                        @php
                            $q = request()->query();
                            $q['mode'] = 'detail';
                            $q['focus_trajet_id'] = $focusTrajet->id;
                            unset($q['page']);
                        @endphp
                        <a class="btn-secondary py-2 px-4 text-sm"
                           href="{{ route('voitures.trajets', ['id'=>$voiture->id] + $q) }}#trajet-{{ $focusTrajet->id }}">
                            <i class="fas fa-map-marked-alt mr-2"></i> Afficher uniquement
                        </a>
                    @else
                        @php
                            $q = request()->query();
                            unset($q['mode'], $q['focus_trajet_id'], $q['page']);
                        @endphp
                        <a class="btn-secondary py-2 px-4 text-sm"
                           href="{{ route('voitures.trajets', ['id'=>$voiture->id] + $q) }}">
                            <i class="fas fa-list mr-2"></i> Retour à la liste filtrée
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- FILTRES (AUTO) -->
    <div class="filter-bar ui-card">
        <form id="filtersForm" method="GET" action="{{ url()->current() }}"
              class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">

            {{-- ✅ IMPORTANT : on n’inclut PAS focus_trajet_id ni mode dans le form --}}
            {{-- reset page quand on refiltre --}}
            <input type="hidden" name="page" value="">

            {{-- SWITCH VEHICULE --}}
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-secondary">Changer de véhicule</label>
                <input id="vehicleSearch" list="vehiclesList" class="ui-input-style w-full"
                       value="{{ $voiture->immatriculation }}"
                       placeholder="Tape une immatriculation…">
                <datalist id="vehiclesList">
                    @foreach($vehicles as $v)
                        <option value="{{ $v->immatriculation }}"></option>
                    @endforeach
                </datalist>
                <p class="text-xs text-secondary mt-1">Choisis une proposition pour basculer (garde les filtres).</p>
            </div>

            <!-- TYPE -->
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-secondary">Filtrer par</label>
                <select id="filter-type" name="quick" class="ui-input-style w-full">
                    <option value="today"     {{ request('quick','today')=='today'?'selected':'' }}>Aujourd'hui</option>
                    <option value="yesterday" {{ request('quick')=='yesterday'?'selected':'' }}>Hier</option>
                    <option value="week"      {{ request('quick')=='week'?'selected':'' }}>Cette semaine</option>
                    <option value="month"     {{ request('quick')=='month'?'selected':'' }}>Ce mois</option>
                    <option value="year"      {{ request('quick')=='year'?'selected':'' }}>Cette année</option>
                    <option value="date"      {{ request('quick')=='date'?'selected':'' }}>Date spécifique</option>
                    <option value="range"     {{ request('quick')=='range'?'selected':'' }}>Plage de dates</option>
                </select>
            </div>

            <!-- DATE UNIQUE -->
            <div id="single-date" class="hidden md:col-span-2">
                <label class="text-sm font-medium text-secondary">Date</label>
                <input id="dateInput" type="date" name="date" class="ui-input-style w-full"
                       value="{{ request('date') }}">
            </div>

            <!-- PLAGE -->
            <div id="date-range" class="hidden md:col-span-2">
                <label class="text-sm font-medium text-secondary">Plage de dates</label>
                <div class="grid grid-cols-2 gap-3">
                    <input id="startDateInput" type="date" name="start_date" class="ui-input-style"
                           value="{{ request('start_date') }}">
                    <input id="endDateInput" type="date" name="end_date" class="ui-input-style"
                           value="{{ request('end_date') }}">
                </div>
            </div>

            <!-- HEURES -->
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-secondary">Heures</label>
                <div class="grid grid-cols-2 gap-3">
                    <input id="startTimeInput" type="time" name="start_time" class="ui-input-style"
                           value="{{ request('start_time') }}">
                    <input id="endTimeInput" type="time" name="end_time" class="ui-input-style"
                           value="{{ request('end_time') }}">
                </div>
            </div>

            <div class="md:col-span-6 -mt-2 text-xs text-secondary">
                <i class="fas fa-bolt mr-1 text-primary"></i>
                Les filtres s’appliquent automatiquement.
            </div>
        </form>
    </div>

    <!-- MAP + OVERLAY (MODE CARTE) -->
    <div class="map-shell">
        <div class="map-overlay">
            <div class="overlay-card">
                <div class="overlay-title">
                    <div class="font-orbitron font-bold" style="color:var(--color-text)">
                        <i class="fas fa-map-marker-alt text-primary mr-2"></i> Mode carte
                    </div>
                    <div class="overlay-actions">
                        <button type="button" class="map-mini-btn" id="btnLocate">
                            <i class="fas fa-crosshairs"></i> Ma position
                        </button>
                        <button type="button" class="map-mini-btn" id="btnTraffic">
                            <i class="fas fa-traffic-light"></i> Trafic
                        </button>
                    </div>
                </div>

                <div class="overlay-actions mb-2">
                    <button type="button" class="map-mini-btn active" data-maptype="roadmap">Plan</button>
                    <button type="button" class="map-mini-btn" data-maptype="hybrid">Hybride</button>
                    <button type="button" class="map-mini-btn" data-maptype="satellite">Satellite</button>
                    <button type="button" class="map-mini-btn" data-maptype="terrain">Terrain</button>
                </div>

                <div class="kv" id="whereBox">
                    <div><b>Lieu :</b> <span id="whereName">—</span></div>
                    <div class="muted" id="whereCoords">—</div>
                    <div class="muted" id="whereHint">Astuce : clique sur un trajet pour voir ce qu’il y a autour.</div>
                </div>

                <div class="poi-list" id="poiList">
                    <div class="muted" style="font-size:12px;">Autour : en attente…</div>
                </div>
            </div>
        </div>

        <div id="map" class="shadow-md border border-border-subtle"></div>
    </div>

    <!-- RÉSUMÉ -->
    <div class="ui-card flex flex-wrap justify-around text-center rounded-2xl">
        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $pageCount }}</p>
            <p class="text-sm text-secondary">Trajets (page)</p>
        </div>

        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $totalDistance }} km</p>
            <p class="text-sm text-secondary">Distance totale</p>
        </div>

        <div class="p-3">
            @php $h = floor($totalDuration / 60); $m = $totalDuration % 60; @endphp
            <p class="text-3xl font-orbitron text-primary">{{ $h }}h {{ $m }}m</p>
            <p class="text-sm text-secondary">Durée totale</p>
        </div>

        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $maxSpeed }} km/h</p>
            <p class="text-sm text-secondary">Vitesse max</p>
        </div>

        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $avgSpeed }} km/h</p>
            <p class="text-sm text-secondary">Vitesse moyenne</p>
        </div>
    </div>

    <!-- TABLEAU TRAJETS (PAGE) -->
    <div class="ui-card p-4 rounded-2xl">
        <div class="flex items-center justify-between gap-2 flex-wrap mb-3">
            <h2 class="text-lg font-orbitron font-bold" style="color: var(--color-text);">
                Liste des trajets (page)
            </h2>

            @if(!empty($focusId))
                <a class="btn-secondary py-2 px-4 text-sm"
                   href="{{ route('voitures.trajets', ['id'=>$voiture->id] + array_diff_key(request()->query(), array_flip(['focus_trajet_id','mode','page']))) }}">
                    <i class="fas fa-times mr-2"></i> Enlever la sélection
                </a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Départ</th>
                        <th>Arrivée</th>
                        <th>Distance</th>
                        <th>Vit. Moy</th>
                        <th>Vit. Max</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($trajets as $t)
                    @php
                        $isFocus = !empty($focusId) && (int)$focusId === (int)$t->id;

                        $qFocus = request()->query();
                        $qFocus['focus_trajet_id'] = $t->id;
                        unset($qFocus['mode']); // ✅ rester en mode liste

                        $qDetail = request()->query();
                        $qDetail['focus_trajet_id'] = $t->id;
                        $qDetail['mode'] = 'detail';
                        unset($qDetail['page']); // ✅ propre pour détail
                    @endphp

                    <tr id="trajet-{{ $t->id }}" class="hover:bg-hover-subtle transition {{ $isFocus ? 'trip-row-focus' : '' }}">
                        <td class="font-semibold text-primary">#{{ $t->id }}</td>

                        <td>
                            <span class="font-medium">
                                {{ \Carbon\Carbon::parse($t->start_time)->format('d/m/Y H:i') }}
                            </span><br>
                            <span class="text-xs text-secondary">
                                (long: {{ number_format($t->start_longitude, 5) }}, lat: {{ number_format($t->start_latitude, 5) }})
                            </span>
                        </td>

                        <td>
                            <span class="font-medium">
                                {{ $t->end_time ? \Carbon\Carbon::parse($t->end_time)->format('d/m/Y H:i') : 'N/A' }}
                            </span><br>
                            <span class="text-xs text-secondary">
                                (long: {{ number_format($t->end_longitude, 5) }}, lat: {{ number_format($t->end_latitude, 5) }})
                            </span>
                        </td>

                        <td class="font-bold text-blue-600">{{ number_format($t->total_distance_km ?? 0, 2) }} km</td>
                        <td class="text-orange-600">{{ number_format($t->avg_speed_kmh ?? 0, 1) }} km/h</td>
                        <td class="text-red-600">{{ number_format($t->max_speed_kmh ?? 0, 1) }} km/h</td>

                        <td class="whitespace-nowrap">
                            <a class="text-primary hover:text-primary-dark font-medium mr-3"
                               href="{{ route('voitures.trajets', ['id'=>$voiture->id] + $qFocus) }}#trajet-{{ $t->id }}">
                                <i class="fas fa-crosshairs mr-1"></i> Voir
                            </a>

                            <a class="text-primary hover:text-primary-dark font-medium"
                               href="{{ route('voitures.trajets', ['id'=>$voiture->id] + $qDetail) }}#trajet-{{ $t->id }}">
                                <i class="fas fa-eye mr-1"></i> Détails
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-6 text-secondary bg-hover-subtle">
                            <i class="fas fa-info-circle mr-1"></i> Aucun trajet trouvé.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($trajets instanceof \Illuminate\Pagination\AbstractPaginator)
            <div class="mt-4 flex justify-end">
                {{ $trajets->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>

{{-- AUTO-FILTER + switch véhicule --}}
<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById('filtersForm');

    const type   = document.getElementById("filter-type");
    const single = document.getElementById("single-date");
    const range  = document.getElementById("date-range");

    const dateInput      = document.getElementById("dateInput");
    const startDateInput = document.getElementById("startDateInput");
    const endDateInput   = document.getElementById("endDateInput");
    const startTimeInput = document.getElementById("startTimeInput");
    const endTimeInput   = document.getElementById("endTimeInput");

    // switch véhicule via datalist
    const vehicleSearch = document.getElementById('vehicleSearch');
    const vehicles = @json(($vehicles ?? collect())->map(fn($v) => ['id'=>$v->id,'immatriculation'=>$v->immatriculation])->values());
    const urlTemplate = @json(route('voitures.trajets', ['id' => '__VID__']));

    function getVehicleIdFromImmat(val) {
        if (!val) return null;
        const norm = String(val).trim().toLowerCase();
        const hit = vehicles.find(v => String(v.immatriculation).toLowerCase() === norm);
        return hit ? hit.id : null;
    }

    if (vehicleSearch) {
        vehicleSearch.addEventListener('change', () => {
            const vid = getVehicleIdFromImmat(vehicleSearch.value);
            if (!vid) return;

            const params = new URLSearchParams(window.location.search);

            // on garde les filtres, mais on enlève focus/mode/page
            params.delete('focus_trajet_id');
            params.delete('mode');
            params.delete('page');

            const url = urlTemplate.replace('__VID__', vid)
                + (params.toString() ? ('?' + params.toString()) : '');

            window.location.href = url;
        });
    }

    // debounce submit
    let tmr = null;
    function debounceSubmit(ms = 450) {
        clearTimeout(tmr);
        tmr = setTimeout(() => form.submit(), ms);
    }

    function updateUI() {
        single.classList.add("hidden");
        range.classList.add("hidden");

        if (type.value === "date")  single.classList.remove("hidden");
        if (type.value === "range") range.classList.remove("hidden");
    }

    function canAutoSubmitForType() {
        if (type.value === 'date') {
            return !!(dateInput && dateInput.value);
        }
        if (type.value === 'range') {
            return !!((startDateInput && startDateInput.value) || (endDateInput && endDateInput.value));
        }
        return true;
    }

    updateUI();

    type.addEventListener("change", () => {
        const pageHidden = form.querySelector('input[name="page"]');
        if (pageHidden) pageHidden.value = '';

        if (type.value !== 'date' && dateInput) dateInput.value = '';
        if (type.value !== 'range') {
            if (startDateInput) startDateInput.value = '';
            if (endDateInput) endDateInput.value = '';
        }

        updateUI();
        if (canAutoSubmitForType()) form.submit();
    });

    if (dateInput) {
        dateInput.addEventListener('change', () => {
            if (type.value === 'date' && canAutoSubmitForType()) form.submit();
        });
    }

    [startDateInput, endDateInput].forEach(inp => {
        if (!inp) return;
        inp.addEventListener('change', () => {
            if (type.value === 'range' && canAutoSubmitForType()) form.submit();
        });
    });

    [startTimeInput, endTimeInput].forEach(inp => {
        if (!inp) return;
        inp.addEventListener('input', () => debounceSubmit(500));
        inp.addEventListener('change', () => debounceSubmit(200));
    });
});
</script>

{{-- GOOGLE MAPS (MODE CARTE + PLACES + POSITION + TRAJETS) --}}
<script>
window.initMap = function () {
    const mapDiv = document.getElementById("map");
    if (!mapDiv) return;

    const tracks  = @json($tracks ?? []);
    const focusId = @json($focusId);

    const primary = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#F58220';

    // UI refs
    const whereName   = document.getElementById('whereName');
    const whereCoords = document.getElementById('whereCoords');
    const whereHint   = document.getElementById('whereHint');
    const poiList     = document.getElementById('poiList');

    const btnLocate   = document.getElementById('btnLocate');
    const btnTraffic  = document.getElementById('btnTraffic');
    const mapTypeBtns = Array.from(document.querySelectorAll('[data-maptype]'));

    // services
    const geocoder = new google.maps.Geocoder();
    const trafficLayer = new google.maps.TrafficLayer();
    let trafficOn = false;

    function setWhere(lat, lng, title = null) {
        if (whereCoords) whereCoords.textContent = `Lat ${Number(lat).toFixed(6)} • Lng ${Number(lng).toFixed(6)}`;
        if (title && whereName) whereName.textContent = title;
    }

    function setPoiLoading(text="Recherche des lieux autour…") {
        if (!poiList) return;
        poiList.innerHTML = `<div class="muted" style="font-size:12px;">${text}</div>`;
    }

    function clearPoiMarkers() {
        while (poiMarkers.length) poiMarkers.pop().setMap(null);
    }

    function distanceKm(from, toLatLng) {
        try {
            if (google.maps.geometry && google.maps.geometry.spherical) {
                return google.maps.geometry.spherical.computeDistanceBetween(
                    new google.maps.LatLng(from.lat, from.lng),
                    toLatLng
                ) / 1000;
            }
        } catch(e){}
        return null;
    }

    function reverseGeocode(latLng) {
        geocoder.geocode({ location: latLng }, (res, status) => {
            if (status === 'OK' && res && res[0]) {
                if (whereName) whereName.textContent = res[0].formatted_address;
            }
        });
    }

    // center default
    let center = { lat: 4.05, lng: 9.7 };
    for (const tr of tracks) {
        if (tr.points && tr.points.length) {
            center = { lat: parseFloat(tr.points[0].lat), lng: parseFloat(tr.points[0].lng) };
            break;
        } else if (tr.start && tr.start.lat) {
            center = { lat: parseFloat(tr.start.lat), lng: parseFloat(tr.start.lng) };
            break;
        }
    }

    // ✅ POI visibles : pas de style qui cache "poi"
    const map = new google.maps.Map(mapDiv, {
        zoom: 13,
        center,
        mapTypeId: "roadmap",
        mapTypeControl: false,
        fullscreenControl: true,
        streetViewControl: true,
        clickableIcons: true,
        gestureHandling: "greedy"
    });

    // Places
    const places = new google.maps.places.PlacesService(map);
    const poiMarkers = [];

    function renderPoiList(items, origin) {
        if (!poiList) return;
        if (!items || !items.length) {
            poiList.innerHTML = `<div class="muted" style="font-size:12px;">Aucun lieu trouvé à proximité.</div>`;
            return;
        }

        poiList.innerHTML = items.map(p => {
            const d = (p.__distKm != null) ? `${p.__distKm.toFixed(2)} km` : '—';
            const open = (p.opening_hours && typeof p.opening_hours.open_now === 'boolean')
                ? (p.opening_hours.open_now ? 'Ouvert' : 'Fermé')
                : '—';
            const rating = (p.rating != null) ? `${Number(p.rating).toFixed(1)}/5` : '—';

            return `
                <div class="poi-item">
                    <b>${p.name || 'Lieu'}</b>
                    <div class="poi-meta">
                        <span><i class="fas fa-walking mr-1"></i>${d}</span>
                        <span><i class="fas fa-star mr-1"></i>${rating}</span>
                        <span><i class="fas fa-store mr-1"></i>${open}</span>
                    </div>
                    <div class="poi-meta">
                        <span>${p.vicinity || ''}</span>
                    </div>
                </div>
            `;
        }).join('');

        // markers
        clearPoiMarkers();
        items.slice(0, 8).forEach(p => {
            if (!p.geometry || !p.geometry.location) return;
            const m = new google.maps.Marker({
                map,
                position: p.geometry.location,
                title: p.name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: "#0ea5e9",
                    fillOpacity: 1,
                    strokeColor: "#ffffff",
                    strokeWeight: 2,
                    scale: 7
                }
            });
            poiMarkers.push(m);
        });
    }

    function searchNearby(originLatLng) {
        const origin = { lat: originLatLng.lat(), lng: originLatLng.lng() };
        setWhere(origin.lat, origin.lng, "Point sélectionné");
        setPoiLoading();

        const types = ['restaurant','gas_station','atm','hospital','pharmacy','police','store'];

        const all = [];
        let done = 0;

        clearPoiMarkers();

        types.forEach(tp => {
            places.nearbySearch({ location: originLatLng, radius: 1200, type: tp }, (results, status) => {
                done++;

                if (status === google.maps.places.PlacesServiceStatus.OK && results && results.length) {
                    results.forEach(r => all.push(r));
                }

                if (done === types.length) {
                    // dédoublonnage
                    const mapById = new Map();
                    all.forEach(p => { if (p.place_id) mapById.set(p.place_id, p); });

                    const unique = Array.from(mapById.values()).slice(0, 14);

                    // distances
                    unique.forEach(p => {
                        if (p.geometry && p.geometry.location) {
                            const d = distanceKm(origin, p.geometry.location);
                            if (d != null) p.__distKm = d;
                        }
                    });

                    unique.sort((a,b) => (a.__distKm ?? 999) - (b.__distKm ?? 999));
                    renderPoiList(unique, origin);
                }
            });
        });
    }

    // my position
    let myMarker = null;
    let myAccuracyCircle = null;

    function locateMe() {
        if (!navigator.geolocation) {
            if (whereHint) whereHint.textContent = "Géolocalisation non supportée sur ce navigateur.";
            return;
        }

        if (whereHint) whereHint.textContent = "Localisation en cours…";
        navigator.geolocation.getCurrentPosition((pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = pos.coords.accuracy || 0;

            const latLng = new google.maps.LatLng(lat, lng);
            map.panTo(latLng);
            if (map.getZoom() < 16) map.setZoom(16);

            if (!myMarker) {
                myMarker = new google.maps.Marker({
                    map,
                    position: latLng,
                    title: "Ma position",
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        fillColor: "#2563eb",
                        fillOpacity: 1,
                        strokeColor: "#ffffff",
                        strokeWeight: 2,
                        scale: 9
                    }
                });
            } else {
                myMarker.setPosition(latLng);
            }

            if (!myAccuracyCircle) {
                myAccuracyCircle = new google.maps.Circle({
                    map,
                    center: latLng,
                    radius: acc,
                    strokeOpacity: 0.2,
                    fillOpacity: 0.08
                });
            } else {
                myAccuracyCircle.setCenter(latLng);
                myAccuracyCircle.setRadius(acc);
            }

            setWhere(lat, lng, "Ma position");
            reverseGeocode(latLng);
            searchNearby(latLng);

            if (whereHint) whereHint.textContent = "Tu peux aussi cliquer sur ton trajet pour explorer autour.";
        }, () => {
            if (whereHint) whereHint.textContent = "Impossible d’obtenir ta position (permission refusée ou GPS indispo).";
        }, { enableHighAccuracy: true, timeout: 8000 });
    }

    // UI events
    mapTypeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            mapTypeBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            map.setMapTypeId(btn.getAttribute('data-maptype'));
        });
    });

    if (btnTraffic) {
        btnTraffic.addEventListener('click', () => {
            trafficOn = !trafficOn;
            if (trafficOn) {
                trafficLayer.setMap(map);
                btnTraffic.classList.add('active');
            } else {
                trafficLayer.setMap(null);
                btnTraffic.classList.remove('active');
            }
        });
    }

    if (btnLocate) btnLocate.addEventListener('click', locateMe);

    // -------- TRACKS DRAWING ----------
    if (!tracks.length) {
        if (whereHint) whereHint.textContent = "Aucun trajet à afficher.";
        return;
    }

    const bounds = new google.maps.LatLngBounds();
    const hoverInfo = new google.maps.InfoWindow();

    function circleIcon(fill) {
        return {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: fill,
            fillOpacity: 1,
            strokeColor: "#ffffff",
            strokeWeight: 2,
            scale: 10
        };
    }

    function dist2(a, b) {
        const dx = a.lat - b.lat;
        const dy = a.lng - b.lng;
        return dx*dx + dy*dy;
    }

    function nearestPoint(latLng, points) {
        if (!points || !points.length) return null;
        const p = { lat: latLng.lat(), lng: latLng.lng() };
        let best = points[0];
        let bestD = Infinity;

        for (let i=0; i<points.length; i++) {
            const d = dist2(p, points[i]);
            if (d < bestD) { bestD = d; best = points[i]; }
        }
        return best;
    }

    tracks.forEach((tr) => {
        const pts = tr.points || [];
        let path = [];

        if (pts.length >= 2) {
            path = pts.map(p => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng) }));
        } else if (tr.start && tr.end) {
            path = [
                { lat: parseFloat(tr.start.lat), lng: parseFloat(tr.start.lng) },
                { lat: parseFloat(tr.end.lat),   lng: parseFloat(tr.end.lng) }
            ];
        } else return;

        path.forEach(p => bounds.extend(p));

        const isFocus = focusId && String(tr.trajet_id) === String(focusId);

        // polyline + flèches
        new google.maps.Polyline({
            path,
            strokeColor: primary,
            strokeOpacity: isFocus ? 1 : 0.85,
            strokeWeight: isFocus ? 7 : 4,
            geodesic: true,
            icons: [{
                icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, strokeWeight: 2, scale: 2.6 },
                offset: '0',
                repeat: '90px'
            }],
            map
        });

        // zone cliquable large
        const hitLine = new google.maps.Polyline({
            path,
            strokeColor: primary,
            strokeOpacity: 0,
            strokeWeight: 24,
            clickable: true,
            map
        });

        const startPos = path[0];
        const endPos   = path[path.length - 1];

        const startMarker = new google.maps.Marker({
            position: startPos,
            map,
            title: `Départ — Trajet #${tr.trajet_id}`,
            label: { text: "D", color: "#ffffff", fontWeight: "800" },
            icon: circleIcon("#16a34a")
        });

        const endMarker = new google.maps.Marker({
            position: endPos,
            map,
            title: `Arrivée — Trajet #${tr.trajet_id}`,
            label: { text: "A", color: "#ffffff", fontWeight: "800" },
            icon: circleIcon("#dc2626")
        });

        const startInfo = new google.maps.InfoWindow({
            content: `<div style="font-size:13px"><b>Départ</b><br>Trajet #${tr.trajet_id}<br>${tr.start_time || ''}</div>`
        });
        startMarker.addListener('click', () => startInfo.open(map, startMarker));

        const endInfo = new google.maps.InfoWindow({
            content: `<div style="font-size:13px"><b>Arrivée</b><br>Trajet #${tr.trajet_id}<br>${tr.end_time || ''}</div>`
        });
        endMarker.addListener('click', () => endInfo.open(map, endMarker));

        function showHover(e) {
            const closest = pts.length ? nearestPoint(e.latLng, pts) : null;

            const lat = closest ? closest.lat : e.latLng.lat();
            const lng = closest ? closest.lng : e.latLng.lng();
            const t   = (closest && closest.t) ? closest.t : (tr.start_time || '');
            const spd = closest ? (closest.speed ?? 0) : null;

            hoverInfo.setContent(`
                <div style="font-size:13px; line-height:1.35">
                    <b>Trajet #${tr.trajet_id}${isFocus ? ' (sélectionné)' : ''}</b><br>
                    <span>Heure: <b>${t}</b></span><br>
                    <span>Lat: <b>${parseFloat(lat).toFixed(6)}</b></span><br>
                    <span>Lng: <b>${parseFloat(lng).toFixed(6)}</b></span><br>
                    ${spd !== null ? `<span>Vitesse: <b>${Number(spd).toFixed(1)} km/h</b></span>` : ``}
                    <div style="margin-top:6px;font-size:12px;color:#6b7280;">Clique pour voir les lieux autour</div>
                </div>
            `);

            const pos = closest
                ? new google.maps.LatLng(parseFloat(closest.lat), parseFloat(closest.lng))
                : e.latLng;

            hoverInfo.setPosition(pos);
            hoverInfo.open({ map });
        }

        hitLine.addListener('mousemove', showHover);
        hitLine.addListener('mouseout', () => hoverInfo.close());

        hitLine.addListener('click', (e) => {
            showHover(e);
            if (whereHint) whereHint.textContent = "Lieux autour du point sélectionné :";
            reverseGeocode(e.latLng);
            searchNearby(e.latLng);
        });
    });

    // fit bounds focus vs global
    const focusTrack = (focusId)
        ? tracks.find(x => String(x.trajet_id) === String(focusId))
        : null;

    if (focusTrack) {
        const pts = focusTrack.points || [];
        const focusBounds = new google.maps.LatLngBounds();
        let focusPath = [];

        if (pts.length >= 2) {
            focusPath = pts.map(p => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng) }));
        } else if (focusTrack.start && focusTrack.end) {
            focusPath = [
                { lat: parseFloat(focusTrack.start.lat), lng: parseFloat(focusTrack.start.lng) },
                { lat: parseFloat(focusTrack.end.lat),   lng: parseFloat(focusTrack.end.lng) }
            ];
        }
        focusPath.forEach(p => focusBounds.extend(p));

        if (!focusBounds.isEmpty()) {
            map.fitBounds(focusBounds, 40);
            google.maps.event.addListenerOnce(map, "idle", () => {
                if (map.getZoom() > 18) map.setZoom(18);
            });
        }
    } else {
        if (!bounds.isEmpty()) {
            map.fitBounds(bounds);
            google.maps.event.addListenerOnce(map, "idle", () => {
                if (map.getZoom() > 17) map.setZoom(17);
            });
        }
    }

    // init overlay texts
    const c = map.getCenter();
    setWhere(c.lat(), c.lng(), "Centre de la carte");
    if (whereHint) whereHint.textContent = "Clique sur un trajet ou utilise “Ma position” pour voir les restaurants autour.";

    // Option auto-localisation au chargement :
    // locateMe();
}
</script>
@endsection
