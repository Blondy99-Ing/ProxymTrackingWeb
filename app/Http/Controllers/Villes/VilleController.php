<?php

namespace App\Http\Controllers\Villes;

use App\Http\Controllers\Controller;
use App\Models\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VilleController extends Controller
{
    public function index()
    {
        // La vue charge la carte + liste des villes (tableau en bas)
        $villes = Ville::orderBy('name')->get();

        return view('villes.index', compact('villes'));
    }

    public function store(Request $request)
    {
        Log::info('[VilleController@store] Requête reçue', ['all' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'code_ville' => 'nullable|string|max:50',
            'name'       => 'required|string|max:255',
            'geom'       => 'required|json', // GeoJSON côté client
        ]);

        if ($validator->fails()) {
            Log::warning('[VilleController@store] Validation échouée', [
                'errors' => $validator->errors()->all(),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $ville = Ville::create([
                'code_ville' => $request->input('code_ville'),
                'name'       => $request->input('name'),
                'geom'       => $request->input('geom'),
            ]);

            Log::info('[VilleController@store] Ville créée', ['id' => $ville->id]);

            return redirect()->route('villes.index')->with('success', 'Ville créée avec succès.');
        } catch (\Throwable $e) {
            Log::error('[VilleController@store] Erreur création ville', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de la création de la ville.')
                ->withInput();
        }
    }

    /**
     * Retourne une ville en JSON (utile pour "Voir sur la carte" / "Modifier")
     */
    public function show(Ville $ville)
    {
        return response()->json([
            'id'        => $ville->id,
            'code_ville'=> $ville->code_ville,
            'name'      => $ville->name,
            'geom'      => $ville->geom,
        ]);
    }

    /**
     * Mise à jour d'une ville (nom/code/geom)
     * Attendu: PUT /villes/{ville}
     */
    public function update(Request $request, Ville $ville)
    {
        Log::info('[VilleController@update] Requête reçue', [
            'id'  => $ville->id,
            'all' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'code_ville' => 'nullable|string|max:50',
            'name'       => 'required|string|max:255',
            'geom'       => 'required|json',
        ]);

        if ($validator->fails()) {
            Log::warning('[VilleController@update] Validation échouée', [
                'errors' => $validator->errors()->all(),
            ]);

            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $ville->update([
                'code_ville' => $request->input('code_ville'),
                'name'       => $request->input('name'),
                'geom'       => $request->input('geom'),
            ]);

            Log::info('[VilleController@update] Ville mise à jour', ['id' => $ville->id]);

            return redirect()->route('villes.index')->with('success', 'Ville mise à jour avec succès.');
        } catch (\Throwable $e) {
            Log::error('[VilleController@update] Erreur mise à jour ville', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour de la ville.')
                ->withInput();
        }
    }

    /**
     * Suppression
     * Attendu: DELETE /villes/{ville}
     */
    public function destroy(Ville $ville)
    {
        Log::info('[VilleController@destroy] Suppression demandée', ['id' => $ville->id]);

        try {
            $ville->delete();

            Log::info('[VilleController@destroy] Ville supprimée', ['id' => $ville->id]);

            return redirect()->route('villes.index')->with('success', 'Ville supprimée avec succès.');
        } catch (\Throwable $e) {
            Log::error('[VilleController@destroy] Erreur suppression ville', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Erreur lors de la suppression de la ville.');
        }
    }

    /**
     * (Optionnel) Endpoint JSON pour charger toutes les villes côté JS
     * Route exemple: GET /villes-geojson  -> name('villes.geojson')
     */
    public function geojson()
    {
        $villes = Ville::orderBy('name')->get(['id', 'code_ville', 'name', 'geom']);

        return response()->json($villes);
    }
}
