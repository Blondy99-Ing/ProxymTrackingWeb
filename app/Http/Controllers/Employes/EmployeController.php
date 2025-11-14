<?php

namespace App\Http\Controllers\Employes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class EmployeController extends Controller
{
    // Affiche la liste
    public function index()
    {
        $employes = Employe::orderBy('nom')->get();
        return view('employes.index', compact('employes'));
    }

    // Enregistre un nouvel employé
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
        ]);

        return redirect()->back()->with('success', 'Employé enregistré avec succès !');
    }

    // Formulaire édition
    public function edit(Employe $employe)
    {
        return view('employes.edit', compact('employe'));
    }

    // Mise à jour
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
    ]);

    if ($request->hasFile('photo')) {
        $path = $request->file('photo')->store('employes', 'public');
        $employe->photo = $path;
    }

    $employe->nom = $request->nom;
    $employe->prenom = $request->prenom;
    $employe->email = strtolower($request->email);
    $employe->phone = $request->phone;
    $employe->ville = $request->ville;
    $employe->quartier = $request->quartier;

    // Changer le mot de passe si rempli
    if ($request->filled('password')) {
        $employe->password = Hash::make($request->password);
    }

    $employe->save();

    return redirect()->back()->with('success', 'Employé mis à jour avec succès !');
}


    // Suppression
    public function destroy(Employe $employe)
    {
        $employe->delete();
        return redirect()->route('employes.index')->with('success', 'Employé supprimé avec succès !');
    }
}
