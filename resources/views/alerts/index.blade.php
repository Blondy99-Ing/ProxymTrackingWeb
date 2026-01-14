{{-- resources/views/alerts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des Alertes')

@section('content')
<div class="space-y-8">

    {{-- STATS (✅ compte uniquement les NON résolues par type) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6">

        {{-- Global ouvertes --}}
        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-red-500">
            <div>
                <p class="text-sm text-secondary uppercase">Alertes Ouvertes</p>
                <p class="text-3xl font-bold text-red-500" id="stat-open">0</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-circle"></i></div>
        </div>

        {{-- Résolues (info globale) --}}
        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-green-500">
            <div>
                <p class="text-sm text-secondary uppercase">Résolues</p>
                <p class="text-3xl font-bold text-green-500" id="stat-resolved">0</p>
            </div>
            <div class="text-3xl text-green-500 opacity-70"><i class="fas fa-check-double"></i></div>
        </div>

        {{-- Types (NON résolues seulement) --}}
        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-orange-500">
            <div>
                <p class="text-sm text-secondary uppercase">Geofence (ouvertes)</p>
                <p class="text-3xl font-bold text-orange-500" id="stat-geofence">0</p>
            </div>
            <div class="text-3xl text-orange-500 opacity-70"><i class="fas fa-route"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-blue-500">
            <div>
                <p class="text-sm text-secondary uppercase">Vitesse (ouvertes)</p>
                <p class="text-3xl font-bold text-blue-500" id="stat-speed">0</p>
            </div>
            <div class="text-3xl text-blue-500 opacity-70"><i class="fas fa-tachometer-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-red-500">
            <div>
                <p class="text-sm text-secondary uppercase">Engine (ouvertes)</p>
                <p class="text-3xl font-bold text-red-500" id="stat-engine">0</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-triangle"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-purple-500">
            <div>
                <p class="text-sm text-secondary uppercase">Safe Zone (ouvertes)</p>
                <p class="text-3xl font-bold text-purple-500" id="stat-safezone">0</p>
            </div>
            <div class="text-3xl text-purple-500 opacity-70"><i class="fas fa-shield-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-yellow-400">
            <div>
                <p class="text-sm text-secondary uppercase">Time Zone (ouvertes)</p>
                <p class="text-3xl font-bold" style="color:#f59e0b" id="stat-timezone">0</p>
            </div>
            <div class="text-3xl opacity-70" style="color:#f59e0b"><i class="fas fa-clock"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-red-700">
            <div>
                <p class="text-sm text-secondary uppercase">Vol (ouvertes)</p>
                <p class="text-3xl font-bold" style="color:#b91c1c" id="stat-stolen">0</p>
            </div>
            <div class="text-3xl opacity-70" style="color:#b91c1c"><i class="fas fa-car-crash"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-red-300">
            <div>
                <p class="text-sm text-secondary uppercase">Low Battery (ouvertes)</p>
                <p class="text-3xl font-bold" style="color:#ef4444" id="stat-lowbattery">0</p>
            </div>
            <div class="text-3xl opacity-70" style="color:#ef4444"><i class="fas fa-battery-quarter"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-red-600">
            <div>
                <p class="text-sm text-secondary uppercase">Unauthorized (ouvertes)</p>
                <p class="text-3xl font-bold" style="color:#dc2626" id="stat-unauthorized">0</p>
            </div>
            <div class="text-3xl opacity-70" style="color:#dc2626"><i class="fas fa-user-clock"></i></div>
        </div>

    </div>

    {{-- TABLE --}}
    <div class="ui-card p-6">
        <div class="flex items-start justify-between gap-4 flex-col sm:flex-row">
            <div>
                <h2 class="text-xl font-bold mb-1" style="color:var(--color-text);">Liste Détaillée des Incidents</h2>
                <p class="text-sm text-secondary">Recherche + filtre type en temps réel.</p>
            </div>

            <button id="refreshBtn" class="btn-secondary">
                <i class="fas fa-sync-alt mr-2"></i> Rafraîchir
            </button>
        </div>

        <div class="flex flex-wrap gap-4 mt-4 mb-4 items-center border-b pb-4"
             style="border-color: var(--color-border-subtle);">

            <input id="alertSearch"
                   class="ui-input-style max-w-sm"
                   placeholder="Recherche véhicule / lieu / utilisateur..." />

            <select id="alertTypeFilter" class="ui-select-style">
                <option value="all">Tous les types</option>
                <option value="geofence">GeoFence</option>
                <option value="speed">Speed</option>
                <option value="engine">Engine</option>
                <option value="safe_zone">Safe Zone</option>
                <option value="time_zone">Time Zone</option>
                <option value="stolen">Stolen / Vol</option>
                <option value="low_battery">Low Battery</option>
                <option value="unauthorized">Unauthorized Time</option>
            </select>

        </div>

        <div class="ui-table-container shadow-md">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Véhicule</th>
                        <th>Utilisateur(s)</th>
                        <th>Déclenchée le</th>
                        <th>Description</th>
                        <th>Statut</th>
                        <th>Traité par</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="alerts-tbody">
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">Chargement...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ✅ MODALE Traitement Alerte + Commentaire (mêmes classes que ton exemple) --}}
<div id="alertProcessModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black bg-opacity-50">
    <div class="bg-card rounded-2xl shadow-2xl w-full max-w-xl p-6 relative ui-card">

        <button type="button" id="closeAlertModalBtn"
            class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
            Traiter l’alerte
        </h2>

        <p class="text-sm text-secondary mb-2">
            <span class="font-medium">Alerte :</span>
            <span id="alertModalTypeLabel" class="font-semibold text-primary"></span>
        </p>

        <p class="text-sm text-secondary mb-4">
            <span class="font-medium">Véhicule :</span>
            <span id="alertModalVehicleLabel" class="font-semibold text-primary"></span>
        </p>

        <div class="ui-card p-4" style="padding: 1rem;">
            <p class="text-sm" style="color: var(--color-text);">
                <span class="font-semibold">Déclenchée :</span>
                <span id="alertModalDateLabel"></span>
            </p>
            <p class="text-sm mt-2" style="color: var(--color-text);">
                <span class="font-semibold">Détails :</span>
                <span id="alertModalLocationLabel" class="text-secondary"></span>
            </p>
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-secondary mb-1">Commentaire (optionnel)</label>
            <textarea id="alertModalComment"
                      class="ui-textarea-style"
                      rows="4"
                      placeholder="Ex: Appel client, vérification GPS, intervention terrain…"></textarea>
        </div>

        <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
            <button type="button" id="cancelAlertBtn" class="btn-secondary">
                Annuler
            </button>
            <button type="button" id="confirmAlertBtn" class="btn-primary">
                <i class="fas fa-check mr-2"></i> Marquer comme traitée
            </button>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {

    const API_INDEX = "{{ route('alerts.index') }}";
    const API_MARK_PROCESSED = "{{ url('/alerts') }}";

    const typeStyle = {
        geofence:    { color: 'bg-orange-500', icon: 'fas fa-map-marker-alt',      label: 'GeoFence' },
        safe_zone:   { color: 'bg-purple-500', icon: 'fas fa-shield-alt',           label: 'Safe Zone' },
        speed:       { color: 'bg-blue-500',   icon: 'fas fa-tachometer-alt',       label: 'Speeding' },
        engine:      { color: 'bg-red-500',    icon: 'fas fa-exclamation-triangle', label: 'Engine' },
        unauthorized:{ color: 'bg-red-600',    icon: 'fas fa-user-clock',           label: 'Unauthorized Time' },
        time_zone:   { color: 'bg-yellow-400', icon: 'fas fa-clock',                label: 'Time Zone' },
        stolen:      { color: 'bg-red-700',    icon: 'fas fa-car-crash',            label: 'Stolen / Vol' },
        low_battery: { color: 'bg-red-300',    icon: 'fas fa-battery-quarter',      label: 'Low Battery' },
    };

    let alerts = [];

    // ===== Toast (compatible avec ton layout: #toast-container + CSS .toast/.show) =====
    const pushToast = (type, title, msg) => {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type === 'success' ? 'toast-success' : 'toast-error'} pointer-events-auto`;
        toast.setAttribute('role', 'alert');

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
            </div>
            <div class="toast-body">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${msg}</div>
            </div>
            <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
        `;

        container.prepend(toast);
        requestAnimationFrame(() => toast.classList.add('show'));

        const close = () => {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 260);
        };

        toast.querySelector('.toast-close')?.addEventListener('click', close);
        setTimeout(close, 5000);
    };

    async function fetchAlertsFromApi() {
        try {
            const res = await fetch(API_INDEX, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (json.status === 'success') return json.data;
            return [];
        } catch (err) {
            console.error(err);
            return [];
        }
    }

    // ✅ STATS = uniquement NON résolues par type
    function updateStats(allData) {
        const countOpenType = (t) => allData.filter(a => a.type === t && !a.processed).length;

        document.getElementById('stat-open').textContent = allData.filter(a => !a.processed).length;
        document.getElementById('stat-resolved').textContent = allData.filter(a => a.processed).length;

        document.getElementById('stat-geofence').textContent = countOpenType('geofence');
        document.getElementById('stat-speed').textContent = countOpenType('speed');
        document.getElementById('stat-engine').textContent = countOpenType('engine');
        document.getElementById('stat-safezone').textContent = countOpenType('safe_zone');
        document.getElementById('stat-timezone').textContent = countOpenType('time_zone');
        document.getElementById('stat-stolen').textContent = countOpenType('stolen');
        document.getElementById('stat-lowbattery').textContent = countOpenType('low_battery');
        document.getElementById('stat-unauthorized').textContent = countOpenType('unauthorized');
    }

    function renderAlerts(rows, statsBase) {
        const tbody = document.getElementById('alerts-tbody');
        tbody.innerHTML = '';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-secondary py-6">Aucune alerte trouvée.</td></tr>';
            updateStats(statsBase || []);
            return;
        }

        rows.forEach(a => {
            const style = typeStyle[a.type] ?? { color: 'bg-gray-500', icon: 'fas fa-bell', label: a.type ?? 'Unknown' };

            const usersLabel   = a.users_labels ?? '-';
            const vehicleLabel = a.voiture ? `${a.voiture.immatriculation} (${a.voiture.marque} ${a.voiture.model})` : 'N/A';
            const alertedHuman = a.alerted_at_human ?? '-';

            const statusText  = a.processed ? 'Résolue' : 'Ouverte';
            const statusClass = a.processed ? 'text-green-500' : 'text-red-500';
            const processedBy = a.processed_by_name ?? '-';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <span class="px-3 py-1 rounded-full text-white text-xs font-semibold ${style.color}">
                        <i class="${style.icon} mr-1"></i> ${a.type_label ?? style.label}
                    </span>
                </td>
                <td style="color:var(--color-text)">${vehicleLabel}</td>
                <td style="color:var(--color-text)">${usersLabel}</td>
                <td class="text-secondary">${alertedHuman}</td>
                <td class="text-secondary">${a.location ?? a.message ?? '-'}</td>
                <td class="${statusClass} font-bold">${statusText}</td>
                <td style="color:var(--color-text)">${processedBy}</td>
                <td class="whitespace-nowrap">
                    <button class="text-blue-600 hover:text-blue-800 mr-3" title="Voir sur le profil et carte"
                        onclick="goToProfile(${a.user_id ?? 'null'}, ${a.voiture_id ?? 'null'})">
                        <i class="fas fa-map-marker-alt"></i>
                    </button>
                    ${ !a.processed
                        ? `<button class="text-green-600 hover:text-green-800" title="Traiter (commentaire)"
                               onclick="openProcessModal(${a.id})">
                               <i class="fas fa-check"></i>
                           </button>`
                        : ''
                    }
                </td>
            `;
            tbody.appendChild(row);
        });

        updateStats(statsBase || []);
    }

    function applyFilters() {
        const q = (document.getElementById('alertSearch').value || '').toLowerCase().trim();
        const type = document.getElementById('alertTypeFilter').value;

        let filtered = alerts.slice();

        if (type && type !== 'all') filtered = filtered.filter(a => a.type === type);

        if (q) {
            filtered = filtered.filter(a => {
                const vehicle = (a.voiture?.immatriculation ?? '') + ' ' + (a.voiture?.marque ?? '') + ' ' + (a.voiture?.model ?? '');
                const users = a.users_labels ?? '';
                const loc = (a.location ?? a.message ?? '');
                return vehicle.toLowerCase().includes(q) || users.toLowerCase().includes(q) || loc.toLowerCase().includes(q);
            });
        }

        // ✅ stats = toujours sur le dataset complet (alerts), pas sur filtered
        renderAlerts(filtered, alerts);
    }

    // ===== MODALE Traitement + commentaire =====
    const modal = document.getElementById('alertProcessModal');
    const closeBtn = document.getElementById('closeAlertModalBtn');
    const cancelBtn = document.getElementById('cancelAlertBtn');
    const confirmBtn = document.getElementById('confirmAlertBtn');

    const typeLabelEl = document.getElementById('alertModalTypeLabel');
    const vehicleLabelEl = document.getElementById('alertModalVehicleLabel');
    const dateLabelEl = document.getElementById('alertModalDateLabel');
    const locLabelEl = document.getElementById('alertModalLocationLabel');
    const commentEl = document.getElementById('alertModalComment');

    let selectedAlertId = null;

    const openModal = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        selectedAlertId = null;
        commentEl.value = '';
    };

    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    window.openProcessModal = function(id) {
        const a = alerts.find(x => Number(x.id) === Number(id));
        if (!a) return;

        selectedAlertId = a.id;

        typeLabelEl.textContent = a.type_label ?? a.type ?? 'Alerte';
        vehicleLabelEl.textContent = a.voiture
            ? `${a.voiture.marque ?? ''} ${a.voiture.model ?? ''} • ${a.voiture.immatriculation ?? ''}`.trim()
            : 'N/A';

        dateLabelEl.textContent = a.alerted_at_human ?? '-';
        locLabelEl.textContent = a.location ?? a.message ?? '-';

        openModal();
    };

    // ✅ PATCH avec commentaire (optionnel)
    async function markAsProcessedWithComment(id, commentaire) {
        const res = await fetch(`${API_MARK_PROCESSED}/${id}/processed`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ commentaire: (commentaire || '').trim() })
        });

        const json = await res.json().catch(() => null);
        return { ok: res.ok && json?.status === 'success', json };
    }

    confirmBtn?.addEventListener('click', async () => {
        if (!selectedAlertId) return;

        const commentaire = commentEl.value || '';

        try {
            const r = await markAsProcessedWithComment(selectedAlertId, commentaire);

            if (!r.ok) {
                pushToast('error', 'Erreur', r.json?.message || "Impossible de traiter l'alerte.");
                return;
            }

            closeModal();
            pushToast('success', 'Succès', "Alerte traitée.");
            await reload(); // ✅ recharge + stats décrémentent
        } catch (e) {
            console.error(e);
            pushToast('error', 'Erreur réseau', "Impossible de contacter le serveur.");
        }
    });

    // ===== NAV =====
    window.goToProfile = function(userId, vehicleId) {
        if (!userId || !vehicleId) return;
        window.location.href = `/users/${userId}/profile?vehicle_id=${vehicleId}`;
    }

    async function reload() {
        alerts = await fetchAlertsFromApi();
        applyFilters();
    }

    document.getElementById('alertSearch').addEventListener('keyup', applyFilters);
    document.getElementById('alertTypeFilter').addEventListener('change', applyFilters);
    document.getElementById('refreshBtn').addEventListener('click', reload);

    (async () => {
        alerts = await fetchAlertsFromApi();
        renderAlerts(alerts, alerts);
    })();

});
</script>
@endpush
