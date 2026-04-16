@extends('layouts.app')

@section('title', 'Historique des positions')

@push('styles')
<style>
:root {
    --hp-z-map-ui: 140;
    --hp-z-floating: 150;
}

.hp-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.hp-grid-main {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

@media (max-width: 1023px) {
    .hp-grid-main { grid-template-columns: 1fr; }
}

@media (min-width: 1024px) {
    .hp-grid-main {
        height: calc(100vh - var(--navbar-h, 52px) - 1.5rem);
    }
    .hp-col-left,
    .hp-col-map { min-height: 0; }
}

.hp-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg, 10px);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

/* LEFT */
.hp-tabs {
    display: flex;
    border-bottom: 1px solid var(--color-border-subtle);
}

.hp-tab {
    flex: 1;
    text-align: center;
    padding: .6rem .4rem;
    font-family: var(--font-display);
    font-size: .64rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
}

.hp-tab.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: var(--color-primary-light);
}

.hp-search {
    padding: .65rem .75rem 0;
}

.hp-swrap {
    position: relative;
}

.hp-swrap i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: .75rem;
    color: var(--color-secondary-text, #8b949e);
}

.hp-swrap input {
    width: 100%;
    border: 1px solid var(--color-border-subtle);
    border-radius: 12px;
    padding: .58rem .7rem .58rem 2rem;
    font-size: .78rem;
    background: var(--color-card);
    color: var(--color-text);
    outline: none;
}

.hp-swrap input:focus {
    border-color: var(--color-primary);
}

.hp-sclear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    display: none;
    width: 18px;
    height: 18px;
    border-radius: 9999px;
    border: none;
    background: var(--color-border-subtle);
    color: var(--color-secondary-text, #8b949e);
    font-weight: 900;
    cursor: pointer;
}

.hp-sclear.show { display: block; }

.hp-pane {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
}

.hp-filter-wrap {
    padding: .6rem .75rem .55rem;
    border-bottom: 1px solid var(--color-border-subtle);
}

.hp-action-row {
    display: flex;
    gap: .45rem;
    margin-bottom: .55rem;
}

.hp-filter-btn {
    flex: 1;
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    border-radius: 9999px;
    padding: .45rem .7rem;
    font-family: var(--font-display);
    font-size: .63rem;
    font-weight: 900;
    color: var(--color-secondary-text, #8b949e);
    cursor: pointer;
    transition: .15s;
}

.hp-filter-btn:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.hp-filter-btn.active {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.hp-inline-panel {
    display: none;
    margin-top: .45rem;
    border: 1px solid var(--color-border-subtle);
    background: rgba(0,0,0,.02);
    border-radius: 14px;
    padding: .65rem;
}

.dark-mode .hp-inline-panel {
    background: rgba(255,255,255,.02);
}

.hp-inline-panel.show {
    display: block;
}

.hp-inline-row {
    display: flex;
    gap: .45rem;
    align-items: center;
    margin-bottom: .5rem;
}

.hp-inline-row:last-child {
    margin-bottom: 0;
}

.hp-inline-row input {
    flex: 1;
    min-width: 0;
    border: 1px solid var(--color-border-subtle);
    border-radius: 10px;
    padding: .5rem .55rem;
    background: var(--color-card);
    color: var(--color-text);
    font-family: var(--font-mono, monospace);
    font-size: .68rem;
    outline: none;
}

.hp-inline-row input:focus {
    border-color: var(--color-primary);
}

.hp-inline-actions {
    display: flex;
    gap: .45rem;
    margin-top: .55rem;
}

.hp-submit {
    flex: 1;
    height: 38px;
    border: none;
    border-radius: 10px;
    background: var(--color-primary);
    color: #fff;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .64rem;
    cursor: pointer;
}

.hp-submit:hover {
    opacity: .95;
}

.hp-submit:disabled {
    opacity: .55;
    cursor: not-allowed;
    filter: grayscale(.15);
}

.hp-reset-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 38px;
    padding: 0 .85rem;
    border-radius: 10px;
    text-decoration: none;
    border: 1px solid var(--color-border-subtle);
    background: var(--color-primary-light);
    color: var(--color-primary);
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
}

.hp-reset-link:hover {
    border-color: var(--color-primary);
}

.hp-selected-vehicle {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin: 0 .75rem .65rem;
    padding: .75rem;
    border: 1px solid var(--color-border-subtle);
    border-radius: 14px;
    background: rgba(37,99,235,.06);
}

.dark-mode .hp-selected-vehicle {
    background: rgba(37,99,235,.10);
}

.hp-selected-empty {
    background: rgba(120,120,120,.06);
}

.dark-mode .hp-selected-empty {
    background: rgba(255,255,255,.03);
}

.hp-selected-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--color-primary), #1d4ed8);
    color: #fff;
    font-size: .95rem;
}

