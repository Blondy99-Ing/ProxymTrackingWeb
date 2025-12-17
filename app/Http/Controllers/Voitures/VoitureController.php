<?php

namespace App\Http\Controllers\Voitures;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voiture;
use App\Models\User; // 👈 A ajouter
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\GpsControlService;
use Illuminate\Validation\Rule;


class VoitureController extends Controller
{
    private GpsControlService $gps;

    public function __construct(GpsControlService $gps)
    {
        $this->gps = $gps;
    }

    /**
     * PAGE INDEX – Liste + Form
     */
    public function index(Request $request)
    {
        $voitures = Voiture::all();
        $voitureEdit = null;

        if ($request->has('edit')) {
            $voitureEdit = Voiture::find($request->edit);
        }

        return view('voitures.index', compact('voitures', 'voitureEdit'));
    }

    /**
     * STORE
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'immatriculation'    => 'required|string|max:255',
            'model'              => 'required|string|max:255',
            'couleur'            => 'required|string|max:255',
            'marque'             => 'required|string|max:255',
            'sim_gps'            => 'nullable|string',
            'mac_id_gps'         => 'required|string|max:255|unique:voitures,mac_id_gps',
            'photo'              => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'geofence_polygon'   => 'nullable|string',
            'geofence_city_code' => 'nullable|string',
            'geofence_city_name' => 'nullable|string',
            'geofence_is_custom' => 'nullable|boolean',
        ]);

        if ($request->hasFile('photo')) {
            $validatedData['photo'] = $request->file('photo')->store('photos');
        }

        $validatedData['voiture_unique_id'] = 'VH-' . now()->format('Ym') . '-' . Str::random(6);

        // 🟠 Conversion polygon JSON → tableau puis JSON string pour la BDD
        $polygonArray = $this->extractPolygon($request->input('geofence_polygon'));
        $validatedData['geofence_zone'] = $polygonArray ? json_encode($polygonArray) : null;

        $validatedData['geofence_city_code'] = $request->input('geofence_city_code');
        $validatedData['geofence_city_name'] = $request->input('geofence_city_name');
        $validatedData['geofence_is_custom'] = $request->input('geofence_is_custom', 0);

        Voiture::create($validatedData);

        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule ajouté avec succès.');
    }

    /**
     * UPDATE
     */
    public function update(Request $request, Voiture $voiture)
{
    $validatedData = $request->validate([
        'immatriculation'    => 'required|string|max:255',
        'model'              => 'required|string|max:255',
        'couleur'            => 'required|string|max:255',
        'marque'             => 'required|string|max:255',
        'sim_gps'            => 'nullable|string',
        'mac_id_gps'         => 'required|string|max:255|unique:voitures,mac_id_gps,' . $voiture->id,
        'photo'              => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',

        'geofence_is_custom' => 'nullable|boolean',
        'geofence_polygon'   => ['nullable','string', Rule::requiredIf(fn() => (int)$request->input('geofence_is_custom') === 1)],
        'geofence_city_code' => 'nullable|string',
        'geofence_city_name' => 'nullable|string',
    ]);

    if ($request->hasFile('photo')) {
        if ($voiture->photo) Storage::delete($voiture->photo);
        $validatedData['photo'] = $request->file('photo')->store('photos');
    }

    $isCustom = (int) $request->input('geofence_is_custom', 0);
    $polygonArray = $this->extractPolygon($request->input('geofence_polygon'));

    // ✅ si custom => doit être valide
    if ($isCustom === 1 && !$polygonArray) {
        return back()->with('error', 'Geofence personnalisé invalide : dessinez puis terminez le polygone.')->withInput();
    }

    // ✅ ne mettre à jour geofence_zone QUE si on a un polygon valide
    if ($polygonArray) {
        $validatedData['geofence_zone'] = json_encode($polygonArray);
    }

    // ✅ gestion city/custom cohérente
    $validatedData['geofence_is_custom'] = $isCustom;

    if ($isCustom === 1) {
        $validatedData['geofence_city_code'] = null;
        $validatedData['geofence_city_name'] = null;
    } else {
        $validatedData['geofence_city_code'] = $request->input('geofence_city_code');
        $validatedData['geofence_city_name'] = $request->input('geofence_city_name');
    }

    // ⚠️ ne pas tenter de sauvegarder geofence_polygon (ce n’est pas une colonne)
    unset($validatedData['geofence_polygon']);

    $voiture->update($validatedData);

    return redirect()->route('tracking.vehicles')->with('success', 'Véhicule mis à jour avec succès.');
}


    /**
     * DELETE ASSOCIATIONS USER<->VOITURE (pas le véhicule lui-même)
     */
   public function destroy($id)
{
    $voiture = Voiture::with('utilisateur')->findOrFail($id);

    // detach relations pivot si besoin
    $voiture->utilisateur()->detach();

    // supprimer photo
    if ($voiture->photo) {
        Storage::delete($voiture->photo);
    }

    // ✅ supprimer le véhicule
    $voiture->delete();

    return redirect()->back()->with('success', 'Véhicule supprimé avec succès.');
}


    /* ============================================================
        ███   STATUT MOTEUR via SERVICE GPS + REDIS
       ============================================================ */

