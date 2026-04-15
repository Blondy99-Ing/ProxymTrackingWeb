@extends('layouts.app')

@section('title', 'Abonnements v1')

@push('styles')
<style>
/* ============================================================
   VARIABLES — design system Fleetra inchangé
   ============================================================ */
:root {
    --color-primary:        #F97316;
    --color-primary-light:  rgba(249,115,22,.10);
    --color-primary-dark:   #C2410C;
    --color-card:           #fff;
    --color-border-subtle:  #e5e7eb;
    --color-text:           #111827;
    --color-secondary-text: #6b7280;
    --color-bg:             #f9fafb;
    --shadow-sm:            0 4px 12px rgba(0,0,0,.05);
    --shadow-lg:            0 15px 40px rgba(0,0,0,.15);
    --radius-card:          16px;
    --radius-btn:           12px;
    --radius-input:         12px;
    --radius-pill:          999px;
}

/* ============================================================
   PAGE
   ============================================================ */
.sub-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 1.5rem;
}

/* ============================================================
   CARD GÉNÉRIQUE
   ============================================================ */
.sub-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

/* ============================================================
   HEADER — titre + CTA bien séparés, fond légèrement teinté
   ============================================================ */
.sub-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--color-border-subtle);
    background: linear-gradient(135deg, rgba(249,115,22,.04) 0%, transparent 60%);
}

.sub-header-text h1 {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--color-text);
    margin: 0;
    letter-spacing: -.01em;
}

.sub-header-text p {
    margin: .3rem 0 0;
    font-size: .85rem;
    color: var(--color-secondary-text);
}

/* ============================================================
   TOOLBAR — filtres + recherche sur fond gris, sans border card
   ============================================================ */
.sub-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
    padding: .85rem 1.5rem;
    background: var(--color-bg);
    border-bottom: 1px solid var(--color-border-subtle);
}

/* Switcher pills dans un seul conteneur arrondi */
.sub-filters {
    display: flex;
    align-items: center;
    gap: .2rem;
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-pill);
    padding: .2rem;
}

.sub-pill {
    display: inline-flex;
    align-items: center;
    padding: .38rem .85rem;
    border-radius: var(--radius-pill);
    font-size: .82rem;
    font-weight: 700;
    color: var(--color-secondary-text);
    text-decoration: none;
    transition: background .15s, color .15s;
    white-space: nowrap;
}

.sub-pill:hover { color: var(--color-primary); }

.sub-pill.active {
    background: var(--color-primary);
    color: #fff;
}

/* Barre de recherche tout-en-un */
.sub-search-group {
    display: flex;
    align-items: center;
    gap: .5rem;
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-input);
    padding: .38rem .38rem .38rem .85rem;
    flex: 1;
    max-width: 500px;
    transition: border-color .15s, box-shadow .15s;
}

.sub-search-group:focus-within {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-light);
}

.sub-search-group input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-size: .88rem;
    color: var(--color-text);
    min-width: 0;
}

.sub-search-group input::placeholder { color: var(--color-secondary-text); }

.sub-search-group .sub-search-btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .48rem .85rem;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s;
}

.sub-search-group .sub-search-btn:hover { background: var(--color-primary-dark); }

/* ============================================================
   BOUTONS
   ============================================================ */
.sub-btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    border: none;
    border-radius: var(--radius-btn);
    padding: .7rem 1.1rem;
    background: var(--color-primary);
    color: #fff;
    font-weight: 800;
    font-size: .88rem;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s, transform .1s;
    white-space: nowrap;
}

.sub-btn:hover {
    background: var(--color-primary-dark);
    transform: translateY(-1px);
    color: #fff;
}

.sub-btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-btn);
    padding: .7rem 1.1rem;
    background: transparent;
    color: var(--color-text);
    font-weight: 700;
    font-size: .88rem;
    cursor: pointer;
    text-decoration: none;
    transition: border-color .15s, color .15s;
}

.sub-btn-secondary:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
}

/* ============================================================
   TABLEAU
   ============================================================ */
.sub-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.sub-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1120px;
}

.sub-table thead tr {
    background: var(--color-bg);
}