.hp-selected-empty .hp-selected-icon {
    background: linear-gradient(135deg, #6b7280, #4b5563);
}

.hp-selected-meta {
    min-width: 0;
}

.hp-selected-label {
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e);
    margin-bottom: .18rem;
}

.hp-selected-title {
    font-family: var(--font-display);
    font-size: .84rem;
    font-weight: 900;
    color: var(--color-text);
    line-height: 1.2;
}

.hp-selected-sub {
    margin-top: .12rem;
    font-size: .68rem;
    color: var(--color-secondary-text, #8b949e);
    line-height: 1.4;
}

.hp-list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .6rem .75rem;
    border-bottom: 1px solid var(--color-border-subtle);
    font-family: var(--font-display);
}

.hp-list-head strong {
    font-size: .72rem;
    color: var(--color-text);
}

.hp-list-head span {
    font-size: .62rem;
    color: var(--color-secondary-text, #8b949e);
}

.hp-scroll {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

.hp-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.hp-scroll::-webkit-scrollbar-thumb {
    background: var(--color-border-subtle);
    border-radius: 999px;
}

.hp-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: .85rem .8rem;
    cursor: pointer;
    border-top: none;
    border-right: none;
    border-bottom: 1px solid var(--color-border-subtle);
    border-left: 3px solid transparent;
    transition: .15s;
    color: inherit;
    position: relative;
    background: transparent;
}

.hp-item:last-child {
    border-bottom: none;
}

.hp-item:hover {
    background: rgba(128,128,128,.05);
}

.hp-item.sel {
    background: linear-gradient(135deg, var(--color-primary-light), rgba(37,99,235,.08));
    border-left-color: var(--color-primary);
    box-shadow: inset 0 0 0 1px rgba(37,99,235,.18);
}

.hp-item.sel .hp-title {
    color: var(--color-primary);
}

.hp-item.sel .hp-avatar {
    background: linear-gradient(135deg, var(--color-primary), #1d4ed8);
    color: #fff;
}

.hp-item.sel::after {
    content: "";
    position: absolute;
    top: 12px;
    right: 10px;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--color-primary);
    box-shadow: 0 0 0 4px rgba(37,99,235,.15);
}

.hp-hrow {
    display: flex;
    align-items: flex-start;
    gap: .7rem;
}

.hp-avatar {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--color-primary-light), rgba(37,99,235,.10));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary);
    font-size: .88rem;
}

.hp-title {
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .82rem;
    color: var(--color-text);
    letter-spacing: .02em;
}

.hp-sub {
    font-family: var(--font-body);
    font-size: .68rem;
    color: var(--color-secondary-text, #8b949e);
    margin-top: .14rem;
    line-height: 1.35;
}

.hp-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--color-secondary-text, #8b949e);
    font-family: var(--font-display);
    font-weight: 800;
}

.hp-alert {
    padding: .75rem;
}

.hp-alert-box {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #991b1b;
    border-radius: 12px;
    padding: .75rem .9rem;
}

.dark-mode .hp-alert-box {
    background: rgba(153, 27, 27, .15);
    color: #fecaca;
    border-color: rgba(254, 202, 202, .3);
}

/* RIGHT / MAP */
.hp-mapwrap {
    position: relative;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.hp-maphead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .7rem .8rem;
    border-bottom: 1px solid var(--color-border-subtle);
}

.hp-maphead h2 {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .9rem;
    color: var(--color-text);
}

.hp-mapmeta {
    font-size: .66rem;
    color: var(--color-secondary-text, #8b949e);
    text-align: right;
}

#historyMap {
    flex: 1;
    min-height: 340px;
}

@media (min-width: 1024px) {
    #historyMap { min-height: 0; }
}

.hp-map-type {
    position: absolute;
    top: 70px;
    left: 14px;
    z-index: var(--hp-z-map-ui);
}

.hp-map-type .btn {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(17,24,39,.62);
    color: #fff;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    backdrop-filter: blur(10px);
    cursor: pointer;
}

.hp-map-type .menu {
    margin-top: .5rem;
    min-width: 170px;
    display: none;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(17,24,39,.62);
    box-shadow: 0 18px 50px rgba(0,0,0,.35);
    backdrop-filter: blur(10px);
}

.hp-map-type .menu.show { display: block; }

.hp-map-type .it {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .6rem .7rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    color: #fff;
    cursor: pointer;
}

