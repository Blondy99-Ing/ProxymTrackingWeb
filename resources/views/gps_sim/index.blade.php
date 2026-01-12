{{-- resources/views/gps_sim/index.blade.php --}}
@extends('layouts.app')

@section('title', 'SIM GPS')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Messages --}}
    @if(session('success'))
    <div class="ui-card p-3">
        <p class="text-sm font-medium text-secondary">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </p>
    </div>
    @endif

    @if(session('error'))
    <div class="ui-card p-3 is-error-state">
        <p class="text-sm font-medium text-secondary">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
        </p>
    </div>
    @endif

    {{-- Actions top --}}
    <div class="flex items-center justify-end gap-2">
        {{-- Sync depuis 18GPS --}}
        <form method="POST" action="{{ route('gps_sim.sync') }}">
            @csrf
            <button type="submit" class="btn-primary">
                <i class="fas fa-cloud-download-alt mr-2"></i>
                Sync GPS depuis 18GPS
            </button>
        </form>
    </div>

    {{-- Liste --}}
    <div class="ui-card mt-2">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste GPS & SIM</h2>

        <div class="ui-table-container shadow-md">
            <table id="gpsSimTable" class="ui-table w-full">
                <thead>
                    {{-- Champ de recherche DANS le tableau (auto, sans bouton) --}}
                    <tr>
                        {{-- ✅ 6 colonnes maintenant --}}
                        <th colspan="6">
                            <form id="gpsSimSearchForm" method="GET" action="{{ route('gps_sim.index') }}">
                                <input id="gpsSimSearchInput" type="text" name="q" value="{{ $q ?? request('q') }}"
                                    class="ui-input-style" placeholder="Rechercher (mac_id / SIM / compte / statut)..."
                                    autocomplete="off" />
                            </form>
                        </th>
                    </tr>

                    <tr>
                        <th>MAC ID</th>
                        <th>Compte</th> {{-- ✅ NEW --}}
                        <th>Statut</th>
                        <th>SIM</th>
                        <th>Dernière MAJ</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody id="gpsSimTbody">
                    @foreach($items ?? [] as $item)
                    <tr data-row>
                        {{-- MAC ID --}}
                        <td>{{ $item->mac_id }}</td>

                        {{-- ✅ COMPTE --}}
                        <td>
                            @php
                                $acc = strtolower(trim((string)($item->account_name ?? '')));
                            @endphp

                            @if($acc === '')
                                <span class="text-secondary">—</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    @if($acc === 'tracking')
                                        bg-blue-500 text-white
                                    @elseif($acc === 'mobility')
                                        bg-purple-600 text-white
                                    @else
                                        bg-gray-400 text-white
                                    @endif
                                ">
                                    <i class="fas fa-user-circle mr-1 text-[10px]"></i>
                                    {{ $item->account_name }}
                                </span>
                            @endif
                        </td>

                        {{-- ✅ STATUT : Moteur + GPS --}}
                        <td>
                            @php
                            $engine = $item->engine_state; // CUT / ON / OFF / UNKNOWN / null
                            $gpsOnline = $item->gps_online; // true / false / null
                            @endphp

                            @if(is_null($engine) && is_null($gpsOnline))
                            <span class="text-secondary text-xs">N/A</span>
                            @else
                            <div class="flex flex-wrap items-center gap-2">
                                {{-- Moteur --}}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px]
                @if($engine === 'CUT')
                    bg-red-500 text-white
                @elseif($engine === 'ON')
                    bg-green-500 text-white
                @elseif($engine === 'OFF')
                    bg-yellow-400 text-black
                @else
                    bg-gray-400 text-white
                @endif
            ">
                                    <i class="fas fa-car-battery mr-1 text-[10px]"></i>
                                    <span class="font-semibold mr-1">Moteur :</span>
                                    <span>{{ $engine ?? 'Inconnu' }}</span>
                                </span>

                                {{-- GPS --}}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px]
                @if($gpsOnline === true)
                    bg-green-500 text-white
                @elseif($gpsOnline === false)
                    bg-red-500 text-white
                @else
                    bg-gray-400 text-white
                @endif
            " @if($item->gps_last_seen)
                                    title="Dernier signal : {{ $item->gps_last_seen }}"
                                    @endif
                                    >
                                    <i class="fas fa-satellite-dish mr-1 text-[10px]"></i>
                                    <span class="font-semibold mr-1">GPS :</span>
                                    <span>
                                        @if($gpsOnline === true)
                                        En ligne
                                        @elseif($gpsOnline === false)
                                        Hors-ligne
                                        @else
                                        Inconnu
                                        @endif
                                    </span>
                                </span>
                            </div>
                            @endif
                        </td>

                        {{-- SIM --}}
                        <td>
                            @if(!empty($item->sim_number))
                            {{ $item->sim_number }}
                            @else
                            <span class="text-secondary">—</span>
                            @endif
                        </td>

                        {{-- Dernière MAJ --}}
                        <td>{{ optional($item->created_at)->format('Y-m-d H:i') }}</td>

                        {{-- Actions --}}
                        <td class="space-x-1 whitespace-nowrap">
                            <button type="button" class="btn-secondary" title="Ajouter / Modifier la SIM" onclick="openSimModal(
                        {{ (int)$item->id }},
                        '{{ addslashes($item->mac_id) }}',
                        '{{ addslashes($item->sim_number ?? '') }}'
                    )">
                                <i class="fas fa-sim-card mr-2"></i>
                                @if(!empty($item->sim_number))
                                Modifier SIM
                                @else
                                Ajouter SIM
                                @endif
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>

            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </div>