.sub-table th {
    padding: .6rem 1rem;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--color-secondary-text);
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid var(--color-border-subtle);
    border-top: 1px solid var(--color-border-subtle);
}

.sub-table td {
    padding: .85rem 1rem;
    font-size: .875rem;
    color: var(--color-text);
    border-bottom: 1px solid var(--color-border-subtle);
    vertical-align: middle;
}

/* Rangées alternées */
.sub-table tbody tr:nth-child(even) td {
    background: rgba(249,115,22,.018);
}

.sub-table tbody tr:last-child td { border-bottom: none; }

.sub-table tbody tr:hover td { background: var(--color-primary-light); }

/* Contenu cellule */
.sub-strong {
    font-weight: 700;
    font-size: .875rem;
    color: var(--color-text);
}

.sub-muted {
    font-size: .78rem;
    color: var(--color-secondary-text);
    margin-top: .15rem;
}

.sub-amount {
    font-weight: 800;
    font-size: .9rem;
    color: var(--color-text);
}

.sub-amount-unit {
    font-size: .75rem;
    font-weight: 500;
    color: var(--color-secondary-text);
    margin-left: .1rem;
}

/* ============================================================
   BADGES
   ============================================================ */
.sub-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .25rem .65rem;
    border-radius: var(--radius-pill);
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .04em;
    white-space: nowrap;
}

.sub-badge-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}

.sub-badge.ok  { background: rgba(16,185,129,.12); color: #065f46; }
.sub-badge.ok  .sub-badge-dot { background: #10b981; }
.sub-badge.off { background: rgba(239,68,68,.10);  color: #991b1b; }
.sub-badge.off .sub-badge-dot { background: #ef4444; }
.sub-badge.pay { background: var(--color-primary-light); color: var(--color-primary-dark); }

/* ============================================================
   PAGINATION
   ============================================================ */
.sub-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
    padding: .85rem 1.5rem;
    border-top: 1px solid var(--color-border-subtle);
    font-size: .83rem;
    color: var(--color-secondary-text);
}

/* ============================================================
   ALERTES
   ============================================================ */
.sub-alert {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .85rem 1rem;
    border-radius: var(--radius-card);
    font-size: .875rem;
    font-weight: 600;
    border: 1px solid transparent;
}

.sub-alert.success {
    background: rgba(16,185,129,.08);
    border-color: rgba(16,185,129,.2);
    color: #065f46;
}

.sub-alert.error {
    background: rgba(239,68,68,.07);
    border-color: rgba(239,68,68,.18);
    color: #991b1b;
}

/* ============================================================
   MODAL BACKDROP
   ============================================================ */
.sub-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}

.sub-modal-backdrop.show { display: flex; }

/* ============================================================
   MODAL
   ============================================================ */
.sub-modal {
    width: min(700px, 100%);
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 92dvh;
    overflow-y: auto;
}

/* Liseré orange en haut */
.sub-modal::before {
    content: '';
    display: block;
    height: 3px;
    background: linear-gradient(90deg, var(--color-primary) 0%, #fb923c 100%);
    flex-shrink: 0;
}

/* Header modal */
.sub-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.1rem 1.25rem .9rem;
    border-bottom: 1px solid var(--color-border-subtle);
    background: var(--color-bg);
}

.sub-modal-head-left {
    display: flex;
    align-items: center;
    gap: .75rem;
}

.sub-modal-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--color-primary-light);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sub-modal-head h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 800;
    color: var(--color-text);
}

.sub-modal-head p {
    margin: .25rem 0 0;
    font-size: .82rem;
    color: var(--color-secondary-text);
}

.sub-close-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    color: var(--color-secondary-text);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color .15s, color .15s;
    padding: 0;
}

.sub-close-btn:hover {
    border-color: #ef4444;
    color: #ef4444;
}

/* Body modal */
.sub-modal-body { padding: 1.15rem 1.25rem; }

/* Grille formulaire */
.sub-grid {
    display: grid;
    gap: .85rem;
    grid-template-columns: 1fr 1fr;
}

.sub-field-full { grid-column: 1 / -1; }

.sub-field {
    display: flex;
    flex-direction: column;
    gap: .35rem;
}

