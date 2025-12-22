@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-4 p-0 md:p-4">



    {{-- Toggle formulaire --}}
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

    {{-- Formulaire --}}
    <div id="vehicle-form"
        class="ui-card mt-4 max-h-0 overflow-hidden opacity-0 transition-all duration-500 ease-in-out @if(isset($voitureEdit)) is-error-state @endif">
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
                    <label for="immatriculation"
                        class="block text-sm font-medium text-secondary">Immatriculation</label>
                    <input type="text" class="ui-input-style mt-1" id="immatriculation" name="immatriculation"
                        placeholder="ABC-123-XY"
                        value="{{ old('immatriculation', $voitureEdit->immatriculation ?? '') }}" required>
                </div>
                <div>
                    <label for="model" class="block text-sm font-medium text-secondary">Modèle</label>
                    <input type="text" class="ui-input-style mt-1" id="model" name="model"
                        placeholder="SUV, Berline, etc." value="{{ old('model', $voitureEdit->model ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="marque" class="block text-sm font-medium text-secondary">Marque</label>
                    <input type="text" class="ui-input-style mt-1" id="marque" name="marque"
                        placeholder="Toyota, Renault..." value="{{ old('marque', $voitureEdit->marque ?? '') }}"
                        required>
                </div>
                <div>
                    <label for="couleur" class="block text-sm font-medium text-secondary">Couleur</label>
                    <input type="color" class="ui-input-style mt-1 h-10 w-full p-0 border-0 cursor-pointer" id="couleur"
                        name="couleur" value="{{ old('couleur', $voitureEdit->couleur ?? '#000000') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mac_id_gps" class="block text-sm font-medium text-secondary">Numéro GPS</label>
                    <input type="text" class="ui-input-style mt-1" id="mac_id_gps" name="mac_id_gps"
                        placeholder="GPS-XXXX-XXXX" value="{{ old('mac_id_gps', $voitureEdit->mac_id_gps ?? '') }}"
                        required>
                </div>
                <div>
                    <label for="sim_gps" class="block text-sm font-medium text-secondary">SIM GPS</label>
                    <input type="text" class="ui-input-style mt-1" id="sim_gps" name="sim_gps" placeholder="696000000"
                        value="{{ old('sim_gps', $voitureEdit->sim_gps ?? '') }}">
                </div>
                <div>
                    <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                    <input type="file" class="ui-input-style mt-1" id="photo" name="photo">
                    @if(isset($voitureEdit) && $voitureEdit->photo)
                    <img src="{{ asset('storage/' . $voitureEdit->photo) }}"
                        class="h-10 w-10 object-cover rounded mt-2">
                    @endif
                </div>
            </div>

            {{-- Geofence : sélection ou dessin --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="city_select" class="block text-sm font-medium text-secondary">
                        Ville (choisir ou personnaliser)
                    </label>
                    <select id="city_select" class="ui-input-style mt-1">
                        <option value="">-- Choisir une ville --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary">Mode Geofence</label>
                    <div class="mt-1">
                        <label class="inline-flex items-center mr-4">
                            <input type="radio" name="geofence_mode" value="city" checked class="form-radio"
                                id="mode_city">
                            <span class="ml-2">Ville prédéfinie</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="geofence_mode" value="custom" class="form-radio" id="mode_custom">
                            <span class="ml-2">Personnalisé (dessiner)</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Carte --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-secondary mb-2">
                    Carte - sélectionner / personnaliser
                </label>
                <div id="map" class="h-96 w-full rounded border border-gray-300"></div>

                <div class="mt-2 flex space-x-2">
                    <button type="button" id="start-draw" class="btn-secondary">Commencer dessin</button>
                    <button type="button" id="finish-draw" class="btn-primary">Terminer polygone</button>
                    <button type="button" id="undo-point" class="btn-warning">Annuler dernier point</button>
                    <button type="button" id="reset-to-city" class="btn-secondary">Réinitialiser vers ville</button>
                </div>
                <p class="text-xs text-secondary mt-2">
                    Cliquer sur la carte ajoute un point au polygone personnalisé.
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
            <table id="example" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>GPS</th>
                        <th>SIM de GPS</th>
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
                        <td>
                            <div class="w-8 h-8 rounded" style="background-color: {{ $voiture->couleur }}"></div>
                        </td>
                        <td>{{ $voiture->mac_id_gps }}</td>
                        <td>{{ $voiture->sim_gps }}</td>
                        <td>
                            @if($voiture->photo)
                            <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo"
                                class="h-10 w-10 object-cover rounded">
                            @endif
                        </td>
                        <td class="space-x-1 whitespace-nowrap">
                            {{-- Modifier --}}
                            <a href="{{ route('tracking.vehicles', ['edit' => $voiture->id]) }}"
                                class="btn-secondary btn-edit" title="Modifier">
                                <i class="fas fa-edit mr-2"></i>
                            </a>

                            {{-- Supprimer --}}
                            <form action="{{ route('tracking.vehicles.destroy', $voiture->id) }}" method="POST"
                                class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-delete"
                                    onclick="return confirm('Voulez-vous vraiment supprimer ce véhicule ?');"
                                    title="Supprimer">
                                    <i class="fas fa-trash mr-2"></i>
                                </button>
                            </form>

                            {{-- Voir Geofence --}}
                            <a href="{{ route('tracking.vehicles.geofence', $voiture->id) }}"
                                class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200 p-2"
                                title="Voir le Geofence">
                                <i class="fas fa-eye"></i>
                            </a>

                            {{-- ➕ Paramètres d’alertes (TimeZone / SpeedZone) --}}
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

{{-- Modal Paramètres d’alertes pour un véhicule --}}
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

            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
                <button type="button" id="cancelVehicleAlertBtn" class="btn-secondary">
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


{{-- Leaflet --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />


{{-- modale timezone et spidezone --}}
<script>
(function () {
  const modal = document.getElementById('vehicleAlertModal');
  const form = document.getElementById('vehicle-alerts-form');
  const labelEl = document.getElementById('vehicleAlertVehicleLabel');

  const closeBtn = document.getElementById('closeVehicleAlertModalBtn');
  const cancelBtn = document.getElementById('cancelVehicleAlertBtn');

  const inputStart = document.getElementById('time_zone_start');
  const inputEnd = document.getElementById('time_zone_end');
  const inputSpeed = document.getElementById('speed_zone');

  // URL template (relative, important en prod si sous-dossier / proxy)
  const urlTpl = @json(route('tracking.vehicles.alerts.define', ['voiture' => '__ID__'], false));

  function normalizeTime(t) {
    if (!t) return '';
    // support "22:00:00" -> "22:00"
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

  // ✅ Fonction globale appelée par le onclick
  window.openVehicleAlertModal = function (vehicleId, label, start, end, speed) {
    if (!form) return;

    // set label
    if (labelEl) labelEl.textContent = label || '';

    // set form action
    form.action = urlTpl.replace('__ID__', String(vehicleId));

    // prefill
    if (inputStart) inputStart.value = normalizeTime(start);
    if (inputEnd) inputEnd.value = normalizeTime(end);

    if (inputSpeed) {
      const sp = (speed === null || speed === undefined) ? '' : String(speed).trim();
      inputSpeed.value = sp;
    }

    openModal();
  };

  // close buttons
  closeBtn && closeBtn.addEventListener('click', closeModal);
  cancelBtn && cancelBtn.addEventListener('click', closeModal);

  // click outside
  modal && modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  // ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
})();
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {

    // ------------------ DOM Elements ------------------
    const toggleBtn = document.getElementById('toggle-form');
    const form = document.getElementById('vehicle-form');

    const citySelect = document.getElementById('city_select');
    const modeCity   = document.getElementById('mode_city');
    const modeCustom = document.getElementById('mode_custom');

    const startDrawBtn  = document.getElementById('start-draw');
    const finishDrawBtn = document.getElementById('finish-draw');
    const undoBtn       = document.getElementById('undo-point');
    const resetBtn      = document.getElementById('reset-to-city');

    const hiddenPolygon  = document.getElementById('geofence_polygon');
    const hiddenCityCode = document.getElementById('geofence_city_code');
    const hiddenCityName = document.getElementById('geofence_city_name');
    const hiddenIsCustom = document.getElementById('geofence_is_custom');

    // ------------------ Variables Leaflet ------------------
    let map, cityLayerGroup, customLayer, tempLayer;
    let geojsonData = null;
    let selectedCityFeature = null;

    let drawing = false;
    let currentPoints = [];  // [[lat,lng], ...]
    let tempMarkers = [];

    // ------------------ Initialisation Map ------------------
    function initMap() {
        if (map) return;

        map = L.map('map').setView([4.05, 9.7], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        cityLayerGroup = L.geoJSON(null, {
            style: () => ({ color: '#2b8a3e', weight: 2, fillOpacity: 0.12 }),
            onEachFeature: onEachCityFeature
        }).addTo(map);

        customLayer = L.layerGroup().addTo(map);
        tempLayer   = L.layerGroup().addTo(map);

        map.on('click', function(e) {
            if (drawing) addPointToDrawing(e.latlng);
        });

        loadCitiesGeoJSON();
    }

    // ------------------ Show/Hide villes (sur la carte) ------------------
    function showCityLayer(show) {
        if (!map || !cityLayerGroup) return;

        if (show) {
            if (!map.hasLayer(cityLayerGroup)) cityLayerGroup.addTo(map);
        } else {
            if (map.hasLayer(cityLayerGroup)) map.removeLayer(cityLayerGroup);
        }
    }

    // ------------------ Helpers ------------------
    function clearTemp() {
        tempLayer.clearLayers();
        tempMarkers = [];
    }

    function closePolygonLngLat(coordsLngLat) {
        if (coordsLngLat.length > 0) {
            const f = coordsLngLat[0], l = coordsLngLat[coordsLngLat.length - 1];
            if (f[0] != l[0] || f[1] != l[1]) coordsLngLat.push(f);
        }
        return coordsLngLat;
    }

    function updateHiddenPolygonFromLatLngPoints(pointsLatLng, source='custom', code=null) {
        // pointsLatLng: [[lat,lng], ...] -> backend wants [[lng,lat],...]
        const coordsLngLat = pointsLatLng.map(p => [p[1], p[0]]);
        closePolygonLngLat(coordsLngLat);

        const feature = {
            type: 'Feature',
            properties: { source, code },
            geometry: { type: 'Polygon', coordinates: [coordsLngLat] }
        };

        hiddenPolygon.value = JSON.stringify(feature);
    }

    function drawFinalPolygon(pointsLatLng) {
        customLayer.clearLayers();
        if (!pointsLatLng || pointsLatLng.length < 3) return;

        L.polygon(pointsLatLng, {
            color: '#4361ee',
            weight: 3,
            fillOpacity: 0.12
        }).addTo(customLayer);
    }

    function getCityFeatureByCode(code) {
        if (!geojsonData || !geojsonData.features) return null;
        return geojsonData.features.find(f => f.properties.code == code) || null;
    }

    // ------------------ Mode Switch ------------------
    function enterCustomMode({resetPolygon=true} = {}) {
        modeCustom.checked = true;
        hiddenIsCustom.value = 1;

        // ✅ on cache les villes pour ne pas bloquer les clics de dessin
        showCityLayer(false);

        // ✅ on garde le select ville ACTIF (pour importer une ville comme base)
        citySelect.disabled = false;
        citySelect.classList.remove('opacity-50', 'pointer-events-none');

        drawing = true;
        currentPoints = [];
        clearTemp();

        if (resetPolygon) {
            customLayer.clearLayers();
            hiddenPolygon.value = '';
        }
    }

    function enterCityMode() {
        modeCity.checked = true;
        hiddenIsCustom.value = 0;

        // ✅ afficher les villes
        showCityLayer(true);

        // ✅ select ville actif
        citySelect.disabled = false;
        citySelect.classList.remove('opacity-50', 'pointer-events-none');

        drawing = false;
        currentPoints = [];
        clearTemp();
    }

    modeCustom.addEventListener('change', () => {
        if (modeCustom.checked) enterCustomMode({resetPolygon:false});
    });

    modeCity.addEventListener('change', () => {
        if (modeCity.checked) enterCityMode();
    });

    // ------------------ Charger GeoJSON Villes ------------------
    async function loadCitiesGeoJSON() {
        try {
            const resp = await fetch('/geojson/ville.geojson', { cache: 'no-cache' });
            if (!resp.ok) throw new Error('Impossible de charger /geojson/ville.geojson');

            const data = await resp.json();
            geojsonData = normalizeGeoJSON(data);

            populateCitySelect(geojsonData);

            cityLayerGroup.clearLayers();
            cityLayerGroup.addData(geojsonData);

            try {
                map.fitBounds(cityLayerGroup.getBounds(), { padding: [20, 20] });
            } catch (e) {}

            applyInitialFromHidden();

        } catch (err) {
            console.error(err);
            alert('Erreur lors du chargement des villes.');
        }
    }

    // ⚠️ ta normalisation fabrique une geometry.coordinates en [lat,lng] (spécifique à ton code)
    function normalizeGeoJSON(raw) {
        if (!raw) return { type: 'FeatureCollection', features: [] };
        if (raw.type === 'FeatureCollection') return raw;

        const features = [];
        for (const code in raw) {
            if (!raw.hasOwnProperty(code)) continue;

            const item = raw[code];
            const poly = item.polygone || item.polygon || item.coordinates;
            if (!poly) continue;

            const latlng = poly.map(pt => [pt[1], pt[0]]); // [lat,lng]

            // fermer
            if (latlng.length > 0) {
                const first = latlng[0], last = latlng[latlng.length - 1];
                if (first[0] !== last[0] || first[1] !== last[1]) latlng.push(first);
            }

            features.push({
                type: 'Feature',
                properties: { nom_ville: item.nom_ville || code, code },
                geometry: { type: 'Polygon', coordinates: [latlng] } // [[lat,lng],...]
            });
        }
        return { type: 'FeatureCollection', features };
    }

    function populateCitySelect(geojson) {
        citySelect.innerHTML = '<option value="">-- Choisir une ville --</option>';
        geojson.features.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.properties.code;
            opt.textContent = `${f.properties.nom_ville} — ${f.properties.code}`;
            citySelect.appendChild(opt);
        });
    }

    function onEachCityFeature(feature, layer) {
        layer.feature = feature;

        layer.on('click', function() {
            // en custom, la couche ville est cachée, donc normalement ça ne se déclenche pas.
            if (modeCustom.checked) return;
            selectCity(feature, layer);
        });

        layer.bindTooltip(feature.properties.nom_ville, { sticky: true });
    }

    // ------------------ Ville selection (MODE CITY) ------------------
    function selectCity(feature, layer) {
        // reset styles
        cityLayerGroup.eachLayer(l => cityLayerGroup.resetStyle && cityLayerGroup.resetStyle(l));
        if (layer && layer.setStyle) layer.setStyle({ color: '#ff7f50', weight: 3, fillOpacity: 0.25 });

        selectedCityFeature = feature;

        hiddenCityCode.value = feature.properties.code || '';
        hiddenCityName.value = feature.properties.nom_ville || '';
        hiddenIsCustom.value = 0;

        // feature.geometry.coordinates[0] est en [lat,lng]
        const latlngCoordsClosed = feature.geometry.coordinates[0];
        const latlngCoords = latlngCoordsClosed.slice(0, -1); // sans le point de fermeture

        drawFinalPolygon(latlngCoords);
        updateHiddenPolygonFromLatLngPoints(latlngCoords, 'city', feature.properties.code);

        try {
            map.fitBounds(layer.getBounds(), { padding: [20, 20] });
        } catch (e) {}
    }

    // ------------------ Import ville comme base (MODE CUSTOM) ------------------
    function importCityAsCustomBase(code) {
        const f = getCityFeatureByCode(code);
        if (!f) return;

        selectedCityFeature = f;

        // On reste en custom !
        enterCustomMode({resetPolygon:false});

        // on garde en mémoire la ville choisie (utile en backend si tu veux)
        hiddenCityCode.value = f.properties.code || '';
        hiddenCityName.value = f.properties.nom_ville || '';

        // base = coords ville (lat,lng)
        const baseClosed = f.geometry.coordinates[0];
        const base = baseClosed.slice(0, -1);

        // on met la base comme points actuels pour permettre add/undo
        currentPoints = [...base];

        drawFinalPolygon(currentPoints);
        updateHiddenPolygonFromLatLngPoints(currentPoints, 'custom', f.properties.code);

        // dessin activé (tu peux cliquer pour ajouter des points)
        drawing = true;
        clearTemp();
    }

    // ------------------ Handler select ville ------------------
    citySelect.addEventListener('change', function() {
        const code = this.value;
        if (!code) return;

        if (modeCustom.checked) {
            // ✅ en custom : importer ville comme base
            importCityAsCustomBase(code);
        } else {
            // ✅ en city : sélectionner ville
            const f = getCityFeatureByCode(code);
            if (!f) return;

            cityLayerGroup.eachLayer(l => {
                if (l.feature === f) selectCity(f, l);
            });
        }
    });

    // ------------------ Apply initial state (edit / old input) ------------------
    function applyInitialFromHidden() {
        const existing = hiddenPolygon.value;
        const isCustom = (hiddenIsCustom.value === '1');

        if (existing) {
            try {
                const parsed = JSON.parse(existing);
                const coordsLngLat = parsed.geometry?.coordinates?.[0] || [];
                const coordsLatLng = coordsLngLat.map(p => [p[1], p[0]]); // [lat,lng]

                // enlever fermeture si présent
                const pts = (coordsLatLng.length > 1)
                    ? coordsLatLng.slice(0, -1)
                    : coordsLatLng;

                drawFinalPolygon(pts);

                if (isCustom) {
                    enterCustomMode({resetPolygon:false});
                    drawing = false;
                } else {
                    enterCityMode();
                }

            } catch (e) {}
        }

        // restaurer select ville si code existe
        const code = hiddenCityCode.value;
        if (code) {
            citySelect.value = code;
        }

        // si custom => cacher villes
        if (isCustom) showCityLayer(false);
        else showCityLayer(true);
    }

    // ------------------ Dessin polygone ------------------
    startDrawBtn.addEventListener('click', function() {
        // reset total custom
        enterCustomMode({resetPolygon:true});
        hiddenCityCode.value = '';
        hiddenCityName.value = '';
    });

    function addPointToDrawing(latlng) {
        currentPoints.push([latlng.lat, latlng.lng]);

        const m = L.circleMarker(latlng, {
            radius: 6,
            fillColor: '#4361ee',
            color: '#fff',
            weight: 2
        }).addTo(tempLayer);

        tempMarkers.push(m);
        redrawTempShape();

        // ✅ update hidden en live
        if (currentPoints.length >= 3) {
            updateHiddenPolygonFromLatLngPoints(currentPoints, 'custom', hiddenCityCode.value || null);
            drawFinalPolygon(currentPoints);
        }
    }

    function redrawTempShape() {
        tempLayer.eachLayer(layer => {
            if (!(layer instanceof L.CircleMarker)) tempLayer.removeLayer(layer);
        });

        if (currentPoints.length === 0) return;

        if (currentPoints.length < 3) {
            L.polyline(currentPoints, { color: '#4361ee', weight: 2, dashArray: '6 4' }).addTo(tempLayer);
        } else {
            L.polygon(currentPoints, { color: '#4361ee', weight: 2, fillOpacity: 0.06 }).addTo(tempLayer);
        }
    }

    undoBtn.addEventListener('click', function() {
        if (!drawing || currentPoints.length === 0) return;

        currentPoints.pop();
        const last = tempMarkers.pop();
        if (last) tempLayer.removeLayer(last);

        redrawTempShape();

        if (currentPoints.length >= 3) {
            updateHiddenPolygonFromLatLngPoints(currentPoints, 'custom', hiddenCityCode.value || null);
            drawFinalPolygon(currentPoints);
        } else {
            // pas assez de points => on vide polygon
            hiddenPolygon.value = '';
            customLayer.clearLayers();
        }
    });

    finishDrawBtn.addEventListener('click', function() {
        if (!drawing || currentPoints.length < 3) {
            alert('Polygone doit avoir ≥3 points');
            return;
        }

        hiddenIsCustom.value = 1;

        // ✅ final sync
        updateHiddenPolygonFromLatLngPoints(currentPoints, 'custom', hiddenCityCode.value || null);
        drawFinalPolygon(currentPoints);

        drawing = false;
        currentPoints = [];
        clearTemp();

        alert('Polygone prêt');
    });

    resetBtn.addEventListener('click', function() {
        // En custom : réimporter la ville choisie comme base (si une ville est sélectionnée)
        if (modeCustom.checked) {
            const code = citySelect.value || hiddenCityCode.value;
            if (!code) {
                alert('Choisissez une ville dans la liste pour réinitialiser la base.');
                return;
            }
            importCityAsCustomBase(code);
            return;
        }

        // En city : reset vers ville sélectionnée (comme avant)
        if (!selectedCityFeature) {
            alert('Aucune ville sélectionnée');
            return;
        }

        const code = selectedCityFeature.properties.code;
        citySelect.value = code;

        cityLayerGroup.eachLayer(l => {
            if (l.feature === selectedCityFeature) selectCity(selectedCityFeature, l);
        });
    });

    // ------------------ Toggle Form ------------------
    toggleBtn.addEventListener('click', () => {
        const isHidden = form.classList.contains('max-h-0');
        if (isHidden) {
            form.classList.remove('max-h-0', 'opacity-0');
            form.classList.add('max-h-[2000px]', 'opacity-100');

            setTimeout(() => {
                if (map) map.invalidateSize();
                else initMap();
            }, 300);

        } else {
            form.classList.remove('max-h-[2000px]', 'opacity-100');
            form.classList.add('max-h-0', 'opacity-0');
        }
    });

    // ------------------ Init Map si formulaire visible (edit ou erreurs) ------------------
    @if(isset($voitureEdit) || $errors->any())
        initMap();
        setTimeout(() => { map && map.invalidateSize(); }, 400);
    @endif

});
</script>

@endsection