@extends('layouts.app')

@section('title', 'Gestion des Employés')

@section('content')

<div class="space-y-8 p-4 md:p-8">

    {{-- Bande de navigation secondaire --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4" style="border-color: var(--color-border-subtle);">
        <h1 class="text-3xl font-bold font-orbitron" style="color: var(--color-text);">Gestion des Employés</h1>
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('tracking.users') }}" class="py-2 px-4 rounded-lg font-semibold text-secondary hover:text-primary transition-colors">
                <i class="fas fa-users mr-2"></i> Utilisateurs
            </a>
            <a href="#" class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                 <i class="fas fa-user-tie mr-2"></i> Employés
            </a>
        </div>
    </div>

    {{-- Messages de succès et erreurs --}}
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <strong>Erreurs de validation :</strong>
        <ul class="list-disc list-inside mt-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- ================ Liste des Employés ================ -->
    <div class="ui-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold font-orbitron">Liste des Employés</h2>
            <button type="button" id="openAddModalBtn" class="btn-primary text-sm">
                <i class="fas fa-plus mr-2"></i> Ajouter un Employé
            </button>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="employeTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Nom et Prénom</th>
                        <th>Téléphone</th>
                        <th>Ville</th>
                        <th>Quartier</th>
                        <th>Email</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employes ?? [] as $employe)
                    <tr>
                        <td>{{ $employe->nom }} {{ $employe->prenom }}</td>
                        <td>{{ $employe->phone }}</td>
                        <td>{{ $employe->ville }}</td>
                        <td>{{ $employe->quartier }}</td>
                        <td>{{ $employe->email }}</td>
                        <td>
                            <img src="{{ $employe->photo ? asset('storage/' . $employe->photo) : 'https://placehold.co/40x40/F58220/ffffff?text=No+Photo' }}" 
                                 alt="Photo" class="h-10 w-10 object-cover rounded-full">
                        </td>
                        <td class="space-x-2">
                            <button class="btn-warning text-sm openEditModalBtn" data-id="{{ $employe->id }}"
                                    data-nom="{{ $employe->nom }}" data-prenom="{{ $employe->prenom }}"
                                    data-phone="{{ $employe->phone }}" data-email="{{ $employe->email }}"
                                    data-ville="{{ $employe->ville }}" data-quartier="{{ $employe->quartier }}">
                                <i class="fas fa-edit"></i>
                            </button>

                            <form action="{{ route('employes.destroy', $employe->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" onclick="return confirm('Voulez-vous vraiment supprimer cet employé ?')" class="btn-danger text-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ================ Modale Ajout Employé ================ -->
<div id="addEmployeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 h-screen">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0">
        <button id="closeAddModalBtn" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900 text-xl font-bold">&times;</button>
        <h2 class="text-xl font-bold font-orbitron mb-6">Ajouter un Employé</h2>

        <form action="{{ route('employes.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="nom" name="nom" required class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="prenom" name="prenom" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="phone" name="phone" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="email" name="email" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ville" class="block text-sm font-medium text-secondary">Ville</label>
                    <input type="text" id="ville" name="ville" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="quartier" class="block text-sm font-medium text-secondary">Quartier</label>
                    <input type="text" id="quartier" name="quartier" class="ui-input-style mt-1">
                </div>
            </div>

            <div class="space-y-2">
                <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                <label for="photo" class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                    Choisir un fichier
                </label>
                <input type="file" class="hidden" id="photo" name="photo" accept="image/*">
                <div id="file-name" class="text-xs text-secondary italic">Aucun fichier sélectionné</div>
                <img id="preview" src="https://placehold.co/100x100/F58220/ffffff?text=Photo" alt="Aperçu" class="mt-2 h-24 w-24 object-cover rounded-full">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-secondary">Mot de passe</label>
                    <input type="password" id="password" name="password" required class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-secondary">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="ui-input-style mt-1">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6">
                <i class="fas fa-user-plus mr-2"></i> Ajouter l'Employé
            </button>
        </form>
    </div>
