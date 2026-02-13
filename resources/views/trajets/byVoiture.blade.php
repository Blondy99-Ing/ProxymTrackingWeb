{{-- resources/views/trajets/byVoiture.blade.php --}}
@extends('layouts.app')

@section('title', 'Trajets sur carte')

@push('head')
<style>
  #map {
      width: 100%;
      height: calc(100vh - 230px);
      min-height: 560px;
      border-radius: 14px;
  }

  .chip {
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 12px; border-radius:999px;
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

  .map-shell{ position: relative; }

  /* only 2 buttons visible */
  .map-top-actions{
      position:absolute;
      top:14px; right:14px;
      z-index: 12;
      display:flex; gap:10px;
      flex-wrap:wrap;
  }

  .floating-btn{
      display:inline-flex; align-items:center; gap:8px;
      padding: 10px 12px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(0,0,0,.22);
      color: #fff;
      backdrop-filter: blur(6px);
      cursor:pointer;
      transition:.15s;
      user-select:none;
      font-size: 13px;
      box-shadow: 0 10px 24px rgba(0,0,0,.18);
  }
  .floating-btn:hover{ transform: translateY(-1px); border-color: rgba(245,130,32,.9); }
  .floating-btn.active{ border-color: rgba(245,130,32,.95); background: rgba(245,130,32,.22); }

  .floating-panel{
      position:absolute;
      top:64px; right:14px;
      z-index: 12;
      width: min(360px, calc(100% - 28px));
      display:none;
  }

  .panel-card{
      border-radius: 16px;
      background: rgba(0,0,0,.28);
      color:#fff;
      border: 1px solid rgba(255,255,255,.14);
      backdrop-filter: blur(10px);
      padding: 12px;
      box-shadow: 0 16px 40px rgba(0,0,0,.22);
  }

  .panel-title{
      display:flex; align-items:center; justify-content:space-between;
      gap:10px; margin-bottom: 10px;
      font-weight: 800;
  }

  .mini-actions{ display:flex; gap:8px; flex-wrap:wrap; }

  .mini-btn{
      display:inline-flex; align-items:center; gap:8px;
      padding: 8px 10px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      color:#fff;
      cursor:pointer;
      font-size: 12px;
      transition:.15s;
      user-select:none;
  }
  .mini-btn:hover{ transform: translateY(-1px); border-color: rgba(245,130,32,.9); }
  .mini-btn.active{ border-color: rgba(245,130,32,.95); background: rgba(245,130,32,.18); }

  .speed-pill{
      display:inline-flex; align-items:center; justify-content:center;
      padding: 6px 10px;
      border-radius: 999px;
      border:1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      font-weight:800;
      min-width: 60px;
  }

  .progress{
      width:100%;
      height: 8px;
      border-radius: 999px;
      background: rgba(255,255,255,.12);
      overflow:hidden;
      margin-top: 10px;
  }
  .progress > div{
      height:100%;
      width: 0%;
      background: rgba(245,130,32,.85);
      transition: width .08s linear;
  }

  .small-grid{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:10px;
      margin-top: 10px;
  }

  .muted{ opacity:.78; font-size: 12px; }
</style>

{{-- ✅ Bootstrap solide : callback Google -> marque prêt -> lance bootMap si DOM prêt --}}
<script>
  window.__gm_ready = false;
  window.__dom_ready = false;
  window.__startMap = null;

  window.initMap = function () {
    window.__gm_ready = true;
    try {
      if (typeof window.__startMap === 'function' && window.__dom_ready) {
        window.__startMap();
      }
    } catch (e) {
      console.error('[Trajets] initMap crash:', e);
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    window.__dom_ready = true;
    try {
      if (typeof window.__startMap === 'function' && window.__gm_ready) {
        window.__startMap();
      }
    } catch (e) {
      console.error('[Trajets] DOMContentLoaded crash:', e);
    }
  });
</script>

<script
  src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&callback=initMap&libraries=places,geometry"
  async defer>
</script>
@endpush

@section('content')
@php
    $filters = $filters ?? [];
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

        <a href="{{ route('trajets.index', $backFilters) }}" class="btn-secondary py-2 px-4 text-sm">
            <i class="fas fa-arrow-left mr-2"></i> Retour
        </a>
    </header>

    {{-- FILTRES --}}
    <div class="ui-card p-4 border border-border-subtle rounded-2xl">
        <form id="filtersForm" method="GET" action="{{ url()->current() }}"
              class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
            <input type="hidden" name="page" value="">

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

            <div id="single-date" class="hidden md:col-span-2">
                <label class="text-sm font-medium text-secondary">Date</label>
                <input id="dateInput" type="date" name="date" class="ui-input-style w-full"
                       value="{{ request('date') }}">
            </div>

            <div id="date-range" class="hidden md:col-span-2">
                <label class="text-sm font-medium text-secondary">Plage de dates</label>
                <div class="grid grid-cols-2 gap-3">
                    <input id="startDateInput" type="date" name="start_date" class="ui-input-style"
                           value="{{ request('start_date') }}">
                    <input id="endDateInput" type="date" name="end_date" class="ui-input-style"
                           value="{{ request('end_date') }}">
                </div>
            </div>

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

    <!-- MAP -->
    <div class="map-shell">
        <div class="map-top-actions">
            <button type="button" class="floating-btn" id="btnMode">
                <i class="fas fa-layer-group"></i> Mode
            </button>
            <button type="button" class="floating-btn" id="btnReplay">
                <i class="fas fa-play-circle"></i> Replay
            </button>
        </div>

        <!-- MODE PANEL -->
        <div class="floating-panel" id="panelMode">
            <div class="panel-card">
                <div class="panel-title">
                    <div><i class="fas fa-layer-group mr-2 text-primary"></i> Mode</div>
                    <button type="button" class="mini-btn" data-close="panelMode"><i class="fas fa-times"></i></button>
                </div>

                <div class="mini-actions">
                    <button type="button" class="mini-btn active" data-maptype="roadmap">Plan</button>
                    <button type="button" class="mini-btn" data-maptype="hybrid">Hybride</button>
                    <button type="button" class="mini-btn" data-maptype="satellite">Satellite</button>
                    <button type="button" class="mini-btn" data-maptype="terrain">Terrain</button>
                </div>

                <div class="small-grid">
                    <button type="button" class="mini-btn" id="btnTraffic"><i class="fas fa-traffic-light"></i> Trafic</button>
                    <button type="button" class="mini-btn" id="btnLocate"><i class="fas fa-crosshairs"></i> Ma position</button>
                </div>

                <div class="muted" style="margin-top:10px">
                    Ce panneau disparaît si tu sors la souris (5s). Pendant replay, il disparaît vite.
                </div>
            </div>
        </div>

        <!-- REPLAY PANEL -->
        <div class="floating-panel" id="panelReplay">
            <div class="panel-card">
                <div class="panel-title">
                    <div><i class="fas fa-play-circle mr-2 text-primary"></i> Replay</div>
                    <button type="button" class="mini-btn" data-close="panelReplay"><i class="fas fa-times"></i></button>
                </div>

                <div class="mini-actions" style="justify-content:space-between">
                    <div class="mini-actions">
                        <button type="button" class="mini-btn" id="rpPrev" title="Précédent"><i class="fas fa-step-backward"></i></button>
                        <button type="button" class="mini-btn" id="rpPlay" title="Play"><i class="fas fa-play"></i></button>
                        <button type="button" class="mini-btn" id="rpPause" title="Pause"><i class="fas fa-pause"></i></button>
                        <button type="button" class="mini-btn" id="rpStop" title="Stop"><i class="fas fa-stop"></i></button>
                        <button type="button" class="mini-btn" id="rpNext" title="Suivant"><i class="fas fa-step-forward"></i></button>
                    </div>

                    <div class="mini-actions">
                        <button type="button" class="mini-btn" id="rpSlow" title="Ralentir"><i class="fas fa-minus"></i></button>
                        <span class="speed-pill" id="rpSpeed">x8</span>
                        <button type="button" class="mini-btn" id="rpFast" title="Accélérer"><i class="fas fa-plus"></i></button>
                    </div>
                </div>

                <div class="muted" style="margin-top:10px">
                    <div><b>Heure :</b> <span id="rpTime">—</span></div>
                    <div id="rpCoords">—</div>
                    <div><b>Vitesse :</b> <span id="rpV">—</span></div>
                </div>

                <div class="progress"><div id="rpBar"></div></div>
            </div>
        </div>

        <div id="map" class="shadow-md border border-border-subtle"></div>
    </div>

    <!-- TABLEAU (optionnel, je le laisse comme tu avais) -->
    <div class="ui-card p-4 rounded-2xl">
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
                        unset($qFocus['mode']);
                        $qDetail = request()->query();
                        $qDetail['focus_trajet_id'] = $t->id;
                        $qDetail['mode'] = 'detail';
                        unset($qDetail['page']);
                    @endphp

                    <tr id="trajet-{{ $t->id }}" class="hover:bg-hover-subtle transition {{ $isFocus ? 'trip-row-focus' : '' }}">
                        <td class="font-semibold text-primary">#{{ $t->id }}</td>
                        <td>{{ \Carbon\Carbon::parse($t->start_time)->format('d/m/Y H:i') }}</td>
                        <td>{{ $t->end_time ? \Carbon\Carbon::parse($t->end_time)->format('d/m/Y H:i') : 'N/A' }}</td>
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
                    <tr><td colspan="7" class="text-center py-6 text-secondary bg-hover-subtle">Aucun trajet trouvé.</td></tr>
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
  if (!form) return;

  const type   = document.getElementById("filter-type");
  const single = document.getElementById("single-date");
  const range  = document.getElementById("date-range");

  const dateInput      = document.getElementById("dateInput");
  const startDateInput = document.getElementById("startDateInput");
  const endDateInput   = document.getElementById("endDateInput");
  const startTimeInput = document.getElementById("startTimeInput");
  const endTimeInput   = document.getElementById("endTimeInput");

  const vehicleSearch = document.getElementById('vehicleSearch');
  const vehicles = @json(($vehicles ?? collect())->map(fn($v) => ['id'=>$v->id,'immatriculation'=>$v->immatriculation])->values());
  const urlTemplate = @json(route('voitures.trajets', ['id' => '__VID__']));

  function updateUI() {
    single?.classList.add("hidden");
    range?.classList.add("hidden");
    if (type?.value === "date")  single?.classList.remove("hidden");
    if (type?.value === "range") range?.classList.remove("hidden");
  }

  function getVehicleIdFromImmat(val) {
    const norm = String(val||'').trim().toLowerCase();
    const hit = vehicles.find(v => String(v.immatriculation).toLowerCase() === norm);
    return hit ? hit.id : null;
  }

  vehicleSearch?.addEventListener('change', () => {
    const vid = getVehicleIdFromImmat(vehicleSearch.value);
    if (!vid) return;

    const params = new URLSearchParams(window.location.search);
    params.delete('focus_trajet_id'); params.delete('mode'); params.delete('page');

    const url = urlTemplate.replace('__VID__', vid) + (params.toString() ? ('?' + params.toString()) : '');
    window.location.href = url;
  });

  let tmr=null;
  function debounceSubmit(ms=450){ clearTimeout(tmr); tmr=setTimeout(()=>form.submit(), ms); }

  updateUI();

  type?.addEventListener("change", () => {
    const pageHidden = form.querySelector('input[name="page"]');
    if (pageHidden) pageHidden.value = '';

    if (type.value !== 'date' && dateInput) dateInput.value = '';
    if (type.value !== 'range') { if(startDateInput) startDateInput.value=''; if(endDateInput) endDateInput.value=''; }

    updateUI();
    // auto-submit only when enough inputs
    if (type.value === 'date' && !(dateInput && dateInput.value)) return;
    if (type.value === 'range' && !((startDateInput && startDateInput.value) || (endDateInput && endDateInput.value))) return;

    form.submit();
  });

  dateInput?.addEventListener('change', ()=> { if(type.value==='date') form.submit(); });
  [startDateInput, endDateInput].forEach(inp => inp?.addEventListener('change', ()=> { if(type.value==='range') form.submit(); }));

  [startTimeInput, endTimeInput].forEach(inp => {
    inp?.addEventListener('input', ()=>debounceSubmit(500));
    inp?.addEventListener('change', ()=>debounceSubmit(200));
  });
});
</script>