.sub-label {
    display: block;
    font-size: .82rem;
    font-weight: 700;
    color: var(--color-text);
}

.sub-input,
.sub-select,
.sub-textarea {
    width: 100%;
    padding: .65rem .9rem;
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-input);
    background: var(--color-bg);
    color: var(--color-text);
    font-size: .875rem;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    font-family: inherit;
}

.sub-input:focus,
.sub-select:focus,
.sub-textarea:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-light);
    background: var(--color-card);
}

.sub-input[readonly] {
    background: var(--color-bg);
    color: var(--color-secondary-text);
    cursor: default;
}

.sub-input[readonly]:focus {
    border-color: var(--color-border-subtle);
    box-shadow: none;
}

.sub-textarea { min-height: 90px; resize: vertical; }

/* Méthode en pill statique */
.sub-method-pill {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .65rem .9rem;
    background: rgba(16,185,129,.08);
    border: 1px solid rgba(16,185,129,.2);
    border-radius: var(--radius-input);
    font-size: .875rem;
    font-weight: 700;
    color: #065f46;
    width: 100%;
}

.sub-method-pill-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
    flex-shrink: 0;
}

/* Erreurs */
.sub-error {
    font-size: .8rem;
    color: #ef4444;
    font-weight: 600;
}

/* Chip sélection véhicule */
.sub-selected {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .82rem;
    color: var(--color-primary-dark);
    font-weight: 600;
    margin-top: .3rem;
    padding: .3rem .65rem;
    background: var(--color-primary-light);
    border-radius: var(--radius-pill);
    border: 1px solid rgba(249,115,22,.25);
}

/* Suggestions */
.sub-suggestions {
    margin-top: .4rem;
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--radius-input);
    max-height: 220px;
    overflow-y: auto;
    background: var(--color-card);
    box-shadow: var(--shadow-sm);
}

.sub-suggestion {
    padding: .75rem .9rem;
    cursor: pointer;
    border-bottom: 1px solid var(--color-border-subtle);
    transition: background .12s;
}

.sub-suggestion:last-child { border-bottom: none; }
.sub-suggestion:hover { background: var(--color-primary-light); }

.sub-suggestion-main {
    font-weight: 700;
    font-size: .85rem;
    color: var(--color-text);
}

.sub-suggestion-sub {
    font-size: .78rem;
    color: var(--color-secondary-text);
    margin-top: .1rem;
}

.sub-empty {
    padding: .9rem;
    color: var(--color-secondary-text);
    font-size: .85rem;
}

/* Footer modal */
.sub-modal-foot {
    display: flex;
    justify-content: flex-end;
    gap: .65rem;
    flex-wrap: wrap;
    padding: .9rem 1.25rem 1.1rem;
    border-top: 1px solid var(--color-border-subtle);
    background: var(--color-bg);
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 900px) {
    .sub-grid { grid-template-columns: 1fr; }
    .sub-field-full { grid-column: 1; }
}

@media (max-width: 768px) {
    .sub-page { padding: 1rem; }

    .sub-header {
        flex-direction: column;
        align-items: stretch;
    }

    .sub-header .sub-btn {
        width: 100%;
        justify-content: center;
    }

    .sub-toolbar {
        flex-direction: column;
        align-items: stretch;
    }

    .sub-search-group { max-width: 100%; }

    .sub-modal-foot { flex-direction: column; }

    .sub-modal-foot .sub-btn,
    .sub-modal-foot .sub-btn-secondary {
        width: 100%;
        justify-content: center;
    }
}
</style>
@endpush