.hp-map-type .it:hover {
    background: rgba(255,255,255,.08);
}

.hp-map-type .it .ck {
    width: 18px;
    height: 18px;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .6rem;
}

.hp-map-type .it.active .ck {
    background: rgba(34,197,94,.25);
    border-color: rgba(34,197,94,.45);
}

.hp-mapzoom {
    position: absolute;
    top: 70px;
    left: 68px;
    z-index: var(--hp-z-map-ui);
    display: flex;
    flex-direction: column;
    gap: .45rem;
}

.hp-mapzoom .btn {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(17,24,39,.62);
    color: #fff;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    backdrop-filter: blur(10px);
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 1rem;
    font-weight: 900;
}

.hp-badge-float {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    z-index: var(--hp-z-floating);
    display: none;
    gap: .45rem;
    flex-wrap: wrap;
    justify-content: center;
    padding: .15rem;
}

.hp-badge-float.show {
    display: flex;
}

.hp-pill {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(17,24,39,.62);
    color: #fff;
    border-radius: 9999px;
    padding: .35rem .6rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
}

.hp-legends {
    position: absolute;
    left: 50%;
    bottom: 14px;
    transform: translateX(-50%);
    z-index: var(--hp-z-map-ui);
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    justify-content: center;
}

.hp-leg {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(17,24,39,.62);
    color: #fff;
    border-radius: 9999px;
    padding: .35rem .6rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .60rem;
}

.hp-leg .d {
    width: 8px;
    height: 8px;
    border-radius: 999px;
}

.hp-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: .65rem;
    padding: .75rem;
    border-top: 1px solid var(--color-border-subtle);
}