{{-- MAP + TRACKS + REPLAY (stable boot) --}}
<script>
  window.__startMap = function bootMap() {
    try {
      console.log('[Trajets] bootMap start…');

      const mapDiv = document.getElementById('map');
      if (!mapDiv) { console.error('[Trajets] #map not found'); return; }
      if (!window.google || !google.maps) { console.error('[Trajets] Google Maps not ready'); return; }

      const tracksRaw = @json($tracks ?? []);
      const focusId = @json($focusId);

      console.log('[Trajets] tracksRaw:', tracksRaw?.length || 0, 'focusId:', focusId);

      // UI
      const btnMode = document.getElementById('btnMode');
      const btnReplay = document.getElementById('btnReplay');
      const panelMode = document.getElementById('panelMode');
      const panelReplay = document.getElementById('panelReplay');

      const btnTraffic = document.getElementById('btnTraffic');
      const btnLocate = document.getElementById('btnLocate');
      const mapTypeBtns = Array.from(document.querySelectorAll('[data-maptype]'));

      const rpPrev  = document.getElementById('rpPrev');
      const rpPlay  = document.getElementById('rpPlay');
      const rpPause = document.getElementById('rpPause');
      const rpStop  = document.getElementById('rpStop');
      const rpNext  = document.getElementById('rpNext');
      const rpSlow  = document.getElementById('rpSlow');
      const rpFast  = document.getElementById('rpFast');
      const rpSpeed = document.getElementById('rpSpeed');
      const rpTime  = document.getElementById('rpTime');
      const rpCoords= document.getElementById('rpCoords');
      const rpV     = document.getElementById('rpV');
      const rpBar   = document.getElementById('rpBar');

      // safe primary color
      const primary = (getComputedStyle(document.documentElement).getPropertyValue('--color-primary') || '').trim() || '#F58220';

      // ----------------------------
      // panels: click only + autohide
      // ----------------------------
      window.__replayPlaying = false;

      function makeAutoHide(panelEl, getPlaying) {
        let timer=null;
        let inside=false;

        function schedule(ms){
          clearTimeout(timer);
          timer=setTimeout(()=>{ if(!inside) panelEl.style.display='none'; }, ms);
        }

        panelEl.addEventListener('mouseenter', ()=>{ inside=true; clearTimeout(timer); });
        panelEl.addEventListener('mouseleave', ()=>{
          inside=false;
          schedule(getPlaying() ? 600 : 5000);
        });

        panelEl.__schedule = schedule;
      }

      if(panelMode) makeAutoHide(panelMode, ()=>window.__replayPlaying);
      if(panelReplay) makeAutoHide(panelReplay, ()=>window.__replayPlaying);

      function togglePanel(panelEl, otherEl){
        if(!panelEl) return;
        const isOpen = panelEl.style.display === 'block';
        if(otherEl) otherEl.style.display = 'none';
        panelEl.style.display = isOpen ? 'none' : 'block';
        if(!isOpen) panelEl.__schedule && panelEl.__schedule(1400);
      }

      btnMode?.addEventListener('click', (e)=>{ e.stopPropagation(); togglePanel(panelMode, panelReplay); });
      btnReplay?.addEventListener('click', (e)=>{ e.stopPropagation(); togglePanel(panelReplay, panelMode); });

      document.querySelectorAll('[data-close]').forEach(x=>{
        x.addEventListener('click', ()=>{
          const id = x.getAttribute('data-close');
          const el = document.getElementById(id);
          if(el) el.style.display='none';
        });
      });

      document.addEventListener('click', (e)=>{
        const insideMode = panelMode && panelMode.contains(e.target);
        const insideReplay = panelReplay && panelReplay.contains(e.target);
        const isBtn = (btnMode && (e.target===btnMode || btnMode.contains(e.target))) || (btnReplay && (e.target===btnReplay || btnReplay.contains(e.target)));
        if(insideMode || insideReplay || isBtn) return;
        if(panelMode) panelMode.style.display='none';
        if(panelReplay) panelReplay.style.display='none';
      });

      // ----------------------------
      // center
      // ----------------------------
      let center = { lat: 4.05, lng: 9.7 };
      for (const tr of (tracksRaw||[])) {
        if (tr?.points?.length) { center = { lat:+tr.points[0].lat, lng:+tr.points[0].lng }; break; }
        if (tr?.start?.lat) { center = { lat:+tr.start.lat, lng:+tr.start.lng }; break; }
      }

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

      // traffic
      const trafficLayer = new google.maps.TrafficLayer();
      let trafficOn=false;
      btnTraffic?.addEventListener('click', ()=>{
        trafficOn = !trafficOn;
        trafficLayer.setMap(trafficOn ? map : null);
        btnTraffic.classList.toggle('active', trafficOn);
      });

      // map types
      mapTypeBtns.forEach(btn=>{
        btn.addEventListener('click', ()=>{
          mapTypeBtns.forEach(b=>b.classList.remove('active'));
          btn.classList.add('active');
          map.setMapTypeId(btn.getAttribute('data-maptype'));
        });
      });

      // locate
      let myMarker=null, myCircle=null;
      btnLocate?.addEventListener('click', ()=>{
        if(!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((pos)=>{
          const lat=pos.coords.latitude, lng=pos.coords.longitude, acc=pos.coords.accuracy||0;
          const latLng=new google.maps.LatLng(lat,lng);
          map.panTo(latLng); if(map.getZoom()<16) map.setZoom(16);

          if(!myMarker){
            myMarker=new google.maps.Marker({
              map, position:latLng, title:"Ma position",
              icon:{ path:google.maps.SymbolPath.CIRCLE, fillColor:"#2563eb", fillOpacity:1, strokeColor:"#fff", strokeWeight:2, scale:9 }
            });
          } else myMarker.setPosition(latLng);

          if(!myCircle){
            myCircle=new google.maps.Circle({ map, center:latLng, radius:acc, strokeOpacity:.2, fillOpacity:.08 });
          } else { myCircle.setCenter(latLng); myCircle.setRadius(acc); }
        }, ()=>{}, { enableHighAccuracy:true, timeout:8000 });
      });

      // ----------------------------
      // ✅ points correction (anti contours)
      // ----------------------------
      function haversineMeters(a,b){
        const R=6371000;
        const toRad=x=>x*Math.PI/180;
        const dLat=toRad(b.lat-a.lat);
        const dLng=toRad(b.lng-a.lng);
        const lat1=toRad(a.lat), lat2=toRad(b.lat);
        const s=Math.sin(dLat/2)**2 + Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)**2;
        return 2*R*Math.atan2(Math.sqrt(s), Math.sqrt(1-s));
      }

      function safeTimeMs(t){
        if(!t) return null;
        // t = "YYYY-mm-dd HH:ii:ss" -> "YYYY-mm-ddTHH:ii:ss"
        const iso = String(t).replace(' ','T');
        const ms = Date.parse(iso); // ✅ pas de 'Z' (sinon tu changes la timezone)
        return Number.isNaN(ms) ? null : ms;
      }

      function cleanPoints(raw){
        const pts = (raw||[]).map(p=>({ lat:+p.lat, lng:+p.lng, t:p.t||null, speed:+(p.speed||0) }));
        if(pts.length<2) return pts;

        const out=[];
        let prev=null;

        const MAX_KMH = 140;
        const MAX_JUMP_M = 220;
        const MAX_JUMP_S = 10;
        const MIN_MOVE_M = 3;

        for(const p of pts){
          if(!prev){ out.push(p); prev=p; continue; }
          const d = haversineMeters(prev,p);
          if(d < MIN_MOVE_M) continue;

          const t1 = safeTimeMs(prev.t);
          const t2 = safeTimeMs(p.t);
          if(t1!=null && t2!=null){
            const dt = Math.abs(t2-t1)/1000;
            if(dt>0){
              const v = (d/dt)*3.6;
              if(v > MAX_KMH) continue;
              if(d > MAX_JUMP_M && dt <= MAX_JUMP_S) continue;
            } else {
              if(d > MAX_JUMP_M) continue;
            }
          } else {
            if(d > MAX_JUMP_M*3) continue;
          }

          out.push(p);
          prev=p;
        }
        return out;
      }

      function smooth(points, w=5){
        if(points.length < 5) return points;
        if(w%2===0) w+=1;
        const half=Math.floor(w/2);
        const res=[];
        for(let i=0;i<points.length;i++){
          let from=Math.max(0,i-half), to=Math.min(points.length-1,i+half);
          let sumLat=0,sumLng=0,c=0;
          for(let j=from;j<=to;j++){ sumLat+=points[j].lat; sumLng+=points[j].lng; c++; }
          res.push({ ...points[i], lat:sumLat/c, lng:sumLng/c });
        }
        return res;
      }

      // ✅ IMPORTANT: on coupe en segments au lieu de tracer un grand trait
      function splitSegments(points){
        const segs=[];
        if(points.length<2) return segs;

        let seg=[points[0]];
        for(let i=1;i<points.length;i++){
          const a=points[i-1], b=points[i];
          const d=haversineMeters(a,b);

          // seuil de coupure : évite les contours anormaux
          if(d > 160){
            if(seg.length>=2) segs.push(seg);
            seg=[b];
          }else{
            seg.push(b);
          }
        }
        if(seg.length>=2) segs.push(seg);
        return segs;
      }

      function prepareTrack(tr){
        const pts0 = tr.points || [];
        let pts = cleanPoints(pts0);
        pts = smooth(pts, 5);
        const segments = splitSegments(pts);
        return { ...tr, __pts: pts, __segments: segments };
      }

      const tracks = (tracksRaw||[]).map(prepareTrack);

      // draw
      const boundsAll = new google.maps.LatLngBounds();

      function circleIcon(fill){
        return { path: google.maps.SymbolPath.CIRCLE, fillColor: fill, fillOpacity: 1, strokeColor: "#fff", strokeWeight: 2, scale: 9 };
      }

      const hoverInfo = new google.maps.InfoWindow();

      function nearestPoint(latLng, points){
        if(!points || !points.length) return null;
        const p = { lat:latLng.lat(), lng:latLng.lng() };
        let best=points[0], bestD=Infinity;
        for(const x of points){
          const dx=p.lat-x.lat, dy=p.lng-x.lng;
          const d=dx*dx+dy*dy;
          if(d<bestD){ bestD=d; best=x; }
        }
        return best;
      }

      function showHover(tr, isFocus, e){
        const closest = nearestPoint(e.latLng, tr.__pts);
        const lat = closest ? closest.lat : e.latLng.lat();
        const lng = closest ? closest.lng : e.latLng.lng();
        const t   = closest?.t || tr.start_time || '';
        const spd = closest ? (closest.speed ?? 0) : null;

        hoverInfo.setContent(`
          <div style="font-size:13px; line-height:1.35">
            <b>Trajet #${tr.trajet_id}${isFocus ? ' (sélectionné)' : ''}</b><br>
            <span>Heure: <b>${t}</b></span><br>
            <span>Lat: <b>${Number(lat).toFixed(6)}</b></span><br>
            <span>Lng: <b>${Number(lng).toFixed(6)}</b></span><br>
            ${spd!==null ? `<span>Vitesse: <b>${Number(spd).toFixed(1)} km/h</b></span>` : ``}
            <div style="margin-top:6px;font-size:12px;color:#6b7280;">Clique pour activer le replay sur ce trajet</div>
          </div>
        `);
        hoverInfo.setPosition(new google.maps.LatLng(lat,lng));
        hoverInfo.open({ map });
      }

      tracks.forEach(tr=>{
        const isFocus = focusId && String(tr.trajet_id)===String(focusId);
        const segs = tr.__segments || [];
        if(!segs.length) return;

        segs.forEach(seg=>{
          const path = seg.map(p=>({lat:p.lat,lng:p.lng}));
          path.forEach(p=>boundsAll.extend(p));

          new google.maps.Polyline({
            path,
            strokeColor: primary,
            strokeOpacity: isFocus ? 1 : 0.82,
            strokeWeight: isFocus ? 7 : 4,
            geodesic: true,
            icons: [{
              icon: { path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW, strokeWeight: 2, scale: 2.4 },
              offset: '0',
              repeat: '120px'
            }],
            map
          });

          const hit = new google.maps.Polyline({
            path,
            strokeColor: primary,
            strokeOpacity: 0,
            strokeWeight: 26,
            clickable: true,
            map
          });

          // markers start/end for each segment
          const startPos = path[0], endPos = path[path.length-1];
          new google.maps.Marker({ position:startPos, map, icon: circleIcon("#16a34a"), label:{text:"D",color:"#fff",fontWeight:"800"} });
          new google.maps.Marker({ position:endPos, map, icon: circleIcon("#dc2626"), label:{text:"A",color:"#fff",fontWeight:"800"} });

          hit.addListener('mousemove', (e)=>showHover(tr,isFocus,e));
          hit.addListener('mouseout', ()=>hoverInfo.close());
          hit.addListener('click', (e)=>{ showHover(tr,isFocus,e); selectTrackForReplay(tr,e.latLng); });
        });
      });

      if(!boundsAll.isEmpty()){
        const focusTrack = focusId ? tracks.find(x=>String(x.trajet_id)===String(focusId)) : null;
        if(focusTrack?.__pts?.length>=2){
          const b=new google.maps.LatLngBounds();
          focusTrack.__pts.forEach(p=>b.extend({lat:p.lat,lng:p.lng}));
          map.fitBounds(b,40);
          google.maps.event.addListenerOnce(map,"idle",()=>{ if(map.getZoom()>18) map.setZoom(18); });
        }else{
          map.fitBounds(boundsAll);
          google.maps.event.addListenerOnce(map,"idle",()=>{ if(map.getZoom()>17) map.setZoom(17); });
        }
      }

      // ----------------------------
      // ✅ Replay
      // ----------------------------
      let currentTrack=null;
      let currentPoints=[];
      let idx=0;
      let timer=null;
      let marker=null;
      let trail=null;

      const speedSteps=[2,4,8,12,16,24,32];
      let speedIndex=2; // x8 default
      const speedMult=()=>speedSteps[speedIndex] || 8;
      const updateSpeedUI=()=>{ if(rpSpeed) rpSpeed.textContent=`x${speedMult()}`; };
      updateSpeedUI();

      function ensureReplay(){
        if(marker) return;
        marker = new google.maps.Marker({
          map,
          position: map.getCenter(),
          title: "Replay",
          icon: { path:google.maps.SymbolPath.CIRCLE, fillColor: primary, fillOpacity:1, strokeColor:"#fff", strokeWeight:2, scale:8 }
        });
        trail = new google.maps.Polyline({
          map,
          path: [],
          strokeColor: primary,
          strokeOpacity: 0.9,
          strokeWeight: 5,
          geodesic: true
        });
      }

      function pause(){
        window.__replayPlaying=false;
        if(timer){ clearInterval(timer); timer=null; }
      }

      function stop(){
        pause();
        idx=0;
        if(trail) trail.setPath([]);
        if(rpBar) rpBar.style.width='0%';
        if(rpTime) rpTime.textContent='—';
        if(rpCoords) rpCoords.textContent='—';
        if(rpV) rpV.textContent='—';
      }

      function stepTo(i){
        if(!currentPoints.length) return;
        idx = Math.max(0, Math.min(currentPoints.length-1, i));
        const p=currentPoints[idx];
        ensureReplay();
        const pos=new google.maps.LatLng(p.lat,p.lng);
        marker.setPosition(pos);

        // follow
        map.panTo(pos);

        // trail
        const path=trail.getPath();
        path.push(pos);
        if(path.getLength()>2000) path.removeAt(0);

        if(rpTime) rpTime.textContent = p.t || '—';
        if(rpCoords) rpCoords.textContent = `Lat ${p.lat.toFixed(6)} • Lng ${p.lng.toFixed(6)}`;
        if(rpV) rpV.textContent = `${Number(p.speed||0).toFixed(1)} km/h`;

        const pct=(idx/Math.max(1,currentPoints.length-1))*100;
        if(rpBar) rpBar.style.width = `${pct.toFixed(2)}%`;
      }

      function tick(){
        const step = Math.max(1, Math.floor(speedMult()/2));
        stepTo(idx + step);
        if(idx >= currentPoints.length-1) pause();
      }

      function play(){
        if(!currentPoints.length) return;
        window.__replayPlaying=true;

        // hide panel on play (your rule)
        if(panelReplay) panelReplay.style.display='none';

        if(timer) clearInterval(timer);
        timer = setInterval(tick, 70);
      }

      function selectTrackForReplay(tr, latLng){
        currentTrack=tr;
        currentPoints=(tr.__pts || []).slice();
        if(currentPoints.length<2) return;

        stop();

        if(latLng){
          const closest=nearestPoint(latLng,currentPoints);
          const j=currentPoints.findIndex(x=>x===closest);
          idx = j>=0 ? j : 0;
        } else idx=0;

        stepTo(idx);

        // open replay briefly
        if(panelReplay){
          panelReplay.style.display='block';
          panelReplay.__schedule && panelReplay.__schedule(1400);
        }
      }

      // default pick
      const defaultTrack = focusId ? tracks.find(x=>String(x.trajet_id)===String(focusId)) : (tracks[0]||null);
      if(defaultTrack) selectTrackForReplay(defaultTrack);

      rpPlay?.addEventListener('click', ()=>play());
      rpPause?.addEventListener('click', ()=>pause());
      rpStop?.addEventListener('click', ()=>stop());
      rpPrev?.addEventListener('click', ()=>{ pause(); stepTo(idx-50); });
      rpNext?.addEventListener('click', ()=>{ pause(); stepTo(idx+50); });

      rpSlow?.addEventListener('click', ()=>{ speedIndex=Math.max(0,speedIndex-1); updateSpeedUI(); });
      rpFast?.addEventListener('click', ()=>{ speedIndex=Math.min(speedSteps.length-1,speedIndex+1); updateSpeedUI(); });

      console.log('[Trajets] bootMap OK ✅');
    } catch (e) {
      console.error('[Trajets] bootMap crash:', e);
    }
  };
</script>
@endsection