@section('content')
<div class="sub-page">

    {{-- ── Alertes ──────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="sub-alert success">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="sub-alert error">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Carte principale ─────────────────────────────────── --}}
    <div class="sub-card">

        {{-- Header --}}
        <div class="sub-header">
            <div class="sub-header-text">
                <h1>Abonnements</h1>
                <p>Par défaut, la liste affiche les abonnements actifs en cours.</p>
            </div>

            <button type="button" class="sub-btn" id="openCashModalBtn">
                <i class="fas fa-cash-register"></i>
                Enregistrer un paiement cash
            </button>
        </div>

        {{-- Toolbar --}}
        <form method="GET" action="{{ route('subscriptions.v1.index') }}" class="sub-toolbar">

            <div class="sub-filters">
                <a
                    href="{{ route('subscriptions.v1.index', array_merge(request()->query(), ['status_filter' => 'active'])) }}"
                    class="sub-pill {{ ($filters['status_filter'] ?? 'active') === 'active' ? 'active' : '' }}"
                >Actifs</a>

                <a
                    href="{{ route('subscriptions.v1.index', array_merge(request()->query(), ['status_filter' => 'inactive'])) }}"
                    class="sub-pill {{ ($filters['status_filter'] ?? '') === 'inactive' ? 'active' : '' }}"
                >Non actifs</a>

                <a
                    href="{{ route('subscriptions.v1.index', array_merge(request()->query(), ['status_filter' => 'all'])) }}"
                    class="sub-pill {{ ($filters['status_filter'] ?? '') === 'all' ? 'active' : '' }}"
                >Tous</a>
            </div>

            <div class="sub-search-group">
                <i class="fas fa-search" style="color:var(--color-secondary-text);font-size:.8rem;flex-shrink:0;"></i>
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Rechercher par nom, prénom, téléphone ou véhicule"
                >
                <button type="submit" class="sub-search-btn">
                    <i class="fas fa-arrow-right" style="font-size:.75rem;"></i>
                    Rechercher
                </button>
            </div>

        </form>

        {{-- Tableau --}}
        <div class="sub-table-wrap">
            <table class="sub-table">
                <thead>
                    <tr>
                        <th>Statut</th>
                        <th>Propriétaire</th>
                        <th>Véhicule</th>
                        <th>Plan</th>
                        <th>Montant</th>
                        <th>Période</th>
                        <th>Méthode de paiement</th>
                        <th>Enregistré par</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $subscription)
                        <tr>
                            <td>
                                @if($subscription->is_active_now)
                                    <span class="sub-badge ok">
                                        <span class="sub-badge-dot"></span>ACTIF
                                    </span>
                                @else
                                    <span class="sub-badge off">
                                        <span class="sub-badge-dot"></span>NON ACTIF
                                    </span>
                                @endif
                            </td>

                            <td>
                                <div class="sub-strong">
                                    {{ trim(($subscription->user?->prenom ?? '').' '.($subscription->user?->nom ?? '')) ?: '-' }}
                                </div>
                                <div class="sub-muted">{{ $subscription->user?->phone ?? '-' }}</div>
                            </td>

                            <td>
                                <div class="sub-strong">{{ $subscription->vehicle?->immatriculation ?? '-' }}</div>
                                <div class="sub-muted">
                                    {{ trim(($subscription->vehicle?->marque ?? '').' '.($subscription->vehicle?->model ?? '')) ?: '-' }}
                                </div>
                            </td>

                            <td>
                                <div class="sub-strong">{{ $subscription->plan?->label ?? '-' }}</div>
                                <div class="sub-muted">{{ $subscription->plan?->duration_months ?? '-' }} mois</div>
                            </td>

                            <td>
                                <span class="sub-amount">
                                    {{ number_format((float)($subscription->plan?->price ?? 0), 0, ',', ' ') }}
                                </span>
                                <span class="sub-amount-unit">{{ $subscription->plan?->currency ?? 'XAF' }}</span>
                            </td>

                            <td>
                                <div class="sub-strong">{{ optional($subscription->start_date)->format('d/m/Y') }}</div>
                                <div class="sub-muted">au {{ optional($subscription->end_date)->format('d/m/Y') }}</div>
                            </td>

                            <td>
                                <span class="sub-badge pay">
                                    {{ $subscription->payment?->method ?? '-' }}
                                </span>
                                <div class="sub-muted">{{ $subscription->payment?->status ?? '-' }}</div>
                            </td>

                            <td>
                                <div class="sub-strong">
                                    {{ trim(($subscription->payment?->recorder?->prenom ?? '').' '.($subscription->payment?->recorder?->nom ?? '')) ?: '-' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:2rem;color:var(--color-secondary-text);font-size:.9rem;">
                                Aucun abonnement trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="sub-pagination">
            @if($subscriptions->total())
                <span>
                    {{ $subscriptions->firstItem() }}–{{ $subscriptions->lastItem() }}
                    sur <strong>{{ $subscriptions->total() }}</strong> abonnements
                </span>
            @endif
            {{ $subscriptions->appends(request()->query())->links() }}
        </div>

    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     MODAL — PAIEMENT CASH
     ═══════════════════════════════════════════════════════ --}}
