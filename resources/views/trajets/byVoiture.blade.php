@extends('layouts.app')

@section('title', 'Trajets sur carte')

@push('head')
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async defer></script>

<style>
    #map {
        width: 100%;
        height: calc(100vh - 280px);
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

    /* petit badge info */
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
</style>
@endpush


@section('content')
@php
    $backFilters = $filters ?? [];
    unset($backFilters['focus_trajet_id']);
@endphp

<div class="max-w-7xl mx-auto p-2 space-y-4">

    <!-- HEADER -->
    <header class="flex items-center justify-between gap-3 flex-wrap">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-orbitron font-bold" style="color: var(--color-text);">
                Trajets de <span class="text-primary">{{ $voiture->immatriculation }}</span>
            </h1>

            @if(request()->filled('focus_trajet_id'))
                <div class="chip">
                    <i class="fas fa-route text-primary"></i>
                    <span>Trajet sélectionné : <b>#{{ request('focus_trajet_id') }}</b></span>
                </div>
            @else
                <div class="chip">
                    <i class="fas fa-list text-primary"></i>
                    <span>Affichage : <b>20 derniers trajets</b></span>
                </div>
            @endif
        </div>

        <a href="{{ route('trajets.index', $backFilters) }}" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Retour</span>
        </a>
    </header>


    <!-- FILTRES (AUTO) -->
    <div class="filter-bar ui-card">
        <form id="filtersForm" method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

            {{-- On conserve le focus si présent --}}
            @if(request()->filled('focus_trajet_id'))
                <input type="hidden" name="focus_trajet_id" value="{{ request('focus_trajet_id') }}">
            @endif

            <!-- TYPE -->
            <div>
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
            <div id="single-date" class="hidden">
                <label class="text-sm font-medium text-secondary">Date</label>
                <input id="dateInput" type="date" name="date" class="ui-input-style w-full" value="{{ request('date') }}">
            </div>

            <!-- PLAGE -->
            <div id="date-range" class="hidden">
                <label class="text-sm font-medium text-secondary">Plage de dates</label>
                <div class="grid grid-cols-2 gap-3">
                    <input id="startDateInput" type="date" name="start_date" class="ui-input-style" value="{{ request('start_date') }}">
                    <input id="endDateInput" type="date" name="end_date" class="ui-input-style" value="{{ request('end_date') }}">
                </div>
            </div>

            <!-- HEURES -->
            <div>
                <label class="text-sm font-medium text-secondary">Heures</label>
                <div class="grid grid-cols-2 gap-3">
                    <input id="startTimeInput" type="time" name="start_time" class="ui-input-style" value="{{ request('start_time') }}">
                    <input id="endTimeInput" type="time" name="end_time" class="ui-input-style" value="{{ request('end_time') }}">
                </div>
            </div>

            <!-- Astuce (aucun bouton filtrer) -->
            <div class="md:col-span-4 -mt-2 text-xs text-secondary">
                <i class="fas fa-bolt mr-1 text-primary"></i>
                Les filtres s’appliquent automatiquement.
            </div>

        </form>
    </div>


    <!-- MAP -->
    <div id="map" class="shadow-md border border-border-subtle"></div>


    <!-- RÉSUMÉ -->
    <div class="ui-card flex flex-wrap justify-around text-center rounded-2xl">
        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $trajets->count() }}</p>
            <p class="text-sm text-secondary">
                {{ request()->filled('focus_trajet_id') ? 'Trajet' : 'Trajets' }}
            </p>
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

</div>


{{-- AUTO-FILTER (sans bouton) --}}
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
            return !!(startDateInput && endDateInput && startDateInput.value && endDateInput.value);
        }
        return true; // today/yesterday/week/month/year
    }

    updateUI();

    // Changement du type : si pas date/range => submit direct
    type.addEventListener("change", () => {
        updateUI();
        if (canAutoSubmitForType()) form.submit();
    });

    // Date spécifique
    if (dateInput) {
        dateInput.addEventListener('change', () => {
            if (type.value === 'date' && canAutoSubmitForType()) form.submit();
        });
    }

    // Range dates
    [startDateInput, endDateInput].forEach(inp => {
        if (!inp) return;
        inp.addEventListener('change', () => {
            if (type.value === 'range' && canAutoSubmitForType()) form.submit();
        });
    });

    // Heures : debounce (évite spam reload)
    [startTimeInput, endTimeInput].forEach(inp => {
        if (!inp) return;
        inp.addEventListener('input', () => debounceSubmit(500));
        inp.addEventListener('change', () => debounceSubmit(200));
    });
});
</script>


