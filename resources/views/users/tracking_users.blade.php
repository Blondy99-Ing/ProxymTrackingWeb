@extends('layouts.app')

@section('title', 'Suivi des Utilisateurs')

@section('content')
<div class="space-y-8 p-4 md:p-8">

    {{-- Bande de navigation secondaire --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
        style="border-color: var(--color-border-subtle);">
        <h1 class="text-3xl font-bold font-orbitron" style="color: var(--color-text);">Gestion des Utilisateurs</h1>
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('tracking.users') }}"
                class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                <i class="fas fa-users mr-2"></i> Utilisateurs
            </a>
            <a href="{{ route('employes.index') }}"
                class="py-2 px-4 rounded-lg font-semibold text-secondary hover:text-primary transition-colors">
                <i class="fas fa-user-tie mr-2"></i> Employés
            </a>
        </div>
    </div>

    {{-- Messages --}}
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

    <!-- ================ Liste des Utilisateurs ================ -->
    <div class="ui-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold font-orbitron">Liste des Utilisateurs</h2>
            <button type="button" id="openAddModalBtn" class="btn-primary text-sm">
                <i class="fas fa-plus mr-2"></i> Ajouter un Utilisateur
            </button>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="usersTable" class="ui-table w-full">
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
                    @foreach($users ?? [] as $user)
                    <tr>
                        <td>{{ $user->nom }} {{ $user->prenom }}</td>
                        <td>{{ $user->phone }}</td>
                        <td>{{ $user->ville }}</td>
                        <td>{{ $user->quartier }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <img src="{{ $user->photo ? asset('storage/' . $user->photo) : 'https://placehold.co/40x40/F58220/ffffff?text=No+Photo' }}"
                                alt="Photo" class="h-10 w-10 object-cover rounded-full">
                        </td>
                        <td class="space-x-2">
                            <button class="btn-secondary btn-edit" data-user="{{ json_encode($user) }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form action="{{ route('tracking.users.destroy', $user->id) }}" method="POST"
                                class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button class="btn-danger" onclick="return confirm('Confirmer la suppression ?')">
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

<!-- ================ Modale Ajout/Édition ================ -->
<div id="userModal" class="fixed  inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div
        class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0">
        <button id="closeModalBtn"
            class="absolute top-4 right-4 text-gray-500 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white text-xl font-bold">&times;</button>
        <h2 id="modalTitle" class="text-xl font-bold font-orbitron mb-6 text-gray-900 dark:text-gray-100">Ajouter un
            Utilisateur</h2>

        <form id="userForm" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="hidden" id="userId" name="user_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nom" class="block text-sm font-medium text-secondary dark:text-gray-300">Nom</label>
                    <input type="text" id="nom" name="nom"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" required>
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium text-secondary dark:text-gray-300">Prénom</label>
                    <input type="text" id="prenom" name="prenom"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-secondary dark:text-gray-300">Téléphone</label>
                    <input type="tel" id="phone" name="phone"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-secondary dark:text-gray-300">Email</label>
                    <input type="email" id="email" name="email"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ville" class="block text-sm font-medium text-secondary dark:text-gray-300">Ville</label>
                    <input type="text" id="ville" name="ville"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </div>
                <div>
                    <label for="quartier" class="block text-sm font-medium text-secondary dark:text-gray-300">Quartier</label>
                    <input type="text" id="quartier" name="quartier"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </div>
            </div>

            <div class="space-y-2">
                <label for="photo" class="block text-sm font-medium text-secondary dark:text-gray-300">Photo</label>
                <label for="photo"
                    class="btn-secondary w-full text-center cursor-pointer transition-colors text-base dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-600">
                    Choisir un fichier
                </label>
                <input type="file" class="hidden" id="photo" name="photo" accept="image/*">
                <div id="file-name" class="text-xs text-secondary dark:text-gray-300 italic">Aucun fichier sélectionné
                </div>
                <img id="preview" src="#" alt="Aperçu" class="mt-2 h-24 w-24 object-cover rounded-full hidden border dark:border-gray-600">
            </div>

            <div id="passwordFields" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-secondary dark:text-gray-300">Mot de
                        passe</label>
                    <input type="password" id="password" name="password"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </div>
                <div>
                    <label for="password_confirmation"
                        class="block text-sm font-medium text-secondary dark:text-gray-300">Confirmer le mot de
                        passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        class="ui-input-style mt-1 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6" id="submitBtn">
                <i class="fas fa-user-plus mr-2"></i> Ajouter
            </button>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- DataTables ---
    $('#usersTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json"
        }
    });

    // --- Modal centrée ---
    const modal = document.getElementById('userModal');
    const openAddBtn = document.getElementById('openAddModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const modalTitle = document.getElementById('modalTitle');
    const userForm = document.getElementById('userForm');
    const submitBtn = document.getElementById('submitBtn');
    const userIdInput = document.getElementById('userId');
    const passwordFields = document.getElementById('passwordFields');

    function openModal() {
        modal.classList.remove('hidden');
        setTimeout(() => modal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
    }

    function closeModal() {
        modal.firstElementChild.classList.add('scale-95', 'opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 200);
        userForm.reset();
        document.getElementById('preview').classList.add('hidden');
        passwordFields.style.display = 'block';
        submitBtn.textContent = 'Ajouter';
        modalTitle.textContent = 'Ajouter un Utilisateur';
        userForm.action = "{{ route('tracking.users.store') }}";
        userIdInput.value = '';
    }

    openAddBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);

    // --- Photo preview ---
    const photoInput = document.getElementById('photo');
    const fileNameDisplay = document.getElementById('file-name');
    const preview = document.getElementById('preview');

    photoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            fileNameDisplay.textContent = 'Fichier : ' + file.name;
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        } else {
            fileNameDisplay.textContent = 'Aucun fichier sélectionné';
            preview.classList.add('hidden');
        }
    });

    // --- Edition ---
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', () => {
            const user = JSON.parse(button.getAttribute('data-user'));

            modalTitle.textContent = 'Modifier l\'Utilisateur';
            submitBtn.textContent = 'Mettre à jour';
            userForm.action = `/users/${user.id}`; // route PUT
            userForm.insertAdjacentHTML('beforeend',
                '<input type="hidden" name="_method" value="PUT">');
            userIdInput.value = user.id;
            document.getElementById('nom').value = user.nom;
            document.getElementById('prenom').value = user.prenom;
            document.getElementById('phone').value = user.phone;
            document.getElementById('email').value = user.email;
            document.getElementById('ville').value = user.ville;
            document.getElementById('quartier').value = user.quartier;
            passwordFields.style.display = 'block';

            if (user.photo) {
                preview.src = `/storage/${user.photo}`;
                preview.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
            }

            openModal();
        });
    });
});
</script>

@endsection