    /**
     * Retourner l'état moteur en temps réel
     */
    public function getEngineStatus($id)
    {
        $voiture = Voiture::findOrFail($id);

        $gps = $this->gps->getEngineStatus($voiture->mac_id_gps);

        if (!$gps['success']) {
            return response()->json([
                'success' => false,
                'engine_on' => false,
                'message' => $gps['message'] ?? "Erreur API"
            ], 500);
        }

        return response()->json([
            'success' => true,
            'engine_on' => $gps['engine_on'],
            'online' => $gps['online'],
            'raw' => $gps
        ]);
    }

    /* ============================================================
       ███   TOGGLE MOTEUR  (OILCUT / OILON)
       ============================================================ */

    public function toggleEngine($id)
    {
        $voiture = Voiture::findOrFail($id);

        // 1️⃣ Lire statut réel
        $gps = $this->gps->getEngineStatus($voiture->mac_id_gps);

        if (!$gps['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible d’obtenir statut moteur'
            ]);
        }

        $isOn = $gps['engine_on'];

        // 2️⃣ Déterminer commande
        $command = $isOn ? "OILCUT" : "OILON";

        // 3️⃣ Envoyer commande
        $response = $this->gps->sendGpsCommand($voiture->mac_id_gps, $command);

        return response()->json([
            'success' => true,
            'command_sent' => $command,
            'previous_state' => $isOn,
            'new_state' => !$isOn,
            'gps_response' => $response
        ]);
    }

    /* ============================================================
       ███   EXTRACTION GEOfence POLYGON
       ============================================================ */
  private function extractPolygon($json): ?array
{
    if (!$json) return null;

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return null;

    $coords = $decoded['geometry']['coordinates'][0] ?? null;

    if (!is_array($coords) || count($coords) < 3) return null;

    return $coords; // [[lng,lat],...]
}

    /* ============================================================
       ███   details GEOfence POLYGON
       ============================================================ */

    public function detailsVehiculeGeofence($id)
    {
        $voiture = Voiture::findOrFail($id);

        // geofence_zone est stocké en JSON (array de [lng, lat])
        $geofenceCoords = [];
        if (!empty($voiture->geofence_zone)) {
            $decoded = json_decode($voiture->geofence_zone, true);
            if (is_array($decoded)) {
                $geofenceCoords = $decoded; // ex: [[lng, lat], [lng, lat], ...]
            }
        }

        return view('voitures.vehicule_geofence', [
            'voiture'        => $voiture,
            'geofenceCoords' => $geofenceCoords,
        ]);
    }

    /* ============================================================
       ███   ALERTES : TimeZone / SpeedZone pour un utilisateur
       ============================================================ */

    public function defineAlertsForUserVehicle(Request $request, User $user, Voiture $voiture)
{
    // ✅ Validation des champs du formulaire
    $data = $request->validate([
        'time_zone_start'       => 'nullable|date_format:H:i',
        'time_zone_end'         => 'nullable|date_format:H:i',
        'speed_zone'            => 'nullable|numeric|min:0',
        'apply_scope'           => 'required|in:one,all,selected',
        'selected_vehicles'     => 'array',
        'selected_vehicles.*'   => 'integer|exists:voitures,id',
    ]);

    $applyScope = $data['apply_scope'];

    // ✅ On vérifie que le véhicule passé en paramètre appartient bien à cet utilisateur
    //    (par sécurité métier)
    if ($applyScope === 'one') {
        $belongs = $user->voitures()->where('voitures.id', $voiture->id)->exists();
        if (! $belongs) {
            return back()->with('error', "Ce véhicule n'appartient pas à cet utilisateur.");
        }

        $targetVehicles = collect([$voiture]);

    } elseif ($applyScope === 'all') {
        // Tous les véhicules associés à cet utilisateur
        $targetVehicles = $user->voitures;

    } else { // "selected"
        $ids = $data['selected_vehicles'] ?? [];

        if (empty($ids)) {
            return back()
                ->with('error', 'Veuillez sélectionner au moins un véhicule.')
                ->withInput();
        }

        // On restreint aux véhicules qui appartiennent bien à l’utilisateur
        $targetVehicles = $user->voitures()
            ->whereIn('voitures.id', $ids)
            ->get();
    }

    // ✅ Application des réglages sur tous les véhicules cibles
    foreach ($targetVehicles as $v) {
        if ($request->filled('time_zone_start')) {
            $v->time_zone_start = $data['time_zone_start'];
        }
        if ($request->filled('time_zone_end')) {
            $v->time_zone_end = $data['time_zone_end'];
        }
        if ($request->filled('speed_zone')) {
            $v->speed_zone = $data['speed_zone'];
        }

        $v->save();
    }

    return back()->with('success', 'Paramètres d’alertes mis à jour avec succès.');
}







//definition du time zone et du speed dans la page des vehicule 

public function defineAlertsForVehicle(Request $request, Voiture $voiture)
{
    $data = $request->validate([
        'time_zone_start' => 'nullable|date_format:H:i',
        'time_zone_end'   => 'nullable|date_format:H:i',
        'speed_zone'      => 'nullable|integer|min:0',
    ]);

    $voiture->time_zone_start = $data['time_zone_start'] ?? null;
    $voiture->time_zone_end   = $data['time_zone_end']   ?? null;
    $voiture->speed_zone      = $data['speed_zone']      ?? null;
    $voiture->save();

    return redirect()
        ->route('tracking.vehicles')
        ->with('success', "Paramètres d’alertes mis à jour pour le véhicule {$voiture->immatriculation}.");
}

}
