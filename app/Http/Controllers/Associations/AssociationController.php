<?php

namespace App\Http\Controllers\Associations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Support\Facades\Log;
use App\Services\DashboardCacheService;

class AssociationController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function index()
    {
        $users = User::all();
        $voitures = Voiture::all(); // tu veux toutes les voitures
        $associations = Voiture::with('utilisateur')->whereHas('utilisateur')->get();

        return view('associations.association', compact('users', 'voitures', 'associations'));
    }

    public function associerVoitureAUtilisateur(Request $request)
    {
        Log::info('Request POST association: ', $request->all());

        $request->validate([
            'user_unique_id'    => 'required|exists:users,user_unique_id',
            'voiture_unique_id' => 'required|array|min:1',
            'voiture_unique_id.*' => 'required|exists:voitures,voiture_unique_id',
            'mode'              => 'nullable|in:create,edit',
        ]);

        $mode = $request->input('mode', 'create');

        $user = User::where('user_unique_id', $request->user_unique_id)->firstOrFail();
        $voitureIds = $request->voiture_unique_id;

        foreach ($voitureIds as $voitureUniqueId) {
            $voiture = Voiture::where('voiture_unique_id', $voitureUniqueId)->firstOrFail();

            if ($mode === 'create') {
                // Si tu veux "1 user max par voiture"
                if ($voiture->utilisateur()->exists()) {
                    return redirect()->back()->with('error', "La voiture {$voiture->immatriculation} est déjà associée.");
                }
            }

            if ($mode === 'edit') {
                $voiture->utilisateur()->detach(); // reset
            }

            $voiture->utilisateur()->syncWithoutDetaching([$user->id]);

            Log::info("Voiture {$voiture->immatriculation} associée à l'utilisateur {$user->nom} (mode: {$mode})");
        }

        // ✅ refresh Redis (OBLIGATOIRE pour que le SSE pousse les nouveaux chiffres + users)
        $this->cache->rebuildStats();
        $this->cache->rebuildFleet();
        // (alerts plus tard si tu veux)

        $message = $mode === 'edit'
            ? 'Association mise à jour avec succès.'
            : 'Associations effectuées avec succès.';

        return redirect()->back()->with('success', $message);
    }

    public function destroy($id)
    {
        $voiture = Voiture::with('utilisateur')->findOrFail($id);
        $voiture->utilisateur()->detach();

        // ✅ refresh Redis
        $this->cache->rebuildStats();
        $this->cache->rebuildFleet();

        return redirect()->back()->with('success', 'Association supprimée avec succès.');
    }
}
