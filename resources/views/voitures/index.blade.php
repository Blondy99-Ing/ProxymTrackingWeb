@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-8 p-4 md:p-8">

    {{-- Bande de navigation secondaire --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4" style="border-color: var(--color-border-subtle);">
        <h1 class="text-3xl font-bold font-orbitron" style="color: var(--color-text);">Gestion des Véhicules</h1>
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('tracking.vehicles') }}" class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                <i class="fas fa-car mr-2"></i> Véhicules
            </a>
        </div>
    </div>

    {{-- Bouton pour basculer le formulaire --}}
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

    {{-- Formulaire conditionnel pour ajout / modification --}}
    <div id="vehicle-form" class="ui-card mt-4 max-h-0 overflow-hidden opacity-0 transition-all duration-500 ease-in-out @if($errors->any() || isset($voitureEdit)) is-error-state @endif">
        <h2 class="text-xl font-bold font-orbitron mb-6">
            @if(isset($voitureEdit))
                Modifier un Véhicule
            @else
                Ajouter un Véhicule
            @endif
        </h2>

        {{-- Affichage des erreurs --}}
        @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong class="font-bold">Erreurs de validation:</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Formulaire --}}
        <form action="@if(isset($voitureEdit)) {{ route('tracking.vehicles.update', $voitureEdit->id) }} @else {{ route('tracking.vehicles.store') }} @endif" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if(isset($voitureEdit))
                @method('PUT')
            @endif

            {{-- Champs de base --}}
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
                    <label for="couleur" class="block text-sm font-medium text-secondary">Couleur</label>
                    <input type="text" class="ui-input-style mt-1" id="couleur" name="couleur" placeholder="Noir, Blanc, Rouge..."
                        value="{{ old('couleur', $voitureEdit->couleur ?? '') }}" required>
                </div>
                <div>
                    <label for="marque" class="block text-sm font-medium text-secondary">Marque</label>
                    <input type="text" class="ui-input-style mt-1" id="marque" name="marque" placeholder="Toyota, Renault..."
                        value="{{ old('marque', $voitureEdit->marque ?? '') }}" required>
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

            {{-- Géofence --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <div>
                    <label for="geofence_latitude" class="block text-sm font-medium text-secondary">Latitude</label>
                    <input type="number" step="0.000001" id="geofence_latitude" name="geofence_latitude"
                        value="{{ old('geofence_latitude', $voitureEdit->geofence_latitude ?? 4.0500) }}" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="geofence_longitude" class="block text-sm font-medium text-secondary">Longitude</label>
                    <input type="number" step="0.000001" id="geofence_longitude" name="geofence_longitude"
                        value="{{ old('geofence_longitude', $voitureEdit->geofence_longitude ?? 9.7000) }}" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="geofence_radius" class="block text-sm font-medium text-secondary">Rayon (mètres)</label>
                    <input type="range" id="geofence_radius_slider" min="100" max="10000000" step="100" value="{{ old('geofence_radius', $voitureEdit->geofence_radius ?? 1000) }}" class="mt-1 w-full">
                    <input type="number" id="geofence_radius" name="geofence_radius" min="100" max="10000000" step="100" value="{{ old('geofence_radius', $voitureEdit->geofence_radius ?? 1000) }}" class="ui-input-style mt-1">
                    <div class="flex justify-between text-xs text-secondary">
                        <span>100m</span>
                        <span>10 000 km</span>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-secondary mb-2">Choisir l'emplacement du véhicule</label>
                <div id="map" class="h-96 w-full rounded border border-gray-300"></div>
            </div>

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
    <div class="ui-card mt-6">
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
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    <tr>
                        <td>{{ $voiture->immatriculation }}</td>
                        <td>{{ $voiture->model }}</td>
                        <td>{{ $voiture->marque }}</td>
                        <td>{{ $voiture->couleur }}</td>
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
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Scripts --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
<script src="https://cdn.jsdelivr.net/npm/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const form = document.getElementById('vehicle-form');
    const toggleBtn = document.getElementById('toggle-form');

    const latInput   = document.getElementById('geofence_latitude');
    const lngInput   = document.getElementById('geofence_longitude');
    const radiusInput = document.getElementById('geofence_radius');
    const radiusSlider = document.getElementById('geofence_radius_slider');

    const initialLat = parseFloat(latInput.value);
    const initialLng = parseFloat(lngInput.value);
    const initialRadius = parseInt(radiusInput.value);

    let mapInitialized = false;
    let map, circle, marker, drawnItems;

    function initMap() {
        if (mapInitialized) return;
        mapInitialized = true;

        map = L.map('map').setView([initialLat, initialLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        circle = L.circle([initialLat, initialLng], {
            radius: initialRadius,
            color: '#4361ee',
            fillColor: '#4895ef',
            fillOpacity: 0.3,
            weight: 2
        }).addTo(map);

        marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);

        function updateFields(lat, lng, radius) {
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);
            radiusInput.value = radius;
            radiusSlider.value = radius;

            circle.setLatLng([lat, lng]);
            circle.setRadius(radius);
            marker.setLatLng([lat, lng]);
        }

        marker.on('drag', e => updateFields(e.latlng.lat, e.latlng.lng, circle.getRadius()));
        map.on('click', e => updateFields(e.latlng.lat, e.latlng.lng, circle.getRadius()));

        radiusInput.addEventListener('input', () =>
            updateFields(parseFloat(latInput.value), parseFloat(lngInput.value), parseInt(radiusInput.value))
        );
        radiusSlider.addEventListener('input', () =>
            updateFields(parseFloat(latInput.value), parseFloat(lngInput.value), parseInt(radiusSlider.value))
        );

        drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        let drawControl = new L.Control.Draw({
            draw: {
                polygon: false,
                polyline: false,
                rectangle: true,
                circle: true,
                marker: false,
                circlemarker: false
            },
            edit: {
                featureGroup: drawnItems
            }
        });
        map.addControl(drawControl);

        map.on(L.Draw.Event.CREATED, function (e) {
            let layer = e.layer;
            drawnItems.clearLayers();
            drawnItems.addLayer(layer);

            if (layer instanceof L.Circle) {
                updateFields(layer.getLatLng().lat, layer.getLatLng().lng, layer.getRadius());
                circle.setLatLng(layer.getLatLng());
                circle.setRadius(layer.getRadius());
            } else if (layer instanceof L.Rectangle) {
                let bounds = layer.getBounds();
                let center = bounds.getCenter();
                let radius = center.distanceTo(bounds.getNorthEast());
                updateFields(center.lat, center.lng, radius);
                circle.setLatLng(center);
                circle.setRadius(radius);
            }
        });

        setTimeout(() => map.invalidateSize(), 200);
    }

    toggleBtn.addEventListener('click', () => {
        const isHidden = form.classList.contains('max-h-0');

        if (isHidden) {
            form.classList.remove('max-h-0', 'opacity-0');
            form.classList.add('max-h-[2000px]', 'opacity-100');
            setTimeout(() => initMap(), 500);
        } else {
            form.classList.remove('max-h-[2000px]', 'opacity-100');
            form.classList.add('max-h-0', 'opacity-0');
        }
    });

    @if(isset($voitureEdit))
        initMap();
        setTimeout(() => map.invalidateSize(), 400);
    @endif

    const table = document.getElementById('example');
    if (table && window.jQuery && typeof $.fn.DataTable !== 'undefined') {
        $(table).DataTable({
            language: { url: "/datatables/i18n/fr-FR.json" }
        });
    }

});
</script>

@endsection
