{{-- resources/views/villes/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Villes & Zones')

@push('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') ?? env('GOOGLE_MAPS_KEY') }}&callback=initMap">
</script>
@endpush

@section('content')
@php
    $storeUrl = \Illuminate\Support\Facades\Route::has('villes.store') ? route('villes.store') : url('/villes');

    $villesCollection = $villes ?? collect();

    // On prépare une structure JSON simple, sans closures Blade complexes
    $villesPayload = $villesCollection->map(function ($ville) {
        $updateUrl = \Illuminate\Support\Facades\Route::has('villes.update')
            ? route('villes.update', $ville->id)
            : url('/villes/'.$ville->id);

        $deleteUrl = \Illuminate\Support\Facades\Route::has('villes.destroy')
            ? route('villes.destroy', $ville->id)
            : url('/villes/'.$ville->id);

        return [
            'id' => $ville->id,
            'code' => $ville->code_ville,
            'name' => $ville->name,
            'geom' => $ville->geom, // GeoJSON string
            'update_url' => $updateUrl,
            'delete_url' => $deleteUrl,
        ];
    })->values();
@endphp

<div class="space-y-6">

    {{-- Messages --}}
    @if(session('success'))
        <div class="ui-card p-3">
            <p class="text-sm font-medium text-green-600">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </p>
        </div>
    @endif

    @if(session('error'))
        <div class="ui-card p-3 is-error-state">
            <p class="text-sm font-medium text-red-600">
                <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
            </p>
        </div>
    @endif

    @if ($errors->any())
        <div class="ui-card p-3 is-error-state">
            <p class="text-sm font-semibold text-red-600 mb-2">
                <i class="fas fa-times-circle mr-2"></i>Erreurs :
            </p>
            <ul class="text-sm text-red-600 list-disc ml-5">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Carte + Formulaire --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Carte --}}
        <div class="lg:col-span-2 ui-card p-4">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                        Dessiner la zone (polygone)
                    </h2>
                    <p class="text-[11px] text-secondary mt-0.5">
                        <b>Dessiner</b> → clique pour ajouter des points • <b>Annuler point</b> retire le dernier •
                        clic droit sur un sommet → sélection puis <b>Supprimer point</b> • <b>Terminer</b> pour finir.
                    </p>
                </div>

                <div class="text-[11px] text-secondary">
                    <div id="mapInfo" class="px-3 py-2 rounded-lg border"
                         style="border-color: var(--color-border-subtle); background: var(--color-card);">
                        Carte en chargement…
                    </div>
                </div>
            </div>

            {{-- Toolbar --}}
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <button id="btnStartDraw" type="button"
                        class="px-3 py-2 rounded-lg text-xs font-semibold border hover:shadow"
                        style="border-color: var(--color-border-subtle);">
                    <i class="fas fa-draw-polygon mr-1"></i> Dessiner
                </button>

                <button id="btnFinishDraw" type="button"
                        class="px-3 py-2 rounded-lg text-xs font-semibold border hover:shadow"
                        style="border-color: var(--color-border-subtle);">
                    <i class="fas fa-check mr-1"></i> Terminer
                </button>

                <button id="btnUndoPoint" type="button"
                        class="px-3 py-2 rounded-lg text-xs font-semibold border hover:shadow"
                        style="border-color: var(--color-border-subtle);">
                    <i class="fas fa-undo mr-1"></i> Annuler point
                </button>

                <button id="btnDeletePoint" type="button"
                        class="px-3 py-2 rounded-lg text-xs font-semibold border hover:shadow"
                        style="border-color: var(--color-border-subtle);">
                    <i class="fas fa-eraser mr-1"></i> Supprimer point
                </button>

                <button id="btnClearPolygon" type="button"
                        class="px-3 py-2 rounded-lg text-xs font-semibold border hover:shadow"
                        style="border-color: var(--color-border-subtle);">
                    <i class="fas fa-trash mr-1"></i> Effacer zone
                </button>
            </div>

            <div id="map" class="rounded-xl shadow-inner"
                 style="height: 560px; border: 1px solid var(--color-border-subtle);"></div>
        </div>

        {{-- Formulaire (Select) --}}
        <div class="ui-card p-4">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                        Ville (sélection)
                    </h2>
                    <p class="text-[11px] text-secondary mt-0.5">
                        Choisis une ville pour modifier sa zone, ou clique “Nouvelle ville”.
                    </p>
                </div>
                <div class="w-9 h-9 rounded-full flex items-center justify-center"
                     style="background: rgba(245,130,32,0.12);">
                    <i class="fas fa-city text-primary text-sm"></i>
                </div>
            </div>

           

            <form id="villeForm" method="POST" action="{{ $storeUrl }}" class="space-y-3">
                @csrf

                <input type="hidden" id="ville_id" name="ville_id" value="">
                <input type="hidden" id="_method" name="_method" value="">
                <input type="hidden" id="geom" name="geom" value="{{ old('geom') }}">

                <div>
                    <label class="text-xs font-semibold text-secondary">Code ville</label>
                    <input id="code_ville" name="code_ville" type="text"
                           class="ui-input-style text-xs mt-1"
                           placeholder="Ex: DLA"
                           value="{{ old('code_ville') }}">
                </div>

                <div>
                    <label class="text-xs font-semibold text-secondary">Nom</label>
                    <input id="name" name="name" type="text"
                           class="ui-input-style text-xs mt-1"
                           placeholder="Ex: Douala"
                           value="{{ old('name') }}" required>
                </div>

                <div class="grid grid-cols-1 gap-2 pt-2">
                    <button id="btnSaveVille" type="submit"
                            class="px-3 py-2 rounded-lg text-xs font-semibold text-white hover:shadow"
                            style="background: var(--color-primary);">
                        <i class="fas fa-save mr-1"></i> Enregistrer
                    </button>

                    
                </div>

                

                <p class="text-[11px] text-secondary">
                    ⚠️ La zone (polygone) est obligatoire.
                </p>
            </form>
        </div>
    </div>

    {{-- TABLEAU --}}
    <div class="ui-card p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                Liste des villes ({{ $villesCollection->count() }})
            </h2>
            <p class="text-[11px] text-secondary">Voir / Modifier / Supprimer</p>
        </div>

        <div class="ui-table-container">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Code</th>
                        <th>Nom</th>
                        <th style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($villesCollection as $ville)
                    @php
                        $updateUrl = \Illuminate\Support\Facades\Route::has('villes.update')
                            ? route('villes.update', $ville->id)
                            : url('/villes/'.$ville->id);

                        $deleteUrl = \Illuminate\Support\Facades\Route::has('villes.destroy')
                            ? route('villes.destroy', $ville->id)
                            : url('/villes/'.$ville->id);
                    @endphp
                    <tr>
                        <td>{{ $ville->code_ville ?? '-' }}</td>
                        <td>{{ $ville->name }}</td>
                        <td>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        class="js-ville-view text-blue-600 hover:text-blue-800 p-2"
                                        data-id="{{ $ville->id }}"
                                        title="Voir la zone">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <button type="button"
                                        class="js-ville-edit text-amber-600 hover:text-amber-800 p-2"
                                        data-id="{{ $ville->id }}"
                                        title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button type="button"
                                        class="js-ville-delete text-red-600 hover:text-red-800 p-2"
                                        data-delete-url="{{ $deleteUrl }}"
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-sm text-secondary py-6">
                            Aucune ville enregistrée.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- JSON villes (robuste, évite erreurs Blade) --}}