{{-- GOOGLE MAPS --}}
<script>
function initMap() {
    const mapDiv = document.getElementById("map");
    if (!mapDiv) return;

    const tracks = @json($tracks ?? []);
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#F58220';

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

    const map = new google.maps.Map(mapDiv, {
        zoom: 13,
        center,
        mapTypeId: "roadmap",
        styles: [{ featureType: "poi", stylers: [{ visibility: "off" }] }]
    });

    const bounds = new google.maps.LatLngBounds();
    const hoverInfo = new google.maps.InfoWindow();

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

    function addLabeledMarker(position, labelText, titleText) {
        return new google.maps.Marker({
            position,
            map,
            title: titleText,
            label: {
                text: labelText,
                color: "#ffffff",
                fontWeight: "800"
            }
        });
    }

    if (!tracks.length) {
        // rien à afficher
        return;
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
        } else {
            return;
        }

        path.forEach(p => bounds.extend(p));

        const line = new google.maps.Polyline({
            path,
            strokeColor: primary,
            strokeOpacity: 0.95,
            strokeWeight: 4,
            clickable: true,
            map
        });

        const startPos = path[0];
        const endPos   = path[path.length - 1];

        const startMarker = addLabeledMarker(startPos, "D", `Départ — Trajet #${tr.trajet_id}`);
        const endMarker   = addLabeledMarker(endPos,   "A", `Arrivée — Trajet #${tr.trajet_id}`);

        const startInfo = new google.maps.InfoWindow({
            content: `<div style="font-size:13px"><b>Départ</b><br>Trajet #${tr.trajet_id}<br>${tr.start_time || ''}</div>`
        });
        startMarker.addListener('click', () => startInfo.open(map, startMarker));

        const endInfo = new google.maps.InfoWindow({
            content: `<div style="font-size:13px"><b>Arrivée</b><br>Trajet #${tr.trajet_id}<br>${tr.end_time || ''}</div>`
        });
        endMarker.addListener('click', () => endInfo.open(map, endMarker));

        // Hover sur polyline => point le plus proche (heure + coords)
        line.addListener('mousemove', (e) => {
            const closest = pts.length ? nearestPoint(e.latLng, pts) : null;

            const lat = closest ? closest.lat : e.latLng.lat();
            const lng = closest ? closest.lng : e.latLng.lng();
            const t   = closest && closest.t ? closest.t : (tr.start_time || '');
            const spd = closest ? (closest.speed ?? 0) : null;

            hoverInfo.setContent(`
                <div style="font-size:13px; line-height:1.35">
                    <b>Trajet #${tr.trajet_id}</b><br>
                    <span>Heure: <b>${t}</b></span><br>
                    <span>Lat: <b>${parseFloat(lat).toFixed(6)}</b></span><br>
                    <span>Lng: <b>${parseFloat(lng).toFixed(6)}</b></span><br>
                    ${spd !== null ? `<span>Vitesse: <b>${Number(spd).toFixed(1)} km/h</b></span>` : ``}
                </div>
            `);

            const pos = closest
                ? { lat: parseFloat(closest.lat), lng: parseFloat(closest.lng) }
                : e.latLng;

            hoverInfo.setPosition(pos);
            hoverInfo.open({ map });
        });

        line.addListener('mouseout', () => hoverInfo.close());

        // Click => verrouille l’info au point
        line.addListener('click', (e) => {
            const closest = pts.length ? nearestPoint(e.latLng, pts) : null;
            if (!closest) return;

            hoverInfo.setPosition({ lat: parseFloat(closest.lat), lng: parseFloat(closest.lng) });
            hoverInfo.open({ map });
        });
    });

    if (!bounds.isEmpty()) {
        map.fitBounds(bounds);
        google.maps.event.addListenerOnce(map, "idle", () => {
            if (map.getZoom() > 17) map.setZoom(17);
        });
    }
}
</script>
@endsection