@media (max-width: 900px) {
    .hp-summary { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
@media (max-width: 560px) {
    .hp-summary { grid-template-columns: 1fr; }
}

.hp-sbox {
    border: 1px solid var(--color-border-subtle);
    border-radius: 14px;
    padding: .6rem;
    background: rgba(0,0,0,.03);
}

.dark-mode .hp-sbox {
    background: rgba(255,255,255,.03);
}

.hp-sbox .k {
    font-family: var(--font-display);
    font-size: .56rem;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e);
}

.hp-sbox .v {
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .95rem;
    color: var(--color-text);
    margin-top: .08rem;
}

.hp-table-wrap {
    padding: .8rem;
    border-top: 1px solid var(--color-border-subtle);
}

.hp-table-title {
    font-family: var(--font-display);
    font-size: .82rem;
    font-weight: 900;
    color: var(--color-text);
    margin-bottom: .75rem;
}

.hp-empty-state {
    padding: 1rem;
    border-top: 1px solid var(--color-border-subtle);
    color: var(--color-secondary-text, #8b949e);
}

.hp-empty-state h3 {
    margin: 0 0 .35rem;
    font-size: .9rem;
    color: var(--color-text);
    font-family: var(--font-display);
    font-weight: 900;
}

.hp-empty-state p {
    margin: 0;
    font-size: .78rem;
    line-height: 1.6;
}
</style>

<script>
window.initHistoryMap = function () {
    if (typeof window.__bootHistoryMap === 'function') {
        window.__bootHistoryMap();
    }
};
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&callback=initHistoryMap&libraries=geometry"
    async defer>
</script>
@endpush

@section('content')
<div class="hp-content">
    <div class="hp-grid-main">

        <div class="hp-col-left">
            <div class="hp-card" style="height:100%;display:flex;flex-direction:column;min-height:0">

                <div class="hp-tabs">
                    <button class="hp-tab active" type="button">📍 Historique flotte</button>
                </div>

                <div class="hp-search">
                    <div class="hp-swrap">
                        <i class="fas fa-search"></i>
                        <input id="hpSearch" placeholder="Immat., marque, propriétaire…" oninput="window.hpDoSearch()" autocomplete="off">
                        <button id="hpClear" class="hp-sclear" type="button" onclick="window.hpClearSearch()">×</button>
                    </div>
                </div>

                <div class="hp-pane">

                    @if ($errors->any())
                        <div class="hp-alert">
                            <div class="hp-alert-box">
                                <div style="font-weight:800;font-size:.78rem;margin-bottom:.35rem;">
                                    Vérifie les filtres
                                </div>
                                <ul style="margin:0;padding-left:1rem;font-size:.72rem;line-height:1.5;">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <form method="GET" action="{{ route('v1.historique_positions.index') }}" id="hpFilterForm">
                        <input type="hidden" name="vehicle_id" id="hpVehicleId" value="{{ $filters['vehicle_id'] ?? '' }}">
                        <input type="hidden" name="mode" id="hpModeInput" value="{{ $filters['mode'] ?? 'exact' }}">
                        <input type="hidden" name="view" id="hpViewInput" value="{{ $filters['view'] ?? (($filters['mode'] ?? 'exact') === 'range' ? 'trajet' : 'position') }}">

                        <div class="hp-filter-wrap">
                            <div class="hp-action-row">
                                <button type="button" class="hp-filter-btn" id="hpBtnExact" onclick="window.hpTogglePanel('exact')">
                                    Instant précis
                                </button>
                                <button type="button" class="hp-filter-btn" id="hpBtnRange" onclick="window.hpTogglePanel('range')">
                                    Trajet sur plage
                                </button>
                            </div>

                            <div class="hp-inline-panel" id="hpPanelExact">
                                <div class="hp-inline-row">
                                    <input type="date" name="target_at" value="{{ $filters['target_at'] ?? '' }}">
                                    <input type="time" step="1" name="target_time" value="{{ $filters['target_time'] ?? '' }}">
                                </div>

                                <div class="hp-inline-actions">
                                    <button
                                        type="submit"
                                        class="hp-submit"
                                        id="hpExactSubmit"
                                        onclick="window.hpSubmitMode('exact','position')"
                                        {{ empty($filters['vehicle_id']) ? 'disabled' : '' }}
                                    >
                                        Voir position
                                    </button>
                                    <a href="{{ route('v1.historique_positions.index') }}" class="hp-reset-link">
                                        Reset
                                    </a>
                                </div>
                            </div>

                            <div class="hp-inline-panel" id="hpPanelRange">
                                <div class="hp-inline-row">
                                    <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
                                    <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
                                </div>

                                <div class="hp-inline-row">
                                    <input type="time" name="start_time" value="{{ $filters['start_time'] ?? '' }}">
                                    <input type="time" name="end_time" value="{{ $filters['end_time'] ?? '' }}">
                                </div>

                                <div class="hp-inline-actions">
                                    <button
                                        type="submit"
                                        class="hp-submit"
                                        id="hpRangeSubmit"
                                        onclick="window.hpSubmitMode('range','trajet')"
                                        {{ empty($filters['vehicle_id']) ? 'disabled' : '' }}
                                    >
                                        Voir trajet
                                    </button>
                                    <a href="{{ route('v1.historique_positions.index') }}" class="hp-reset-link">
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>

                    @php
                        $selectedVehicleItem = null;

                        if (!empty($filters['vehicle_id'])) {
                            $selectedVehicleItem = $vehicles->firstWhere('id', (int) $filters['vehicle_id']);
                        }

                        if (!$selectedVehicleItem && !empty($selectedHistory['vehicle']['id'])) {
                            $selectedVehicleItem = (object) [
                                'id' => $selectedHistory['vehicle']['id'],
                                'immatriculation' => $selectedHistory['vehicle']['immatriculation'] ?? null,
                                'marque' => $selectedHistory['vehicle']['marque'] ?? null,
                                'utilisateur' => collect([
                                    (object) [
                                        'prenom' => data_get($selectedHistory, 'vehicle.owner.prenom'),
                                        'nom' => data_get($selectedHistory, 'vehicle.owner.nom'),
                                    ]
                                ]),
                            ];
                        }
                    @endphp

                    @if($selectedVehicleItem)
                        @php
                            $selectedOwner = $selectedVehicleItem->utilisateur->first();
                            $selectedOwnerName = trim(($selectedOwner->prenom ?? '') . ' ' . ($selectedOwner->nom ?? ''));
                        @endphp

                        <div class="hp-selected-vehicle" id="hpSelectedVehicleBox">
                            <div class="hp-selected-icon">
                                <i class="fas fa-car-side"></i>
                            </div>

                            <div class="hp-selected-meta">
                                <div class="hp-selected-label">Véhicule sélectionné</div>
                                <div class="hp-selected-title" id="hpSelectedVehicleTitle">
                                    {{ $selectedVehicleItem->immatriculation ?: 'Véhicule' }}
                                </div>
                                <div class="hp-selected-sub" id="hpSelectedVehicleSub">
                                    {{ $selectedVehicleItem->marque ?: 'Marque non renseignée' }}
                                    @if($selectedOwnerName)
                                        • {{ $selectedOwnerName }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="hp-selected-vehicle hp-selected-empty" id="hpSelectedVehicleBox">
                            <div class="hp-selected-icon">
                                <i class="fas fa-circle-info"></i>
                            </div>

                            <div class="hp-selected-meta">
                                <div class="hp-selected-label">Aucun véhicule sélectionné</div>
                                <div class="hp-selected-title" id="hpSelectedVehicleTitle">
                                    Aucun véhicule sélectionné
                                </div>
                                <div class="hp-selected-sub" id="hpSelectedVehicleSub">
                                    Choisis d’abord un véhicule dans la liste, puis lance une recherche de position ou de trajet.
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="hp-list-head">
                        <strong>Véhicules</strong>
                        <span>{{ $vehicles->total() }} résultat(s)</span>
                    </div>

                    <div class="hp-scroll" id="hpVehicleList">
                        @forelse($vehicles as $vehicle)
                            @php
                                $owner = $vehicle->utilisateur->first();
                                $ownerName = trim(($owner->prenom ?? '').' '.($owner->nom ?? ''));
                                $isSelected = (($filters['vehicle_id'] ?? null) == $vehicle->id);
                            @endphp

                            <button
                                type="button"
                                class="hp-item {{ $isSelected ? 'sel' : '' }}"
                                data-id="{{ $vehicle->id }}"
                                data-s="{{ strtolower(trim(($vehicle->immatriculation ?? '').' '.($vehicle->marque ?? '').' '.$ownerName)) }}"
                                data-immat="{{ $vehicle->immatriculation ?: 'Véhicule' }}"
                                data-marque="{{ $vehicle->marque ?: 'Marque non renseignée' }}"
                                data-owner="{{ $ownerName ?: 'Propriétaire non associé' }}"
                                onclick="window.hpPickVehicle(event, this, '{{ $vehicle->id }}')"
                            >
                                <div class="hp-hrow">
                                    <div class="hp-avatar">
                                        <i class="fas fa-car-side"></i>
                                    </div>

                                    <div style="min-width:0;flex:1">
                                        <div class="hp-title">{{ $vehicle->immatriculation ?: 'Véhicule' }}</div>
                                        <div class="hp-sub">{{ $vehicle->marque ?: 'Marque non renseignée' }}</div>
                                        <div class="hp-sub">{{ $ownerName ?: 'Propriétaire non associé' }}</div>
                                    </div>
                                </div>
                            </button>
                        @empty
                            <div class="hp-empty">
                                <div>Aucun véhicule trouvé</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="hp-col-map" style="grid-column:span 3;min-height:0">
            <div class="hp-card hp-mapwrap" style="height:100%">
                <div class="hp-maphead">
                    <h2>
                        @if(($filters['mode'] ?? 'exact') === 'range')
                            Trajet du véhicule
                        @else
                            Position du véhicule
                        @endif
                    </h2>

                    <div class="hp-mapmeta">
                        @if($selectedHistory)
                            @if(($filters['mode'] ?? 'exact') === 'range')
                                {{ $selectedHistory['window']['start'] ?? '—' }} → {{ $selectedHistory['window']['end'] ?? '—' }}
                            @else
                                {{ $selectedHistory['window']['target_at'] ?? '—' }}
                            @endif
                        @else
                            Sélectionne un véhicule puis applique un filtre
                        @endif
                    </div>
                </div>

                <div id="historyMap"></div>

                <div class="hp-map-type" id="hpMapTypeCtrl">
                    <button class="btn" type="button" onclick="window.hpToggleMapTypeMenu()" title="Type de carte">
                        <i class="fas fa-map"></i>
                    </button>
                    <div class="menu" id="hpMapTypeMenu">
                        <div class="it active" data-type="roadmap" onclick="window.hpSetMapType(this, 'roadmap')"><span class="ck">✓</span> Carte</div>
                        <div class="it" data-type="satellite" onclick="window.hpSetMapType(this, 'satellite')"><span class="ck">✓</span> Satellite</div>
                        <div class="it" data-type="hybrid" onclick="window.hpSetMapType(this, 'hybrid')"><span class="ck">✓</span> Hybride</div>
                        <div class="it" data-type="terrain" onclick="window.hpSetMapType(this, 'terrain')"><span class="ck">✓</span> Terrain</div>
                    </div>
                </div>

                <div class="hp-mapzoom">
                    <button class="btn" type="button" onclick="window.hpZoomInMap()" title="Zoom avant">+</button>
                    <button class="btn" type="button" onclick="window.hpZoomOutMap()" title="Zoom arrière">−</button>
                </div>

                <div class="hp-badge-float {{ $selectedHistory ? 'show' : '' }}" id="hpBadgeFloat">
                    @if($selectedHistory)
                        <div class="hp-pill">
                            <i class="fas fa-car-side"></i>
                            {{ $selectedHistory['vehicle']['immatriculation'] ?? '—' }}
                        </div>

                        <div class="hp-pill">
                            <i class="fas fa-user"></i>
                            {{ $selectedHistory['vehicle']['owner']['nom_complet'] ?? 'Non associé' }}
                        </div>

                        @if(($filters['mode'] ?? 'exact') === 'range')
                            <div class="hp-pill">
                                <i class="fas fa-route"></i>
                                {{ $selectedHistory['track']['count'] ?? 0 }} points
                            </div>
                        @else
                            <div class="hp-pill">
                                <i class="fas fa-clock"></i>
                                {{ $selectedHistory['window']['target_at'] ?? 'Instant précis' }}
                            </div>
                        @endif
                    @endif
                </div>

                <div class="hp-legends">
                    @if(($filters['mode'] ?? 'exact') === 'range')
                        <div class="hp-leg"><span class="d" style="background:#16a34a"></span> Début</div>
                        <div class="hp-leg"><span class="d" style="background:#dc2626"></span> Fin</div>
                        <div class="hp-leg"><span class="d" style="background:#f59e0b"></span> Trajet</div>
                    @else
                        <div class="hp-leg"><span class="d" style="background:#2563eb"></span> Position trouvée</div>
                        <div class="hp-leg"><span class="d" style="background:#7c3aed"></span> Point précédent</div>
                        <div class="hp-leg"><span class="d" style="background:#0ea5e9"></span> Point suivant</div>
                    @endif
                </div>

                @if (!($filters['vehicle_id'] ?? null))
                    <div class="hp-empty-state">
                        <h3>Choisis un véhicule pour commencer</h3>
                        <p>
                            1. Sélectionne un véhicule dans la liste de gauche.<br>
                            2. Vérifie qu’il apparaît comme véhicule sélectionné.<br>
                            3. Lance ensuite une recherche de position à un instant précis ou de trajet sur une plage.
                        </p>
                    </div>
                @elseif (!$selectedHistory)
                    <div class="hp-empty-state">
                        <h3>Recherche prête pour le véhicule sélectionné</h3>
                        <p>
                            Le véhicule est bien sélectionné. Renseigne maintenant soit la date et l’heure pour retrouver sa position,
                            soit une plage de dates/heures pour afficher son trajet.
                        </p>
                    </div>
                @else
                    <div class="hp-summary">
                        <div class="hp-sbox">
                            <div class="k">Véhicule</div>
                            <div class="v">{{ $selectedHistory['vehicle']['immatriculation'] ?? '—' }}</div>
                        </div>

                        <div class="hp-sbox">
                            <div class="k">Propriétaire</div>
                            <div class="v" style="font-size:.78rem">
                                {{ $selectedHistory['vehicle']['owner']['nom_complet'] ?? 'Non associé' }}
                            </div>
                        </div>

                        <div class="hp-sbox">
                            <div class="k">Mode</div>
                            <div class="v" style="font-size:.78rem">
                                {{ ($filters['mode'] ?? 'exact') === 'exact' ? 'Instant précis' : 'Trajet sur plage' }}
                            </div>
                        </div>

                        <div class="hp-sbox">
                            <div class="k">Résultat</div>
                            <div class="v" style="font-size:.74rem">
                                @if(($filters['mode'] ?? 'exact') === 'exact')
                                    {{ $selectedHistory['position_at_time']['datetime'] ?? 'Aucune position trouvée' }}
                                @else
                                    {{ ($selectedHistory['track']['count'] ?? 0) }} points GPS
                                @endif
                            </div>
                        </div>
                    </div>

                    @if(($filters['mode'] ?? 'exact') === 'exact')
                        <div class="hp-table-wrap">
                            <div class="hp-table-title">Position retrouvée autour de l’instant demandé</div>

                            <div class="overflow-x-auto">
                                <table class="ui-table w-full">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Date / heure</th>
                                            <th>Latitude</th>
                                            <th>Longitude</th>
                                            <th>Vitesse</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Point retenu</td>
                                            <td>{{ $selectedHistory['position_at_time']['datetime'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['position_at_time']['lat'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['position_at_time']['lng'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['position_at_time']['speed'] ?? 0 }} km/h</td>
                                            <td>{{ $selectedHistory['position_at_time']['status'] ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Point précédent</td>
                                            <td>{{ $selectedHistory['context_points']['previous']['datetime'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['context_points']['previous']['lat'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['context_points']['previous']['lng'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['context_points']['previous']['speed'] ?? 0 }} km/h</td>
                                            <td>{{ $selectedHistory['context_points']['previous']['status'] ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td>Point suivant</td>
                                            <td>{{ $selectedHistory['context_points']['next']['datetime'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['context_points']['next']['lat'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['context_points']['next']['lng'] ?? '—' }}</td>
                                            <td>{{ $selectedHistory['context_points']['next']['speed'] ?? 0 }} km/h</td>
                                            <td>{{ $selectedHistory['context_points']['next']['status'] ?? '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if(($filters['mode'] ?? 'exact') === 'range')
                        <div class="hp-table-wrap">
                            <div class="hp-table-title">Trajets dans l’intervalle</div>

                            <div class="overflow-x-auto">
                                <table class="ui-table w-full">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Départ</th>
                                            <th>Arrivée</th>
                                            <th>Distance</th>
                                            <th>Durée</th>
                                            <th>Vit. moy.</th>
                                            <th>Vit. max.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($selectedHistory['trajets'] ?? []) as $trajet)
                                            <tr>
                                                <td>{{ $trajet['id'] }}</td>
                                                <td>{{ $trajet['start_time'] ?? '—' }}</td>
                                                <td>{{ $trajet['end_time'] ?? '—' }}</td>
                                                <td>{{ $trajet['total_distance_km'] ?? 0 }} km</td>
                                                <td>{{ $trajet['duration_minutes'] ?? 0 }} min</td>
                                                <td>{{ $trajet['avg_speed_kmh'] ?? 0 }} km/h</td>
                                                <td>{{ $trajet['max_speed_kmh'] ?? 0 }} km/h</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-6 text-secondary">
                                                    Aucun trajet trouvé sur cette plage.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>

    </div>
</div>

@if($selectedHistory)
<script>
window.__historyPayload = @json($selectedHistory);
</script>
@else
<script>
window.__historyPayload = null;
</script>
@endif

<script>
(() => {
    let hpMap = null;

    window.hpPickVehicle = function(event, el, vehicleId) {
        const hidden = document.getElementById('hpVehicleId');
        if (hidden) hidden.value = vehicleId;

        document.querySelectorAll('#hpVehicleList .hp-item').forEach(item => {
            item.classList.remove('sel');
        });

        el.classList.add('sel');

        const selectedBox = document.getElementById('hpSelectedVehicleBox');
        const selectedTitle = document.getElementById('hpSelectedVehicleTitle');
        const selectedSub = document.getElementById('hpSelectedVehicleSub');
        const exactBtn = document.getElementById('hpExactSubmit');
        const rangeBtn = document.getElementById('hpRangeSubmit');

        if (selectedBox) {
            selectedBox.classList.remove('hp-selected-empty');
        }

        if (selectedTitle) {
            selectedTitle.textContent = el.dataset.immat || 'Véhicule';
        }

        if (selectedSub) {
            const marque = el.dataset.marque || '';
            const owner = el.dataset.owner || '';
            selectedSub.textContent = owner ? `${marque} • ${owner}` : marque;
        }

        if (exactBtn) exactBtn.disabled = false;
        if (rangeBtn) rangeBtn.disabled = false;
    };

    window.hpSubmitMode = function(mode, view) {
        const modeInput = document.getElementById('hpModeInput');
        const viewInput = document.getElementById('hpViewInput');

        if (modeInput) modeInput.value = mode;
        if (viewInput) viewInput.value = view;
    };

    window.hpTogglePanel = function(type) {
        const exactPanel = document.getElementById('hpPanelExact');
        const rangePanel = document.getElementById('hpPanelRange');
        const btnExact = document.getElementById('hpBtnExact');
        const btnRange = document.getElementById('hpBtnRange');

        const isExactOpen = exactPanel.classList.contains('show');
        const isRangeOpen = rangePanel.classList.contains('show');

        exactPanel.classList.remove('show');
        rangePanel.classList.remove('show');
        btnExact.classList.remove('active');
        btnRange.classList.remove('active');

        if (type === 'exact' && !isExactOpen) {
            exactPanel.classList.add('show');
            btnExact.classList.add('active');
            return;
        }

        if (type === 'range' && !isRangeOpen) {
            rangePanel.classList.add('show');
            btnRange.classList.add('active');
            return;
        }
    };

    window.hpDoSearch = function() {
        const q = (document.getElementById('hpSearch')?.value || '').toLowerCase().trim();
        document.getElementById('hpClear')?.classList.toggle('show', q.length > 0);

        document.querySelectorAll('#hpVehicleList .hp-item').forEach(el => {
            el.style.display = (!q || (el.dataset.s || '').includes(q)) ? '' : 'none';
        });
    };

    window.hpClearSearch = function() {
        const q = document.getElementById('hpSearch');
        if (q) q.value = '';
        document.getElementById('hpClear')?.classList.remove('show');
        window.hpDoSearch();
    };

    window.hpToggleMapTypeMenu = function() {
        document.getElementById('hpMapTypeMenu')?.classList.toggle('show');
    };

    window.hpSetMapType = function(el, type) {
        if (hpMap) hpMap.setMapTypeId(type);
        document.querySelectorAll('#hpMapTypeMenu .it').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('hpMapTypeMenu')?.classList.remove('show');
    };

    window.hpZoomInMap = function() {
        if (!hpMap) return;
        hpMap.setZoom((hpMap.getZoom() || 10) + 1);
    };

    window.hpZoomOutMap = function() {
        if (!hpMap) return;
        hpMap.setZoom((hpMap.getZoom() || 10) - 1);
    };

    window.__bootHistoryMap = function () {
        const payload = window.__historyPayload;
        const mapEl = document.getElementById('historyMap');
        if (!mapEl) return;

        hpMap = new google.maps.Map(mapEl, {
            zoom: 13,
            center: { lat: 3.8480, lng: 11.5021 },
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
        });

        if (!payload) return;

        const points = payload.track?.points || [];
        const exact = payload.position_at_time || null;
        const previous = payload.context_points?.previous || null;
        const next = payload.context_points?.next || null;
        const bounds = new google.maps.LatLngBounds();
        const currentMode = document.getElementById('hpModeInput')?.value || '';

        if (points.length > 0) {
            const path = points.map(p => ({ lat: Number(p.lat), lng: Number(p.lng) }));

            new google.maps.Polyline({
                path,
                map: hpMap,
                strokeColor: '#f59e0b',
                strokeOpacity: 1,
                strokeWeight: 4,
            });

            path.forEach(p => bounds.extend(p));

            if (currentMode === 'range') {
                new google.maps.Marker({
                    position: path[0],
                    map: hpMap,
                    title: 'Début',
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 7,
                        fillColor: '#16a34a',
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 2,
                    }
                });

                if (path.length > 1) {
                    new google.maps.Marker({
                        position: path[path.length - 1],
                        map: hpMap,
                        title: 'Fin',
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 7,
                            fillColor: '#dc2626',
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2,
                        }
                    });
                }
            }
        }

        if (exact && currentMode === 'exact') {
            const marker = { lat: Number(exact.lat), lng: Number(exact.lng) };
            bounds.extend(marker);

            const exactMarker = new google.maps.Marker({
                position: marker,
                map: hpMap,
                title: 'Position exacte / la plus proche',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8,
                    fillColor: '#2563eb',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                }
            });

            const info = new google.maps.InfoWindow({
                content: `
                    <div style="min-width:210px">
                        <div style="font-weight:800;margin-bottom:6px">Position retrouvée</div>
                        <div><strong>Date/heure :</strong> ${exact.datetime || '—'}</div>
                        <div><strong>Latitude :</strong> ${exact.lat ?? '—'}</div>
                        <div><strong>Longitude :</strong> ${exact.lng ?? '—'}</div>
                        <div><strong>Vitesse :</strong> ${exact.speed ?? 0} km/h</div>
                    </div>
                `
            });

            info.open({ map: hpMap, anchor: exactMarker });
        }

        if (previous && currentMode === 'exact') {
            const marker = { lat: Number(previous.lat), lng: Number(previous.lng) };
            bounds.extend(marker);

            new google.maps.Marker({
                position: marker,
                map: hpMap,
                title: 'Point précédent',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 5,
                    fillColor: '#7c3aed',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                }
            });
        }

        if (next && currentMode === 'exact') {
            const marker = { lat: Number(next.lat), lng: Number(next.lng) };
            bounds.extend(marker);

            new google.maps.Marker({
                position: marker,
                map: hpMap,
                title: 'Point suivant',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 5,
                    fillColor: '#0ea5e9',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                }
            });
        }

        if (!bounds.isEmpty()) {
            hpMap.fitBounds(bounds);
        }
    };

    document.addEventListener('click', function(e) {
        const ctrl = document.getElementById('hpMapTypeCtrl');
        const menu = document.getElementById('hpMapTypeMenu');
        if (!ctrl || !menu) return;
        if (!ctrl.contains(e.target)) {
            menu.classList.remove('show');
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        window.hpDoSearch();

        const mode = document.getElementById('hpModeInput')?.value;
        if (mode === 'exact') {
            document.getElementById('hpPanelExact')?.classList.add('show');
            document.getElementById('hpBtnExact')?.classList.add('active');
        } else if (mode === 'range') {
            document.getElementById('hpPanelRange')?.classList.add('show');
            document.getElementById('hpBtnRange')?.classList.add('active');
        }
    });
})();
</script>
@endsection