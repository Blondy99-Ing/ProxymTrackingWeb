@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Messages d'erreur ou succès --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <strong class="font-bold">Erreurs de validation:</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
    <div id="vehicle-form" class="ui-card mt-4 max-h-0 overflow-hidden opacity-0 transition-all duration-500 ease-in-out @if(isset($voitureEdit)) is-error-state @endif">
        <h2 class="text-xl font-bold font-orbitron mb-6">
            @if(isset($voitureEdit))
                Modifier un Véhicule
            @else
                Ajouter un Véhicule
            @endif
        </h2>

        <form id="vehicle-form-el" action="@if(isset($voitureEdit)) {{ route('tracking.vehicles.update', $voitureEdit->id) }} @else {{ route('tracking.vehicles.store') }} @endif" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if(isset($voitureEdit))
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="immatriculation" class="block text-sm font-medium text-secondary">Immatriculation</label>
                    <input type="text" class="ui-input-style mt-1" id="immatriculation" name="immatriculation" placeholder="ABC-123-XY"
                        value="{{ old('immatriculation', $voitureEdit->immatriculation ?? '') }}" required>
                </div>
                <div>
                    <label for="model" class="block text-sm font-medium text-secondary">Modèle</label>
                    <input type="text" class="ui-input-style mt-1" id="model" name="model" placeholder="SUV, Berline, etc."
                        value="{{ old('model', $voitureEdit->model ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="marque" class="block text-sm font-medium text-secondary">Marque</label>
                    <input type="text" class="ui-input-style mt-1" id="marque" name="marque" placeholder="Toyota, Renault..."
                        value="{{ old('marque', $voitureEdit->marque ?? '') }}" required>
                </div>
                <div>
                    <label for="couleur" class="block text-sm font-medium text-secondary">Couleur</label>
                    <input type="color" class="ui-input-style mt-1 h-10 w-full p-0 border-0 cursor-pointer" id="couleur" name="couleur"
                        value="{{ old('couleur', $voitureEdit->couleur ?? '#000000') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mac_id_gps" class="block text-sm font-medium text-secondary">Numéro GPS</label>
                    <input type="text" class="ui-input-style mt-1" id="mac_id_gps" name="mac_id_gps" placeholder="GPS-XXXX-XXXX"
                        value="{{ old('mac_id_gps', $voitureEdit->mac_id_gps ?? '') }}" required>
                </div>
                <div>
                    <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                    <input type="file" class="ui-input-style mt-1" id="photo" name="photo">
                    @if(isset($voitureEdit) && $voitureEdit->photo)
                        <img src="{{ asset('storage/' . $voitureEdit->photo) }}" class="h-10 w-10 object-cover rounded mt-2">
                    @endif
                </div>
            </div>

            {{-- Geofence : sélection ou dessin --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="city_select" class="block text-sm font-medium text-secondary">Ville (choisir ou personnaliser)</label>
                    <select id="city_select" class="ui-input-style mt-1">
                        <option value="">-- Choisir une ville --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary">Mode Geofence</label>
                    <div class="mt-1">
                        <label class="inline-flex items-center mr-4">
                            <input type="radio" name="geofence_mode" value="city" checked class="form-radio" id="mode_city">
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
                <label class="block text-sm font-medium text-secondary mb-2">Carte - sélectionner / personnaliser</label>
                <div id="map" class="h-96 w-full rounded border border-gray-300"></div>

                <div class="mt-2 flex space-x-2">
                    <button type="button" id="start-draw" class="btn-secondary">Commencer dessin</button>
                    <button type="button" id="finish-draw" class="btn-primary">Terminer polygone</button>
                    <button type="button" id="undo-point" class="btn-warning">Annuler dernier point</button>
                    <button type="button" id="reset-to-city" class="btn-secondary">Réinitialiser vers ville</button>
                </div>
                <p class="text-xs text-secondary mt-2">Cliquer sur la carte ajoute un point au polygone personnalisé.</p>
            </div>

            {{-- Champs cachés pour backend --}}
            <input type="hidden" name="geofence_polygon" id="geofence_polygon" value="{{ old('geofence_polygon', $voitureEdit->geofence_polygon ?? '') }}">
            <input type="hidden" name="geofence_city_code" id="geofence_city_code" value="{{ old('geofence_city_code', $voitureEdit->geofence_city_code ?? '') }}">
            <input type="hidden" name="geofence_city_name" id="geofence_city_name" value="{{ old('geofence_city_name', $voitureEdit->geofence_city_name ?? '') }}">
            <input type="hidden" name="geofence_is_custom" id="geofence_is_custom" value="{{ old('geofence_is_custom', $voitureEdit->geofence_is_custom ?? 0) }}">

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
                        <th>Photo</th>
                        <th>Actions</th>
                        <th>Moteur</th>
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
                        <td>
                            @if($voiture->photo)
                            <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo" class="h-10 w-10 object-cover rounded">
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('tracking.vehicles', ['edit' => $voiture->id]) }}" class="btn-secondary btn-edit">
                                <i class="fas fa-edit mr-2"></i>
                            </a>
                            <form action="{{ route('tracking.vehicles.destroy', $voiture->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-delete" onclick="return confirm('Voulez-vous vraiment supprimer ce véhicule ?');">
                                    <i class="fas fa-trash mr-2"></i>
                                </button>
                            </form>
                        </td>

                        <td>
                            <button class="toggleEngineBtn" data-id="{{ $voiture->id }}"></button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
    #toggleEngineBtn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        color: white;
        font-size: 16px;
        cursor: pointer;
    }
