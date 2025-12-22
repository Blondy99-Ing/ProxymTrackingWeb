<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class TrackingUserController extends Controller
{
    private const ALLOWED_ROLE_SLUGS = [
        'gestionnaire_plateforme',
        'utilisateur_principale',
        'utilisateur_secondaire',
    ];

    /**
     * Affiche la liste des utilisateurs.
     */
    public function index()
    {
        $users = User::with('role')->orderBy('nom')->get();

        // ✅ rôles autorisés pour la plateforme (filtre par SLUG)
        $roles = Role::whereIn('slug', self::ALLOWED_ROLE_SLUGS)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return view('users.tracking_users', compact('users', 'roles'));
    }

    /**
     * Enregistre un nouvel utilisateur.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', 'unique:users,phone'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:8048'],

            // ✅ rôle obligatoire et limité aux slugs autorisés
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where(fn ($q) => $q->whereIn('slug', self::ALLOWED_ROLE_SLUGS)),
            ],

            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $data = $request->only(['nom', 'prenom', 'phone', 'email', 'ville', 'quartier', 'role_id']);
        $data['email'] = strtolower($data['email']);
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $anneemois = now()->format('Ym');
        $data['user_unique_id'] = 'PxT-' . $anneemois . '-' . Str::upper(Str::random(4));

        User::create($data);

        return redirect()->route('tracking.users')->with('success', 'Utilisateur ajouté avec succès.');
    }

    /**
     * Met à jour un utilisateur existant.
     */
    public function update(Request $request, User $trackingUser)
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', 'unique:users,phone,' . $trackingUser->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $trackingUser->id],
            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:8048'],

            // ✅ rôle obligatoire et limité
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where(fn ($q) => $q->whereIn('slug', self::ALLOWED_ROLE_SLUGS)),
            ],

            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        $data = $request->only(['nom', 'prenom', 'phone', 'email', 'ville', 'quartier', 'role_id']);
        $data['email'] = strtolower($data['email']);

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
