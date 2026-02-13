<?php

namespace App\Http\Controllers\Employes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use App\Services\Media\MediaService;

class EmployeController extends Controller
{
    private MediaService $media;

    public function __construct(MediaService $media)
    {
        $this->media = $media;
    }
//gestion des roles sur les actions sensibles
    private function ensureAdmin(): void
{
    abort_unless(auth('web')->user()?->isAdmin(), 403);
}

    /**
     * Liste employés + rôles autorisés (admin, call_center)
     */
    public function index()
    {
        $employes = Employe::with('role')->orderBy('nom')->get();

        $roles = Role::whereIn('slug', ['admin', 'call_center'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        return view('employes.index', compact('employes', 'roles'));
    }

    /**
     * Création employé
     */
    public function store(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'nom'      => ['required', 'string', 'max:255'],
            'prenom'   => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:employes,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'    => ['nullable', 'string', 'max:20'],
            'ville'    => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],

            // ✅ même logique images que users/vehicules
            'photo'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:8048'],

            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->whereIn('slug', ['admin', 'call_center'])),
            ],
        ]);

        $data = $request->only(['nom', 'prenom', 'email', 'phone', 'ville', 'quartier', 'role_id']);
        $data['email'] = strtolower($data['email']);
        $data['password'] = Hash::make($request->password);

        // ✅ ID stable AVANT photo (pour dossier)
        $data['unique_id'] = (string) Str::uuid();

        // ✅ Photo via MediaService
        if ($request->hasFile('photo')) {
            $folder = 'employes/' . $data['unique_id'];
            $data['photo'] = $this->media->storeImage($request->file('photo'), $folder);
        }

        Employe::create($data);

        return redirect()->back()->with('success', 'Employé enregistré avec succès !');
    }

    /**
     * Mise à jour employé
     */
    public function update(Request $request, Employe $employe)
    {
        $this->ensureAdmin();


        $request->validate([
            'nom'      => ['required', 'string', 'max:255'],
            'prenom'   => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:employes,email,' . $employe->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'phone'    => ['nullable', 'string', 'max:20'],
            'ville'    => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],

            'photo'    => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:8048'],

            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->whereIn('slug', ['admin', 'call_center'])),
            ],
        ]);

        $data = $request->only(['nom', 'prenom', 'email', 'phone', 'ville', 'quartier', 'role_id']);
        $data['email'] = strtolower($data['email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // ✅ Photo: remplace + supprime l’ancienne (sur le bon disk)
        if ($request->hasFile('photo')) {
            $folder = 'employes/' . ($employe->unique_id ?: 'unknown');
            $data['photo'] = $this->media->replaceImage(
                $employe->photo,
                $request->file('photo'),
                $folder
            );
        }

        $employe->update($data);

        return redirect()->back()->with('success', 'Employé mis à jour avec succès !');
    }

    /**
     * Suppression employé
     */
    public function destroy(Employe $employe)
    {
        $this->ensureAdmin();
        // ✅ supprimer la photo avant delete
        $this->media->delete($employe->photo);

        $employe->delete();

        return redirect()->route('employes.index')->with('success', 'Employé supprimé avec succès !');
    }
}