</div>

{{-- MODALE Ajouter/Modifier SIM --}}
<div id="simModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black bg-opacity-50">
    <div class="bg-card rounded-2xl shadow-2xl w-full max-w-xl p-6 relative ui-card">

        <button type="button" id="closeSimModalBtn"
            class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
            Ajouter / Modifier la SIM
        </h2>

        <p class="text-sm text-secondary mb-4">
            <span class="font-medium">GPS ciblé :</span>
            <span id="simModalMacLabel" class="font-semibold text-primary"></span>
        </p>

        <form id="sim-form" method="POST" action="#">
            @csrf
            {{-- ✅ on force PATCH explicitement (évite les soucis de cache @method) --}}
            <input type="hidden" name="_method" id="sim_method" value="PATCH">

            <input type="hidden" id="simGpsId" name="id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-secondary mb-1">MAC ID</label>
                    <input type="text" id="simMacReadonly" class="ui-input-style" readonly>
                </div>

                <div>
                    <label for="sim_number" class="block text-sm font-medium text-secondary mb-1">
                        Numéro SIM
                    </label>
                    <input type="text" id="sim_number" name="sim_number" class="ui-input-style"
                        placeholder="Ex: 696000000">
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
                <button type="button" id="cancelSimBtn" class="btn-secondary">
                    Annuler
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i>
                    Enregistrer
                </button>
            </div>
        </form>

    </div>
</div>

<script>
(function() {
    // =========================
    // Recherche automatique
    // =========================
    const input = document.getElementById('gpsSimSearchInput');
    const form = document.getElementById('gpsSimSearchForm');
    const tbody = document.getElementById('gpsSimTbody');

    if (input && form && tbody) {
        let timer = null;

        function filterRows(q) {
            const val = (q || '').toLowerCase().trim();
            const rows = tbody.querySelectorAll('tr[data-row]');
            rows.forEach(tr => {
                const text = (tr.innerText || '').toLowerCase();
                tr.style.display = text.includes(val) ? '' : 'none';
            });
        }

        filterRows(input.value);

        input.addEventListener('input', function() {
            filterRows(input.value);

            clearTimeout(timer);
            timer = setTimeout(() => {
                form.submit();
            }, 600);
        });
    }

    // =========================
    // Modale SIM
    // =========================
    const modal = document.getElementById('simModal');
    const closeBtn = document.getElementById('closeSimModalBtn');
    const cancelBtn = document.getElementById('cancelSimBtn');

    const formSim = document.getElementById('sim-form');
    const macLabel = document.getElementById('simModalMacLabel');
    const macReadonly = document.getElementById('simMacReadonly');
    const simInput = document.getElementById('sim_number');
    const idInput = document.getElementById('simGpsId');
    const methodInput = document.getElementById('sim_method');

    // ✅ route PATCH existante
    const urlTpl = @json(route('gps_sim.sim.update', ['simGps' => '__ID__'], false));

    function openModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    window.openSimModal = function(id, macId, simNumber) {
        if (!formSim) return;

        formSim.setAttribute('method', 'POST');
        if (methodInput) methodInput.value = 'PATCH';

        if (idInput) idInput.value = String(id || '');
        if (macLabel) macLabel.textContent = macId || '';
        if (macReadonly) macReadonly.value = macId || '';
        if (simInput) simInput.value = simNumber || '';

        formSim.action = String(urlTpl).replace('__ID__', String(id));

        openModal();
    };

    closeBtn && closeBtn.addEventListener('click', closeModal);
    cancelBtn && cancelBtn.addEventListener('click', closeModal);

    modal && modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
@endsection
