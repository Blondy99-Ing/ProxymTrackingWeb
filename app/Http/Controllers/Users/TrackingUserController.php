<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TrackingUserController extends Controller
{
    /**
     * Affiche la liste des utilisateurs.
     */
    public function index()
    {
        $users = User::orderBy('nom')->get();
        return view('users.tracking_users', compact('users'));
    }

    /**
     * Enregistre un nouvel utilisateur.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:users',
            'email' => 'required|email|unique:users',
            'ville' => 'nullable|string|max:255',
            'quartier' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'password' => 'required|string|confirmed|min:6',
        ]);

        $data = $request->only(['nom','prenom','phone','email','ville','quartier']);
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $anneemois = now()->format('Ym');
        $data['user_unique_id'] = 'PxT-' . $anneemois . '-' . Str::random(4);

        User::create($data);

        return redirect()->route('tracking.users')->with('success', 'Utilisateur ajouté avec succès.');
    }

    /**
     * Met à jour un utilisateur existant.
     */
    public function update(Request $request, User $trackingUser)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:users,phone,' . $trackingUser->id,
            'email' => 'required|email|unique:users,email,' . $trackingUser->id,
            'ville' => 'nullable|string|max:255',
            'quartier' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'password' => 'nullable|string|confirmed|min:6',
        ]);

        $data = $request->only(['nom','prenom','phone','email','ville','quartier']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $trackingUser->update($data);

        return redirect()->route('tracking.users')->with('success', 'Utilisateur mis à jour avec succès.');
    }

    /**
     * Supprime un utilisateur.
     */
    public function destroy(User $trackingUser)
    {
        $trackingUser->delete();
        return redirect()->route('tracking.users')->with('success', 'Utilisateur supprimé avec succès.');
    }
}
