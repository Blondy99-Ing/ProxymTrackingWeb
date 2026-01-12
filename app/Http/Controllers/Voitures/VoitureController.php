<?php

namespace App\Http\Controllers\Voitures;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voiture;
use App\Models\Ville;
use App\Models\User; // 👈 A ajouter
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\GpsControlService;
use Illuminate\Validation\Rule;
use App\Models\SimGps;
use App\Services\Media\MediaService;




class VoitureController extends Controller
{
     private GpsControlService $gps;
    private MediaService $media;
    

    public function __construct(GpsControlService $gps, MediaService $media)
    {
        $this->gps = $gps;
        $this->media = $media;
    }



    

    /**
     * PAGE INDEX – Liste + Form
     */
  public function index(Request $request)
{
    $voitures = Voiture::all();
    $villes = Ville::orderBy('name')->get();
    $voitureEdit = null;

    if ($request->has('edit')) {
        $voitureEdit = Voiture::find($request->edit);
    }

    return view('voitures.index', compact('voitures', 'voitureEdit','villes'));
}



    /**
     * STORE
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'immatriculation'    => 'required|string|max:255',
            'vin'                => 'nullable|string|max:255',
            'model'              => 'required|string|max:255',
            'couleur'            => 'required|string|max:255',
            'marque'             => 'required|string|max:255',
            'mac_id_gps'         => 'required|string|max:255|unique:voitures,mac_id_gps',
            'photo'              => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:8048',

            'geofence_polygon'   => 'nullable|string',
            'geofence_city_code' => 'nullable|string',
            'geofence_city_name' => 'nullable|string',
            'geofence_is_custom' => 'nullable|boolean',
        ]);

        // ✅ Générer l’ID unique AVANT stockage (utile pour ranger les photos)
        $validatedData['voiture_unique_id'] = 'VH-' . now()->format('Ym') . '-' . Str::upper(Str::random(6));

        // ✅ Photo (via service) -> stockage stable local/prod
        if ($request->hasFile('photo')) {
            $folder = 'vehicles/' . $validatedData['voiture_unique_id'];
            $validatedData['photo'] = $this->media->storeImage($request->file('photo'), $folder);
        }

        // Geofence
        $polygonArray = $this->extractPolygon($request->input('geofence_polygon'));
        $validatedData['geofence_zone'] = $polygonArray ? json_encode($polygonArray) : null;

        $validatedData['geofence_city_code'] = $request->input('geofence_city_code');
        $validatedData['geofence_city_name'] = $request->input('geofence_city_name');
        $validatedData['geofence_is_custom'] = (int) $request->input('geofence_is_custom', 0);

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
            'vin'                => 'nullable|string|max:255',
            'model'              => 'required|string|max:255',
            'couleur'            => 'required|string|max:255',
            'marque'             => 'required|string|max:255',
            'mac_id_gps'         => 'required|string|max:255|unique:voitures,mac_id_gps,' . $voiture->id,
            'photo'              => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:8048',

            'geofence_is_custom' => 'nullable|boolean',
            'geofence_polygon'   => ['nullable', 'string', Rule::requiredIf(fn() => (int) $request->input('geofence_is_custom') === 1)],
            'geofence_city_code' => 'nullable|string',
            'geofence_city_name' => 'nullable|string',
        ]);

        // ✅ Photo (replace) via service
        if ($request->hasFile('photo')) {
            $folder = 'vehicles/' . ($voiture->voiture_unique_id ?: 'unknown');
            $validatedData['photo'] = $this->media->replaceImage(
                $voiture->photo,
                $request->file('photo'),
                $folder
            );
        }

        $isCustom = (int) $request->input('geofence_is_custom', 0);
        $polygonArray = $this->extractPolygon($request->input('geofence_polygon'));

        if ($isCustom === 1 && !$polygonArray) {
            return back()
                ->with('error', 'Geofence personnalisé invalide : dessinez puis terminez le polygone.')
                ->withInput();
        }

        if ($polygonArray) {
            $validatedData['geofence_zone'] = json_encode($polygonArray);
        }

        $validatedData['geofence_is_custom'] = $isCustom;

        if ($isCustom === 1) {
            $validatedData['geofence_city_code'] = null;
            $validatedData['geofence_city_name'] = null;
        } else {
            $validatedData['geofence_city_code'] = $request->input('geofence_city_code');
            $validatedData['geofence_city_name'] = $request->input('geofence_city_name');
        }

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

        // detach relations pivot
        $voiture->utilisateur()->detach();

        // ✅ supprimer photo via service (bon disk)
        $this->media->delete($voiture->photo);

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
    $macId = trim((string) $voiture->mac_id_gps);

    if ($macId === '') {
        return response()->json([
            'success' => false,
            'engine_on' => false,
            'message' => 'mac_id_gps vide'
        ], 422);
    }

    // ✅ Appel service (provider 18GPS) via macid
    $gps = $this->gps->getEngineStatusFromLastLocation($macId);

    if (!($gps['success'] ?? false)) {
        return response()->json([
            'success' => false,
            'engine_on' => false,
            'message' => $gps['message'] ?? "Erreur API"
        ], 500);
    }

    // engineState: CUT / ON / OFF / UNKNOWN
    $engineState = $gps['decoded']['engineState'] ?? 'UNKNOWN';

    // "engine_on" = vrai si moteur ON (tu peux aussi décider: true si pas CUT)
    $engineOn = ($engineState === 'ON');

    // online: même logique que ton GpsSimController (<= 10 min)
    $last = $gps['location']['heart_time']
        ?? $gps['location']['sys_time']
        ?? $gps['datetime']
        ?? null;

    $online = null;
    if ($last) {
        try {
            $online = \Carbon\Carbon::parse($last)->diffInMinutes(now()) <= 10;
        } catch (\Throwable $e) {
            $online = null;
        }
    }

    return response()->json([
        'success' => true,
        'engine_on' => $engineOn,
        'engine_state' => $engineState,
        'online' => $online,
        'raw' => $gps,
    ]);
}

    /* ============================================================
       ███   TOGGLE MOTEUR  (OILCUT / OILON)
       ============================================================ */

