

@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@section('content')
<div class="space-y-8">

    {{-- Statistiques --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Utilisateurs Actifs</p>
                <p class="text-3xl font-bold mt-1 text-primary">{{ $usersCount }}</p>
            </div>
            <div class="text-3xl text-primary opacity-70"><i class="fas fa-users"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Véhicules de la Flotte</p>
                <p class="text-3xl font-bold mt-1">{{ $vehiclesCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-car-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Associations Actives</p>
                <p class="text-3xl font-bold mt-1">{{ $associationsCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-link"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider">Alertes Non-résolues</p>
                <p class="text-3xl font-bold mt-1 text-red-500">{{ $alertsCount }}</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>

    {{-- Carte --}}
    <div class="ui-card">
        <h2 class="text-xl font-bold mb-4">Localisation de la Flotte Globale</h2>
        <div id="fleetMap" class="rounded-lg shadow-inner" style="height: 700px;"></div>
    </div>

    {{-- Tableau alertes --}}
    <div class="ui-card">
        <h2 class="text-xl font-bold mb-4">Historique des Dernières Alertes</h2>
        <table class="ui-table w-full">
            <thead>
                <tr>
                    <th>Véhicule</th>
                    <th>Type</th>
                    <th>Utilisateur</th>
                    <th>Heure</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alerts as $alert)
                    <tr>
                        <td>{{ $alert['vehicle'] }}</td>
                        <td>{{ $alert['type'] }}</td>
                        <td>{{ $alert['users'] ?? '-' }}</td>
                        <td>{{ $alert['time'] }}</td>
                        <td>
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $alert['status_color'] }}" style="color:white;">
                                {{ $alert['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

{{-- Google Maps --}}
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async></script>

<script>
let map;

function initMap() {
    const vehicles = @json($vehicles);

    map = new google.maps.Map(document.getElementById('fleetMap'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 7
    });

    const bounds = new google.maps.LatLngBounds();

    vehicles.forEach(v => {
        if(v.lat == null || v.lon == null) return; // ignore seulement si pas de coords

        const lat = parseFloat(v.lat);
        const lon = parseFloat(v.lon);

        // Icône par défaut bleu
        
        const marker = new google.maps.Marker({
            position: { lat, lng: lon },
            map: map,
            title: v.immatriculation,
            icon: {
                url: "/assets/icons/car_icon.png",
                scaledSize: new google.maps.Size(40, 40)
            }
        });

        bounds.extend(marker.getPosition());

        // InfoWindow avec boutons GPS ON/OFF
        const infoWindow = new google.maps.InfoWindow({
            content: `
                <b>${v.immatriculation}</b><br>
                Status: ${v.status}<br>
                <button onclick="toggleGPS(${v.id}, true)" style="background:green;color:white;padding:2px 5px;margin:2px;border:none;border-radius:4px;">ON</button>
                <button onclick="toggleGPS(${v.id}, false)" style="background:red;color:white;padding:2px 5px;margin:2px;border:none;border-radius:4px;">OFF</button>
            `
        });

        marker.addListener('click', () => infoWindow.open(map, marker));
    });

    if(vehicles.length > 0) {
        map.fitBounds(bounds);

        const listener = google.maps.event.addListener(map, "idle", function() {
            if(map.getZoom() > 14) map.setZoom(14);
            google.maps.event.removeListener(listener);
        });
    }
}

// Fonction toggle GPS (à compléter côté backend)
function toggleGPS(vehicleId, turnOn) {
    fetch(`/vehicles/${vehicleId}/toggle-gps`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
        body: JSON.stringify({ on: turnOn })
    }).then(res => {
        if(res.ok) alert(`Véhicule ${turnOn ? 'activé' : 'désactivé'}`);
        else alert('Erreur lors du changement du GPS');
    });
}
</script>
@endsection

