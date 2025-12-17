<?php

namespace App\Http\Controllers\Associations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssociationController extends Controller
{
    
    public function index()
{
    $users = User::all();
    $voitures = Voiture::all(); // 🔁 au lieu de whereDoesntHave('utilisateur')
    $associations = Voiture::with('utilisateur')->whereHas('utilisateur')->get();

    return view('associations.association', compact('users', 'voitures', 'associations'));
}

   public function associerVoitureAUtilisateur(Request $request)
{
    Log::info('Request POST association: ', $request->all());

    $request->validate([
        'user_unique_id'   => 'required|exists:users,user_unique_id',
        'voiture_unique_id'=> 'required|array',
        'mode'             => 'nullable|in:create,edit',
    ]);

    $mode = $request->input('mode', 'create');

    $user = User::where('user_unique_id', $request->user_unique_id)->firstOrFail();
    $voitureIds = $request->voiture_unique_id;

    foreach ($voitureIds as $voitureUniqueId) {
        $voiture = Voiture::where('voiture_unique_id', $voitureUniqueId)->firstOrFail();

        if ($mode === 'create') {
            if ($voiture->utilisateur()->exists()) {
                return redirect()->back()->with('error', "La voiture {$voiture->immatriculation} est déjà associée.");
            }
        }

        if ($mode === 'edit') {
            // On enlève toutes les anciennes associations pour ce véhicule
            $voiture->utilisateur()->detach();
        }

        // On associe le nouveau user
        $voiture->utilisateur()->syncWithoutDetaching([$user->id]);

        Log::info("Voiture {$voiture->immatriculation} associée à l'utilisateur {$user->nom} (mode: {$mode})");
    }

    $message = $mode === 'edit'
        ? 'Association mise à jour avec succès.'
        : 'Associations effectuées avec succès.';

    return redirect()->back()->with('success', $message);
}


   public function destroy($id)
{
    // ici $id = ID de la voiture, reçu depuis la vue
    $voiture = Voiture::with('utilisateur')->findOrFail($id);

    // On supprime toutes les associations user <-> cette voiture
    $voiture->utilisateur()->detach();

    return redirect()->back()->with('success', 'Association supprimée avec succès.');
}



}