    public function toggleEngine($id)
{
    $voiture = Voiture::findOrFail($id);
    $macId = trim((string) $voiture->mac_id_gps);

    if ($macId === '') {
        return response()->json([
            'success' => false,
            'message' => 'mac_id_gps vide'
        ], 422);
    }

    // 1️⃣ Lire statut réel (provider 18GPS via ton service)
    $gps = $this->gps->getEngineStatusFromLastLocation($macId);

    if (!($gps['success'] ?? false)) {
        return response()->json([
            'success' => false,
            'message' => $gps['message'] ?? 'Impossible d’obtenir statut moteur'
        ], 500);
    }

    $engineState = $gps['decoded']['engineState'] ?? 'UNKNOWN';

    // Ici on considère ON = moteur allumé (ACC=1 + relay ok)
    $isOn = ($engineState === 'ON');

    // 2️⃣ Envoyer commande via wrappers du service
    // - si moteur ON => on coupe (close relay)
    // - sinon => on restaure (open relay)
    $response = $isOn
        ? $this->gps->cutEngine($macId)
        : $this->gps->restoreEngine($macId);

    $ok = ($response['success'] ?? null);

    return response()->json([
        'success' => ($ok === null) ? true : (strtolower((string)$ok) === 'true' || $ok === true || $ok === 1),
        'command_sent' => $isOn ? 'CUT_ENGINE' : 'RESTORE_ENGINE',
        'previous_state' => $engineState,
        'expected_new_state' => $isOn ? 'CUT' : 'ON',
        'gps_response' => $response,
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



// api pour la recherche des GPS dans le formulaire d'ajout de vehicule
public function searchSimGps(Request $request)
{
    $q = trim((string) $request->query('q', ''));

    // On évite les requêtes inutiles
    if (mb_strlen($q) < 2) {
        return response()->json([]);
    }

    // On retourne uniquement mac_id (comme demandé)
    $items = SimGps::query()
        ->where('mac_id', 'like', '%' . $q . '%')
        ->whereNotNull('mac_id')
        ->select('mac_id')
        ->distinct()
        ->orderBy('mac_id')
        ->limit(15)
        ->get();

    return response()->json($items);
}

}