</style>


<script>
document.querySelectorAll(".toggleEngineBtn").forEach(btn => {
    const id = btn.dataset.id;

    function render(state){
        btn.textContent = state ? "Éteindre le véhicule" : "Allumer le véhicule";
        btn.style.backgroundColor = state ? "red" : "green";
    }

    // Récupérer statut réel au chargement
    fetch(`/voitures/${id}/engine-status`)
    .then(res => res.json())
    .then(data => render(data.engine_on))
    .catch(() => render(false));

    // Toggle moteur
    btn.addEventListener("click", () => {
        fetch(`/voitures/${id}/toggle-engine`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                "Content-Type": "application/json"
            }
        })
        .then(res => res.json())
        .then(data => render(data.engine_on))
        .catch(() => alert("Erreur lors de la commande moteur"));
    });
});

</script>

{{-- Leaflet --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

{{-- JS : formulaire toggle, geofence et couleur --}}
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ------------------ DOM Elements ------------------
    const toggleBtn = document.getElementById('toggle-form');
    const form = document.getElementById('vehicle-form');

    const citySelect = document.getElementById('city_select');
    const modeCity = document.getElementById('mode_city');
    const modeCustom = document.getElementById('mode_custom');

    const startDrawBtn = document.getElementById('start-draw');
    const finishDrawBtn = document.getElementById('finish-draw');
    const undoBtn = document.getElementById('undo-point');
    const resetBtn = document.getElementById('reset-to-city');

    const hiddenPolygon = document.getElementById('geofence_polygon');
    const hiddenCityCode = document.getElementById('geofence_city_code');
    const hiddenCityName = document.getElementById('geofence_city_name');
    const hiddenIsCustom = document.getElementById('geofence_is_custom');

    // ------------------ Variables Leaflet ------------------
    let map, cityLayerGroup, customLayer, tempLayer;
    let geojsonData = null;
    let selectedCityFeature = null;
    let drawing = false;
    let currentPoints = [];
    let tempMarkers = [];

    // ------------------ Initialisation Map ------------------
    function initMap() {
        if (map) return; // éviter double init
        map = L.map('map').setView([4.05, 9.7], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        cityLayerGroup = L.geoJSON(null, {
            style: feature => ({ color: '#2b8a3e', weight: 2, fillOpacity: 0.12 }),
            onEachFeature: onEachCityFeature
        }).addTo(map);

        customLayer = L.layerGroup().addTo(map);
        tempLayer = L.layerGroup().addTo(map);

        map.on('click', function(e) {
            if (drawing) addPointToDrawing(e.latlng);
        });

        loadCitiesGeoJSON();
    }

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

            try { map.fitBounds(cityLayerGroup.getBounds(), { padding: [20,20] }); } catch(e){}

            applyInitialFromHidden();

        } catch (err) {
            console.error(err);
            alert('Erreur lors du chargement des villes.');
        }
    }

    function normalizeGeoJSON(raw) {
        if (!raw) return { type:'FeatureCollection', features:[] };
        if (raw.type === 'FeatureCollection') return raw;

        const features = [];
        for (const code in raw) {
            if (!raw.hasOwnProperty(code)) continue;
            const item = raw[code];
            const poly = item.polygone || item.polygon || item.coordinates;
            if (!poly) continue;
            const coords = poly.map(pt => [pt[1], pt[0]]);
            if (coords.length > 0) {
                const first = coords[0], last = coords[coords.length-1];
                if (first[0] !== last[0] || first[1] !== last[1]) coords.push(first);
            }
            features.push({
                type: 'Feature',
                properties: { nom_ville: item.nom_ville || code, code: code },
                geometry: { type:'Polygon', coordinates:[coords] }
            });
        }
        return { type:'FeatureCollection', features };
    }

    function populateCitySelect(geojson) {
        citySelect.innerHTML = '<option value="">-- Choisir une ville --</option>';
        geojson.features.forEach((f, idx) => {
            const opt = document.createElement('option');
            opt.value = f.properties.code;
            opt.textContent = `${f.properties.nom_ville} — ${f.properties.code}`;
            citySelect.appendChild(opt);
        });
    }

    function onEachCityFeature(feature, layer) {
        layer.feature = feature;
        layer.on('click', function() { selectCity(feature, layer); });
        layer.bindTooltip(feature.properties.nom_ville, { sticky:true });
    }

    function selectCity(feature, layer) {
        cityLayerGroup.eachLayer(l => cityLayerGroup.resetStyle && cityLayerGroup.resetStyle(l));
        if (layer && layer.setStyle) layer.setStyle({ color:'#ff7f50', weight:3, fillOpacity:0.25 });

        selectedCityFeature = feature;
        hiddenCityCode.value = feature.properties.code || '';
        hiddenCityName.value = feature.properties.nom_ville || '';

        if (modeCity.checked) {
            hiddenPolygon.value = JSON.stringify({ type:'Feature', properties:{source:'city', code:feature.properties.code}, geometry: feature.geometry });
            hiddenIsCustom.value = 0;
        }

        cityLayerGroup.eachLayer(l => { if (l.feature===feature) map.fitBounds(l.getBounds(), { padding:[20,20] }); });
    }

    function applyInitialFromHidden() {
        const existing = hiddenPolygon.value;
        if (existing) {
            try {
                const parsed = JSON.parse(existing);
                if (parsed.geometry) {
                    customLayer.clearLayers();
                    L.geoJSON(parsed.geometry, { style:{ color:'#4361ee', weight:3, fillOpacity:0.12 } }).addTo(customLayer);
                    if (hiddenIsCustom.value==='1') modeCustom.checked=true; else modeCity.checked=true;
                }
            } catch(e) {}
        }
        const code = hiddenCityCode.value;
        if (code && geojsonData) {
            const f = geojsonData.features.find(ff => ff.properties.code==code);
            if (f) cityLayerGroup.eachLayer(l => { if (l.feature===f) selectCity(f,l); });
        }
    }

    // ------------------ Dessin Polygone ------------------
    startDrawBtn.addEventListener('click', function() {
        drawing = true; currentPoints=[]; clearTemp(); hiddenIsCustom.value=1; modeCustom.checked=true; selectedCityFeature=null;
        cityLayerGroup.eachLayer(l => cityLayerGroup.resetStyle && cityLayerGroup.resetStyle(l));
    });

    function addPointToDrawing(latlng) {
        currentPoints.push([latlng.lat, latlng.lng]);
        const m = L.circleMarker(latlng,{radius:6,fillColor:'#4361ee',color:'#fff',weight:2}).addTo(tempLayer);
        tempMarkers.push(m);
        redrawTempShape();
    }

    function redrawTempShape() {
        tempLayer.eachLayer(layer => { if (!(layer instanceof L.CircleMarker)) tempLayer.removeLayer(layer); });
        if (currentPoints.length===0) return;
        if (currentPoints.length<3) L.polyline(currentPoints,{color:'#4361ee',weight:2,dashArray:'6 4'}).addTo(tempLayer);
        else L.polygon(currentPoints,{color:'#4361ee',weight:2,fillOpacity:0.06}).addTo(tempLayer);
    }

    undoBtn.addEventListener('click', function() {
        if (!drawing || currentPoints.length===0) return;
        currentPoints.pop();
        const last = tempMarkers.pop();
        if (last) tempLayer.removeLayer(last);
        redrawTempShape();
    });

    finishDrawBtn.addEventListener('click', function() {
        if (!drawing || currentPoints.length<3) { alert('Polygone doit avoir ≥3 points'); return; }
        const coords = currentPoints.map(p=>[p[1],p[0]]);
        if (coords.length>0) { const f=coords[0], l=coords[coords.length-1]; if(f[0]!=l[0]||f[1]!=l[1]) coords.push(f); }
        const feature = { type:'Feature', properties:{source:'custom'}, geometry:{ type:'Polygon', coordinates:[coords] } };
        customLayer.clearLayers(); L.geoJSON(feature.geometry,{style:{color:'#4361ee',weight:3,fillOpacity:0.12}}).addTo(customLayer);
        hiddenPolygon.value = JSON.stringify(feature); hiddenIsCustom.value=1;
        drawing=false; currentPoints=[]; clearTemp(); alert('Polygone prêt');
    });

    resetBtn.addEventListener('click', function() {
        if (!selectedCityFeature) { alert('Aucune ville sélectionnée'); return; }
        customLayer.clearLayers();
        L.geoJSON(selectedCityFeature.geometry,{style:{color:'#4361ee',weight:3,fillOpacity:0.12}}).addTo(customLayer);
        hiddenPolygon.value = JSON.stringify({type:'Feature',properties:{source:'city',code:selectedCityFeature.properties.code},geometry:selectedCityFeature.geometry});
        hiddenIsCustom.value=0; drawing=false; currentPoints=[]; clearTemp();
    });

    function clearTemp() { tempLayer.clearLayers(); tempMarkers=[]; }

    // ------------------ Toggle Form ------------------
    toggleBtn.addEventListener('click', () => {
        const isHidden = form.classList.contains('max-h-0');
        if (isHidden) {
            form.classList.remove('max-h-0','opacity-0'); form.classList.add('max-h-[2000px]','opacity-100');
            setTimeout(()=>{ if(map) map.invalidateSize(); else initMap(); }, 300);
        } else {
            form.classList.remove('max-h-[2000px]','opacity-100'); form.classList.add('max-h-0','opacity-0');
        }
    });

    // ------------------ Init Map si formulaire visible (edit ou erreurs) ------------------
    @if(isset($voitureEdit) || $errors->any())
        initMap();
        setTimeout(()=>{ map.invalidateSize(); },400);
    @endif
});
</script>


@endsection
