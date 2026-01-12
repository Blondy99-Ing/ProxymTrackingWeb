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
use App\Services\Media\MediaService;

class TrackingUserController extends Controller
{
    private const ALLOWED_ROLE_SLUGS = [
        'gestionnaire_plateforme',
        'utilisateur_principale',
        'utilisateur_secondaire',
    ];

    private MediaService $media;

    public function __construct(MediaService $media)
    {
        $this->media = $media;
    }

    /**
     * Affiche la liste des utilisateurs.
     */
    public function index()
    {
        $users = User::with('role')->orderBy('nom')->get();

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
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:8048'],

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

        // ID unique
        $anneemois = now()->format('Ym');
        $data['user_unique_id'] = 'PxT-' . $anneemois . '-' . Str::upper(Str::random(4));

        // Photo via MediaService
        if ($request->hasFile('photo')) {
            $folder = 'users/' . $data['user_unique_id'];
            $data['photo'] = $this->media->storeImage($request->file('photo'), $folder);
        }

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
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:8048'],

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

        // Photo replace via MediaService
        if ($request->hasFile('photo')) {
            $folder = 'users/' . ($trackingUser->user_unique_id ?: 'unknown');
            $data['photo'] = $this->media->replaceImage(
                $trackingUser->photo,
                $request->file('photo'),
                $folder
            );
        }

        $trackingUser->update($data);

        return redirect()->route('tracking.users')->with('success', 'Utilisateur mis à jour avec succès.');
    }

    /**
     * Supprime un utilisateur.
     */
    public function destroy(User $trackingUser)
    {
        $this->media->delete($trackingUser->photo);
        $trackingUser->delete();

        return redirect()->route('tracking.users')->with('success', 'Utilisateur supprimé avec succès.');
    }
}
