@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@php
    $isOpen = isset($voitureEdit) || (($errors ?? null) && $errors->any());

    // Payload villes pour JS (geom = string GeoJSON stocké en DB)
    $villesPayload = ($villes ?? collect())->map(function($v){
        return [
            'id'   => $v->id,
            'code' => $v->code_ville ?: ('ID-'.$v->id),
            'name' => $v->name,
            'geom' => $v->geom,
        ];
    })->values();
@endphp

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Toggle formulaire --}}
    <div class="flex justify-end">
        <button id="toggle-form" class="btn-primary" type="button">
            <i class="fas fa-plus mr-2"></i>
            @if(isset($voitureEdit))
                Modifier le véhicule
            @else
                Ajouter un véhicule
            @endif
        </button>
    </div>

    {{-- Formulaire --}}
    <div id="vehicle-form"
         class="ui-card mt-4 overflow-hidden transition-all duration-500 ease-in-out
         {{ $isOpen ? 'max-h-[2500px] opacity-100' : 'max-h-0 opacity-0' }}">

        <h2 class="text-xl font-bold font-orbitron mb-6">
            @if(isset($voitureEdit))
                Modifier un Véhicule
            @else
                Ajouter un Véhicule
            @endif
        </h2>

        <form id="vehicle-form-el"
              action="@if(isset($voitureEdit)) {{ route('tracking.vehicles.update', $voitureEdit->id) }} @else {{ route('tracking.vehicles.store') }} @endif"
              method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if(isset($voitureEdit))
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="immatriculation" class="block text-sm font-medium text-secondary">Immatriculation</label>
                    <input type="text" class="ui-input-style mt-1" id="immatriculation" name="immatriculation"
                           placeholder="ABC-123-XY"
                           value="{{ old('immatriculation', $voitureEdit->immatriculation ?? '') }}" required>
                </div>

                <div>
                    <label for="vin" class="block text-sm font-medium text-secondary">VIN (optionnel)</label>
                    <input type="text" class="ui-input-style mt-1" id="vin" name="vin"
                           placeholder="VIN (facultatif)"
                           value="{{ old('vin', $voitureEdit->vin ?? '') }}">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="marque" class="block text-sm font-medium text-secondary">Marque</label>
                    {{-- rempli en JS depuis public/marque_voiture.json --}}
                    <select id="marque" name="marque" class="ui-input-style mt-1"
                            data-current="{{ old('marque', $voitureEdit->marque ?? '') }}" required>
                        <option value="">Chargement des marques…</option>
                    </select>
                    <p class="text-xs text-secondary mt-1">La liste vient de <code>public/marque_voiture.json</code>.</p>
                </div>

                <div>
                    <label for="model" class="block text-sm font-medium text-secondary">Modèle</label>
                    <input type="text" class="ui-input-style mt-1" id="model" name="model"
                           placeholder="SUV, Berline, etc."
                           value="{{ old('model', $voitureEdit->model ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mac_id_gps" class="block text-sm font-medium text-secondary">IMEI / MAC GPS (recherche)</label>

                    <input type="text"
                           class="ui-input-style mt-1"
                           id="mac_id_gps"
                           name="mac_id_gps"
                           list="gps_suggestions"
                           placeholder="Commence à taper…"
                           autocomplete="off"
                           value="{{ old('mac_id_gps', $voitureEdit->mac_id_gps ?? '') }}"
                           required>

                    <datalist id="gps_suggestions"></datalist>

                    <p class="text-xs text-secondary mt-1" id="gps_hint">
                        Suggestions depuis la table <code>sim_gps</code> (mac_id).
                    </p>
                </div>

                <div>
                    <label for="couleur" class="block text-sm font-medium text-secondary">Couleur</label>
                    <input type="color" class="ui-input-style mt-1 h-10 w-full p-0 border-0 cursor-pointer" id="couleur"
                           name="couleur" value="{{ old('couleur', $voitureEdit->couleur ?? '#000000') }}" required>
                </div>

                <div>
                    <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                    <input type="file" class="ui-input-style mt-1" id="photo" name="photo">
                    @if(isset($voitureEdit) && $voitureEdit->photo)
                        <img src="{{ asset('storage/' . $voitureEdit->photo) }}" class="h-10 w-10 object-cover rounded mt-2" alt="Photo">
                    @endif
                </div>
            </div>

            {{-- Geofence : sélection ou dessin --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="city_select" class="block text-sm font-medium text-secondary">
                        Ville (BD) : choisir ou personnaliser
                    </label>

                    <select id="city_select" class="ui-input-style mt-1">
                        <option value="">-- Choisir une ville --</option>
                        @foreach($villes ?? [] as $ville)
                            @php $code = $ville->code_ville ?: ('ID-'.$ville->id); @endphp
                            <option value="{{ $code }}" @selected(old('geofence_city_code', $voitureEdit->geofence_city_code ?? '') === $code)>
                                {{ $ville->name }} — {{ $code }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-secondary mt-1">Polygones depuis <code>villes.geom</code> (GeoJSON).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-secondary">Mode Geofence</label>
                    <div class="mt-1">
                        <label class="inline-flex items-center mr-4">
                            <input type="radio" name="geofence_mode" value="city" class="form-radio" id="mode_city" checked>
                            <span class="ml-2">Ville prédéfinie</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="geofence_mode" value="custom" class="form-radio" id="mode_custom">
                            <span class="ml-2">Personnalisé (dessiner)</span>
                        </label>
                    </div>
                    <p class="text-xs text-secondary mt-1">
                        (Le backend lit <code>geofence_is_custom</code> via le champ caché ci-dessous.)
                    </p>
                </div>
            </div>

            {{-- Carte (Google Maps) --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-secondary mb-2">
                    Carte - sélectionner / personnaliser
                </label>

                <div id="map" class="w-full rounded border border-gray-300" style="height: 420px;"></div>

                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button" id="start-draw" class="btn-secondary">Commencer dessin</button>
                    <button type="button" id="finish-draw" class="btn-primary">Terminer polygone</button>
                    <button type="button" id="undo-point" class="btn-warning">Annuler dernier point</button>
                    <button type="button" id="reset-to-city" class="btn-secondary">Réinitialiser vers ville</button>
                </div>
                <p class="text-xs text-secondary mt-2">
                    En mode personnalisé, cliquer sur la carte ajoute un point.
                </p>
            </div>

            {{-- Champs cachés pour backend --}}
            @php
                $initialGeofencePolygon = '';
                if (isset($voitureEdit) && !empty($voitureEdit->geofence_zone)) {
                    $coords = json_decode($voitureEdit->geofence_zone, true); // [[lng,lat],...]
                    if (is_array($coords) && count($coords) >= 3) {
                        $initialGeofencePolygon = json_encode([
                            'type' => 'Feature',
                            'properties' => [
                                'source' => ($voitureEdit->geofence_is_custom ? 'custom' : 'city'),
                                'code' => $voitureEdit->geofence_city_code,
                            ],
                            'geometry' => [
                                'type' => 'Polygon',
                                'coordinates' => [$coords],
                            ],
                        ]);
                    }
                }
            @endphp

            <input type="hidden" name="geofence_polygon" id="geofence_polygon"
                   value="{{ old('geofence_polygon', $initialGeofencePolygon) }}">

            <input type="hidden" name="geofence_city_code" id="geofence_city_code"
                   value="{{ old('geofence_city_code', $voitureEdit->geofence_city_code ?? '') }}">
            <input type="hidden" name="geofence_city_name" id="geofence_city_name"
                   value="{{ old('geofence_city_name', $voitureEdit->geofence_city_name ?? '') }}">
            <input type="hidden" name="geofence_is_custom" id="geofence_is_custom"
                   value="{{ old('geofence_is_custom', $voitureEdit->geofence_is_custom ?? 0) }}">

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
    <div class="ui-card mt-2">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste des Véhicules</h2>

        <div class="ui-table-container shadow-md">
            <table id="vehiclesTable" class="ui-table w-full">
                <thead>
                <tr>
                    <th>Immatriculation</th>
                    <th>VIN</th>
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
                        <td>{{ $voiture->vin ?? '-' }}</td>
                        <td>{{ $voiture->model }}</td>
                        <td>{{ $voiture->marque }}</td>
                        <td><div class="w-8 h-8 rounded" style="background-color: {{ $voiture->couleur }}"></div></td>
                        <td>{{ $voiture->mac_id_gps }}</td>
                        <td>
                            @if($voiture->photo)
                                <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo"
                                     class="h-10 w-10 object-cover rounded">
                            @endif
                        </td>
                        <td class="space-x-1 whitespace-nowrap">
                            <a href="{{ route('tracking.vehicles', ['edit' => $voiture->id]) }}"
                               class="btn-secondary btn-edit" title="Modifier">
                                <i class="fas fa-edit mr-2"></i>
                            </a>

                            <form action="{{ route('tracking.vehicles.destroy', $voiture->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-delete"
                                        onclick="return confirm('Voulez-vous vraiment supprimer ce véhicule ?');"
                                        title="Supprimer">
                                    <i class="fas fa-trash mr-2"></i>
                                </button>
                            </form>

                            <a href="{{ route('tracking.vehicles.geofence', $voiture->id) }}"
                               class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200 p-2"
                               title="Voir le Geofence">
                                <i class="fas fa-eye"></i>
                            </a>

                            <button type="button"
                                    class="text-secondary hover:text-yellow-500 transition-colors p-2"
                                    title="Paramètres d’alertes"
                                    onclick="openVehicleAlertModal(
                                        {{ $voiture->id }},
                                        '{{ addslashes($voiture->immatriculation.' - '.$voiture->marque.' '.$voiture->model) }}',
                                        '{{ $voiture->time_zone_start ?? '' }}',
                                        '{{ $voiture->time_zone_end ?? '' }}',
                                        '{{ $voiture->speed_zone ?? '' }}'
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

{{-- Modal Paramètres d’alertes --}}
<div id="vehicleAlertModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black bg-opacity-50">
    <div class="bg-card rounded-2xl shadow-2xl w-full max-w-xl p-6 relative ui-card">

        <button type="button" id="closeVehicleAlertModalBtn"
                class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
            Paramètres d’alertes du véhicule
        </h2>

        <p class="text-sm text-secondary mb-4">
            <span class="font-medium">Véhicule ciblé :</span>
            <span id="vehicleAlertVehicleLabel" class="font-semibold text-primary"></span>
        </p>

        <form id="vehicle-alerts-form"
              method="POST"
              action="#"
              data-url-template="{{ route('tracking.vehicles.alerts.define', ['voiture' => '__ID__']) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="time_zone_start" class="block text-sm font-medium text-secondary mb-1">
                        Heure de début (TimeZone)
                    </label>
                    <input type="time" id="time_zone_start" name="time_zone_start" class="ui-input-style">
                </div>
                <div>
                    <label for="time_zone_end" class="block text-sm font-medium text-secondary mb-1">
                        Heure de fin (TimeZone)
                    </label>
                    <input type="time" id="time_zone_end" name="time_zone_end" class="ui-input-style">
                </div>
            </div>

            <div class="mb-4">
                <label for="speed_zone" class="block text-sm font-medium text-secondary mb-1">
                    Vitesse maximale autorisée (SpeedZone)
                </label>
                <input type="number" step="1" min="0" id="speed_zone" name="speed_zone" class="ui-input-style"
                       placeholder="Ex: 80">
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
                <button type="button" id="cancelVehicleAlertBtn" class="btn-secondary">Annuler</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- JSON villes DB pour Google Maps --}}
<script type="application/json" id="villes-db-json">@json($villesPayload)</script>

@endsection


@push('scripts')
<script>
/* ===============================
   Modal TimeZone / SpeedZone
================================ */
(function () {
  const modal = document.getElementById('vehicleAlertModal');
  const form = document.getElementById('vehicle-alerts-form');
  const labelEl = document.getElementById('vehicleAlertVehicleLabel');

  const closeBtn = document.getElementById('closeVehicleAlertModalBtn');
  const cancelBtn = document.getElementById('cancelVehicleAlertBtn');

  const inputStart = document.getElementById('time_zone_start');
  const inputEnd = document.getElementById('time_zone_end');
  const inputSpeed = document.getElementById('speed_zone');

  function normalizeTime(t) {
    if (!t) return '';
    const s = String(t).trim();
    return s.length >= 5 ? s.slice(0, 5) : s;
  }

  function openModal() {
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  window.openVehicleAlertModal = function (vehicleId, label, start, end, speed) {
    if (!form) return;

    if (labelEl) labelEl.textContent = label || '';

    const tpl = form.dataset.urlTemplate || '';
    form.action = tpl.replace('__ID__', String(vehicleId));

    if (inputStart) inputStart.value = normalizeTime(start);
    if (inputEnd) inputEnd.value = normalizeTime(end);

    if (inputSpeed) {
      const sp = (speed === null || speed === undefined) ? '' : String(speed).trim();
      inputSpeed.value = sp;
    }

    openModal();
  };

  closeBtn && closeBtn.addEventListener('click', closeModal);
  cancelBtn && cancelBtn.addEventListener('click', closeModal);

  modal && modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
})();
</script>

<script>
/* ===============================
   DataTable (si DataTables présent)
================================ */
document.addEventListener('DOMContentLoaded', () => {
  if (window.jQuery && $.fn.DataTable) {
    $('#vehiclesTable').DataTable({
      responsive: true,
      language: { url: "/datatables/i18n/fr-FR.json" }
    });
  }
});
</script>

<script>
/* ===============================
   Marque select (public/marque_voiture.json)
================================ */
document.addEventListener('DOMContentLoaded', () => {
  const select = document.getElementById('marque');
  if (!select) return;

  const current = select.getAttribute('data-current') || '';

  fetch("{{ asset('marque_voiture.json') }}", { cache: 'no-cache' })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(list => {
      select.innerHTML = '<option value="">-- Choisir une marque --</option>';

      list.sort((a,b) => (a.tier || '').localeCompare(b.tier || '') || (a.brand || '').localeCompare(b.brand || ''));

      let lastTier = null;
      list.forEach(item => {
        const tier = item.tier || '';
        const brand = item.brand || '';
        if (!brand) return;

        if (tier && tier !== lastTier) {
          const sep = document.createElement('option');
          sep.disabled = true;
          sep.textContent = `── Tier ${tier} ──`;
          select.appendChild(sep);
          lastTier = tier;
        }

        const opt = document.createElement('option');
        opt.value = brand;
        opt.textContent = brand;
        if (current && current === brand) opt.selected = true;
        select.appendChild(opt);
      });

      if (current && !Array.from(select.options).some(o => o.value === current)) {
        const opt = document.createElement('option');
        opt.value = current;
        opt.textContent = current + ' (existant)';
        opt.selected = true;
        select.appendChild(opt);
      }
    })
    .catch(() => {
      select.innerHTML = '<option value="">Erreur chargement marques</option>';
    });
});
</script>

<script>
/* ===============================
   Auto-suggest IMEI/MAC (sim_gps.mac_id)
   Endpoint: GET /sim-gps/search?q=...
================================ */
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('mac_id_gps');
  const datalist = document.getElementById('gps_suggestions');
  if (!input || !datalist) return;

  const searchUrl = "{{ url('/sim-gps/search') }}";
  let timer = null;

  function clearOptions() {
    while (datalist.firstChild) datalist.removeChild(datalist.firstChild);
  }

  input.addEventListener('input', () => {
    const q = (input.value || '').trim();

    clearTimeout(timer);
    if (q.length < 2) { clearOptions(); return; }

    timer = setTimeout(() => {
      fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(items => {
          clearOptions();
          (items || []).slice(0, 15).forEach(it => {
            if (!it || !it.mac_id) return;
            const opt = document.createElement('option');
            opt.value = it.mac_id;
            datalist.appendChild(opt);
          });
        })
        .catch(() => clearOptions());
    }, 250);
  });
});
</script>

<script>
/* ===============================
   Google Maps Geofence (DB villes)
   ✅ Fiabilisé (attend que la map soit visible + resize + pas de double chargement)
================================ */
(function(){
  let map = null;
  let googleReady = false;

  const cityPolygons = new Map(); // code -> { polygon, data, path }
  let selectedCityCode = null;

  let drawing = false;
  let drawPoints = [];
  let drawMarkers = [];
  let tempLine = null;
  let customPolygon = null;

  let formWrap, toggleBtn, citySelect, modeCity, modeCustom;
  let startBtn, finishBtn, undoBtn, resetBtn;
  let hiddenPolygon, hiddenCityCode, hiddenCityName, hiddenIsCustom;

  function qs(id){ return document.getElementById(id); }

  function isMapDivUsable() {
    const mapDiv = qs('map');
    if (!mapDiv) return false;
    const r = mapDiv.getBoundingClientRect();
    return r.width > 200 && r.height > 200;
  }

  function safeResize() {
    if (!map || !window.google?.maps) return;
    const c = map.getCenter();
    google.maps.event.trigger(map, 'resize');
    if (c) map.setCenter(c);
  }

  function waitForVisibleThen(fn, tries = 0) {
    if (isMapDivUsable()) return fn();
    if (tries > 90) return; // ~90 frames max
    requestAnimationFrame(() => waitForVisibleThen(fn, tries + 1));
  }

  function readVilles() {
    const el = qs('villes-db-json');
    if (!el) return [];
    try { return JSON.parse(el.textContent || '[]') || []; } catch(e){ return []; }
  }

  function parseVilleGeom(geomStr) {
    if (!geomStr) return null;
    let obj = null;
    try { obj = JSON.parse(geomStr); } catch(e){ return null; }
    if (!obj) return null;

    let g = obj;
    if (g.type === 'FeatureCollection' && Array.isArray(g.features) && g.features[0]) g = g.features[0];
    if (g.type === 'Feature' && g.geometry) g = g.geometry;

    if (!g || !g.type || !g.coordinates) return null;

    if (g.type === 'Polygon') return g.coordinates?.[0] || null;
    if (g.type === 'MultiPolygon') return g.coordinates?.[0]?.[0] || null;
    return null;
  }

  function lngLatToPath(coordsLngLat) {
    if (!Array.isArray(coordsLngLat)) return [];
    let coords = coordsLngLat.slice();
    if (coords.length >= 2) {
      const f = coords[0], l = coords[coords.length-1];
      if (f && l && f[0] === l[0] && f[1] === l[1]) coords = coords.slice(0, -1);
    }
    return coords
      .map(p => ({ lat: Number(p[1]), lng: Number(p[0]) }))
      .filter(p => Number.isFinite(p.lat) && Number.isFinite(p.lng));
  }

  function buildFeatureFromPath(path, source, code) {
    const coords = (path || []).map(p => [p.lng, p.lat]);
    if (coords.length > 0) {
      const f = coords[0], l = coords[coords.length - 1];
      if (f[0] !== l[0] || f[1] !== l[1]) coords.push([f[0], f[1]]);
    }
    return {
      type: 'Feature',
      properties: { source: source || 'custom', code: code || null },
      geometry: { type: 'Polygon', coordinates: [coords] }
    };
  }

  function ensureCustomPolygon() {
    if (customPolygon || !map) return;
    customPolygon = new google.maps.Polygon({
      map,
      paths: [],
      strokeColor: '#4361ee',
      strokeOpacity: 1,
      strokeWeight: 3,
      fillOpacity: 0.15,
      clickable: false
    });
  }

  function drawCustomFinal(path) {
    ensureCustomPolygon();
    customPolygon.setPaths(path || []);
  }

  function fitToPath(path) {
    if (!map || !path || path.length === 0) return;
    const bounds = new google.maps.LatLngBounds();
    path.forEach(p => bounds.extend(p));
    map.fitBounds(bounds);
  }

  function setCityLayerVisible(show) {
    cityPolygons.forEach(({polygon}) => polygon.setMap(show ? map : null));
  }

  function clearDrawing() {
    drawMarkers.forEach(m => m.setMap(null));
    drawMarkers = [];
    if (tempLine) { tempLine.setMap(null); tempLine = null; }
  }

  function redrawTemp() {
    if (!map || !hiddenPolygon) return;

    if (tempLine) tempLine.setMap(null);
    tempLine = new google.maps.Polyline({
      map,
      path: drawPoints,
      strokeColor: '#4361ee',
      strokeOpacity: 1,
      strokeWeight: 2
    });

    if (drawPoints.length >= 3) {
      drawCustomFinal(drawPoints);
      hiddenPolygon.value = JSON.stringify(buildFeatureFromPath(drawPoints, 'custom', hiddenCityCode?.value || null));
    } else {
      if (customPolygon) customPolygon.setPaths([]);
      hiddenPolygon.value = '';
    }
  }

  function selectCityByCode(code, doFit=true) {
    if (!code) return;
    const pack = cityPolygons.get(code);
    if (!pack) return;

    cityPolygons.forEach(({polygon}) => polygon.setOptions({ strokeWeight: 2, fillOpacity: 0.10 }));
    pack.polygon.setOptions({ strokeWeight: 3, fillOpacity: 0.22 });

    selectedCityCode = code;

    if (hiddenCityCode) hiddenCityCode.value = pack.data.code || '';
    if (hiddenCityName) hiddenCityName.value = pack.data.name || '';
    if (hiddenIsCustom) hiddenIsCustom.value = '0';

    drawCustomFinal(pack.path);
    if (hiddenPolygon) hiddenPolygon.value = JSON.stringify(buildFeatureFromPath(pack.path, 'city', pack.data.code || null));

    if (doFit) fitToPath(pack.path);
  }

  function importCityAsCustomBase(code) {
    const pack = cityPolygons.get(code);
    if (!pack) return;

    enterCustomMode({ resetPolygon: false });

    if (hiddenCityCode) hiddenCityCode.value = pack.data.code || '';
    if (hiddenCityName) hiddenCityName.value = pack.data.name || '';

    clearDrawing();
    drawPoints = pack.path.map(p => ({ lat: p.lat, lng: p.lng }));

    drawPoints.forEach(p => {
      const m = new google.maps.Marker({ map, position: p, clickable:false });
      drawMarkers.push(m);
    });

    redrawTemp();
    drawing = true;
  }

  function enterCustomMode(opts) {
    opts = opts || {};
    if (modeCustom) modeCustom.checked = true;
    if (hiddenIsCustom) hiddenIsCustom.value = '1';
    setCityLayerVisible(false);

    drawing = true;
    if (opts.resetPolygon) {
      drawPoints = [];
      clearDrawing();
      if (customPolygon) customPolygon.setPaths([]);
      if (hiddenPolygon) hiddenPolygon.value = '';
    }
  }

  function enterCityMode() {
    if (modeCity) modeCity.checked = true;
    if (hiddenIsCustom) hiddenIsCustom.value = '0';
    setCityLayerVisible(true);

    drawing = false;
    drawPoints = [];
    clearDrawing();

    const code = (citySelect && citySelect.value) || (hiddenCityCode && hiddenCityCode.value) || selectedCityCode;
    if (code) selectCityByCode(code, false);
  }

  function applyInitialFromHidden() {
    const codeHidden = hiddenCityCode?.value || '';
    if (citySelect && codeHidden) citySelect.value = codeHidden;

    const existing = hiddenPolygon?.value || '';
    const isCustom = (hiddenIsCustom?.value === '1');

    if (isCustom) {
      enterCustomMode({ resetPolygon: false });
      drawing = false;
      setCityLayerVisible(false);
    } else {
      enterCityMode();
      setCityLayerVisible(true);
    }

    if (existing) {
      try {
        const parsed = JSON.parse(existing);
        const coords = parsed?.geometry?.coordinates?.[0] || [];
        const path = lngLatToPath(coords);
        if (path.length >= 3) {
          drawCustomFinal(path);
          if (!isCustom) {
            const code = codeHidden || parsed?.properties?.code;
            if (code) selectCityByCode(code, true);
            else fitToPath(path);
          } else {
            fitToPath(path);
          }
        }
      } catch(e){}
    } else {
      if (!isCustom && codeHidden) selectCityByCode(codeHidden, true);
    }
  }

  function buildCityPolygons() {
    const villes = readVilles();
    villes.forEach(v => {
      const coords = parseVilleGeom(v.geom);
      const path = lngLatToPath(coords);
      if (!path || path.length < 3) return;

      const polygon = new google.maps.Polygon({
        map,
        paths: path,
        strokeColor: '#2b8a3e',
        strokeOpacity: 1,
        strokeWeight: 2,
        fillOpacity: 0.10,
        clickable: true
      });

      polygon.addListener('click', () => {
        if (modeCustom && modeCustom.checked) return;
        if (citySelect) citySelect.value = v.code;
        selectCityByCode(v.code, true);
      });

      cityPolygons.set(v.code, { polygon, data: v, path });
    });
  }

  function initMapImpl() {
    if (map || !googleReady) return;
    const mapDiv = qs('map');
    if (!mapDiv) return;

    map = new google.maps.Map(mapDiv, {
      center: { lat: 4.05, lng: 9.70 },
      zoom: 8,
      mapTypeId: 'roadmap'
    });

    ensureCustomPolygon();
    buildCityPolygons();

    map.addListener('click', (e) => {
      if (!drawing || !(modeCustom && modeCustom.checked)) return;
      const p = { lat: e.latLng.lat(), lng: e.latLng.lng() };
      drawPoints.push(p);

      const m = new google.maps.Marker({ map, position: p, clickable:false });
      drawMarkers.push(m);

      redrawTemp();
    });

    applyInitialFromHidden();

    setTimeout(safeResize, 60);

    // resize auto si le conteneur change
    try {
      const ro = new ResizeObserver(() => safeResize());
      ro.observe(mapDiv);
    } catch (e) {}
  }

  function whenGoogleReady(cb){
    if (window.google?.maps) return cb();
    // app.blade dispatch "gmaps:ready" (si tu as mis le loader conseillé)
    window.addEventListener('gmaps:ready', () => cb(), { once: true });
    // fallback: si callback initMap est utilisé dans app.blade, on attend un peu
    let tries = 0;
    const t = setInterval(() => {
      tries++;
      if (window.google?.maps) { clearInterval(t); cb(); }
      if (tries > 40) clearInterval(t);
    }, 100);
  }

  document.addEventListener('DOMContentLoaded', () => {
    formWrap = qs('vehicle-form');
    toggleBtn = qs('toggle-form');
    citySelect = qs('city_select');
    modeCity = qs('mode_city');
    modeCustom = qs('mode_custom');

    startBtn = qs('start-draw');
    finishBtn = qs('finish-draw');
    undoBtn = qs('undo-point');
    resetBtn = qs('reset-to-city');

    hiddenPolygon = qs('geofence_polygon');
    hiddenCityCode = qs('geofence_city_code');
    hiddenCityName = qs('geofence_city_name');
    hiddenIsCustom = qs('geofence_is_custom');

    // Mode initial radios selon hiddenIsCustom
    if (hiddenIsCustom && hiddenIsCustom.value === '1') {
      if (modeCustom) modeCustom.checked = true;
    } else {
      if (modeCity) modeCity.checked = true;
    }

    modeCustom && modeCustom.addEventListener('change', () => {
      if (modeCustom.checked) enterCustomMode({ resetPolygon: false });
    });

    modeCity && modeCity.addEventListener('change', () => {
      if (modeCity.checked) enterCityMode();
    });

    citySelect && citySelect.addEventListener('change', () => {
      const code = citySelect.value;
      if (!code) return;

      if (modeCustom && modeCustom.checked) importCityAsCustomBase(code);
      else selectCityByCode(code, true);
    });

    startBtn && startBtn.addEventListener('click', () => {
      enterCustomMode({ resetPolygon: true });
      if (hiddenCityCode) hiddenCityCode.value = '';
      if (hiddenCityName) hiddenCityName.value = '';
    });

    undoBtn && undoBtn.addEventListener('click', () => {
      if (!(modeCustom && modeCustom.checked)) return;
      if (drawPoints.length === 0) return;

      drawPoints.pop();
      const m = drawMarkers.pop();
      if (m) m.setMap(null);

      redrawTemp();
    });

    finishBtn && finishBtn.addEventListener('click', () => {
      if (!(modeCustom && modeCustom.checked)) return;

      if (drawPoints.length < 3) {
        alert('Polygone doit avoir ≥3 points');
        return;
      }

      if (hiddenIsCustom) hiddenIsCustom.value = '1';
      if (hiddenPolygon) hiddenPolygon.value = JSON.stringify(buildFeatureFromPath(drawPoints, 'custom', hiddenCityCode?.value || null));
      drawCustomFinal(drawPoints);

      drawing = false;
      drawPoints = [];
      clearDrawing();

      alert('Polygone prêt');
    });

    resetBtn && resetBtn.addEventListener('click', () => {
      const code = (citySelect && citySelect.value) || (hiddenCityCode && hiddenCityCode.value) || selectedCityCode;
      if (!code) { alert('Choisissez une ville'); return; }

      if (modeCustom && modeCustom.checked) importCityAsCustomBase(code);
      else selectCityByCode(code, true);
    });

    function openForm() {
      if (!formWrap) return;
      formWrap.classList.remove('max-h-0', 'opacity-0');
      formWrap.classList.add('max-h-[2500px]', 'opacity-100');

      // attendre fin transition + visibilité réelle
      setTimeout(() => {
        whenGoogleReady(() => {
          googleReady = true;
          waitForVisibleThen(() => {
            initMapImpl();
            setTimeout(safeResize, 90);
          });
        });
      }, 520);
    }

    function closeForm() {
      if (!formWrap) return;
      formWrap.classList.add('max-h-0', 'opacity-0');
      formWrap.classList.remove('max-h-[2500px]', 'opacity-100');
    }

    toggleBtn && toggleBtn.addEventListener('click', () => {
      const isHidden = formWrap.classList.contains('max-h-0');
      if (isHidden) openForm();
      else closeForm();
    });

    // si déjà ouvert (edit/erreurs)
    if (formWrap && !formWrap.classList.contains('max-h-0')) {
      whenGoogleReady(() => {
        googleReady = true;
        waitForVisibleThen(() => {
          initMapImpl();
          setTimeout(safeResize, 90);
        });
      });
    }

    window.addEventListener('resize', () => setTimeout(safeResize, 120));
  });

})();
</script>
@endpush
