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

class EmployeController extends Controller
{
    /**
     * Liste employés + rôles autorisés (admin, call_center)
     */
    public function index()
    {
        $employes = Employe::with('role')->orderBy('nom')->get();

        // ✅ seulement admin et call_center (filtre par SLUG, pas par NAME)
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
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:employes,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],

            // ✅ rôle obligatoire + autorisé seulement admin/call_center (par slug)
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->whereIn('slug', ['admin', 'call_center'])),
            ],
        ]);

        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('employes', 'public');
        }

        Employe::create([
            'unique_id' => (string) Str::uuid(),
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'ville' => $request->ville,
            'quartier' => $request->quartier,
            'photo' => $path,
            'role_id' => (int) $request->role_id,
        ]);

        return redirect()->back()->with('success', 'Employé enregistré avec succès !');
    }

    /**
     * Mise à jour employé
     */
    public function update(Request $request, Employe $employe)
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:employes,email,' . $employe->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],

            // ✅ changement de rôle autorisé seulement admin/call_center (par slug)
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->whereIn('slug', ['admin', 'call_center'])),
            ],
        ]);

        if ($request->hasFile('photo')) {
            $employe->photo = $request->file('photo')->store('employes', 'public');
        }

        $employe->nom = $request->nom;
        $employe->prenom = $request->prenom;
        $employe->email = strtolower($request->email);
        $employe->phone = $request->phone;
        $employe->ville = $request->ville;
        $employe->quartier = $request->quartier;
        $employe->role_id = (int) $request->role_id;

        if ($request->filled('password')) {
            $employe->password = Hash::make($request->password);
        }

        $employe->save();

        return redirect()->back()->with('success', 'Employé mis à jour avec succès !');
    }

    /**
     * Suppression employé
     */
    public function destroy(Employe $employe)
    {
        $employe->delete();
        return redirect()->route('employes.index')->with('success', 'Employé supprimé avec succès !');
    }
}