<div class="sub-modal-backdrop" id="cashModalBackdrop">
    <div class="sub-modal">

        <div class="sub-modal-head">
            <div class="sub-modal-head-left">
                <div class="sub-modal-icon">
                    <i class="fas fa-cash-register" style="font-size:15px;"></i>
                </div>
                <div>
                    <h2>Paiement cash d'abonnement</h2>
                    <p>Seuls les véhicules sans abonnement actif en cours sont proposés.</p>
                </div>
            </div>

            <button type="button" class="sub-close-btn" id="closeCashModalBtn" aria-label="Fermer">
                <i class="fas fa-times" style="font-size:12px;"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('subscriptions.v1.store_cash') }}" id="cashForm">
            @csrf

            <div class="sub-modal-body">
                <div class="sub-grid">

                    <div class="sub-field sub-field-full">
                        <label class="sub-label">Rechercher un client ou un véhicule</label>

                        <input
                            type="text"
                            id="eligibleSearch"
                            class="sub-input"
                            placeholder="Nom, prénom, téléphone ou immatriculation…"
                            autocomplete="off"
                        >

                        <input type="hidden" name="vehicle_id" id="selectedVehicleId" value="{{ old('vehicle_id') }}">

                        <div id="selectedVehicleLabel"></div>

                        <div id="eligibleSuggestions" class="sub-suggestions" style="display:none;"></div>

                        @error('vehicle_id')
                            <div class="sub-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="sub-field">
                        <label class="sub-label" for="planSelect">Plan</label>
                        <select name="plan_id" id="planSelect" class="sub-select" required>
                            <option value="">— Choisir un plan —</option>
                            @foreach($plans as $plan)
                                <option
                                    value="{{ $plan->id }}"
                                    data-price="{{ $plan->price }}"
                                    data-currency="{{ $plan->currency ?? 'XAF' }}"
                                    data-duration="{{ $plan->duration_months }}"
                                    {{ (string)old('plan_id') === (string)$plan->id ? 'selected' : '' }}
                                >
                                    {{ $plan->label }} — {{ $plan->duration_months }} mois
                                </option>
                            @endforeach
                        </select>
                        @error('plan_id')
                            <div class="sub-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="sub-field">
                        <label class="sub-label">Méthode</label>
                        <div class="sub-method-pill">
                            <span class="sub-method-pill-dot"></span>
                            CASH
                        </div>
                    </div>

                    <div class="sub-field">
                        <label class="sub-label">Montant</label>
                        <input type="text" id="planAmountDisplay" class="sub-input" readonly placeholder="—">
                    </div>

                    <div class="sub-field">
                        <label class="sub-label" for="paidAt">Date de paiement</label>
                        <input
                            type="datetime-local"
                            name="paid_at"
                            id="paidAt"
                            class="sub-input"
                            value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}"
                        >
                        @error('paid_at')
                            <div class="sub-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="sub-field sub-field-full">
                        <label class="sub-label">Note</label>
                        <textarea
                            name="notes"
                            class="sub-textarea"
                            placeholder="Commentaire interne, référence caisse, précision…"
                        >{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="sub-error">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
            </div>

            <div class="sub-modal-foot">
                <button type="button" class="sub-btn-secondary" id="cancelCashModalBtn">Annuler</button>
                <button type="submit" class="sub-btn">
                    <i class="fas fa-check" style="font-size:.8rem;"></i>
                    Enregistrer le paiement
                </button>
            </div>
        </form>

    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const openBtn           = document.getElementById('openCashModalBtn');
    const closeBtn          = document.getElementById('closeCashModalBtn');
    const cancelBtn         = document.getElementById('cancelCashModalBtn');
    const backdrop          = document.getElementById('cashModalBackdrop');
    const eligibleSearch    = document.getElementById('eligibleSearch');
    const suggestions       = document.getElementById('eligibleSuggestions');
    const selectedVehicleId = document.getElementById('selectedVehicleId');
    const selectedVehicleLbl= document.getElementById('selectedVehicleLabel');
    const planSelect        = document.getElementById('planSelect');
    const amountDisplay     = document.getElementById('planAmountDisplay');

    function openModal()  { backdrop.classList.add('show');    document.body.style.overflow = 'hidden'; }
    function closeModal() { backdrop.classList.remove('show'); document.body.style.overflow = '';       }

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);

    backdrop?.addEventListener('click', function (e) {
        if (e.target === backdrop) closeModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop.classList.contains('show')) closeModal();
    });

    function refreshAmount() {
        const opt = planSelect?.options?.[planSelect.selectedIndex];
        if (!opt || !opt.dataset.price) { amountDisplay.value = ''; return; }
        const amount   = Number(opt.dataset.price || 0);
        const currency = opt.dataset.currency || 'XAF';
        const duration = opt.dataset.duration  || '';
        amountDisplay.value = `${amount.toLocaleString('fr-FR')} ${currency} / ${duration} mois`;
    }

    planSelect?.addEventListener('change', refreshAmount);
    refreshAmount();

    let aborter = null;
    let debounceTimer = null;

    function escapeHtml(v) {
        return String(v ?? '')
            .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
            .replaceAll('"','&quot;').replaceAll("'","&#039;");
    }

    function renderSuggestions(items) {
        if (!items.length) {
            suggestions.style.display = 'block';
            suggestions.innerHTML = '<div class="sub-empty">Aucun véhicule éligible trouvé.</div>';
            return;
        }

        suggestions.style.display = 'block';
        suggestions.innerHTML = items.map(item => {
            const owner   = item.owner
                ? `${item.owner.prenom ?? ''} ${item.owner.nom ?? ''}`.trim()
                : 'Utilisateur non trouvé';
            const vehicle = item.vehicle?.immatriculation ?? '-';
            const details = [item.vehicle?.marque, item.vehicle?.model].filter(Boolean).join(' ');

            return `
                <div class="sub-suggestion"
                     data-id="${escapeHtml(String(item.id))}"
                     data-label="${escapeHtml(owner)} — ${escapeHtml(vehicle)}">
                    <div class="sub-suggestion-main">${escapeHtml(owner)} — ${escapeHtml(vehicle)}</div>
                    <div class="sub-suggestion-sub">${escapeHtml(details)}</div>
                </div>`;
        }).join('');

        suggestions.querySelectorAll('.sub-suggestion').forEach(el => {
            el.addEventListener('click', () => {
                selectedVehicleId.value = el.dataset.id || '';
                eligibleSearch.value    = el.dataset.label || '';
                selectedVehicleLbl.innerHTML = `
                    <span class="sub-selected">
                        <i class="fas fa-check" style="font-size:.7rem;"></i>
                        ${escapeHtml(el.dataset.label || '')}
                    </span>`;
                suggestions.style.display = 'none';
            });
        });
    }

    async function searchEligibleVehicles(term) {
        if (aborter) aborter.abort();
        aborter = new AbortController();

        const url = new URL(@json(route('subscriptions.v1.eligible_vehicles')));
        url.searchParams.set('q', term);

        try {
            const res = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: aborter.signal,
            });
            const payload = await res.json();
            renderSuggestions(payload.data || []);
        } catch (err) {
            if (err.name !== 'AbortError') {
                suggestions.style.display = 'block';
                suggestions.innerHTML = '<div class="sub-empty">Erreur lors de la recherche.</div>';
            }
        }
    }

    eligibleSearch?.addEventListener('input', function () {
        const term = this.value.trim();
        selectedVehicleId.value = '';
        selectedVehicleLbl.innerHTML = '';
        clearTimeout(debounceTimer);

        if (term.length < 2) {
            suggestions.style.display = 'none';
            suggestions.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => searchEligibleVehicles(term), 250);
    });

    @if(
        $errors->has('vehicle_id') ||
        $errors->has('plan_id')    ||
        $errors->has('paid_at')    ||
        $errors->has('notes')
    )
        openModal();
        refreshAmount();
    @endif
})();
</script>
@endpush