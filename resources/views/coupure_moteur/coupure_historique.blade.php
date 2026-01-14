@extends('layouts.app')

@section('title', 'Historique Coupure / Allumage')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Navigation Coupure Moteur --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
        style="border-color: var(--color-border-subtle);">
        <div class="flex mt-4 sm:mt-0 space-x-4">

            <a href="{{ route('engine.action.index') }}"
               class="py-2 px-4 rounded-lg font-semibold transition-colors
                    {{ request()->routeIs('engine.action.index')
                        ? 'text-primary border-b-2 border-primary'
                        : 'text-secondary hover:text-primary' }}">
                <i class="fas fa-power-off mr-2"></i> Actions
            </a>

            <a href="{{ route('engine.action.history') }}"
               class="py-2 px-4 rounded-lg font-semibold transition-colors
                    {{ request()->routeIs('engine.action.history')
                        ? 'text-primary border-b-2 border-primary'
                        : 'text-secondary hover:text-primary' }}">
                <i class="fas fa-history mr-2"></i> Historique
            </a>

        </div>
    </div>

    <div class="ui-card">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                    Historique des commandes
                </h2>
                <p class="text-sm text-secondary mt-1">
                    Période active : <span class="font-semibold text-primary">{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</span>
                    → <span class="font-semibold text-primary">{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('engine.action.history') }}"
                   class="btn-secondary">
                    Réinitialiser
                </a>

                {{-- Bouton pour afficher/masquer le panneau (pas un bouton "filtrer") --}}
                <button id="toggleFiltersBtn" type="button" class="btn-secondary">
                    <i class="fas fa-sliders-h mr-2"></i> Options
                </button>
            </div>
        </div>

        {{-- FORM (auto-submit) --}}
        <form id="filtersForm" method="GET" action="{{ route('engine.action.history') }}" class="mt-5">

            {{-- Recherche (toujours visible) --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-secondary mb-1">
                        Recherche véhicule / CmdNo
                    </label>
                    <input
                        type="text"
                        name="q"
                        id="qInput"
                        value="{{ $q ?? '' }}"
                        class="ui-input-style"
                        placeholder="Ex: LT-123-AB • Toyota • 8620... • CmdNo..."
                        autocomplete="off"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">
                        Type d’action
                    </label>
                    <select name="type" id="typeSelect" class="ui-select-style">
                        <option value="all" {{ ($type ?? 'all') === 'all' ? 'selected' : '' }}>Tous</option>
                        <option value="coupure" {{ ($type ?? '') === 'coupure' ? 'selected' : '' }}>Coupure</option>
                        <option value="allumage" {{ ($type ?? '') === 'allumage' ? 'selected' : '' }}>Allumage</option>
                    </select>
                </div>
            </div>

            {{-- Panneau caché --}}
            <div id="filtersPanel" class="hidden mt-4">
                <div class="ui-card" style="padding: 1rem;">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Mode date --}}
                        <div>
                            <label class="block text-sm font-medium text-secondary mb-1">Mode de date</label>
                            <select name="date_mode" id="dateMode" class="ui-select-style">
                                <option value="period" {{ ($dateMode ?? 'period') === 'period' ? 'selected' : '' }}>Période</option>
                                <option value="single" {{ ($dateMode ?? '') === 'single' ? 'selected' : '' }}>Date précise</option>
                                <option value="range"  {{ ($dateMode ?? '') === 'range' ? 'selected' : '' }}>Plage de dates</option>
                            </select>
                        </div>

                        {{-- Période --}}
                        <div id="periodWrap">
                            <label class="block text-sm font-medium text-secondary mb-1">Période</label>
                            <select name="period" id="periodSelect" class="ui-select-style">
                                <option value="today" {{ ($period ?? 'week') === 'today' ? 'selected' : '' }}>Aujourd’hui</option>
                                <option value="week"  {{ ($period ?? 'week') === 'week' ? 'selected' : '' }}>Cette semaine (défaut)</option>
                                <option value="month" {{ ($period ?? 'week') === 'month' ? 'selected' : '' }}>Ce mois</option>
                                <option value="year"  {{ ($period ?? 'week') === 'year' ? 'selected' : '' }}>Cette année</option>
                            </select>
                        </div>

                        {{-- Date précise --}}
                        <div id="singleWrap" class="hidden">
                            <label class="block text-sm font-medium text-secondary mb-1">Date</label>
                            <input type="date" name="date" id="singleDate" value="{{ $date ?? '' }}" class="ui-input-style">
                        </div>

                        {{-- Plage --}}
                        <div id="rangeWrap" class="hidden md:col-span-2">
                            <label class="block text-sm font-medium text-secondary mb-1">Plage</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input type="date" name="from" id="fromDate" value="{{ $fromInput ?? '' }}" class="ui-input-style" placeholder="Du">
                                <input type="date" name="to"   id="toDate"   value="{{ $toInput ?? '' }}" class="ui-input-style" placeholder="Au">
                            </div>
                            <p class="text-xs text-secondary mt-2">
                                Astuce : tu peux remplir seulement “Du” ou seulement “Au”.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </div>

    {{-- TABLE --}}
    <div class="ui-card">
        <div class="ui-table-container shadow-md">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Véhicule</th>
                        <th>CmdNo</th>
                        <th>Status</th>
                        <th>Envoyé par</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commands as $cmd)
                        @php
                            $veh = $cmd->vehicule;
                            $isEmploye = !is_null($cmd->employe_id);
                            $actor = $isEmploye ? $cmd->employe : $cmd->user;
                            $actorLabel = $isEmploye ? 'Employé' : 'Partenaire';
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <div class="font-semibold">
                                    {{ optional($cmd->created_at)->format('d/m/Y') }}
                                </div>
                                <div class="text-xs text-secondary">
                                    {{ optional($cmd->created_at)->format('H:i:s') }}
                                </div>
                            </td>

                            <td class="whitespace-nowrap">
                                <span class="engine-badge {{ $cmd->type_commande === 'coupure' ? 'cut' : 'on' }}">
                                    {{ strtoupper($cmd->type_commande ?? 'N/A') }}
                                </span>
                            </td>

                            <td>
                                <div class="font-semibold">
                                    {{ $veh->marque ?? '—' }} {{ $veh->model ?? '' }}
                                </div>
                                <div class="text-xs text-secondary">
                                    <span class="font-medium">Immat :</span> {{ $veh->immatriculation ?? '—' }}
                                    <span class="mx-2">•</span>
                                    <span class="font-medium">GPS :</span> <span class="font-mono">{{ $veh->mac_id_gps ?? '—' }}</span>
                                </div>
                            </td>

                            <td class="font-mono text-xs">
                                {{ $cmd->CmdNo ?? '—' }}
                            </td>

                            <td class="whitespace-nowrap">
                                {{-- Tu peux styliser selon status --}}
                                <span class="gps-badge {{ ($cmd->status ?? '') === 'SEND_OK' ? '' : 'off' }}">
                                    {{ $cmd->status ?? '—' }}
                                </span>
                            </td>

                            <td>
                                <div class="font-semibold">
                                    {{ $actor ? ($actor->prenom.' '.$actor->nom) : '—' }}
                                </div>
                                <div class="text-xs text-secondary">
                                    <span class="font-semibold text-primary">{{ $actorLabel }}</span>
                                    @if($actor?->email)
                                        <span class="mx-2">•</span>{{ $actor->email }}
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-8">
                                Aucun résultat pour ces filtres.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $commands->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('filtersForm');
    const panel = document.getElementById('filtersPanel');
    const toggleBtn = document.getElementById('toggleFiltersBtn');

    const qInput = document.getElementById('qInput');
    const typeSelect = document.getElementById('typeSelect');

    const dateMode = document.getElementById('dateMode');
    const periodWrap = document.getElementById('periodWrap');
    const singleWrap = document.getElementById('singleWrap');
    const rangeWrap  = document.getElementById('rangeWrap');

    const periodSelect = document.getElementById('periodSelect');
    const singleDate = document.getElementById('singleDate');
    const fromDate = document.getElementById('fromDate');
    const toDate = document.getElementById('toDate');

    // --- Panel state (localStorage) ---
    const key = 'engineHistoryFiltersOpen';
    const openPanel = () => { panel.classList.remove('hidden'); localStorage.setItem(key, '1'); };
    const closePanel = () => { panel.classList.add('hidden'); localStorage.setItem(key, '0'); };

    if (localStorage.getItem(key) === '1') openPanel();

    toggleBtn?.addEventListener('click', () => {
        if (panel.classList.contains('hidden')) openPanel();
        else closePanel();
    });

    // --- Date mode UI ---
    function syncDateModeUI() {
        const mode = (dateMode?.value || 'period');

        periodWrap.classList.toggle('hidden', mode !== 'period');
        singleWrap.classList.toggle('hidden', mode !== 'single');
        rangeWrap.classList.toggle('hidden', mode !== 'range');
    }
    syncDateModeUI();

    // --- Auto-submit (debounced for search) ---
    let t = null;
    const submitSoon = (delay = 350) => {
        clearTimeout(t);
        t = setTimeout(() => form.submit(), delay);
    };

    qInput?.addEventListener('input', () => submitSoon(450));

    // selects
    typeSelect?.addEventListener('change', () => submitSoon(0));
    periodSelect?.addEventListener('change', () => submitSoon(0));

    dateMode?.addEventListener('change', () => {
        syncDateModeUI();
        submitSoon(0);
    });

    singleDate?.addEventListener('change', () => submitSoon(0));
    fromDate?.addEventListener('change', () => submitSoon(0));
    toDate?.addEventListener('change', () => submitSoon(0));
});
</script>
@endpush