</div>

<!-- ================ Modale Modification Employé ================ -->
<div id="editEmployeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 h-screen">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0">
        <button id="closeEditModalBtn" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900 text-xl font-bold">&times;</button>
        <h2 class="text-xl font-bold font-orbitron mb-6">Modifier l'Employé</h2>

        <form id="editForm" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            <input type="hidden" id="edit_id" name="id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="edit_nom" name="nom" required class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="edit_prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="edit_prenom" name="prenom" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="edit_phone" name="phone" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="edit_email" name="email" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_ville" class="block text-sm font-medium text-secondary">Ville</label>
                    <input type="text" id="edit_ville" name="ville" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="edit_quartier" class="block text-sm font-medium text-secondary">Quartier</label>
                    <input type="text" id="edit_quartier" name="quartier" class="ui-input-style mt-1">
                </div>
            </div>

            <!-- Champs mot de passe -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_password" class="block text-sm font-medium text-secondary">Nouveau mot de passe</label>
                    <input type="password" id="edit_password" name="password" class="ui-input-style mt-1" placeholder="Laisser vide si inchangé">
                </div>
                <div>
                    <label for="edit_password_confirmation" class="block text-sm font-medium text-secondary">Confirmer mot de passe</label>
                    <input type="password" id="edit_password_confirmation" name="password_confirmation" class="ui-input-style mt-1" placeholder="Laisser vide si inchangé">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6">
                <i class="fas fa-save mr-2"></i> Enregistrer les modifications
            </button>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- DataTables ---
    $('#employeTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" }
    });

    // --- Modales ---
    const addModal = document.getElementById('addEmployeModal');
    const openAddBtn = document.getElementById('openAddModalBtn');
    const closeAddBtn = document.getElementById('closeAddModalBtn');
    openAddBtn.addEventListener('click', () => {
        addModal.classList.remove('hidden');
        setTimeout(() => addModal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
    });
    closeAddBtn.addEventListener('click', () => {
        addModal.firstElementChild.classList.add('scale-95', 'opacity-0');
        setTimeout(() => addModal.classList.add('hidden'), 200);
    });

    const editModal = document.getElementById('editEmployeModal');
    const closeEditBtn = document.getElementById('closeEditModalBtn');
    closeEditBtn.addEventListener('click', () => {
        editModal.firstElementChild.classList.add('scale-95', 'opacity-0');
        setTimeout(() => editModal.classList.add('hidden'), 200);
    });

    // --- Ouvrir modale modification ---
    document.querySelectorAll('.openEditModalBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nom').value = this.dataset.nom;
            document.getElementById('edit_prenom').value = this.dataset.prenom;
            document.getElementById('edit_phone').value = this.dataset.phone;
            document.getElementById('edit_email').value = this.dataset.email;
            document.getElementById('edit_ville').value = this.dataset.ville;
            document.getElementById('edit_quartier').value = this.dataset.quartier;

            document.getElementById('editForm').action = `/employes/${id}`;
            editModal.classList.remove('hidden');
            setTimeout(() => editModal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
        });
    });

    // --- Photo preview Ajout ---
    const photoInput = document.getElementById('photo');
    const fileNameDisplay = document.getElementById('file-name');
    const preview = document.getElementById('preview');
    photoInput.addEventListener('change', function() {
        const file = this.files[0];
        if(file){
            fileNameDisplay.textContent = 'Fichier : ' + file.name;
            const reader = new FileReader();
            reader.onload = e => preview.src = e.target.result;
            reader.readAsDataURL(file);
        } else {
            fileNameDisplay.textContent = 'Aucun fichier sélectionné';
            preview.src="https://placehold.co/100x100/F58220/ffffff?text=Photo";
        }
    });
});
</script>

@endsection
