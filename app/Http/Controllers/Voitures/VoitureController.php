<?php

namespace App\Http\Controllers\Voitures;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voiture;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\GpsControlService;


class VoitureController extends Controller
{
    /**
     * Affichage de la liste + gestion modification
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


    private GpsControlService $gpsService;

    public function __construct(GpsControlService $gpsService)
    {
        $this->gpsService = $gpsService;
    }


    /**
     * Enregistrement d'un nouveau véhicule
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'immatriculation'    => 'required|string|max:255',
            'model'              => 'required|string|max:255',
            'couleur'            => 'required|string|max:255',
            'marque'             => 'required|string|max:255',
            'mac_id_gps'         => 'required|string|max:255|unique:voitures,mac_id_gps',
            'photo'              => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'geofence_polygon'   => 'nullable|string',
            'geofence_city_code' => 'nullable|string',
            'geofence_city_name' => 'nullable|string',
            'geofence_is_custom' => 'nullable|boolean',
        ]);

        // LOG du JSON reçu
        Log::info("Polygon reçu (STORE) :", [
            'polygon_raw' => $request->input('geofence_polygon')
        ]);

        // Upload photo si fournie
        if ($request->hasFile('photo')) {
            $validatedData['photo'] = $request->file('photo')->store('photos');
        }

        $validatedData['voiture_unique_id'] = 'VH-' . now()->format('Ym') . '-' . Str::random(6);

        // Extraire uniquement le tableau de coordonnées du polygon
        $polygonArray = null;
        $polygonJson = $request->input('geofence_polygon');
        if ($polygonJson) {
            $decoded = json_decode($polygonJson, true);
            if (isset($decoded['geometry']['coordinates'][0])) {
                $polygonArray = $decoded['geometry']['coordinates'][0];
            }
        }

        $validatedData['geofence_zone'] = $polygonArray ? json_encode($polygonArray) : null;
        $validatedData['geofence_city_code'] = $request->input('geofence_city_code');
        $validatedData['geofence_city_name'] = $request->input('geofence_city_name');
        $validatedData['geofence_is_custom'] = $request->input('geofence_is_custom', 0);

        Voiture::create($validatedData);

        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule ajouté avec succès.');
    }

    /**
     * Mise à jour d'un véhicule existant
     */
    public function update(Request $request, Voiture $voiture)
    {
        $validatedData = $request->validate([
            'immatriculation'    => 'required|string|max:255',
            'model'              => 'required|string|max:255',
            'couleur'            => 'required|string|max:255',
            'marque'             => 'required|string|max:255',
            'mac_id_gps'         => 'required|string|max:255|unique:voitures,mac_id_gps,' . $voiture->id,
            'photo'              => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'geofence_polygon'   => 'nullable|string',
            'geofence_city_code' => 'nullable|string',
            'geofence_city_name' => 'nullable|string',
            'geofence_is_custom' => 'nullable|boolean',
        ]);

        // LOG du JSON reçu
        Log::info("Polygon reçu (UPDATE) :", [
            'voiture_id'  => $voiture->id,
            'polygon_raw' => $request->input('geofence_polygon')
        ]);

        // Upload nouvelle photo si fournie
        if ($request->hasFile('photo')) {
            if ($voiture->photo && Storage::exists($voiture->photo)) {
                Storage::delete($voiture->photo);
            }
            $validatedData['photo'] = $request->file('photo')->store('photos');
        }

        // Extraire uniquement le tableau de coordonnées du polygon
        $polygonArray = null;
        $polygonJson = $request->input('geofence_polygon');
        if ($polygonJson) {
            $decoded = json_decode($polygonJson, true);
            if (isset($decoded['geometry']['coordinates'][0])) {
                $polygonArray = $decoded['geometry']['coordinates'][0];
            }
        }

        $validatedData['geofence_zone'] = $polygonArray ? json_encode($polygonArray) : null;
        $validatedData['geofence_city_code'] = $request->input('geofence_city_code');
        $validatedData['geofence_city_name'] = $request->input('geofence_city_name');
        $validatedData['geofence_is_custom'] = $request->input('geofence_is_custom', 0);

        $voiture->update($validatedData);

        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule mis à jour avec succès.');
    }

    /**
     * Suppression d'un véhicule
     */
    public function destroy(Voiture $voiture)
    {
        if ($voiture->photo && Storage::exists($voiture->photo)) {
            Storage::delete($voiture->photo);
        }

        $voiture->delete();

        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule supprimé avec succès.');
    }





    /**
 * Récupérer l'état actuel du moteur via le GPS
 */
public function getEngineStatus($id)
{
    $voiture = Voiture::findOrFail($id);
    $status = $this->gpsService->getRealtimeStatusByMac($voiture->mac_id_gps);

    return response()->json([
        'success' => $status['success'],
        'engine_on' => $status['accState'] ?? false,
        'raw' => $status
    ]);
}




/**
 * Allumer ou éteindre le moteur d'un véhicule
 */
public function toggleEngine($id)
{
    $voiture = Voiture::findOrFail($id);

    // 1️⃣ Récupérer le statut réel actuel du moteur via le GPS
    $status = $this->gpsService->getRealtimeStatusByMac($voiture->mac_id_gps);

    if (!$status['success']) {
        return response()->json([
            'success' => false,
            'message' => 'Impossible d’obtenir le statut GPS'
        ], 500);
    }

    // Le fournisseur renvoie accState = true si moteur ON
    $currentState = $status['accState'] ?? false;

    // 2️⃣ Déterminer la commande à envoyer
    // Si moteur est ON → on doit envoyer CLOSERELAY pour éteindre
    // Si moteur est OFF → on doit envoyer OPENRELAY pour allumer
    $command = $currentState ? "CLOSERELAY" : "OPENRELAY";

    // 3️⃣ Envoyer la commande
    $response = $this->gpsService->sendGpsCommand($voiture->mac_id_gps, $command);

    return response()->json([
        'success' => true,
        'sent_command' => $command,
        'gps_response' => $response,
        'previous_state' => $currentState,
        'new_state' => !$currentState,
    ]);
}


}
