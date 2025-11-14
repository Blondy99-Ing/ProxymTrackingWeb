<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Nouveau Véhicule | ProxymTracking</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Custom Tailwind Configuration (for Inter font and Orange colors) -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-orange': '#FF7800',
                        'primary-light': '#FFE2CC',
                        'primary-dark': '#E66A00',
                        'success-green': '#10b981',
                        'success-light-green': '#d1fae5',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Custom styles for non-Tailwind elements like Leaflet map and pseudo-elements -->
    <style>
        /* Base styles */
        body {
            background-color: #f8f9fa; /* gray-100 */
            color: #343a40; /* gray-800 */
            line-height: 1.6;
            min-height: 100vh;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(255, 120, 0, 0.03) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(255, 120, 0, 0.03) 0%, transparent 20%);
        }

        /* Gradient top bar for Card and Map Container (mimicking original design) */
        .card::before, .map-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(135deg, #FF7800 0%, #FF9A40 100%);
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            z-index: 10;
        }
        .map-container::before {
            background: linear-gradient(90deg, #FF7800 0%, #FF9A40 100%);
        }

        /* Map specific styles */
        #map {
            flex: 1;
            width: 100%;
            background: #f8f9fa; /* gray-100 */
            min-height: 500px;
        }

        /* Leaflet Tooltip for selected region (custom styling) */
        .region-label {
            background-color: white !important;
            border: none !important;
            box-shadow: 0 10px 25px -5px rgba(255, 120, 0, 0.25) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            color: #FF7800 !important;
            transform: translateY(-5px) !important;
            opacity: 0.95;
            transition: all 0.3s;
        }

        /* Header Layout Adjustments */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 0.75rem;
            }
            .title-divider {
                display: none;
            }
        }
    </style>
</head>

