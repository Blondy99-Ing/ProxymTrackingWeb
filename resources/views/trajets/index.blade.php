{{-- resources/views/trajets/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Liste des Trajets')

@section('content')
<div class="p-2 md:p-8 space-y-8">

    <div class="ui-card p-6 border border-border-subtle bg-background shadow-md rounded-xl">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                Trajets
            </h2>

            <div class="flex items-center gap-2">
                {{-- Bouton retour --}}
                <button type="button" class="btn-secondary py-2 px-4 text-sm" onclick="window.history.back()">
                    <i class="fas fa-arrow-left mr-2"></i> Retour
                </button>

                {{-- Reset filtres --}}
                <a href="{{ route('trajets.index') }}" class="btn-secondary py-2 px-4 text-sm">
                    <i class="fas fa-undo mr-2"></i> Réinitialiser
                </a>
            </div>
        </div>

        {{-- ===================== --}}
        {{-- FILTRES + RECHERCHE  --}}
        {{-- ===================== --}}
        <form method="GET" class="space-y-5">

            {{-- ==== FILTRES RAPIDES ==== --}}
            @php $quick = request('quick'); @endphp
            <div class="flex flex-wrap gap-2 border-b border-border-subtle pb-4">

                <button type="submit" name="quick" value="today"
                    class="px-4 py-2 text-sm rounded-full transition
                    {{ $quick=='today' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Aujourd'hui
                </button>

                <button type="submit" name="quick" value="yesterday"
                    class="px-4 py-2 text-sm rounded-full transition
                    {{ $quick=='yesterday' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Hier
                </button>

                <button type="submit" name="quick" value="week"
                    class="px-4 py-2 text-sm rounded-full transition
                    {{ $quick=='week' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Cette semaine
                </button>

                <button type="submit" name="quick" value="month"
                    class="px-4 py-2 text-sm rounded-full transition
                    {{ $quick=='month' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Ce mois
                </button>

                <button type="submit" name="quick" value="year"
                    class="px-4 py-2 text-sm rounded-full transition
                    {{ $quick=='year' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Cette année
                </button>

            </div>

            {{-- ==== FILTRES AVANCÉS (3 par ligne) ==== --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">

                {{-- VEHICULE (autocomplete) --}}
                <div class="relative">
                    <label class="text-sm font-medium text-secondary">Véhicule (recherche)</label>

                    {{-- hidden: id si sélection --}}
                    <input type="hidden" name="vehicle_id" id="vehicle_id" value="{{ request('vehicle_id') }}">
                    {{-- compatibilité avec ton controller actuel (vehicule LIKE immatriculation) --}}
                    <input type="hidden" name="vehicule" id="vehicule" value="{{ request('vehicule') }}">

                    <div class="relative">
                        <input
                            type="text"
                            id="vehicleSearch"
                            value="{{ request('vehicule') }}"
                            placeholder="Tape une immatriculation…"
                            autocomplete="off"
                            class="ui-input-style pl-10 pr-10 w-full"
                        />
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>

                        <button type="button"
                            id="clearVehicleBtn"
                            class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-secondary hover:text-red-500 transition p-1"
                            title="Effacer">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>

                    {{-- dropdown (sera déplacé dans body par JS) --}}
                    <div id="vehicleDropdown"
                         class="hidden border border-border-subtle rounded-xl shadow-xl overflow-hidden">
                        <div class="px-3 py-2 text-xs text-secondary border-b border-border-subtle">
                            Suggestions (clique pour sélectionner)
                        </div>

                        <div id="vehicleResults" class="max-h-56 overflow-y-auto"></div>

                        <div id="vehicleEmpty" class="hidden px-3 py-3 text-sm text-secondary">
                            Aucun véhicule correspondant.
                        </div>

                        <div class="px-3 py-2 text-xs text-secondary border-t border-border-subtle bg-hover-subtle">
                            Astuce : tape une partie de l’immatriculation.
                        </div>
                    </div>
                </div>

                {{-- DATE DEBUT --}}
                <div>
                    <label class="text-sm font-medium text-secondary">Date début</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}"
                        class="ui-input-style w-full" />
                </div>

                {{-- DATE FIN --}}
                <div>
                    <label class="text-sm font-medium text-secondary">Date fin</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}"
                        class="ui-input-style w-full" />
                </div>

                {{-- HEURE DEBUT --}}
                <div>
                    <label class="text-sm font-medium text-secondary">Heure début</label>
                    <input type="time" name="start_time" value="{{ request('start_time') }}"
                        class="ui-input-style w-full" />
                </div>

                {{-- HEURE FIN --}}
                <div>
                    <label class="text-sm font-medium text-secondary">Heure fin</label>
                    <input type="time" name="end_time" value="{{ request('end_time') }}"
                        class="ui-input-style w-full" />
                </div>

                {{-- APPLY --}}
                <div class="flex justify-end md:justify-start">
                    <button class="btn-primary w-full md:w-auto h-[42px] px-6">
                        <i class="fas fa-filter mr-2"></i> Appliquer
                    </button>
                </div>
            </div>
        </form>

        <hr class="my-6 border-border-subtle">

        {{-- ===================== --}}
        {{-- TABLEAU DES TRAJETS   --}}
        {{-- ===================== --}}
        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th>Départ</th>
                        <th>Arrivée</th>
                        <th>Distance</th>
                        <th>Vit. Moy</th>
                        <th>Vit. Max</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($trajets as $trajet)
                        <tr class="hover:bg-hover-subtle transition">
                            <td class="font-semibold text-primary">
                                {{ $trajet->voiture->immatriculation ?? 'N/A' }}
                            </td>

                            <td>
                                <span class="font-medium">
                                    {{ \Carbon\Carbon::parse($trajet->start_time)->format('d/m/Y H:i') }}
                                </span><br>
                                <span class="text-xs text-secondary">
                                    ( long:{{ number_format($trajet->start_longitude, 5) }},
                                    lat:{{ number_format($trajet->start_latitude, 5) }} )
                                </span>
                            </td>

                            <td>
                                <span class="font-medium">
                                    {{ \Carbon\Carbon::parse($trajet->end_time)->format('d/m/Y H:i') }}
                                </span><br>
                                <span class="text-xs text-secondary">
                                    ( long:{{ number_format($trajet->end_longitude, 5) }},
                                    lat:{{ number_format($trajet->end_latitude, 5) }} )
                                </span>
                            </td>

                            <td class="font-bold text-blue-600">
                                {{ number_format($trajet->total_distance_km, 2) }} km
                            </td>

                            <td class="text-orange-600">
                                {{ number_format($trajet->avg_speed_kmh, 1) }} km/h
                            </td>

                            <td class="text-red-600">
                                {{ number_format($trajet->max_speed_kmh, 1) }} km/h
                            </td>

                            <td>
                                <a href="{{ route('voitures.trajets', ['id' => $trajet->vehicle_id, 'focus_trajet_id' => $trajet->id] + request()->query()) }}">
                                    <i class="fas fa-eye mr-1"></i> Détails
                                </a>


                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-secondary bg-hover-subtle">
                                <i class="fas fa-info-circle mr-1"></i> Aucun trajet trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="mt-4 flex justify-end">
            {{ $trajets->appends(request()->query())->links() }}
        </div>

    </div>

</div>
@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  /**
   * Pour proposer des véhicules dans l'autocomplete, il faut passer $vehicles depuis le controller:
   * $vehicles = Voiture::select('id','immatriculation')->orderBy('immatriculation')->get();
   * return view('trajets.index', compact('trajets','vehicles'));
   */
  const vehicles = @json($vehicles ?? []);

  const searchInput   = document.getElementById('vehicleSearch');
  const dropdown      = document.getElementById('vehicleDropdown');
  const resultsEl     = document.getElementById('vehicleResults');
  const emptyEl       = document.getElementById('vehicleEmpty');
  const clearBtn      = document.getElementById('clearVehicleBtn');

  const vehicleIdEl   = document.getElementById('vehicle_id');
  const vehiculeText  = document.getElementById('vehicule');

  if (!searchInput || !dropdown) return;

  // porter le dropdown dans le body pour éviter blur / stacking
  document.body.appendChild(dropdown);
  dropdown.style.position = 'fixed';
  dropdown.style.zIndex = '99999';
  dropdown.style.display = 'none';
  dropdown.style.backdropFilter = 'none';
  dropdown.style.filter = 'none';
  dropdown.style.opacity = '1';

  function makeOpaque(color) {
    const c = (color || '').trim();
    const m = c.match(/^rgba?\((.+)\)$/i);
    if (!m) return c || '#ffffff';
    const parts = m[1].split(',').map(x => x.trim());
    const r = parts[0] ?? '255', g = parts[1] ?? '255', b = parts[2] ?? '255';
    return `rgb(${r}, ${g}, ${b})`;
  }

  function applySolidBackground() {
    const root = getComputedStyle(document.documentElement);
    const card = root.getPropertyValue('--color-card')?.trim();
    const bg   = root.getPropertyValue('--color-background')?.trim();
    const chosen = bg || card || '#ffffff';
    dropdown.style.background = makeOpaque(chosen);
  }
  applySolidBackground();

  function placeDropdown() {
    const r = searchInput.getBoundingClientRect();
    const gap = 8;
    dropdown.style.left = `${Math.max(8, r.left)}px`;
    dropdown.style.top  = `${r.bottom + gap}px`;
    dropdown.style.width = `${r.width}px`;
  }

  function openDropdown() {
    placeDropdown();
    dropdown.style.display = 'block';
  }

  function closeDropdown() {
    dropdown.style.display = 'none';
  }

  window.addEventListener('resize', () => {
    if (dropdown.style.display === 'block') placeDropdown();
  });
  window.addEventListener('scroll', () => {
    if (dropdown.style.display === 'block') placeDropdown();
  }, true);

  function normalize(str) {
    return (str || '')
      .toString()
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, '')
      .trim();
  }

  function scoreMatch(query, target) {
    if (!query) return 0;
    if (target.startsWith(query)) return 3;
    if (target.includes(query)) return 2;

    let qi = 0;
    for (let i = 0; i < target.length && qi < query.length; i++) {
      if (target[i] === query[qi]) qi++;
    }
    return (qi === query.length) ? 1 : 0;
  }

  function setSelectedVehicle(vehicle) {
    vehicleIdEl.value = vehicle.id;
    searchInput.value = vehicle.immatriculation;
    vehiculeText.value = vehicle.immatriculation;

    clearBtn?.classList.remove('hidden');
    closeDropdown();
  }

  function clearVehicle() {
    vehicleIdEl.value = '';
    vehiculeText.value = '';
    searchInput.value = '';
    clearBtn?.classList.add('hidden');
    closeDropdown();
  }

  function renderResults(list) {
    resultsEl.innerHTML = '';

    if (!list.length) {
      emptyEl.classList.remove('hidden');
      return;
    }
    emptyEl.classList.add('hidden');

    list.forEach(v => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'w-full text-left px-3 py-2 hover:bg-hover-subtle transition flex items-center justify-between';
      item.innerHTML = `
        <span class="text-sm font-semibold text-primary">${v.immatriculation}</span>
        <span class="text-xs text-secondary">#${v.id}</span>
      `;
      item.addEventListener('click', () => setSelectedVehicle(v));
      resultsEl.appendChild(item);
    });
  }

  function runSearch() {
    const qRaw = searchInput.value || '';
    const q = normalize(qRaw);

    vehicleIdEl.value = '';
    vehiculeText.value = qRaw.trim();

    if (!q || q.length < 1) {
      resultsEl.innerHTML = '';
      emptyEl.classList.add('hidden');
      closeDropdown();
      if (!qRaw.trim()) clearBtn?.classList.add('hidden');
      return;
    }

    clearBtn?.classList.remove('hidden');

    const scored = vehicles
      .map(v => ({ v, s: scoreMatch(q, normalize(v.immatriculation || '')) }))
      .filter(x => x.s > 0)
      .sort((a,b) => b.s - a.s)
      .slice(0, 12)
      .map(x => x.v);

    renderResults(scored);
    openDropdown();
  }

  searchInput.addEventListener('input', runSearch);
  searchInput.addEventListener('focus', () => {
    if ((searchInput.value || '').trim()) runSearch();
  });

  clearBtn?.addEventListener('click', clearVehicle);

  document.addEventListener('click', (e) => {
    if (e.target !== searchInput && !dropdown.contains(e.target)) {
      closeDropdown();
    }
  });

  // init clear button visibility
  if ((vehicleIdEl.value || '').trim() || (searchInput.value || '').trim()) {
    clearBtn?.classList.remove('hidden');
  }
});
</script>
@endpush
