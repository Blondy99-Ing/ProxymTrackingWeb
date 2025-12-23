@extends('layouts.app')

@section('title', 'Détails Véhicule & Geofence')

@push('head')
    {{-- Google Maps API avec callback --}}
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async defer></script>
@endpush

@section('content')
<div class="space-y-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne 1 : Informations du véhicule --}}
        <div class="lg:col-span-1 space-y-6">

            <div class="ui-card p-6 space-y-4">
                <h3 class="text-lg font-semibold border-b pb-2" style="border-color: var(--color-border-subtle);">
                    Détails du Véhicule
                </h3>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Immatriculation :</span>
                    <span class="text-primary font-semibold">{{ $voiture->immatriculation }}</span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Modèle :</span>
                    <span class="text-semibold">{{ $voiture->marque }} {{ $voiture->model }}</span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Couleur :</span>
                    <span class="flex items-center">
                        <span class="inline-block w-5 h-5 rounded mr-2" style="background-color: {{ $voiture->couleur }}"></span>
                        <span class="text-xs">{{ $voiture->couleur }}</span>
                    </span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Numéro GPS :</span>
                    <span class="text-semibold">{{ $voiture->mac_id_gps }}</span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">SIM GPS :</span>
                    <span class="text-semibold">{{ $voiture->sim_gps ?? '-' }}</span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">ID Unique :</span>
                    <span class="text-semibold">{{ $voiture->voiture_unique_id ?? '-' }}</span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Ville Geofence :</span>
                    <span class="text-semibold">{{ $voiture->geofence_city_name ?? '-' }}</span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Code Ville :</span>
                    <span class="text-semibold">{{ $voiture->geofence_city_code ?? '-' }}</span>
                </div>

                <div class="flex justify-between items-center text-secondary">
                    <span class="font-medium">Type Geofence :</span>
                    <span class="text-semibold">
                        @if($voiture->geofence_is_custom)
                            Personnalisé
                        @else
                            Ville prédéfinie
                        @endif
                    </span>
                </div>

                @if($voiture->photo)
                    <div class="mt-4">
                        <span class="font-medium text-secondary block mb-1">Photo :</span>
                        <img src="{{ asset('storage/' . $voiture->photo) }}"
                             alt="Photo véhicule"
                             class="h-32 w-32 object-cover rounded cursor-pointer"
                             onclick="openImageModal('{{ asset('storage/' . $voiture->photo) }}')">
                    </div>
                @endif

                <div class="mt-4">
                    <a href="{{ route('tracking.vehicles') }}" class="btn-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Retour à la liste
                    </a>
                </div>
            </div>
        </div>

        {{-- Colonne 2/3 : Carte --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="ui-card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);" id="map-title">
                        Carte & Geofence du véhicule
                    </h2>
                </div>
                <div id="userMap" class="rounded-lg shadow-inner" style="height: 70vh;"></div>
            </div>
        </div>
    </div>
</div>

{{-- Modal image --}}
<div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-75">
    <div class="relative bg-white rounded-lg shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden">
        <button id="closeModalBtn" class="absolute top-4 right-4 text-3xl font-bold text-white hover:text-primary transition-colors bg-gray-900 rounded-full h-10 w-10 flex items-center justify-center">&times;</button>
        <img id="modalImage" src="" alt="Image en grand plan" class="w-full h-auto object-contain max-h-[85vh] p-2">
    </div>
</div>
@endsection

@push('scripts')
<script>
  // ====== Données envoyées par le contrôleur ======
  const vehicle = {
    id: {{ $voiture->id }},
    immat: @json($voiture->immatriculation),
    model: @json($voiture->marque . ' ' . $voiture->model),
    lat: {{ $voiture->latestLocation->latitude ?? 0 }},
    lng: {{ $voiture->latestLocation->longitude ?? 0 }},
    status: @json($voiture->latestLocation->status ?? 'Inconnu'),
    photo: @json($voiture->photo ? asset('storage/'.$voiture->photo) : 'https://placehold.co/600x400')
  };

  // geofenceCoords = array de [lng, lat] passé par le contrôleur
  const geofenceCoords = @json($geofenceCoords ?? []);

  // Icône véhicule
  const carIconUrl = @json(asset('assets/icons/car_icon.png'));

  let map;
  let marker;
  let geofencePolygon;
  let infoWindow;

  // ✅ Paramètres "follow vehicle"
  const FOLLOW_ENABLED = true;     // toujours centrer
  const FOLLOW_INTERVAL_MS = 1500; // fréquence de recentrage

  function isValidLatLng(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0);
  }

  function getVehicleLatLng() {
    return { lat: Number(vehicle.lat), lng: Number(vehicle.lng) };
  }

  function centerOnVehicle(forceZoom = false) {
    if (!map || !marker) return;

    const pos = marker.getPosition();
    if (!pos) return;

    // recentrage doux
    map.panTo(pos);

    // zoom mini pour rester lisible
    if (forceZoom) {
      map.setZoom(14);
    } else {
      const z = map.getZoom();
      if (typeof z === "number" && z < 14) map.setZoom(14);
    }
  }

  // (optionnel) si un jour tu veux mettre à jour la position via AJAX/polling
  function updateVehiclePosition(lat, lng, status = null) {
    vehicle.lat = lat;
    vehicle.lng = lng;
    if (status !== null) vehicle.status = status;

    if (marker) {
      marker.setPosition({ lat, lng });
      if (FOLLOW_ENABLED) centerOnVehicle(false);
    }
  }

  // IMPORTANT: le callback Google Maps cherche initMap dans window
  window.initMap = function initMap() {
    const hasPosition = isValidLatLng(vehicle.lat, vehicle.lng);
    const fallbackCenter = { lat: 4.05, lng: 9.7 };

    // Création map
    map = new google.maps.Map(document.getElementById("userMap"), {
      center: hasPosition ? getVehicleLatLng() : fallbackCenter,
      zoom: hasPosition ? 14 : 8,
      gestureHandling: "greedy"
    });

    // Marker véhicule
    if (hasPosition) {
      marker = new google.maps.Marker({
        position: getVehicleLatLng(),
        map,
        title: `${vehicle.model} (${vehicle.immat})`,
        icon: {
          url: carIconUrl,
          scaledSize: new google.maps.Size(72, 72),
          anchor: new google.maps.Point(36, 72) // ✅ bas-centre pour une icône 72x72
        }
      });

      infoWindow = new google.maps.InfoWindow({
        content: `
          <div style="font-size:14px;">
            <b>${vehicle.model} (${vehicle.immat})</b><br>
            Statut: ${vehicle.status}<br>
            Lat: ${vehicle.lat}<br>
            Lng: ${vehicle.lng}
          </div>
        `
      });

      marker.addListener("click", () => infoWindow.open(map, marker));

      // ✅ centre immédiatement
      centerOnVehicle(true);

      // ✅ recentrage régulier (toujours centré)
      if (FOLLOW_ENABLED) {
        setInterval(() => centerOnVehicle(false), FOLLOW_INTERVAL_MS);
      }

      // ✅ recentrer si l'utilisateur redimensionne la fenêtre
      window.addEventListener("resize", () => {
        if (!map) return;
        google.maps.event.trigger(map, "resize");
        centerOnVehicle(false);
      });
    }

    // Geofence (polygone)
    if (geofenceCoords && geofenceCoords.length > 0) {
      const path = geofenceCoords.map((pt) => ({ lat: pt[1], lng: pt[0] }));

      geofencePolygon = new google.maps.Polygon({
        paths: path,
        strokeColor: "#F58220",
        strokeOpacity: 0.9,
        strokeWeight: 2,
        fillColor: "#F58220",
        fillOpacity: 0.15,
        map
      });

      // Si pas de position véhicule, on fit sur le geofence
      if (!hasPosition) {
        const bounds = new google.maps.LatLngBounds();
        path.forEach((p) => bounds.extend(p));
        map.fitBounds(bounds);
      }
      // Si position véhicule, on NE laisse PAS fitBounds déplacer la vue
      // (tu veux toujours centré sur le véhicule)
    }
  };

  // --- Modal image ---
  const imageModal = document.getElementById("imageModal");
  const modalImage = document.getElementById("modalImage");
  const closeModalBtn = document.getElementById("closeModalBtn");

  window.openImageModal = function (url) {
    modalImage.src = url;
    imageModal.classList.remove("hidden");
    imageModal.classList.add("flex");
    document.body.style.overflow = "hidden";
  };

  closeModalBtn.addEventListener("click", function () {
    imageModal.classList.add("hidden");
    imageModal.classList.remove("flex");
    document.body.style.overflow = "";
  });

  imageModal.addEventListener("click", (e) => {
    if (e.target.id === "imageModal") {
      closeModalBtn.click();
    }
  });
</script>

@endpush