<body class="font-sans">
    <div class="max-w-7xl mx-auto p-4 md:p-8">
        <!-- Header -->
        <header class="py-6 mb-6">
            <div class="flex items-center justify-center flex-wrap gap-4 header-content">
                <!-- Logo -->
                <div class="flex items-center gap-1.5 font-extrabold text-2xl text-gray-800 logo-container">
                    <i class="fas fa-satellite-dish text-primary-orange text-3xl"></i> <span>Proxym</span><span class="text-primary-orange">Tracking</span>
                </div>
                <!-- Divider -->
                <div class="h-8 w-px bg-gray-300 mx-4 title-divider"></div>
                <!-- Page Title -->
                <div class="flex items-center gap-1.5 text-xl font-semibold page-title">
                    <i class="fas fa-car-side text-primary-orange"></i> Ajouter un Nouveau Véhicule
                </div>
            </div>
        </header>

        <!-- Placeholder for Success Message (Original Blade logic) -->
        <!--
        <div class="bg-success-light-green text-success-green border border-green-300 p-4 rounded-lg flex items-center mb-6">
             <i class="fas fa-check-circle mr-2"></i> Le véhicule a été ajouté avec succès !
        </div>
        -->

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Form Card -->
            <div class="card bg-white rounded-xl shadow-xl p-6 md:p-8 h-full relative transition duration-300 hover:shadow-2xl">
                
                <!-- Selected Region Display -->
                <div id="selected-region-display" class="hidden items-center bg-primary-light text-primary-orange p-3 rounded-lg mb-6 font-medium border-l-4 border-primary-orange animate-in fade-in slide-in-from-top-1">
                    <i class="fas fa-map-marker-alt mr-2 animate-pulse"></i>
                    <span id="region-display-text">Aucune région sélectionnée</span>
                </div>

                <form method="POST" action="#" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">

                        <!-- Immatriculation -->
                        <div class="form-group">
                            <label class="flex items-center gap-2 mb-2 font-semibold text-gray-800" for="immatriculation">
                                <i class="fas fa-id-card text-primary-orange"></i> Immatriculation
                            </label>
                            <input type="text" id="immatriculation" name="immatriculation" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-primary-orange focus:ring-4 focus:ring-primary-orange/30 transition duration-300" required placeholder="Ex: CE-123-AA">
                        </div>

                        <!-- MAC ID GPS -->
                        <div class="form-group">
                            <label class="flex items-center gap-2 mb-2 font-semibold text-gray-800" for="mac_id_gps">
                                <i class="fas fa-satellite-dish text-primary-orange"></i> MAC ID GPS
                            </label>
                            <input type="text" id="mac_id_gps" name="mac_id_gps" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-primary-orange focus:ring-4 focus:ring-primary-orange/30 transition duration-300" required placeholder="Ex: AA:BB:CC:DD:EE:FF">
                        </div>

                        <!-- Marque -->
                        <div class="form-group">
                            <label class="flex items-center gap-2 mb-2 font-semibold text-gray-800" for="marque">
                                <i class="fas fa-copyright text-primary-orange"></i> Marque
                            </label>
                            <input type="text" id="marque" name="marque" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-primary-orange focus:ring-4 focus:ring-primary-orange/30 transition duration-300" required placeholder="Toyota, Honda, etc.">
                        </div>

                        <!-- Model -->
                        <div class="form-group">
                            <label class="flex items-center gap-2 mb-2 font-semibold text-gray-800" for="model">
                                <i class="fas fa-car text-primary-orange"></i> Modèle
                            </label>
                            <input type="text" id="model" name="model" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-primary-orange focus:ring-4 focus:ring-primary-orange/30 transition duration-300" required placeholder="Corolla, Civic, etc.">
                        </div>

                        <!-- Couleur -->
                        <div class="form-group">
                            <label class="flex items-center gap-2 mb-2 font-semibold text-gray-800" for="couleur">
                                <i class="fas fa-palette text-primary-orange"></i> Couleur
                            </label>
                            <input type="text" id="couleur" name="couleur" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-primary-orange focus:ring-4 focus:ring-primary-orange/30 transition duration-300" required placeholder="Bleu, Noir, etc.">
                        </div>

                        <!-- Photo -->
                        <div class="form-group">
                            <label class="flex items-center gap-2 mb-2 font-semibold text-gray-800" for="photo">
                                <i class="fas fa-camera text-primary-orange"></i> Photo
                            </label>
                            <input type="file" id="photo" name="photo" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-light file:text-primary-orange hover:file:bg-primary-orange/20 cursor-pointer" accept="image/*">
                        </div>

                        <!-- Hidden fields for Region info (full-width) -->
                        <input type="hidden" id="region_name" name="region_name">
                        <input type="hidden" id="region_polygon" name="region_polygon">

                        <!-- Form Footer (full-width) -->
                        <div class="md:col-span-2 flex justify-end pt-4">
                            <button type="submit" class="btn-primary inline-flex items-center justify-center gap-2 font-semibold py-3 px-6 rounded-lg text-lg text-white bg-gradient-to-br from-primary-orange to-primary-orange/80 shadow-primary-orange/50 shadow-xl transition duration-300 hover:scale-[1.01] hover:shadow-primary-orange/60 hover:bg-primary-dark">
                                <i class="fas fa-plus-circle"></i> Ajouter le Véhicule
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Map Container -->
            <div class="map-container bg-white rounded-xl shadow-xl h-full flex flex-col relative lg:order-last">
                <div class="map-header bg-white p-4 font-semibold flex items-center gap-2 border-b border-gray-200 relative">
                    <i class="fas fa-map-marked-alt text-primary-orange text-xl"></i> Sélectionner une Région du Cameroun
                </div>
                <div id="map" class="flex-1 w-full min-h-[400px]"></div>
                <!-- Simple warning that data is simulated -->
                <div id="map-warning" class="absolute bottom-0 left-0 right-0 p-2 text-center text-sm bg-yellow-100 text-yellow-800 font-medium opacity-90 z-[500]">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Données de région simulées (GeoJSON factice) pour la démonstration d'interactivité.
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // --- Map Initialization ---
            
            // Initialize map centered on Cameroon
            let map = L.map('map', {
                center: [6.0, 12.5], 
                zoom: 6,
                minZoom: 5,
                maxBounds: [
                    [-5, 5], 
                    [15, 20]  
                ]
            });

            // Load OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            let selectedLayer = null;
            let selectedTooltip = null;

            // --- Fictional GeoJSON Data for Demonstration ---
            // Simulating a couple of regions (Polygons should be in [Lng, Lat] format for GeoJSON)
            const mockGeoJsonData = {
                type: "FeatureCollection",
                features: [
                    {
                        type: "Feature",
                        properties: { "name": "Région du Centre" },
                        geometry: {
                            type: "Polygon",
                            // Simplified mock coordinates for demonstration
                            coordinates: [[
                                [11.5, 4.5], [12.5, 3.5], [14.0, 4.0], [13.0, 5.5], [11.5, 4.5]
                            ]]
                        }
                    },
                    {
                        type: "Feature",
                        properties: { "name": "Région de l'Extrême-Nord" },
                        geometry: {
                            type: "Polygon",
                            // Simplified mock coordinates for demonstration
                            coordinates: [[
                                [13.0, 12.0], [15.0, 10.5], [14.0, 8.5], [12.0, 10.0], [13.0, 12.0]
                            ]]
                        }
                    }
                ]
            };


            // --- Map Interaction Logic ---

            const defaultStyle = {
                color: '#6c757d', // gray-600
                weight: 1.5,
                fillColor: '#FFE2CC', // primary-light
                fillOpacity: 0.4
            };

            const hoverStyle = {
                fillColor: '#FFCFA3',
                fillOpacity: 0.7,
                weight: 2
            };

            const selectedStyle = {
                color: '#2563eb', // blue-600
                weight: 3,
                fillColor: '#93c5fd', // blue-300
                fillOpacity: 0.7
            };

            const regions = L.geoJSON(mockGeoJsonData, {
                style: defaultStyle,
                onEachFeature: function(feature, layer) {

                    // Mouse Events (Hover)
                    layer.on('mouseover', function() {
                        if (layer !== selectedLayer) {
                            layer.setStyle(hoverStyle);
                        }
                    });

                    layer.on('mouseout', function() {
                        if (layer !== selectedLayer) {
                            layer.setStyle(defaultStyle);
                        }
                    });

                    // Click Event (Selection)
                    layer.on('click', function() {
                        const regionName = feature.properties.name || "Unknown Region";
                        const geometry = feature.geometry;

                        // 1. Reset previous selection style and tooltip
                        if (selectedLayer) {
                            selectedLayer.setStyle(defaultStyle);
                        }
                        if (selectedTooltip) {
                            map.removeLayer(selectedTooltip);
                        }
                        
                        // 2. Set new selection style
                        selectedLayer = layer;
                        layer.setStyle(selectedStyle);

                        // 3. Update hidden form inputs
                        document.getElementById('region_name').value = regionName;
                        // Store the polygon data as a JSON string
                        document.getElementById('region_polygon').value = JSON.stringify(geometry); 

                        // 4. Update visible UI display
                        document.getElementById('region-display-text').textContent = `Région sélectionnée: ${regionName}`;
                        document.getElementById('selected-region-display').classList.remove('hidden');
                        document.getElementById('selected-region-display').classList.add('flex');

                        // 5. Fit map to region bounds and add permanent tooltip
                        map.fitBounds(layer.getBounds(), { padding: [40, 40] });

                        selectedTooltip = L.tooltip({
                            permanent: true,
                            direction: 'center',
                            className: 'region-label'
                        })
                            .setContent(regionName)
                            .setLatLng(layer.getBounds().getCenter())
                            .addTo(map);

                        console.log('Région sélectionnée:', regionName, JSON.stringify(geometry));
                    });
                }
            }).addTo(map);

            // Fit map bounds to the loaded GeoJSON data
            map.fitBounds(regions.getBounds(), { padding: [10, 10] });
            
            // Ensure the selected layer is always on top on zoom end
            map.on('zoomend', function() {
                if (selectedLayer) {
                    selectedLayer.bringToFront();
                }
            });
        });
    </script>
</body>
</html>