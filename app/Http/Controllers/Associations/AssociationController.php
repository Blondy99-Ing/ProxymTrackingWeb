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
        $voitures = Voiture::whereDoesntHave('utilisateur')->get();
        $associations = Voiture::with('utilisateur')->whereHas('utilisateur')->get();

        return view('associations.association', compact('users', 'voitures', 'associations'));
    }

    public function associerVoitureAUtilisateur(Request $request)
    {
        Log::info('Request POST association: ', $request->all());

        $request->validate([
            'user_unique_id' => 'required|exists:users,user_unique_id',
            'voiture_unique_id' => 'required|array',
        ]);

        $user = User::where('user_unique_id', $request->user_unique_id)->first();
        $voitureIds = $request->voiture_unique_id;

        foreach ($voitureIds as $voitureUniqueId) {
            $voiture = Voiture::where('voiture_unique_id', $voitureUniqueId)->first();

            if ($voiture->utilisateur()->exists()) {
                return redirect()->back()->with('error', "La voiture {$voiture->immatriculation} est déjà associée.");
            }

            $voiture->utilisateur()->syncWithoutDetaching([$user->id]);
            Log::info("Voiture {$voiture->immatriculation} associée à l'utilisateur {$user->nom}");
        }

        return redirect()->back()->with('success', 'Associations effectuées avec succès.');
    }

    public function destroy($id)
    {
        DB::table('association_user_voitures')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Association supprimée avec succès.');
    }
}
