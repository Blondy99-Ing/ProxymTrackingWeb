<?php

namespace App\Http\Controllers\Voitures;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voiture;
use Illuminate\Support\Str;

class VoitureController extends Controller
{
    // Affichage de la liste + gestion modification
    public function index(Request $request)
    {
        $voitures = Voiture::all();

        // Si on clique sur modifier, récupérer le véhicule
        $voitureEdit = null;
        if ($request->has('edit')) {
            $voitureEdit = Voiture::find($request->edit);
        }

        return view('voitures.index', compact('voitures', 'voitureEdit'));
    }

    // Enregistrement d'un nouveau véhicule
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'immatriculation' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'couleur' => 'required|string|max:255',
            'marque' => 'required|string|max:255',
            'mac_id_gps' => 'required|string|max:255|unique:voitures,mac_id_gps',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'geofence_latitude' => 'required|numeric',
            'geofence_longitude' => 'required|numeric',
            'geofence_radius' => 'required|integer|min:100',
        ]);

        // Upload photo
        if ($request->hasFile('photo')) {
            $validatedData['photo'] = $request->file('photo')->store('photos');
        }

        // Generate unique ID
        $validatedData['voiture_unique_id'] = 'VH-' . now()->format('Ym') . '-' . Str::random(6);

        Voiture::create($validatedData);

        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule ajouté avec succès.');
    }

    // Mise à jour d'un véhicule existant
    public function update(Request $request, Voiture $voiture)
    {
        $validatedData = $request->validate([
            'immatriculation' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'couleur' => 'required|string|max:255',
            'marque' => 'required|string|max:255',
            'mac_id_gps' => 'required|string|max:255|unique:voitures,mac_id_gps,' . $voiture->id,
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'geofence_latitude' => 'required|numeric',
            'geofence_longitude' => 'required|numeric',
            'geofence_radius' => 'required|integer|min:100',
        ]);

        // Upload nouvelle photo si fournie
        if ($request->hasFile('photo')) {
            $validatedData['photo'] = $request->file('photo')->store('photos');
        }

        $voiture->update($validatedData);

        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule mis à jour avec succès.');
    }

    // Suppression d'un véhicule
    public function destroy(Voiture $voiture)
    {
        // Supprimer la photo si elle existe
        if ($voiture->photo && \Storage::exists($voiture->photo)) {
            \Storage::delete($voiture->photo);
        }

        $voiture->delete();

        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule supprimé avec succès.');
    }
}
