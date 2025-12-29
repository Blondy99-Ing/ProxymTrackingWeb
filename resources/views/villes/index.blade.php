{{-- resources/views/villes/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des Villes - Dessiner un périmètre')

@push('head')
    <style>
        #map { width: 100%; height: calc(100vh - 260px); min-height: 520px; border-radius: 14px; }
        .map-toolbar{
            position:absolute; top:14px; left:14px; z-index:10;
            display:flex; flex-wrap:wrap; gap:8px;
            padding:10px; border-radius:14px;
            background: rgba(255,255,255,.92);
            border:1px solid rgba(0,0,0,.08);
            backdrop-filter: blur(6px);
        }
        .dark .map-toolbar{ background: rgba(17,24,39,.88); border-color: rgba(255,255,255,.08); }
        .map-hint{
            position:absolute; bottom:14px; left:14px; z-index:10;
            max-width: 520px; padding:10px 12px; border-radius:12px; font-size:12px;
            background: rgba(255,255,255,.92);
            border: 1px solid rgba(0,0,0,.08);
            color:#111827;
            backdrop-filter: blur(6px);
        }
        .dark .map-hint{ background: rgba(17,24,39,.88); border-color: rgba(255,255,255,.08); color:#e5e7eb; }
        .btn{ padding:8px 10px; border-radius:10px; font-size:12px; border:1px solid rgba(0,0,0,.08); background:white; }
        .dark .btn{ background: rgba(31,41,55,1); border-color: rgba(255,255,255,.08); color:#e5e7eb; }
        .btn-primary{ border-color: rgba(245,130,32,.25); background: rgba(245,130,32,.12); color:#F58220; }
        .btn-danger{ border-color: rgba(239,68,68,.25); background: rgba(239,68,68,.12); color:#ef4444; }
        .btn:disabled{ opacity:.45; cursor:not-allowed; }

        .ui-input{ width:100%; padding:10px 12px; border-radius:12px; border:1px solid rgba(0,0,0,.10); background:white; font-size:13px; }
        .dark .ui-input{ background: rgba(31,41,55,1); border-color: rgba(255,255,255,.10); color:#e5e7eb; }

        .ui-table-container{ overflow-x:auto; }
        .ui-table{ width:100%; border-collapse:collapse; }
        .ui-table th,.ui-table td{ padding:10px 12px; border-bottom:1px solid rgba(0,0,0,.06); font-size:13px; }
        .dark .ui-table th,.dark .ui-table td{ border-bottom:1px solid rgba(255,255,255,.08); }
        .ui-table th{ text-align:left; font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; }
        .dark .ui-table th{ color:#9ca3af; }
    </style>

    {{-- ✅ Google Maps (callback = initMap) --}}
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&callback=initMap&libraries=geometry"
        async defer>
    </script>
@endpush

@section('content')

@php
    // ✅ on prépare un array propre pour JS (évite les ParseError dans @json)
    $villesJs = ($villes ?? collect())->map(function($v){
        return [
            'id' => $v->id,
            'code_ville' => $v->code_ville,
            'name' => $v->name,
            'geom' => $v->geom, // string json ou objet -> ok
        ];
    })->values();
@endphp

<div class="space-y-4 p-0 md:p-4">

    {{-- Carte + Formulaire --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Carte --}}
        <div class="lg:col-span-2">
            <div class="ui-card p-3 relative">
                <div id="map"></div>

                {{-- Toolbar --}}
                <div class="map-toolbar">
                    <button id="btnStart" type="button" class="btn btn-primary">
                        <i class="fas fa-draw-polygon mr-1"></i> Dessiner
                    </button>

                    <button id="btnUndo" type="button" class="btn">
                        <i class="fas fa-undo mr-1"></i> Annuler dernier point
                    </button>

                    <button id="btnDeleteVertex" type="button" class="btn btn-danger">
                        <i class="fas fa-eraser mr-1"></i> Supprimer point sélectionné
                    </button>

                    <button id="btnFinish" type="button" class="btn btn-primary">
                        <i class="fas fa-check mr-1"></i> Terminer
                    </button>

                    <button id="btnClear" type="button" class="btn">
                        <i class="fas fa-trash mr-1"></i> Effacer tout
                    </button>
                </div>

                <div id="mapHint" class="map-hint">
                    Clique sur <b>Dessiner</b>, puis clique sur la carte pour ajouter des points.
                    <br>• <b>Annuler dernier point</b> enlève le dernier sommet.
                    <br>• Clique sur un point (sommet) pour le sélectionner, puis <b>Supprimer point sélectionné</b>.
                    <br>• Clique sur <b>Terminer</b> pour générer le polygone (modifiable).
                </div>
            </div>
        </div>

        {{-- Form --}}
        <div class="lg:col-span-1">
            <div class="ui-card p-4 space-y-3">
                <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                    <span id="formTitle">Créer une ville + périmètre</span>
                </h2>

                <form id="cityForm" method="POST" action="{{ route('villes.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" id="methodField" name="_method" value="">
                    <input type="hidden" id="editingCityId" value="">

                    <div>
                        <label class="text-xs text-secondary">Code ville (optionnel)</label>
                        <input id="code_ville" class="ui-input" type="text" name="code_ville"
                               value="{{ old('code_ville') }}" placeholder="Ex: DLA-001">
                    </div>

                    <div>
                        <label class="text-xs text-secondary">Nom *</label>
                        <input id="name" class="ui-input" type="text" name="name"
                               value="{{ old('name') }}" required placeholder="Ex: Douala">
                    </div>

                    <div>
                        <label class="text-xs text-secondary">GeoJSON (auto) *</label>
                        <textarea id="geom" name="geom" class="ui-input" rows="6" readonly required
                                  placeholder='{"type":"Polygon","coordinates":[...] }'>{{ old('geom') }}</textarea>
                        <p class="text-[11px] text-secondary mt-1">
                            Le champ est rempli automatiquement quand tu termines le dessin / modifies le polygone.
                        </p>
                    </div>

                    <div class="pt-2 flex items-center gap-2">
                        <button id="btnSubmit" type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Enregistrer
                        </button>

                        <button id="btnCancelEdit" type="button" class="btn" style="display:none;">
                            <i class="fas fa-times mr-1"></i> Annuler édition
                        </button>

                        <button type="button" class="btn" onclick="window.location.reload()">
                            <i class="fas fa-rotate mr-1"></i> Réinitialiser
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ✅ TABLEAU EN BAS (3 colonnes) --}}
    <div class="ui-card p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">Liste des villes</h2>
            <p class="text-[11px] text-secondary">{{ ($villes ?? collect())->count() }} ville(s)</p>
        </div>

        <div class="ui-table-container">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Code</th>
                        <th>Nom</th>
                        <th style="width: 260px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($villes ?? collect()) as $ville)
                        <tr>
                            <td style="color: var(--color-text);">
                                {{ $ville->code_ville ?? '-' }}
                            </td>
                            <td style="color: var(--color-text);">
                                {{ $ville->name }}
                            </td>
                            <td>
                                
                                    <button type="button" class="btn"
                                            onclick="showCityZone({{ $ville->id }})" title="Voir la zone">
                                        <i class="fas fa-map-marked-alt mr-1"></i> Voir zone
                                    </button>

                                    <button type="button" class="btn btn-primary"
                                            onclick="editCity({{ $ville->id }})" title="Modifier">
                                        <i class="fas fa-pen mr-1"></i> Modifier
                                    </button>

                                    <form method="POST" action="{{ route('villes.destroy', $ville->id) }}"
                                          onsubmit="return confirm('Supprimer cette ville ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" title="Supprimer">
                                            <i class="fas fa-trash mr-1"></i> Supprimer
                                        </button>
                                    </form>
                               
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-secondary">Aucune ville enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
let map;

// Mode dessin
let isDrawing = false;
let previewLine = null;
let previewMarkers = [];

// Polygone éditable (form)
let polygon = null;

// Polygone “voir zone” (lecture seule)
let viewPolygon = null;

// Sélection sommet
let selectedVertexIndex = null;
let selectedVertexMarker = null;

// DOM
const geomEl = () => document.getElementById('geom');
const hintEl = () => document.getElementById('mapHint');

const btnStart = () => document.getElementById('btnStart');
const btnUndo  = () => document.getElementById('btnUndo');
const btnDeleteVertex = () => document.getElementById('btnDeleteVertex');
const btnFinish = () => document.getElementById('btnFinish');
const btnClear  = () => document.getElementById('btnClear');

const formEl = () => document.getElementById('cityForm');
const formTitleEl = () => document.getElementById('formTitle');
const btnSubmitEl = () => document.getElementById('btnSubmit');
const btnCancelEditEl = () => document.getElementById('btnCancelEdit');
const methodFieldEl = () => document.getElementById('methodField');
const editingCityIdEl = () => document.getElementById('editingCityId');
const nameEl = () => document.getElementById('name');
const codeVilleEl = () => document.getElementById('code_ville');

// ✅ Routes (safe)
const ROUTE_STORE = @json(route('villes.store'));
const ROUTE_UPDATE_0 = @json(route('villes.update', 0)); // => .../villes/0
function buildUpdateUrl(id){ return ROUTE_UPDATE_0.replace('/0', '/' + id); }

// ✅ Villes pour JS (safe)
const VILLES = @json($villesJs);
const citiesById = {};
VILLES.forEach(v => { citiesById[v.id] = v; });

function setHint(html){ if (hintEl()) hintEl().innerHTML = html; }

function updateButtons(){
    const previewLen = previewLine ? previewLine.getPath().getLength() : 0;
    const polyLen = polygon ? polygon.getPath().getLength() : 0;
    btnUndo().disabled = (previewLen === 0 && polyLen === 0);
    btnFinish().disabled = !(isDrawing && previewLen >= 3);
    btnDeleteVertex().disabled = (selectedVertexIndex === null);
    btnClear().disabled = (previewLen === 0 && polyLen === 0 && !geomEl().value && !viewPolygon);
}

function clearPreviewMarkers(){
    previewMarkers.forEach(m => m.setMap(null));
    previewMarkers = [];
}

function ensureSelectedMarker(){
    if (selectedVertexMarker) return;
    selectedVertexMarker = new google.maps.Marker({
        map,
        clickable: false,
        zIndex: 9999,
        icon: { path: google.maps.SymbolPath.CIRCLE, scale: 7, fillOpacity: 1, strokeOpacity: 1 }
    });
}

function syncPreviewMarkers(){
    clearPreviewMarkers();
    const path = previewLine.getPath();
    for (let i=0;i<path.getLength();i++){
        const pos = path.getAt(i);
        const m = new google.maps.Marker({
            map,
            position: pos,
            clickable: true,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale: 5, fillOpacity: 1, strokeOpacity: 1 }
        });
        m.addListener('click', () => {
            selectedVertexIndex = i;
            ensureSelectedMarker();
            selectedVertexMarker.setPosition(pos);
            setHint(`Point <b>#${i+1}</b> sélectionné. Clique sur <b>Supprimer point sélectionné</b>.`);
            updateButtons();
        });
        previewMarkers.push(m);
    }
}

function removeViewPolygon(){
    if (viewPolygon){ viewPolygon.setMap(null); viewPolygon = null; }
}

function resetFormToCreate(){
    formTitleEl().textContent = "Créer une ville + périmètre";
    btnSubmitEl().innerHTML = `<i class="fas fa-save mr-1"></i> Enregistrer`;
    btnCancelEditEl().style.display = 'none';
    methodFieldEl().value = '';
    editingCityIdEl().value = '';
    formEl().action = ROUTE_STORE;
}

function clearAll(){
    removeViewPolygon();

    if (polygon){ polygon.setMap(null); polygon = null; }

    if (previewLine){ previewLine.setPath([]); }
    clearPreviewMarkers();

    selectedVertexIndex = null;
    ensureSelectedMarker();
    selectedVertexMarker.setPosition(null);

    geomEl().value = '';
    isDrawing = false;

    setHint(`Clique sur <b>Dessiner</b>, puis clique sur la carte pour ajouter des points.
        <br>• <b>Annuler dernier point</b> enlève le dernier sommet.
        <br>• Clique sur un point (sommet) pour le sélectionner, puis <b>Supprimer point sélectionné</b>.
        <br>• Clique sur <b>Terminer</b> pour générer le polygone (modifiable).`);

    updateButtons();
}

function startDrawing(){
    resetFormToCreate();
    clearAll();
    isDrawing = true;

    setHint(`<b>Mode dessin activé.</b> Clique sur la carte pour ajouter des points.
        <br>• Utilise <b>Annuler dernier point</b> si tu te trompes.
        <br>• Clique sur un point pour le sélectionner puis <b>Supprimer point sélectionné</b>.
        <br>• Clique sur <b>Terminer</b> quand tu as au moins 3 points.`);
    updateButtons();
}

function undoLastPoint(){
    if (isDrawing && previewLine){
        const p = previewLine.getPath();
        const len = p.getLength();
        if (len>0){ p.removeAt(len-1); }
        selectedVertexIndex = null;
        ensureSelectedMarker(); selectedVertexMarker.setPosition(null);
        syncPreviewMarkers();
    } else if (polygon){
        const p = polygon.getPath();
        const len = p.getLength();
        if (len>3){ p.removeAt(len-1); updateGeomFromPolygon(); }
    }
    updateButtons();
}

function deleteSelectedVertex(){
    if (selectedVertexIndex === null){
        setHint("Aucun point sélectionné. Clique sur un sommet pour le sélectionner.");
        updateButtons();
        return;
    }

    if (isDrawing && previewLine){
        const p = previewLine.getPath();
        if (p.getLength() <= 1) p.clear();
        else p.removeAt(selectedVertexIndex);

        selectedVertexIndex = null;
        ensureSelectedMarker(); selectedVertexMarker.setPosition(null);
        syncPreviewMarkers();
        setHint("Point supprimé (mode dessin).");
        updateButtons();
        return;
    }

    if (polygon){
        const p = polygon.getPath();
        if (p.getLength() <= 3){
            setHint("Impossible: un polygone doit garder au moins 3 points.");
            updateButtons();
            return;
        }
        p.removeAt(selectedVertexIndex);
        selectedVertexIndex = null;
        ensureSelectedMarker(); selectedVertexMarker.setPosition(null);
        updateGeomFromPolygon();
        setHint("Point supprimé (polygone).");
        updateButtons();
    }
}

function bindPolygonEvents(poly){
    poly.addListener('click', (e) => {
        if (typeof e.vertex === 'number'){
            selectedVertexIndex = e.vertex;
            const pos = poly.getPath().getAt(selectedVertexIndex);
            ensureSelectedMarker();
            selectedVertexMarker.setPosition(pos);
            setHint(`Sommet <b>#${selectedVertexIndex+1}</b> sélectionné. Clique sur <b>Supprimer point sélectionné</b>.`);
            updateButtons();
        }
    });

    const path = poly.getPath();
    path.addListener('set_at', updateGeomFromPolygon);
    path.addListener('insert_at', updateGeomFromPolygon);
    path.addListener('remove_at', updateGeomFromPolygon);
}

function updateGeomFromPolygon(){
    if (!polygon) return;
    const ring = [];
    const path = polygon.getPath();

    for (let i=0;i<path.getLength();i++){
        const pt = path.getAt(i);
        ring.push([pt.lng(), pt.lat()]);
    }

    // fermer l'anneau
    if (ring.length){
        const first = ring[0];
        const last = ring[ring.length-1];
        if (first[0] !== last[0] || first[1] !== last[1]){
            ring.push([first[0], first[1]]);
        }
    }

    geomEl().value = JSON.stringify({ type:"Polygon", coordinates:[ring] });
    updateButtons();
}

function finishPolygon(){
    if (!isDrawing || !previewLine) return;

    const p = previewLine.getPath();
    if (p.getLength() < 3){
        setHint("Ajoute au moins 3 points pour créer un polygone.");
        updateButtons();
        return;
    }

    removeViewPolygon();

    polygon = new google.maps.Polygon({
        map,
        paths: p,
        editable: true
    });

    isDrawing = false;
    previewLine.setPath([]);
    clearPreviewMarkers();

    selectedVertexIndex = null;
    ensureSelectedMarker(); selectedVertexMarker.setPosition(null);

    bindPolygonEvents(polygon);
    updateGeomFromPolygon();

    setHint("Polygone créé ✅ Tu peux déplacer les sommets. Le GeoJSON se met à jour automatiquement.");
    updateButtons();
}

function geojsonToLatLngs(geojson){
    if (!geojson) return null;

    let gj = geojson;
    if (typeof gj === 'string'){
        try { gj = JSON.parse(gj); } catch(e){ return null; }
    }

    if (!gj || gj.type !== 'Polygon' || !gj.coordinates || !gj.coordinates[0] || !gj.coordinates[0].length) return null;

    const coords = gj.coordinates[0];
    const noClose = (coords.length >= 2) ? coords.slice(0, -1) : coords;

    return noClose.map(([lng, lat]) => ({ lat: parseFloat(lat), lng: parseFloat(lng) }));
}

function fitToLatLngs(latlngs){
    const bounds = new google.maps.LatLngBounds();
    latlngs.forEach(p => bounds.extend(p));
    map.fitBounds(bounds);
}

// ✅ TABLE ACTION: Voir zone
window.showCityZone = function(cityId){
    const city = citiesById[cityId];
    if (!city) return;

    const latlngs = geojsonToLatLngs(city.geom);
    if (!latlngs || latlngs.length < 3){
        alert("GeoJSON invalide pour cette ville.");
        return;
    }

    removeViewPolygon();
    viewPolygon = new google.maps.Polygon({
        map,
        paths: latlngs,
        editable: false,
        clickable: false
    });

    fitToLatLngs(latlngs);
    setHint(`Zone affichée : <b>${city.name}</b>. Clique sur <b>Modifier</b> si tu veux l’éditer.`);
    updateButtons();
}

// ✅ TABLE ACTION: Modifier
window.editCity = function(cityId){
    const city = citiesById[cityId];
    if (!city) return;

    formTitleEl().textContent = `Modifier la ville : ${city.name}`;
    btnSubmitEl().innerHTML = `<i class="fas fa-save mr-1"></i> Mettre à jour`;
    btnCancelEditEl().style.display = '';

    editingCityIdEl().value = cityId;
    methodFieldEl().value = 'PUT';
    formEl().action = buildUpdateUrl(cityId);

    nameEl().value = city.name || '';
    codeVilleEl().value = city.code_ville || '';

    removeViewPolygon();
    isDrawing = false;

    if (previewLine) previewLine.setPath([]);
    clearPreviewMarkers();

    selectedVertexIndex = null;
    ensureSelectedMarker(); selectedVertexMarker.setPosition(null);

    const latlngs = geojsonToLatLngs(city.geom);
    if (!latlngs || latlngs.length < 3){
        alert("GeoJSON invalide pour cette ville.");
        return;
    }

    if (polygon) polygon.setMap(null);
    polygon = new google.maps.Polygon({ map, paths: latlngs, editable: true });
    bindPolygonEvents(polygon);
    updateGeomFromPolygon();
    fitToLatLngs(latlngs);

    setHint(`Édition activée : <b>${city.name}</b>. Modifie les sommets puis clique sur <b>Mettre à jour</b>.`);
    updateButtons();
}

btnCancelEditEl().addEventListener('click', function(){
    resetFormToCreate();
    clearAll();
});

// Init map
function initMap(){
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 12
    });

    previewLine = new google.maps.Polyline({ map, clickable:false });
    ensureSelectedMarker();
    selectedVertexMarker.setPosition(null);

    map.addListener('click', (e) => {
        if (!isDrawing) return;
        const path = previewLine.getPath();
        path.push(e.latLng);

        syncPreviewMarkers();
        selectedVertexIndex = path.getLength() - 1;
        ensureSelectedMarker();
        selectedVertexMarker.setPosition(e.latLng);

        updateButtons();
    });

    btnStart().addEventListener('click', startDrawing);
    btnUndo().addEventListener('click', undoLastPoint);
    btnDeleteVertex().addEventListener('click', deleteSelectedVertex);
    btnFinish().addEventListener('click', finishPolygon);
    btnClear().addEventListener('click', () => { resetFormToCreate(); clearAll(); });

    // Recharger old geom si exist
    const existing = geomEl().value;
    if (existing){
        const latlngs = geojsonToLatLngs(existing);
        if (latlngs && latlngs.length >= 3){
            polygon = new google.maps.Polygon({ map, paths: latlngs, editable:true });
            bindPolygonEvents(polygon);
            fitToLatLngs(latlngs);
            setHint("Polygone rechargé depuis l'ancien GeoJSON. Tu peux le modifier.");
        }
    }

    updateButtons();
}

window.initMap = initMap;
</script>
@endsection
