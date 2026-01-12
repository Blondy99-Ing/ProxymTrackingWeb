@extends('layouts.app')

@section('title', 'Suivi des Utilisateurs')

@section('content')
<div class="space-y-8 p-4 md:p-8">

    {{-- Navigation --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
        style="border-color: var(--color-border-subtle);">
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

    <div class="ui-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">Liste des Utilisateurs</h2>
            <button type="button" id="openAddModalBtn" class="btn-primary text-sm">
                <i class="fas fa-plus mr-2"></i> Ajouter un Utilisateur
            </button>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="usersTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Rôle</th>
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
                        @php
                            $thumbUrl = $user->photo_url ?: 'https://placehold.co/40x40/F58220/ffffff?text=NP';
                            $fullUrl  = $user->photo_url ?: 'https://placehold.co/600x600/F58220/ffffff?text=NP';
                            $fullName = trim(($user->prenom ?? '').' '.($user->nom ?? ''));
                        @endphp

                        <tr class="hover:bg-hover-subtle transition-colors">
                            <td>{{ $user->role?->name ?? '-' }}</td>
                            <td style="color: var(--color-text);">{{ $user->nom }} {{ $user->prenom }}</td>
                            <td class="text-secondary">{{ $user->phone }}</td>
                            <td>{{ $user->ville }}</td>
                            <td>{{ $user->quartier }}</td>
                            <td>{{ $user->email }}</td>

                            <td>
                                <img
                                    src="{{ $thumbUrl }}"
                                    alt="Photo"
                                    class="h-10 w-10 object-cover rounded-full border border-border-subtle cursor-pointer hover:opacity-90 transition js-user-photo"
                                    data-full-url="{{ $fullUrl }}"
                                    data-title="{{ $fullName }}"
                                    title="Voir la photo"
                                >
                            </td>

                            <td class="space-x-2 whitespace-nowrap">
                                {{-- Voir --}}
                                <a href="{{ route('users.profile', ['id' => $user->id]) }}"
                                   class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200 p-2"
                                   title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </a>

                                {{-- Modifier --}}
                                <button
                                    class="text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-200 p-2 btn-edit"
                                    data-user='@json($user)'
                                    title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>

                                {{-- Supprimer --}}
                                <form action="{{ route('tracking.users.destroy', $user->id) }}" method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Confirmer la suppression de {{ $user->prenom }} {{ $user->nom }} ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 p-2"
                                            title="Supprimer">
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

{{-- ================= MODALE AJOUT / EDIT ================= --}}
<div id="userModal"
     class="fixed inset-0 bg-black bg-opacity-75 hidden z-[9999] flex items-center justify-center transition-opacity duration-300">
    <div class="bg-card rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card">

        <button id="closeModalBtn"
                class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 id="modalTitle" class="text-xl font-bold font-orbitron mb-6" style="color: var(--color-text);">
            Ajouter un Utilisateur
        </h2>

        <form id="userForm"
              action="{{ route('tracking.users.store') }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-4">
            @csrf

            <input type="hidden" id="userId" value="">
            <div id="methodSpoofContainer"></div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="nom" name="nom" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="prenom" name="prenom" class="ui-input-style mt-1" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="phone" name="phone" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="email" name="email" class="ui-input-style mt-1" required>
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

            {{-- Role --}}
            <div>
                <label class="block text-sm font-medium text-secondary">Rôle</label>
                <select id="role_id" name="role_id" required class="ui-input-style mt-1">
                    <option value="">— Choisir un rôle —</option>
                    @foreach($roles ?? [] as $role)
                        <option value="{{ $role->id }}"
                            {{ $role->slug === 'gestionnaire_plateforme' ? 'selected' : '' }}>
                            {{ strtoupper($role->name) }} — {{ $role->description }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Photo --}}
            <div class="space-y-2">
                <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                <label for="photo" class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                    Choisir un fichier
                </label>
                <input type="file" class="hidden" id="photo" name="photo" accept="image/*">
                <div id="file-name" class="text-xs text-secondary italic">Aucun fichier sélectionné</div>

                <img id="preview" src="#" alt="Aperçu"
                     class="mt-2 h-24 w-24 object-cover rounded-full hidden border border-border-subtle">
            </div>

            {{-- Password --}}
            <div id="passwordFields" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-secondary">Mot de passe</label>
                    <input type="password" id="password" name="password" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-secondary">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="ui-input-style mt-1" required>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6" id="submitBtn">
                <i class="fas fa-user-plus mr-2"></i> Ajouter
            </button>
        </form>
    </div>
</div>

{{-- ================= MODALE PHOTO (VIEW) ================= --}}
<div id="imageModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black bg-opacity-75">
    <div class="relative bg-white rounded-lg shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden">
        <button id="closeImageModalBtn"
            class="absolute top-4 right-4 text-3xl font-bold text-white hover:text-primary transition-colors bg-gray-900 rounded-full h-10 w-10 flex items-center justify-center">
            &times;
        </button>

        <div class="px-4 pt-4">
            <div id="imageModalTitle" class="text-sm font-semibold text-secondary"></div>
        </div>

        <img id="modalImage" src="" alt="Image"
             class="w-full h-auto object-contain max-h-[85vh] p-4">
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    // DataTables
    if (window.jQuery && $.fn.DataTable) {
        $('#usersTable').DataTable({
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" }
        });
    }

    // ============ MODALE AJOUT/EDIT ============
    const modal = document.getElementById('userModal');
    const openAddBtn = document.getElementById('openAddModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');

    const modalTitle = document.getElementById('modalTitle');
    const userForm = document.getElementById('userForm');
    const submitBtn = document.getElementById('submitBtn');

    const userIdHidden = document.getElementById('userId');
    const methodSpoofContainer = document.getElementById('methodSpoofContainer');

    const roleSelect = document.getElementById('role_id');

    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirmation');

    const photoInput = document.getElementById('photo');
    const fileNameDisplay = document.getElementById('file-name');
    const preview = document.getElementById('preview');

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
        setTimeout(() => modal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
    }

    function closeModal() {
        modal.firstElementChild.classList.add('scale-95', 'opacity-0');
        document.body.style.overflow = '';
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('opacity-100');
        }, 200);
        resetToAdd();
    }

    function resetToAdd() {
        userForm.reset();
        userForm.action = "{{ route('tracking.users.store') }}";
        userIdHidden.value = '';
        methodSpoofContainer.innerHTML = '';

        passwordInput.required = true;
        passwordConfirmInput.required = true;
        passwordInput.value = '';
        passwordConfirmInput.value = '';

        preview.src = '#';
        preview.classList.add('hidden');
        fileNameDisplay.textContent = 'Aucun fichier sélectionné';

        modalTitle.textContent = 'Ajouter un Utilisateur';
        submitBtn.innerHTML = '<i class="fas fa-user-plus mr-2"></i> Ajouter';
    }

    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (openAddBtn) openAddBtn.addEventListener('click', () => { resetToAdd(); openModal(); });

    if (photoInput) {
        photoInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (file) {
                fileNameDisplay.textContent = 'Fichier : ' + file.name;
                const reader = new FileReader();
                reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
                reader.readAsDataURL(file);
            } else {
                fileNameDisplay.textContent = 'Aucun fichier sélectionné';
                preview.classList.add('hidden');
            }
        });
    }

    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', () => {
            const user = JSON.parse(button.getAttribute('data-user'));

            modalTitle.textContent = "Modifier l'Utilisateur";
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Mettre à jour';

            userForm.action = `{{ url('tracking/users') }}/${user.id}`;
            userIdHidden.value = user.id;

            methodSpoofContainer.innerHTML = `<input type="hidden" name="_method" value="PUT">`;

            document.getElementById('nom').value = user.nom || '';
            document.getElementById('prenom').value = user.prenom || '';
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('email').value = user.email || '';
            document.getElementById('ville').value = user.ville || '';
            document.getElementById('quartier').value = user.quartier || '';

            roleSelect.value = user.role_id || '';

            passwordInput.required = false;
            passwordConfirmInput.required = false;
            passwordInput.value = '';
            passwordConfirmInput.value = '';

            if (user.photo_url) {
                preview.src = user.photo_url;
                preview.classList.remove('hidden');
                fileNameDisplay.textContent = 'Laisser vide pour conserver la photo actuelle';
            } else {
                preview.src = '#';
                preview.classList.add('hidden');
                fileNameDisplay.textContent = 'Laisser vide pour conserver la photo actuelle';
            }

            openModal();
        });
    });

    // ============ MODALE IMAGE (PHOTO USER) ============
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const imageModalTitle = document.getElementById('imageModalTitle');
    const closeImageModalBtn = document.getElementById('closeImageModalBtn');

    function openImageModal(url, title) {
        if (!url) return;
        modalImage.src = url;
        imageModalTitle.textContent = title ? `Photo : ${title}` : 'Photo';
        imageModal.classList.remove('hidden');
        imageModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        imageModal.classList.add('hidden');
        imageModal.classList.remove('flex');
        modalImage.src = '';
        document.body.style.overflow = '';
    }

    if (closeImageModalBtn) closeImageModalBtn.addEventListener('click', closeImageModal);

    if (imageModal) {
        imageModal.addEventListener('click', (e) => {
            if (e.target === imageModal) closeImageModal();
        });
    }

    // ✅ EVENT DELEGATION (marche même si DataTables redraw)
    document.addEventListener('click', function(e) {
        const img = e.target.closest('.js-user-photo');
        if (!img) return;

        const url = img.getAttribute('data-full-url');
        const title = img.getAttribute('data-title');
        openImageModal(url, title);
    });

});
</script>
@endpush

@endsection
