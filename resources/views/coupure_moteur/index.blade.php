@extends('layouts.app')

@section('title', 'Immobilisation des Véhicules')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Liste des véhicules --}}
    <div class="ui-card mt-2">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste des Véhicules</h2>
        <div class="ui-table-container shadow-md">
            <table id="example" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>GPS</th>
                        <th>SIM de GPS</th>
                        <th>Moteur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    <tr>
                        <td>{{ $voiture->immatriculation }}</td>
                        <td>{{ $voiture->model }}</td>
                        <td>{{ $voiture->marque }}</td>
                        <td>
                            <div class="w-8 h-8 rounded" style="background-color: {{ $voiture->couleur }}"></div>
                        </td>
                        <td>{{ $voiture->mac_id_gps }}</td>
                        <td>{{ $voiture->sim_gps }}</td>
                      

                        <td class="whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <button class="engine-switch" data-id="{{ $voiture->id }}"
                                    data-toggle-url="{{ route('voitures.toggleEngine', $voiture->id, false) }}"
                                    aria-label="Toggle moteur">
                                    <span class="engine-knob"></span>
                                </button>

                                <div class="flex flex-col leading-tight">
                                    <span class="engine-badge" id="engineBadge-{{ $voiture->id }}">Chargement…</span>
                                    <span class="gps-badge" id="gpsBadge-{{ $voiture->id }}">GPS…</span>
                                </div>
                            </div>
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>


<style>
.engine-switch {
    width: 56px;
    height: 30px;
    border-radius: 999px;
    background: #e5e7eb;
    position: relative;
    transition: .2s ease;
    border: 1px solid rgba(0, 0, 0, .08);
    box-shadow: 0 6px 16px rgba(0, 0, 0, .08);
}

.engine-switch .engine-knob {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    border-radius: 999px;
    background: white;
    transition: .2s ease;
    box-shadow: 0 6px 16px rgba(0, 0, 0, .18);
}

.engine-switch.is-cut {
    background: #ef4444;
}

/* coupé */
.engine-switch.is-on {
    background: #22c55e;
}

/* actif */
.engine-switch.is-on .engine-knob {
    left: 29px;
}

.engine-switch.is-loading {
    opacity: .7;
    pointer-events: none;
    filter: grayscale(0.2);
}

.engine-badge {
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    width: fit-content;
    background: #f3f4f6;
    color: #111827;
}

.engine-badge.cut {
    background: #fee2e2;
    color: #991b1b;
}

.engine-badge.on {
    background: #dcfce7;
    color: #166534;
}

.gps-badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    width: fit-content;
    background: #eef2ff;
    color: #3730a3;
}

.gps-badge.off {
    background: #f3f4f6;
    color: #374151;
}
</style>


<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const switches = Array.from(document.querySelectorAll('.engine-switch'));
  const ids = switches.map(b => b.dataset.id).filter(Boolean);

  const setUI = (id, payload) => {
    const btn = document.querySelector(`.engine-switch[data-id="${id}"]`);
    const engineBadge = document.getElementById(`engineBadge-${id}`);
    const gpsBadge = document.getElementById(`gpsBadge-${id}`);
    if (!btn || !engineBadge || !gpsBadge) return;

    if (!payload || payload.success === false) {
      btn.classList.remove('is-on', 'is-cut');
      engineBadge.textContent = 'UNKNOWN';
      engineBadge.className = 'engine-badge';
      gpsBadge.textContent = 'GPS: N/A';
      gpsBadge.className = 'gps-badge off';
      return;
    }

    const cut = !!payload.engine?.cut;
    const online = payload.gps?.online;

    btn.dataset.cut = cut ? '1' : '0';
    btn.classList.toggle('is-cut', cut);
    btn.classList.toggle('is-on', !cut);

    engineBadge.textContent = cut ? 'COUPÉ' : 'ACTIF';
    engineBadge.className = 'engine-badge ' + (cut ? 'cut' : 'on');

    let gpsTxt = 'GPS: N/A';
    if (online === true) gpsTxt = 'GPS: ONLINE';
    if (online === false) gpsTxt = 'GPS: OFFLINE';
    gpsBadge.textContent = gpsTxt;
    gpsBadge.className = 'gps-badge ' + (online === true ? '' : 'off');

    btn.title = cut ? 'Rétablir le moteur' : 'Couper le moteur';
  };

  // Batch load
  const batchUrl = @json(route('voitures.engineStatusBatch', [], false));

  fetch(`${batchUrl}?ids=${encodeURIComponent(ids.join(','))}`, {
    cache: 'no-cache',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(async r => {
    const json = await r.json().catch(() => null);
    if (!r.ok || !json) throw new Error('batch failed');
    return json;
  })
  .then(json => {
    ids.forEach(id => setUI(id, json.data?.[id] ?? { success:false }));
  })
  .catch(() => ids.forEach(id => setUI(id, { success:false })));

  // Toggle
  switches.forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const url = btn.dataset.toggleUrl;

      btn.classList.add('is-loading');

      try {
        const res = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({})
        });

        if (res.status === 419) {
          alert("Session expirée (CSRF). Recharge la page.");
          window.location.reload();
          return;
        }

        const data = await res.json().catch(() => ({ success:false }));

        if (!res.ok || !data.success) {
          alert(data.message || "Échec commande moteur");
          return;
        }

        setUI(id, {
          success: true,
          engine: { cut: !!data.engine?.cut },
          gps: { online: null }
        });

      } catch (e) {
        alert("Erreur réseau");
      } finally {
        btn.classList.remove('is-loading');
      }
    });
  });
});
</script>


@endsection