<script type="application/json" id="villesJson">
{!! json_encode($villesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>

<script>
(function () {
  let map = null;

  // Drawing state
  let drawingMode = false;
  let tempPath = [];
  let tempMarkers = [];
  let tempPolyline = null;

  // Final polygon
  let polygon = null;
  let selectedVertex = null;

  const $ = (id) => document.getElementById(id);

  function getVillesData() {
    const el = $('villesJson');
    if (!el) return [];
    try { return JSON.parse(el.textContent || '[]'); } catch(e) { return []; }
  }

  const villesIndex = getVillesData();

  function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function setInfo(msg) {
    const el = $('mapInfo');
    if (el) el.textContent = msg;
  }

  function setButtonsState() {
    const hasPolygon = !!polygon;
    const hasTemp = tempPath.length > 0;

    if ($('btnStartDraw')) $('btnStartDraw').disabled = drawingMode;
    if ($('btnFinishDraw')) $('btnFinishDraw').disabled = !drawingMode || tempPath.length < 3;
    if ($('btnUndoPoint')) $('btnUndoPoint').disabled = drawingMode ? !hasTemp : !hasPolygon;
    if ($('btnDeletePoint')) $('btnDeletePoint').disabled = !(hasPolygon && selectedVertex !== null);
    if ($('btnClearPolygon')) $('btnClearPolygon').disabled = !(hasPolygon || hasTemp);

    if ($('btnDeleteVille')) $('btnDeleteVille').disabled = !($('ville_id') && $('ville_id').value);
  }

  function clearTemp() {
    tempPath = [];
    tempMarkers.forEach(m => m.setMap(null));
    tempMarkers = [];
    if (tempPolyline) {
      tempPolyline.setMap(null);
      tempPolyline = null;
    }
  }

  function clearPolygon() {
    if (polygon) {
      polygon.setMap(null);
      polygon = null;
    }
    selectedVertex = null;
    if ($('geom')) $('geom').value = '';
  }

  function safeParseGeo(geomStr) {
    if (!geomStr) return null;
    try { return JSON.parse(geomStr); } catch(e) { return null; }
  }

  function geojsonToPaths(geo) {
    if (!geo) return [];
    let g = geo;
    if (g.type === 'Feature') g = g.geometry;
    if (g.type === 'FeatureCollection') g = (g.features && g.features[0]) ? g.features[0].geometry : null;
    if (!g || g.type !== 'Polygon') return [];

    const ring = (g.coordinates && g.coordinates[0]) ? g.coordinates[0] : [];
    const pts = ring.map(function (pair) {
      const lng = parseFloat(pair[0]);
      const lat = parseFloat(pair[1]);
      return { lat: lat, lng: lng };
    }).filter(p => Number.isFinite(p.lat) && Number.isFinite(p.lng));

    // Si le dernier point = premier, on peut enlever pour l'affichage/edit
    if (pts.length > 2) {
      const a = pts[0];
      const b = pts[pts.length - 1];
      if (a.lat === b.lat && a.lng === b.lng) pts.pop();
    }
    return pts;
  }

  function polygonToGeojson(poly) {
    const path = poly.getPath();
    const coords = [];
    for (let i = 0; i < path.getLength(); i++) {
      const p = path.getAt(i);
      coords.push([p.lng(), p.lat()]);
    }
    // ferme l'anneau
    if (coords.length) coords.push([coords[0][0], coords[0][1]]);
    return {
      type: "Feature",
      properties: {},
      geometry: { type: "Polygon", coordinates: [coords] }
    };
  }

  function syncGeom() {
    if (!polygon || !$('geom')) return;
    $('geom').value = JSON.stringify(polygonToGeojson(polygon));
  }

  function fitTo(paths) {
    if (!map || !paths.length) return;
    const bounds = new google.maps.LatLngBounds();
    paths.forEach(p => bounds.extend(p));
    map.fitBounds(bounds);
  }

  function attachPolygonEvents() {
    if (!polygon) return;

    const path = polygon.getPath();
    google.maps.event.addListener(path, 'set_at', syncGeom);
    google.maps.event.addListener(path, 'insert_at', syncGeom);
    google.maps.event.addListener(path, 'remove_at', syncGeom);

    google.maps.event.addListener(polygon, 'rightclick', function (e) {
      if (e.vertex != null) {
        selectedVertex = e.vertex;
        setInfo("Sommet sélectionné → clique \"Supprimer point\".");
        setButtonsState();
      }
    });

    syncGeom();
  }

  function loadPolygonFromVille(villeId, editable) {
    const v = villesIndex.find(x => String(x.id) === String(villeId));
    clearTemp();
    clearPolygon();

    if (!v) {
      setInfo("Ville introuvable.");
      setButtonsState();
      return;
    }

    const geo = safeParseGeo(v.geom);
    const paths = geojsonToPaths(geo);

    if (paths.length < 3) {
      setInfo("Zone vide ou GeoJSON invalide.");
      setButtonsState();
      return;
    }

    polygon = new google.maps.Polygon({
      map: map,
      paths: paths,
      editable: !!editable,
      draggable: !!editable,
      strokeWeight: 2
    });

    attachPolygonEvents();
    fitTo(paths);
    setInfo(editable ? "Zone chargée (édition activée)." : "Zone affichée (lecture).");
    setButtonsState();
  }

  function startDrawing() {
    drawingMode = true;
    selectedVertex = null;

    clearTemp();
    clearPolygon();

    tempPolyline = new google.maps.Polyline({
      map: map,
      path: [],
      strokeWeight: 2
    });

    setInfo("Mode dessin : clique pour ajouter des points.");
    setButtonsState();
  }

  function addPoint(latLng) {
    const p = { lat: latLng.lat(), lng: latLng.lng() };
    tempPath.push(p);

    tempMarkers.push(new google.maps.Marker({
      map: map,
      position: p,
      clickable: false
    }));

    if (tempPolyline) tempPolyline.setPath(tempPath);

    if (tempPath.length >= 3) {
      if (!polygon) {
        polygon = new google.maps.Polygon({
          map: map,
          paths: tempPath,
          editable: true,
          draggable: true,
          strokeWeight: 2
        });
        attachPolygonEvents();
      } else {
        polygon.setPaths(tempPath);
        syncGeom();
      }
    }

    setButtonsState();
  }

  function finishDrawing() {
    if (tempPath.length < 3) {
      setInfo("Il faut au moins 3 points.");
      return;
    }
    drawingMode = false;
    clearTemp();
    setInfo("Polygone terminé. Tu peux déplacer les sommets.");
    setButtonsState();
  }

  function undo() {
    if (drawingMode) {
      if (!tempPath.length) return;

      tempPath.pop();
      const m = tempMarkers.pop();
      if (m) m.setMap(null);

      if (tempPolyline) tempPolyline.setPath(tempPath);

      if (polygon) {
        if (tempPath.length >= 3) {
          polygon.setPaths(tempPath);
          syncGeom();
        } else {
          clearPolygon();
        }
      }

      setButtonsState();
      return;
    }

    if (!polygon) return;
    const path = polygon.getPath();
    if (path.getLength() > 0) path.removeAt(path.getLength() - 1);
    setButtonsState();
  }

  function deleteSelectedVertex() {
    if (!polygon || selectedVertex === null) return;
    const path = polygon.getPath();
    if (selectedVertex >= 0 && selectedVertex < path.getLength()) {
      path.removeAt(selectedVertex);
      selectedVertex = null;
      setInfo("Sommet supprimé.");
      setButtonsState();
    }
  }

  function clearAll() {
    drawingMode = false;
    clearTemp();
    clearPolygon();
    setInfo("Zone effacée.");
    setButtonsState();
  }

  function setFormCreateMode() {
    if ($('ville_id')) $('ville_id').value = '';
    if ($('_method')) $('_method').value = '';
    if ($('villeForm')) $('villeForm').action = $('btnNewVille') ? $('btnNewVille').dataset.storeUrl : '';

    if ($('code_ville')) $('code_ville').value = '';
    if ($('name')) $('name').value = '';
    if ($('villeSelect')) $('villeSelect').value = '';

    clearAll();
    setInfo("Nouvelle ville : dessine la zone puis enregistre.");
    setButtonsState();
  }

  function setFormEditMode(v) {
    if ($('ville_id')) $('ville_id').value = v.id || '';
    if ($('_method')) $('_method').value = 'PUT';
    if ($('villeForm')) $('villeForm').action = v.update_url;

    if ($('code_ville')) $('code_ville').value = v.code || '';
    if ($('name')) $('name').value = v.name || '';
    if ($('villeSelect')) $('villeSelect').value = v.id;

    loadPolygonFromVille(v.id, true);
    setButtonsState();
  }

  function deleteVilleByUrl(url) {
    if (!url) {
      alert("URL de suppression introuvable.");
      return;
    }
    if (!confirm("Supprimer cette ville ?")) return;

    const f = document.createElement('form');
    f.method = 'POST';
    f.action = url;

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = csrfToken();

    const m = document.createElement('input');
    m.type = 'hidden';
    m.name = '_method';
    m.value = 'DELETE';

    f.appendChild(csrf);
    f.appendChild(m);
    document.body.appendChild(f);
    f.submit();
  }

  function bindUI() {
    if ($('btnStartDraw')) $('btnStartDraw').addEventListener('click', function (e) { e.preventDefault(); startDrawing(); });
    if ($('btnFinishDraw')) $('btnFinishDraw').addEventListener('click', function (e) { e.preventDefault(); finishDrawing(); });
    if ($('btnUndoPoint')) $('btnUndoPoint').addEventListener('click', function (e) { e.preventDefault(); undo(); });
    if ($('btnDeletePoint')) $('btnDeletePoint').addEventListener('click', function (e) { e.preventDefault(); deleteSelectedVertex(); });
    if ($('btnClearPolygon')) $('btnClearPolygon').addEventListener('click', function (e) { e.preventDefault(); clearAll(); });

    if ($('btnNewVille')) $('btnNewVille').addEventListener('click', function (e) { e.preventDefault(); setFormCreateMode(); });

    if ($('villeSelect')) $('villeSelect').addEventListener('change', function () {
      const id = this.value;
      if (!id) { setFormCreateMode(); return; }
      const v = villesIndex.find(x => String(x.id) === String(id));
      if (!v) { setFormCreateMode(); return; }
      setFormEditMode(v);
    });

    if ($('btnDeleteVille')) $('btnDeleteVille').addEventListener('click', function (e) {
      e.preventDefault();
      const id = $('ville_id') ? $('ville_id').value : '';
      if (!id) return;
      const v = villesIndex.find(x => String(x.id) === String(id));
      if (!v) return;
      deleteVilleByUrl(v.delete_url);
    });

    document.querySelectorAll('.js-ville-edit').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        const v = villesIndex.find(x => String(x.id) === String(id));
        if (v) setFormEditMode(v);
      });
    });

    document.querySelectorAll('.js-ville-view').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const id = btn.getAttribute('data-id');
        loadPolygonFromVille(id, false);
      });
    });

    document.querySelectorAll('.js-ville-delete').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        deleteVilleByUrl(btn.getAttribute('data-delete-url'));
      });
    });

    if ($('villeForm')) $('villeForm').addEventListener('submit', function (e) {
      const geomVal = $('geom') ? ($('geom').value || '').trim() : '';
      if (!geomVal) {
        e.preventDefault();
        alert("Dessine une zone (polygone) avant d’enregistrer.");
      }
    });

    setButtonsState();
  }

  window.initMap = function () {
    map = new google.maps.Map(document.getElementById('map'), {
      center: { lat: 4.0511, lng: 9.7679 },
      zoom: 12
    });

    map.addListener('click', function (e) {
      if (!drawingMode) return;
      addPoint(e.latLng);
    });

    bindUI();
    setInfo("Carte prête. Choisis une ville dans le select ou clique 'Nouvelle ville'.");
  };
})();
</script>
@endsection
