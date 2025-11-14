@extends('layouts.app')

@section('title', 'Associations Utilisateurs - Véhicules')

@section('content')
<div class="p-4 md:p-8 space-y-8">

    {{-- Messages de succès / erreur --}}
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded ui-card">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded ui-card">
        {{ session('error') }}
    </div>
    @endif

    {{-- Bouton pour ouvrir la modale d'association --}}
    <div>
        <button type="button" class="btn-primary" data-modal-target="associationModal">
            Ajouter une association
        </button>
    </div>

    {{-- Modal pour sélectionner utilisateur et véhicules --}}
    <div id="associationModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
        <div class="bg-card rounded-lg w-full max-w-4xl p-6 relative ui-card">
            <h2 class="text-xl font-bold font-orbitron mb-4">Associer un utilisateur à un ou plusieurs véhicules</h2>

            <button type="button" class="absolute top-3 right-3 text-secondary hover:text-red-500"
                data-modal-close="associationModal">
                <i class="fas fa-times"></i>
            </button>

            <form action="{{ route('association.store') }}" method="POST" class="space-y-4" id="associationForm">
                @csrf
                <div class="flex flex-col md:flex-row gap-4">
                    {{-- Liste Utilisateurs --}}
                    <div class="flex-1 bg-white dark:bg-gray-800 dark:text-gray-100 p-3 rounded shadow">
                        <label class="block font-medium text-secondary dark:text-gray-300 mb-2">Utilisateur</label>
                        <input type="text" placeholder="Rechercher utilisateur..." id="searchUser"
                            class="w-full mb-3 px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">

                        <div id="userList" class="space-y-2 max-h-64 overflow-y-auto">
                            @foreach($users as $user)
                            <label
                                class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded cursor-pointer">
                                <input type="radio" name="user_unique_id" value="{{ $user->user_unique_id }}"
                                    class="form-radio" required>
                                <span>{{ $user->nom }} {{ $user->prenom }} (ID: {{ $user->user_unique_id }})</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Liste Véhicules --}}
                    <div class="flex-1 bg-white dark:bg-gray-800 p-3 rounded shadow">
                        <label class="block font-medium text-secondary mb-2">Véhicules</label>
                        <input type="text" placeholder="Rechercher véhicule..." id="searchVehicle"
                            class="w-full mb-3 px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                        <div id="vehicleList" class="space-y-2 max-h-64 overflow-y-auto">
                            @foreach($voitures as $voiture)
                            <label
                                class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded cursor-pointer">
                                <input type="checkbox" name="voiture_unique_id[]"
                                    value="{{ $voiture->voiture_unique_id }}" class="form-checkbox">
                                <span>{{ $voiture->immatriculation }} - {{ $voiture->marque }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-4 space-x-2">
                    <button type="button" class="btn-secondary" data-modal-close="associationModal">Annuler</button>
                    <button type="submit" class="btn-primary">Associer</button>
                </div>
            </form>

        </div>
    </div>

    {{-- Tableau des associations --}}
    <div class="ui-card overflow-x-auto shadow-md">
        <h2 class="text-xl font-bold font-orbitron mb-4">Liste des Associations Utilisateur - Véhicule</h2>
        <div class="ui-table-container">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>ID Utilisateur</th>
                        <th>Nom et Prénom</th>
                        <th>ID Véhicule</th>
                        <th>Immatriculation</th>
                        <th>Marque</th>
                        <th>Date d'Association</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($associations as $assoc)
                    <tr>
                        <td>{{ $assoc->utilisateur->first()->user_unique_id ?? 'Non défini' }}</td>
                        <td>{{ $assoc->utilisateur->first()->nom ?? 'N/A' }}
                            {{ $assoc->utilisateur->first()->prenom ?? 'N/A' }}</td>
                        <td>{{ $assoc->voiture_unique_id }}</td>
                        <td>{{ $assoc->immatriculation }}</td>
                        <td>{{ $assoc->marque }}</td>
                        <td>{{ optional($assoc->created_at)->format('d/m/Y H:i') ?? 'Non défini' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Scripts pour la modale et la recherche --}}
<script>
function filterList(inputId, listId) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    input.addEventListener('input', () => {
        const filter = input.value.toLowerCase();
        list.querySelectorAll('label').forEach(label => {
            const text = label.textContent.toLowerCase();
            label.style.display = text.includes(filter) ? '' : 'none';
        });
    });
}

filterList('searchUser', 'userList');
filterList('searchVehicle', 'vehicleList');

// Modale
document.querySelectorAll('[data-modal-target]').forEach(btn => {
    btn.addEventListener('click', () => {
        const modalId = btn.getAttribute('data-modal-target');
        document.getElementById(modalId).classList.remove('hidden');
    });
});

document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
        const modalId = btn.getAttribute('data-modal-close');
        document.getElementById(modalId).classList.add('hidden');
    });
});

document.querySelectorAll('.fixed.inset-0').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) modal.classList.add('hidden');
    });
});
</script>
@endsection
