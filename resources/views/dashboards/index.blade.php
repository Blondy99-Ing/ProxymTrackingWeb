{{-- resources/views/dashboard/live.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard Live')

@push('styles')
<style>
/* ============================================================
   LIVE DASHBOARD
   FIXES v2:
   - Alert modal opens immediately + 15s auto-close
   - Sound unlock on first user interaction (click/key/touch)
   - Compact alert modal: type/vehicle/driver/phone/time only
   - Report mandatory with inline validation
   - Trip tab: map no longer follows live vehicle
   - Zoom buttons repositioned, no collision with follow-pill
   - Follow-pill repositioned beside zoom column
   - SSE: reconnect backoff, variant event names, safe parsing
   - JS: all DOM accesses guarded, clean separation
   ============================================================ */

:root {
    --z-kpi: 0;
    --z-map-ui: 140;
    --z-modal: 170;
    --z-legend: 130;
}

/* Sticky KPI bar */
.kpi-sticky {
    position: fixed;
    top: var(--navbar-h, 52px);
    left: 0;
    right: 0;
    z-index: var(--z-kpi);
    background: var(--color-bg);
    padding: .5rem 1.25rem .35rem;
    box-shadow: 0 6px 24px rgba(0,0,0,.08);
}
.dark-mode .kpi-sticky { box-shadow: 0 10px 40px rgba(0,0,0,.45) }

.kpi-grid {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(180px,1fr) minmax(180px,1fr) minmax(0,5fr);
    gap: .5rem;
    align-items: stretch;
}
.kpi-grid > * { min-width: 0 }
@media (max-width:1200px) {
    .kpi-grid { grid-template-columns: 1fr 1fr }
    .kpi-panel { grid-column: 1 / -1 }
}
@media (max-width:1023px) {
    .kpi-grid { grid-template-columns: 1fr }
    .kpi-panel { grid-column: auto }
}

.card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg,10px);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.kpi,.kpi-panel,.type { width:100%; min-width:0; box-sizing:border-box }

.kpi {
    display:flex; align-items:center; justify-content:space-between;
    padding:.35rem .55rem; cursor:pointer; transition:.15s
}
.kpi:hover { transform:translateY(-1px); box-shadow:var(--shadow-md); border-color:var(--color-primary-border) }
.kpi .lbl { font-family:var(--font-display); font-size:.62rem; letter-spacing:.08em; text-transform:uppercase; color:var(--color-secondary-text,#8b949e); margin:0 }
.kpi .val { font-family:var(--font-display); font-weight:800; font-size:1.2rem; line-height:1; color:var(--color-primary); margin:.1rem 0 0 }
.kpi .ico { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:var(--color-primary-light) }
.kpi .ico i { color:var(--color-primary) }

.kpi-panel { padding:.55rem .75rem }
.kpi-types { width:100%; display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.5rem; align-items:stretch }
@media (max-width:1023px) { .kpi-types { grid-template-columns:repeat(2,minmax(0,1fr)) } }

.type {
    border:1px solid var(--color-border-subtle); border-radius:10px;
    padding:.45rem .55rem; display:flex; align-items:center; justify-content:space-between;
    gap:.5rem; cursor:pointer; transition:.15s; background:rgba(0,0,0,.03)
}
.dark-mode .type { background:rgba(255,255,255,.03) }
.type:hover { border-color:var(--color-primary-border); background:var(--color-primary-light) }
.type .t { font-family:var(--font-body); font-size:.62rem; color:var(--color-secondary-text,#8b949e); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
.type .n { font-family:var(--font-display); font-weight:900; font-size:1.1rem; color:var(--color-text); margin:.05rem 0 0 }

.content {
    margin-top:calc(var(--kpi-h,96px) + .75rem);
    display:flex; flex-direction:column; gap:1rem
}

.grid-main { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem }
@media(max-width:1023px) { .grid-main { grid-template-columns:1fr } }
@media(min-width:1024px) {
    .grid-main { height:calc(100vh - var(--navbar-h,52px) - var(--kpi-h,96px) - 2.5rem) }
    .col-left,.col-map { min-height:0 }
}

.tabs { display:flex; border-bottom:1px solid var(--color-border-subtle) }
.tab {
    flex:1; text-align:center; padding:.55rem .4rem;
    font-family:var(--font-display); font-size:.62rem; font-weight:800;
    letter-spacing:.04em; text-transform:uppercase; color:var(--color-secondary-text,#8b949e);
    background:transparent; border:none; border-bottom:2px solid transparent; cursor:pointer
}
.tab:hover { color:var(--color-text) }
.tab.active { color:var(--color-primary); border-bottom-color:var(--color-primary); background:var(--color-primary-light) }

.badge { background:#dc2626; color:#fff; border-radius:9999px; font-size:.55rem; font-weight:900; padding:0 .35rem; min-width:16px; display:inline-flex; justify-content:center }

.pane { display:none; flex-direction:column; min-height:0; flex:1 }
.pane.active { display:flex }

.search { padding:.55rem .75rem 0 }
.swrap { position:relative }
.swrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:.7rem; color:var(--color-secondary-text,#8b949e) }
.swrap input { width:100%; border:1px solid var(--color-border-subtle); border-radius:10px; padding:.5rem .6rem .5rem 2rem; font-size:.78rem; background:var(--color-card); color:var(--color-text); outline:none }
.swrap input:focus { border-color:var(--color-primary) }
.sclear { position:absolute; right:10px; top:50%; transform:translateY(-50%); display:none; width:18px; height:18px; border-radius:9999px; border:none; background:var(--color-border-subtle); color:var(--color-secondary-text,#8b949e); font-weight:900; cursor:pointer }
.sclear.show { display:block }

.modebar { display:flex; gap:.35rem; padding:.45rem .75rem 0 }
.mbtn { flex:1; border:1px solid var(--color-border-subtle); background:transparent; border-radius:9999px; padding:.35rem .5rem; font-family:var(--font-display); font-size:.6rem; font-weight:800; color:var(--color-secondary-text,#8b949e); cursor:pointer; transition:.12s }
.mbtn:hover { border-color:var(--color-primary); color:var(--color-primary) }
.mbtn.active { background:var(--color-primary-light); border-color:var(--color-primary); color:var(--color-primary) }

.fbar { display:flex; gap:.4rem; align-items:center; justify-content:space-between; padding:.45rem .75rem .15rem }
.fbtn { border:1px solid var(--color-border-subtle); background:transparent; border-radius:10px; padding:.35rem .55rem; font-family:var(--font-display); font-weight:900; font-size:.62rem; cursor:pointer }
.fbtn:hover { border-color:var(--color-primary); color:var(--color-primary) }
.fbtn2 { border:1px solid var(--color-border-subtle); background:var(--color-primary-light); color:var(--color-primary); border-radius:10px; padding:.35rem .55rem; font-family:var(--font-display); font-weight:900; font-size:.62rem; cursor:pointer }
.fbtn2:hover { border-color:var(--color-primary) }

.filters,.quickbar,.datebox { display:none }
.filters.show,.quickbar.show,.datebox.show { display:flex }
.datebox.show { display:block }
.filters { gap:.35rem; flex-wrap:wrap; padding:.25rem .75rem .35rem }
.f { border:1px solid var(--color-border-subtle); border-radius:9999px; padding:.22rem .55rem; font-family:var(--font-display); font-size:.58rem; font-weight:800; color:var(--color-secondary-text,#8b949e); cursor:pointer; transition:.12s; background:transparent }
.f:hover { border-color:var(--color-primary); color:var(--color-primary) }
.f.active { background:var(--color-primary-light); border-color:var(--color-primary); color:var(--color-primary) }

.quickbar { gap:.35rem; flex-wrap:wrap; padding:.25rem .75rem .1rem }
.qc { border:1px solid var(--color-border-subtle); border-radius:9999px; padding:.22rem .55rem; font-family:var(--font-display); font-size:.58rem; font-weight:900; color:var(--color-secondary-text,#8b949e); cursor:pointer; transition:.12s; background:transparent }
.qc:hover { border-color:var(--color-primary); color:var(--color-primary) }
.qc.active { background:var(--color-primary-light); border-color:var(--color-primary); color:var(--color-primary) }

.datebox { padding:.35rem .75rem .35rem }
.dr { display:flex; gap:.35rem; align-items:center; margin-bottom:.35rem }
.dr input { flex:1; min-width:0; border:1px solid var(--color-border-subtle); border-radius:10px; padding:.42rem .5rem; background:var(--color-card); color:var(--color-text); font-family:var(--font-mono,monospace); font-size:.64rem; outline:none }
.dr input:focus { border-color:var(--color-primary) }
.dr span { color:var(--color-secondary-text,#8b949e); font-size:.65rem }

.scroll { flex:1; min-height:0; overflow:auto; border-top:1px solid var(--color-border-subtle) }
.scroll::-webkit-scrollbar { height:6px; width:6px }
.scroll::-webkit-scrollbar-thumb { background:var(--color-border-subtle); border-radius:999px }

.item { padding:.65rem .75rem; cursor:pointer; border-left:3px solid transparent; transition:.12s }
.item:hover { background:rgba(128,128,128,.06) }
.item.sel { background:var(--color-primary-light); border-left-color:var(--color-primary) }

.hrow { display:flex; align-items:center; justify-content:space-between; gap:.5rem }
.title { font-family:var(--font-display); font-weight:900; font-size:.78rem }
.sub { font-family:var(--font-body); font-size:.66rem; color:var(--color-secondary-text,#8b949e); margin-top:.15rem }
.tags { display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.35rem }
.tag { font-family:var(--font-display); font-weight:800; font-size:.55rem; padding:.2rem .45rem; border-radius:9999px; display:inline-flex; align-items:center; gap:.25rem }
.dot { width:7px; height:7px; border-radius:9999px }

@keyframes pulse { 0%,100% { opacity:1 } 50% { opacity:.25 } }

.empty { padding:2rem 1rem; text-align:center; color:var(--color-secondary-text,#8b949e); font-family:var(--font-display); font-weight:800 }
.sep { height:1px; background:var(--color-border-subtle) }

/* Map */
.mapwrap { position:relative; min-height:0; display:flex; flex-direction:column }
.maphead { display:flex; align-items:center; justify-content:space-between; padding:.65rem .75rem; border-bottom:1px solid var(--color-border-subtle) }
.maphead h2 { margin:0; font-family:var(--font-display); font-weight:900; font-size:.85rem }
#fleetMap { flex:1; min-height:280px }
@media(min-width:1024px) { #fleetMap { min-height:0 } }

.sse { display:inline-flex; align-items:center; gap:.4rem; border:1px solid var(--color-border-subtle); border-radius:9999px; padding:.22rem .5rem; font-size:.65rem; color:var(--color-secondary-text,#8b949e) }
.ssedot { width:7px; height:7px; border-radius:9999px; background:#9ca3af }

#toast { position:absolute; left:50%; bottom:26px; transform:translateX(-50%); display:none; background:#16a34a; color:#fff; border-radius:12px; padding:.55rem .9rem; font-family:var(--font-display); font-weight:900; font-size:.72rem; box-shadow:0 12px 35px rgba(0,0,0,.35); z-index:80; white-space:nowrap }

/* Top trips KPIs */
#topTripsKpis { position:absolute; top:14px; left:50%; transform:translateX(-50%); z-index:var(--z-map-ui); display:none; gap:.45rem; flex-wrap:wrap; justify-content:center; padding:.15rem; pointer-events:auto }
#topTripsKpis.show { display:flex }

.pill { display:inline-flex; align-items:center; gap:.45rem; border:1px solid rgba(255,255,255,.12); background:rgba(17,24,39,.62); color:#fff; border-radius:9999px; padding:.35rem .55rem; backdrop-filter:blur(10px); box-shadow:0 10px 30px rgba(0,0,0,.25); font-family:var(--font-display); font-weight:900; font-size:.62rem; cursor:default }
.pill i { opacity:.9 }
.pill .v { font-size:.70rem }
.pill.clickable { cursor:pointer }
.pill.clickable:hover { border-color:rgba(255,255,255,.25); transform:translateY(-1px) }

/* Map type dropdown — left col 1 */
.maptype { position:absolute; top:70px; left:14px; z-index:var(--z-map-ui); pointer-events:auto }
.maptype .btn { width:44px; height:44px; border-radius:14px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.14); background:rgba(17,24,39,.62); color:#fff; box-shadow:0 10px 30px rgba(0,0,0,.25); backdrop-filter:blur(10px); cursor:pointer }
.maptype .btn:hover { border-color:rgba(255,255,255,.28) }
.maptype .menu { margin-top:.5rem; min-width:180px; display:none; border-radius:16px; overflow:hidden; border:1px solid rgba(255,255,255,.12); background:rgba(17,24,39,.62); box-shadow:0 18px 50px rgba(0,0,0,.35); backdrop-filter:blur(10px) }
.maptype .menu.show { display:block }
.maptype .it { display:flex; align-items:center; gap:.6rem; padding:.6rem .7rem; font-family:var(--font-display); font-weight:900; font-size:.62rem; color:#fff; cursor:pointer }
.maptype .it:hover { background:rgba(255,255,255,.08) }
.maptype .it .ck { width:18px; height:18px; border-radius:6px; border:1px solid rgba(255,255,255,.18); display:flex; align-items:center; justify-content:center; font-size:.6rem; opacity:.9 }
.maptype .it.active .ck { background:rgba(34,197,94,.25); border-color:rgba(34,197,94,.45) }
.maptype .it small { opacity:.85; font-family:var(--font-body); font-weight:600 }

/* Zoom controls — left col 2, top: 70px, left: 68px — no overlap with follow-pill */
.mapzoom { position:absolute; top:70px; left:68px; z-index:var(--z-map-ui); display:flex; flex-direction:column; gap:.45rem; pointer-events:auto }
.mapzoom .btn { width:44px; height:44px; border-radius:14px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,.14); background:rgba(17,24,39,.62); color:#fff; box-shadow:0 10px 30px rgba(0,0,0,.25); backdrop-filter:blur(10px); cursor:pointer; font-family:var(--font-display); font-size:1rem; font-weight:900 }
.mapzoom .btn:hover { border-color:rgba(255,255,255,.28) }

/* Follow pill — top-left, below zoom buttons (top: 70 + 44+45+8 ≈ 172px) */
.follow-pill {
    position:absolute;
    top:172px;
    left:14px;
    z-index:calc(var(--z-map-ui) + 2);
    display:none;
    align-items:center;
    gap:.45rem;
    border:1px solid rgba(255,255,255,.14);
    background:rgba(17,24,39,.62);
    color:#fff;
    border-radius:9999px;
    padding:.38rem .65rem;
    backdrop-filter:blur(10px);
    box-shadow:0 10px 30px rgba(0,0,0,.25);
    font-family:var(--font-display); font-weight:900; font-size:.60rem;
    cursor:pointer; user-select:none;
}
.follow-pill:hover { border-color:rgba(255,255,255,.28) }
.follow-pill.off { opacity:.72 }
.follow-pill .d { width:8px; height:8px; border-radius:999px; background:#22c55e }

/* Bottom legend */
.legend { position:absolute; left:50%; bottom:14px; transform:translateX(-50%); z-index:var(--z-legend); display:flex; gap:.5rem; flex-wrap:wrap; justify-content:center; pointer-events:auto }
.leg { display:inline-flex; align-items:center; gap:.45rem; border:1px solid rgba(255,255,255,.12); background:rgba(17,24,39,.62); color:#fff; border-radius:9999px; padding:.35rem .6rem; backdrop-filter:blur(10px); box-shadow:0 10px 30px rgba(0,0,0,.25); font-family:var(--font-display); font-weight:900; font-size:.60rem }
.leg .d { width:8px; height:8px; border-radius:999px }

/* Trip modal */
#tripModal { position:absolute; top:14px; right:14px; width:360px; max-width:calc(100% - 28px); z-index:var(--z-modal); display:none }
#tripModal.show { display:block }

.tm-h { display:flex; align-items:flex-start; justify-content:space-between; gap:.6rem; padding:.75rem }
.tm-h strong { font-family:var(--font-display); font-size:.82rem }
.tm-h small { display:block; color:var(--color-secondary-text,#8b949e); font-size:.65rem; margin-top:.15rem }
.tm-b { padding:0 .75rem .75rem }
.tm-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-top:.35rem }
.tm-box { border:1px solid var(--color-border-subtle); border-radius:14px; padding:.55rem; background:rgba(0,0,0,.03) }
.dark-mode .tm-box { background:rgba(255,255,255,.03) }
.tm-box .k { font-family:var(--font-display); font-size:.56rem; font-weight:900; letter-spacing:.06em; text-transform:uppercase; color:var(--color-secondary-text,#8b949e) }
.tm-box .v { font-family:var(--font-display); font-weight:900; font-size:.92rem; color:var(--color-text); margin-top:.08rem }
.tm-actions { display:flex; gap:.4rem; margin-top:.6rem }
.tm-actions button { flex:1; border-radius:12px; padding:.45rem .5rem; font-family:var(--font-display); font-weight:900; font-size:.62rem; cursor:pointer; transition:.15s }
.tm-actions .b1 { background:transparent; border:1px solid var(--color-border-subtle); color:var(--color-secondary-text,#8b949e) }
.tm-actions .b1:hover { background:rgba(128,128,128,.08); color:var(--color-text) }
.tm-actions .b2 { background:var(--color-primary); border:none; color:#fff }
.tm-actions .b2:hover { background:var(--color-primary-hover,#e07318) }

/* Vehicle modal */
#vehicleModal { position:absolute; top:14px; right:14px; width:320px; max-width:calc(100% - 28px); z-index:var(--z-modal); display:none }
#vehicleModal.show { display:block }

/* Replay */
#tripReplay { position:absolute; left:14px; bottom:56px; right:14px; display:none; z-index:75 }
.rp { padding:.65rem .75rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap }
.rp strong { font-family:var(--font-display); font-size:.7rem }
.rp button { border:1px solid var(--color-border-subtle); background:transparent; border-radius:10px; padding:.35rem .5rem; font-family:var(--font-display); font-weight:900; font-size:.62rem; cursor:pointer }
.rp button:hover { border-color:var(--color-primary); color:var(--color-primary) }
.rp button.active-btn { background:var(--color-primary-light); border-color:var(--color-primary); color:var(--color-primary) }
.rp input[type="range"] { flex:1; min-width:140px }
.rp small { color:var(--color-secondary-text,#8b949e); font-family:var(--font-mono,monospace); font-size:.62rem }
.speed-chips { display:flex; gap:.2rem; align-items:center }
.speed-chip { border:1px solid var(--color-border-subtle); background:transparent; border-radius:8px; padding:.25rem .4rem; font-family:var(--font-display); font-weight:900; font-size:.58rem; cursor:pointer; color:var(--color-secondary-text,#8b949e) }
.speed-chip:hover { border-color:var(--color-primary); color:var(--color-primary) }
.speed-chip.active-chip { background:var(--color-primary-light); border-color:var(--color-primary); color:var(--color-primary) }

/* Alert detail — right panel */
#alertDetail { position:absolute; top:14px; right:14px; width:380px; max-width:calc(100% - 28px); display:none; z-index:var(--z-modal); box-shadow:0 18px 50px rgba(0,0,0,.28) }
.ad-h { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; padding:.85rem .9rem .7rem; border-bottom:1px solid var(--color-border-subtle) }
.ad-h strong { font-family:var(--font-display); font-size:.88rem }
.ad-h small { display:block; color:var(--color-secondary-text,#8b949e); font-size:.68rem; margin-top:.18rem }
.ad-b { padding:.8rem .9rem .9rem }
.ad-btns { display:flex; gap:.45rem; margin-top:.75rem; flex-wrap:wrap }
.ad-btns button,.ad-btns a { flex:1 1 110px; border-radius:12px; padding:.55rem .6rem; font-family:var(--font-display); font-weight:900; font-size:.64rem; cursor:pointer; transition:.15s; text-align:center; align-items:center; justify-content:center; display:inline-flex; text-decoration:none }
.ad-btns .b1 { background:transparent; border:1px solid var(--color-border-subtle); color:var(--color-secondary-text,#8b949e) }
.ad-btns .b1:hover { background:rgba(128,128,128,.08); color:var(--color-text) }
.ad-btns .b2 { background:var(--color-primary); border:none; color:#fff }
.ad-btns .b2:hover { background:var(--color-primary-hover,#e07318) }

/* Alert brief popup */
#alertFlashBrief {
    position:absolute; left:50%; top:18px; transform:translateX(-50%);
    z-index:999; min-width:320px; max-width:min(92vw,520px); display:none;
}
@keyframes alertFlashDrop {
    0% { opacity:0; transform:translateX(-50%) translateY(-10px) }
    100% { opacity:1; transform:translateX(-50%) translateY(0) }
}
#alertFlashBrief.show { display:block !important; animation:alertFlashDrop .18s ease-out }

@keyframes selectedVehicleBounce {
    0%,100% { transform:translateX(-50%) translateY(0) }
    50% { transform:translateX(-50%) translateY(-8px) }
}
</style>
@endpush

@section('content')
@php
$alertTypesMeta = [
    'stolen'      => ['Vol',           'fa-mask',               '#dc2626'],
    'low_battery' => ['Batterie faible','fa-battery-quarter',   '#f59e0b'],
    'geofence'    => ['Geofence',       'fa-draw-polygon',      '#2563eb'],
    'safe_zone'   => ['Safe Zone',      'fa-shield-halved',     '#16a34a'],
    'speed'       => ['Vitesse',        'fa-gauge-high',        '#ea580c'],
    'offline'     => ['Offline',        'fa-plug-circle-xmark', '#6b7280'],
    'time_zone'   => ['Time Zone',      'fa-calendar-alt',      '#7c3aed'],
];
@endphp

<div class="kpi-sticky" id="kpiBar">
    <div class="kpi-grid">
        <div class="card kpi" onclick="window.switchTab('flotte')">
            <div>
                <p class="lbl">Chauffeurs</p>
                <p class="val" id="kUsers">{{ (int)($usersCount ?? 0) }}</p>
            </div>
            <div class="ico"><i class="fas fa-users"></i></div>
        </div>
        <div class="card kpi" onclick="window.switchTab('flotte')">
            <div>
                <p class="lbl">Véhicules</p>
                <p class="val" style="color:var(--color-text)" id="kVeh">{{ (int)($vehiclesCount ?? 0) }}</p>
            </div>
            <div class="ico" style="background:rgba(107,114,128,.1)">
                <i class="fas fa-car-alt" style="color:var(--color-secondary-text,#8b949e)"></i>
            </div>
        </div>
        <div class="card kpi-panel">
            <div class="kpi-types">
                @foreach($alertTypesMeta as $k => [$label,$icon,$color])
                <div class="type" onclick="window.switchTab('alertes');window.filterAlertsByType('{{ $k }}')">
                    <div style="min-width:0">
                        <p class="t">{{ $label }}</p>
                        <p class="n" id="kA_{{ $k }}">{{ (int)($alertStats[$k] ?? 0) }}</p>
                    </div>
                    <div style="width:30px;height:25px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:{{ $color }}1A">
                        <i class="fas {{ $icon }}" style="color:{{ $color }};font-size:.7rem"></i>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="grid-main">

        {{-- LEFT PANEL --}}
        <div class="col-left">
            <div class="card" style="height:100%;display:flex;flex-direction:column;min-height:0">

                <div class="tabs">
                    <button class="tab active" id="tab-flotte"  onclick="window.switchTab('flotte')">📍 Flotte</button>
                    <button class="tab"         id="tab-trajets" onclick="window.switchTab('trajets')">🛣️ Trajets</button>
                    <button class="tab"         id="tab-alertes" onclick="window.switchTab('alertes')">🚨 <span class="badge" id="bAlerts">0</span></button>
                </div>

                <div class="search">
                    <div class="swrap">
                        <i class="fas fa-search"></i>
                        <input id="q" placeholder="Immat., chauffeur…" oninput="window.doSearch()" autocomplete="off">
                        <button id="qClear" class="sclear" onclick="window.clearSearch()">×</button>
                    </div>
                </div>

                {{-- FLOTTE --}}
                <div class="pane active" id="pane-flotte">
                    <div class="modebar">
                        <button class="mbtn"        id="mode-flotte-simple"   onclick="window.setMode('flotte','simple')">Liste</button>
                        <button class="mbtn active" id="mode-flotte-detailed" onclick="window.setMode('flotte','detailed')">Détaillé</button>
                    </div>
                    <div class="fbar">
                        <button class="fbtn"  onclick="window.togglePaneFilters('flotte')"><i class="fas fa-sliders-h"></i> Filtres</button>
                        <button class="fbtn2" onclick="window.resetFleetFilters()"><i class="fas fa-rotate-left"></i> Reset</button>
                    </div>
                    <div class="filters" id="vf">
                        <span class="f active" data-f="all"     onclick="window.setVehFilter(this,'all')">Tous</span>
                        <span class="f"         data-f="moving"  onclick="window.setVehFilter(this,'moving')">● En mouvement</span>
                        <span class="f"         data-f="idle"    onclick="window.setVehFilter(this,'idle')">● En arrêt</span>
                        <span class="f"         data-f="online"  onclick="window.setVehFilter(this,'online')">● Online</span>
                        <span class="f"         data-f="offline" onclick="window.setVehFilter(this,'offline')">● Offline</span>
                    </div>
                    <div class="scroll" id="vehList">
                        <div class="empty"><i class="fas fa-circle-notch fa-spin"></i><div style="margin-top:.6rem">Chargement…</div></div>
                    </div>
                </div>

                {{-- TRAJETS --}}
                <div class="pane" id="pane-trajets">
                    <div class="modebar">
                        <button class="mbtn"        id="mode-trajets-simple"   onclick="window.setMode('trajets','simple')">Liste</button>
                        <button class="mbtn active" id="mode-trajets-detailed" onclick="window.setMode('trajets','detailed')">Détaillé</button>
                    </div>
                    <div class="fbar">
                        <button class="fbtn"  onclick="window.togglePaneFilters('trajets')"><i class="fas fa-sliders-h"></i> Filtres</button>
                        <button class="fbtn2" onclick="window.toggleTripsCustom()"><i class="fas fa-calendar"></i> Personnaliser</button>
                    </div>
                    <div class="quickbar" id="tQuick">
                        <span class="qc active" data-q="today"      onclick="window.setTripsQuick(this,'today')">Aujourd'hui</span>
                        <span class="qc"         data-q="yesterday"  onclick="window.setTripsQuick(this,'yesterday')">Hier</span>
                        <span class="qc"         data-q="this_week"  onclick="window.setTripsQuick(this,'this_week')">Semaine</span>
                        <span class="qc"         data-q="this_month" onclick="window.setTripsQuick(this,'this_month')">Mois</span>
                        <span class="qc"         data-q="this_year"  onclick="window.setTripsQuick(this,'this_year')">Année</span>
                    </div>
                    <div class="datebox" id="tDateBox">
                        <div class="dr">
                            <input type="date" id="tFrom"><span>→</span><input type="date" id="tTo">
                        </div>
                    </div>
                    <div class="filters" id="tf">
                        <span class="f active" data-f="all"    onclick="window.setTripFilter(this,'all')">Tous</span>
                        <span class="f"         data-f="active" onclick="window.setTripFilter(this,'active')">En cours</span>
                        <span class="f"         data-f="done"   onclick="window.setTripFilter(this,'done')">Terminés</span>
                    </div>
                    <div class="scroll" id="tripList">
                        <div class="empty"><i class="fas fa-route"></i><div style="margin-top:.6rem">Aucun trajet chargé</div></div>
                    </div>
                </div>

                {{-- ALERTES --}}
                <div class="pane" id="pane-alertes">
                    <div class="modebar">
                        <button class="mbtn"        id="mode-alertes-simple"   onclick="window.setMode('alertes','simple')">Liste</button>
                        <button class="mbtn active" id="mode-alertes-detailed" onclick="window.setMode('alertes','detailed')">Détaillé</button>
                    </div>
                    <div class="fbar">
                        <button class="fbtn"  onclick="window.togglePaneFilters('alertes')"><i class="fas fa-sliders-h"></i> Filtres</button>
                        <button class="fbtn2" onclick="window.toggleAlertsCustom()"><i class="fas fa-calendar"></i> Personnaliser</button>
                    </div>
                    <div class="quickbar" id="aQuick">
                        <span class="qc active" data-q="today"      onclick="window.setAlertsQuick(this,'today')">Aujourd'hui</span>
                        <span class="qc"         data-q="yesterday"  onclick="window.setAlertsQuick(this,'yesterday')">Hier</span>
                        <span class="qc"         data-q="this_week"  onclick="window.setAlertsQuick(this,'this_week')">Semaine</span>
                        <span class="qc"         data-q="this_month" onclick="window.setAlertsQuick(this,'this_month')">Mois</span>
                        <span class="qc"         data-q="this_year"  onclick="window.setAlertsQuick(this,'this_year')">Année</span>
                    </div>
                    <div class="filters" id="af">
                        <span class="f active" data-at="all"         onclick="window.setAlertType(this,'all')">Toutes</span>
                        <span class="f"         data-at="stolen"      onclick="window.setAlertType(this,'stolen')">🔴 Vol</span>
                        <span class="f"         data-at="low_battery" onclick="window.setAlertType(this,'low_battery')">🪫 Batterie</span>
                        <span class="f"         data-at="geofence"    onclick="window.setAlertType(this,'geofence')">📍 Geofence</span>
                        <span class="f"         data-at="safe_zone"   onclick="window.setAlertType(this,'safe_zone')">🛡️ Safe Zone</span>
                        <span class="f"         data-at="speed"       onclick="window.setAlertType(this,'speed')">⚡ Vitesse</span>
                        <span class="f"         data-at="offline"     onclick="window.setAlertType(this,'offline')">📴 Offline</span>
                        <span class="f"         data-at="time_zone"   onclick="window.setAlertType(this,'time_zone')">🌙 Time Zone</span>
                    </div>
                    <div class="datebox" id="aDateBox">
                        <div class="dr">
                            <input type="date" id="aFrom"><span>→</span><input type="date" id="aTo">
                        </div>
                        <div class="dr">
                            <input type="time" id="aHFrom" placeholder="HH:MM"><span>→</span><input type="time" id="aHTo" placeholder="HH:MM">
                        </div>
                    </div>
                    <div class="scroll" id="alertList">
                        <div class="empty"><i class="fas fa-bell"></i><div style="margin-top:.6rem">Aucune alerte chargée</div></div>
                    </div>
                </div>

            </div>
        </div>

        {{-- MAP --}}
        <div class="col-map" style="grid-column:span 3;min-height:0">
            <div class="card mapwrap" style="height:100%">

                <div class="maphead">
                    <h2>Localisation de la flotte</h2>
                    <div style="display:flex;align-items:center;gap:.6rem">
                        <span class="sse"><span class="ssedot" id="sseDot"></span><span id="sseTxt">Connexion…</span></span>
                        <span id="lastUp" style="font-size:.65rem;color:var(--color-secondary-text,#8b949e)"></span>
                    </div>
                </div>

                <div id="fleetMap"></div>

                {{-- Map type selector (col 1, top:70px) --}}
                <div class="maptype" id="mapTypeCtrl">
                    <button class="btn" onclick="window.toggleMapTypeMenu()" title="Type de carte">
                        <i class="fas fa-map"></i>
                    </button>
                    <div class="menu" id="mapTypeMenu">
                        <div class="it active" data-type="roadmap"   onclick="window.setMapType(this,'roadmap')"><span class="ck">✓</span> Carte <small>(Roadmap)</small></div>
                        <div class="it"         data-type="satellite" onclick="window.setMapType(this,'satellite')"><span class="ck">✓</span> Satellite</div>
                        <div class="it"         data-type="hybrid"    onclick="window.setMapType(this,'hybrid')"><span class="ck">✓</span> Hybride</div>
                        <div class="it"         data-type="terrain"   onclick="window.setMapType(this,'terrain')"><span class="ck">✓</span> Terrain</div>
                    </div>
                </div>

                {{-- Zoom buttons (col 2, top:70px, left:68px) --}}
                <div class="mapzoom" aria-label="Zoom carte">
                    <button class="btn" type="button" onclick="window.zoomInMap()"  title="Zoom avant">+</button>
                    <button class="btn" type="button" onclick="window.zoomOutMap()" title="Zoom arrière">−</button>
                </div>

                {{-- Follow pill — below zoom, left:14px, top:172px --}}
                <div class="follow-pill" id="followSelectedPill" onclick="window.toggleSelectedVehicleFollow()">
                    <span class="d"></span>
                    <span id="followSelectedTxt">Suivi véhicule : ON</span>
                </div>

                {{-- Top-center trips KPIs --}}
                <div id="topTripsKpis">
                    <div class="pill"><i class="fas fa-route"></i> <span class="v" id="tkCount">0</span> trajets</div>
                    <div class="pill"><i class="fas fa-road"></i>  <span class="v" id="tkDist">0.0 km</span></div>
                    <div class="pill"><i class="fas fa-clock"></i> <span class="v" id="tkDur">0m</span></div>
                    <div class="pill"><i class="fas fa-gauge-high"></i> <span class="v" id="tkMax">0 km/h</span></div>
                </div>

                {{-- Legend --}}
                <div class="legend">
                    <div class="leg"><span class="d" style="background:#16a34a"></span> En mouvement</div>
                    <div class="leg"><span class="d" style="background:#2563eb"></span> En ligne</div>
                    <div class="leg"><span class="d" style="background:#d97706"></span> À l'arrêt</div>
                    <div class="leg"><span class="d" style="background:#6b7280"></span> Offline</div>
                </div>

                {{-- Trip modal --}}
                <div class="card" id="tripModal">
                    <div class="tm-h">
                        <div style="min-width:0"><strong id="tmTitle">Trajet</strong><small id="tmSub">—</small></div>
                        <button class="mbtn" style="flex:0 0 auto;padding:.3rem .55rem;border-radius:10px" onclick="window.closeTripModal()">✕</button>
                    </div>
                    <div class="tm-b">
                        <div class="tm-grid">
                            <div class="tm-box"><div class="k">Distance</div><div class="v" id="tmDist">0.0 km</div></div>
                            <div class="tm-box"><div class="k">Durée</div><div class="v" id="tmDur">0m</div></div>
                            <div class="tm-box"><div class="k">Vitesse max</div><div class="v" id="tmMax">0 km/h</div></div>
                            <div class="tm-box"><div class="k">Points</div><div class="v" id="tmPts">0</div></div>
                        </div>
                        <div class="tm-grid" style="margin-top:.35rem">
                            <div class="tm-box" style="grid-column:span 2"><div class="k">Départ</div><div class="v" id="tmStart" style="font-size:.75rem">—</div></div>
                            <div class="tm-box" style="grid-column:span 2"><div class="k">Arrivée</div><div class="v" id="tmEnd" style="font-size:.75rem">—</div></div>
                        </div>
                        <div class="tm-actions">
                            <button class="b1" onclick="window.replayPlay()">▶ Jouer</button>
                            <button class="b1" onclick="window.replayStop()">⏹ Reset</button>
                            <button class="b2" onclick="window.focusTrip()">📍 Centrer</button>
                        </div>
                    </div>
                </div>

                {{-- Vehicle modal --}}
                <div class="card" id="vehicleModal">
                    <div class="tm-h">
                        <div style="min-width:0"><strong id="vmTitle">Véhicule</strong><small id="vmSub">—</small></div>
                        <button class="mbtn" style="flex:0 0 auto;padding:.3rem .55rem;border-radius:10px" onclick="window.closeVehicleModal()">✕</button>
                    </div>
                    <div class="tm-b">
                        <div class="tm-grid">
                            <div class="tm-box"><div class="k">Immatriculation</div><div class="v" id="vmImmat">—</div></div>
                            <div class="tm-box"><div class="k">Marque / Modèle</div><div class="v" id="vmBrand" style="font-size:.78rem">—</div></div>
                            <div class="tm-box"><div class="k">Chauffeur</div><div class="v" id="vmDriver" style="font-size:.75rem">—</div></div>
                            <div class="tm-box"><div class="k">Vitesse</div><div class="v" id="vmSpeed">— km/h</div></div>
                            <div class="tm-box"><div class="k">Statut</div><div class="v" id="vmStatus">—</div></div>
                            <div class="tm-box"><div class="k">Dernière MàJ</div><div class="v" id="vmUpdated" style="font-size:.72rem">—</div></div>
                        </div>
                        <div class="tm-grid" style="margin-top:.35rem">
                            <div class="tm-box" style="grid-column:span 2"><div class="k">Position</div><div class="v" id="vmPos" style="font-size:.72rem;font-family:var(--font-mono,monospace)">—</div></div>
                        </div>
                        <div class="tm-actions">
                            <button class="b2" onclick="window.locateVehicleFromModal()">📍 Localiser</button>
                        </div>
                    </div>
                </div>

                {{-- Replay bar --}}
                <div class="card" id="tripReplay">
                    <div class="rp">
                        <strong>Replay</strong>
                        <button onclick="window.replayPlay()">▶</button>
                        <button onclick="window.replayPause()">⏸</button>
                        <button onclick="window.replayStop()">⏹</button>
                        <div class="speed-chips">
                            <button class="speed-chip" data-spd="0.25" onclick="window.replaySetSpeed(0.25)">¼x</button>
                            <button class="speed-chip" data-spd="0.5"  onclick="window.replaySetSpeed(0.5)">½x</button>
                            <button class="speed-chip active-chip" data-spd="1" onclick="window.replaySetSpeed(1)">1x</button>
                            <button class="speed-chip" data-spd="2"  onclick="window.replaySetSpeed(2)">2x</button>
                            <button class="speed-chip" data-spd="4"  onclick="window.replaySetSpeed(4)">4x</button>
                            <button class="speed-chip" data-spd="8"  onclick="window.replaySetSpeed(8)">8x</button>
                            <button class="speed-chip" data-spd="16" onclick="window.replaySetSpeed(16)">16x</button>
                        </div>
                        <button onclick="window.replaySlower()" title="Ralentir">−</button>
                        <small>x<span id="rpSpeed">1</span></small>
                        <button onclick="window.replayFaster()" title="Accélérer">+</button>
                        <button id="rpFollow" onclick="window.toggleFollow()" title="Suivre le véhicule">🎯 Suivre</button>
                        <input id="rpRange" type="range" min="0" max="0" value="0" oninput="window.replaySeek(this.value)">
                        <small id="rpMeta">0/0</small>
                        <button onclick="window.closeReplay()">✕</button>
                    </div>
                </div>

                {{-- Alert detail panel --}}
                <div class="card" id="alertDetail">
                    <div class="ad-h">
                        <div style="min-width:0">
                            <strong id="adTitle">—</strong>
                            <small id="adVeh">—</small>
                        </div>
                        <button class="mbtn" style="flex:0 0 auto;padding:.3rem .55rem;border-radius:10px" onclick="window.closeAlertDetail()">✕</button>
                    </div>
                    <div class="ad-b">

                        {{-- Compact meta grid --}}
                        <div id="adMeta" style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.75rem"></div>

                        {{-- Driver card --}}
                        <div id="adUserCard" style="display:none;margin-bottom:.75rem;border:1px solid var(--color-border-subtle);border-radius:14px;padding:.8rem;background:var(--color-card)">
                            <div style="font-family:var(--font-display);font-size:.6rem;font-weight:900;color:var(--color-secondary-text,#8b949e);letter-spacing:.05em;text-transform:uppercase;margin-bottom:.45rem">Chauffeur</div>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
                                <div>
                                    <div id="adUserName" style="font-weight:900;font-size:.82rem">—</div>
                                    <div id="adUserPhone" style="margin-top:.12rem;font-size:.7rem;color:var(--color-secondary-text,#8b949e)">—</div>
                                </div>
                                <a id="adCallBtn" href="javascript:void(0)" class="b2 ad-btns" style="display:none;flex:0 0 auto;padding:.45rem .65rem;border-radius:12px;font-family:var(--font-display);font-weight:900;font-size:.64rem;text-decoration:none;background:var(--color-primary);color:#fff">📞 Appeler</a>
                            </div>
                        </div>

                        {{-- Mandatory report --}}
                        <div id="adReportWrap" style="margin-bottom:.75rem">
                            <label for="adReport" style="display:block;margin-bottom:.35rem;font-family:var(--font-display);font-size:.62rem;font-weight:900;color:var(--color-secondary-text,#8b949e);letter-spacing:.05em;text-transform:uppercase">
                                Rapport de traitement <span style="color:#dc2626">*</span>
                            </label>
                            <textarea id="adReport" rows="3"
                                placeholder="Action menée, personne contactée, constat terrain…"
                                style="width:100%;resize:vertical;border:1px solid var(--color-border-subtle);border-radius:12px;padding:.65rem .75rem;background:var(--color-card);color:var(--color-text);font-size:.72rem;line-height:1.45;outline:none;box-sizing:border-box"
                            ></textarea>
                            <div id="adReportError" style="display:none;margin-top:.3rem;color:#dc2626;font-size:.67rem;font-weight:700">
                                ⚠ Le rapport est obligatoire pour traiter une alerte.
                            </div>
                        </div>

                        <div class="ad-btns">
                            <button class="b1" onclick="window.markAlertProcessed()">🛠️ Traiter</button>
                            <button class="b2" onclick="window.locateAlert()">📍 Localiser</button>
                            <a id="adProcessCallBtn" href="javascript:void(0)" class="b2" style="display:none">📞 Appeler</a>
                        </div>
                    </div>
                </div>

                {{-- Alert brief popup --}}
                <div id="alertFlashBrief">
                    <div class="card" style="border:1px solid rgba(255,255,255,.08);box-shadow:0 18px 50px rgba(0,0,0,.35)">
                        <div style="padding:.8rem .9rem;display:flex;align-items:flex-start;gap:.75rem">
                            <div id="afbIcon" style="width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(220,38,38,.12);color:#dc2626;flex:0 0 auto">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div style="min-width:0;flex:1">
                                <div id="afbTitle" style="font-family:var(--font-display);font-weight:900;font-size:.85rem">Nouvelle alerte</div>
                                <div id="afbVeh"   style="margin-top:.1rem;font-size:.7rem;color:var(--color-secondary-text,#8b949e)">—</div>
                                <div id="afbDrv"   style="margin-top:.05rem;font-size:.68rem;color:var(--color-secondary-text,#8b949e)">—</div>
                                <div id="afbTime"  style="margin-top:.05rem;font-size:.65rem;color:var(--color-secondary-text,#8b949e)">—</div>
                                <div style="margin-top:.35rem;display:flex;gap:.4rem;flex-wrap:wrap;align-items:center">
                                    <a id="afbCallBtn" href="javascript:void(0)" style="display:none;font-family:var(--font-display);font-weight:900;font-size:.62rem;padding:.3rem .55rem;border-radius:10px;background:var(--color-primary);color:#fff;text-decoration:none">📞 Appeler</a>
                                    <button onclick="window.openAlertDetailFromBrief()" style="font-family:var(--font-display);font-weight:900;font-size:.62rem;padding:.3rem .55rem;border-radius:10px;border:1px solid var(--color-border-subtle);background:transparent;cursor:pointer">Voir détail</button>
                                </div>
                            </div>
                            <button class="mbtn" style="flex:0 0 auto;padding:.25rem .5rem;border-radius:10px" onclick="window.closeAlertBrief()">✕</button>
                        </div>
                    </div>
                </div>

                {{-- Audio --}}
                <audio id="alertSoundPassive" preload="auto">
                    <source src="{{ asset('assets/song/alert_passif.mp3') }}" type="audio/mpeg">
                </audio>
                <audio id="alertSoundActive" preload="auto">
                    <source src="{{ asset('assets/song/alert_actif.mp3') }}" type="audio/mpeg">
                </audio>

                <div id="toast"></div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';

    /* ─────────────────────────────────────────────
       ROUTES
    ───────────────────────────────────────────── */
    const R = {
        stream:       @json(route('dashboard.stream')),
        alertsDay:    @json(url('/alerts')),
        trajetsList:  @json(route('trajets.index')),
        trajetDetail: (vId, tId) => {
            const base = @json(url('/trajets/show'));
            return `${base}/${encodeURIComponent(vId)}/${encodeURIComponent(tId)}?format=json`;
        },
        processAlert: (id) => @json(url('/alerts')) + `/${encodeURIComponent(id)}/processed`,
    };

    /* ─────────────────────────────────────────────
       CONSTANTS
    ───────────────────────────────────────────── */
    const COLORS = { moving:'#16a34a', online:'#2563eb', idle:'#d97706', offline:'#6b7280' };

    const ALERT_META = {
        stolen:      { label:'Vol détecté',    icon:'fa-mask',              color:'#dc2626', level:'active'  },
        low_battery: { label:'Batterie faible', icon:'fa-battery-quarter',   color:'#f59e0b', level:'passive' },
        geofence:    { label:'Geofence',        icon:'fa-draw-polygon',      color:'#2563eb', level:'passive' },
        safe_zone:   { label:'Safe Zone',       icon:'fa-shield-halved',     color:'#16a34a', level:'passive' },
        speed:       { label:'Survitesse',      icon:'fa-gauge-high',        color:'#ea580c', level:'active'  },
        offline:     { label:'Offline',         icon:'fa-plug-circle-xmark', color:'#6b7280', level:'passive' },
        time_zone:   { label:'Time Zone',       icon:'fa-calendar-alt',      color:'#7c3aed', level:'passive' },
        engine_on:   { label:'Moteur ON',       icon:'fa-power-off',         color:'#16a34a', level:'passive' },
        engine_off:  { label:'Moteur OFF',      icon:'fa-engine-warning',    color:'#dc2626', level:'active'  },
        other:       { label:'Autre alerte',    icon:'fa-bell',              color:'#0f766e', level:'passive' },
        unknown:     { label:'Alerte inconnue', icon:'fa-circle-question',   color:'#64748b', level:'passive' },
    };

    const ALLOWED_ALERT_TYPES = new Set(Object.keys(ALERT_META));
    const SPEED_STEPS = [0.25, 0.5, 1, 2, 4, 8, 16];

    /* ─────────────────────────────────────────────
       STATE
    ───────────────────────────────────────────── */
    let map = null;
    let sse = null;
    let sseReconnectDelay = 2000;
    let sseReconnectTimer = null;

    let markers = {};
    let selectedVehicleId = null;
    let selectedVehicleIndicator = null;
    let selectedVehicleIndicatorEl = null;
    let followSelectedVehicle = true;
    let vehicleHeadingCache = new Map();

    let vehicles = @json($vehicles ?? []);
    let trips = [];
    let alerts = [];

    let vehFilter = 'all';
    let tripFilter = 'all';
    let alertType = 'all';
    let alertsQuick = 'today';
    let tripsQuick = 'today';
    let viewMode = { flotte:'detailed', trajets:'detailed', alertes:'detailed' };

    let currentAlert = null;
    let currentAlertForBrief = null;   // holds last realtime alert for "Voir détail"
    let currentTrip = null;
    let tripPolyline = null;
    let tripCursor = null;
    let tripStartMarker = null;
    let tripEndMarker = null;
    let replayPoints = [];
    let replayIndex = 0;
    let replayTimer = null;
    let replaySpeedFactor = 1;
    let replayFollow = false;

    // seenAlertIds: IDs already triggered via SSE (sound+modal). Never pre-filled
    // from snapshots — doing so silently blocks every incoming alert.new event.
    let seenAlertIds = new Set();
    // snapshotAlertIds: IDs from initial HTTP/SSE snapshot, used only to avoid
    // duplicating entries in the alert list render. Does NOT block sound/modal.
    let snapshotAlertIds = new Set();
    let alertBriefTimer = null;
    let realtimeAlertModalTimer = null;

    /* ─────────────────────────────────────────────
       AUDIO
    ───────────────────────────────────────────── */
    let audioUnlocked = false;

    function unlockAlertAudio() {
        if (audioUnlocked) return;
        audioUnlocked = true;
        ['alertSoundPassive', 'alertSoundActive'].forEach(id => {
            const a = document.getElementById(id);
            if (!a) return;
            a.muted = true;
            const p = a.play();
            if (p && typeof p.then === 'function') {
                p.then(() => { a.pause(); a.currentTime = 0; a.muted = false; }).catch(() => { a.muted = false; });
            }
        });
    }

    function playAlertSoundForType(type) {
        const meta = getAlertMeta(type);
        const id = meta.level === 'active' ? 'alertSoundActive' : 'alertSoundPassive';
        const audio = document.getElementById(id);
        if (!audio) return;
        try {
            audio.currentTime = 0;
            audio.play().catch(() => {});
        } catch (_) {}
    }

    /* ─────────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────────── */
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));

    const fmtMin = (m) => {
        m = parseInt(m || 0, 10);
        const h = Math.floor(m / 60), r = m % 60;
        if (h <= 0) return r + 'm';
        return h + 'h' + String(r).padStart(2, '0');
    };

    function debounce(fn, ms = 350) {
        let t = null;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    const ymd = (d) => {
        const z = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate());
    };

    function toast(msg, ok = true) {
        const t = document.getElementById('toast');
        if (!t) return;
        t.style.background = ok ? '#16a34a' : '#dc2626';
        t.textContent = msg;
        t.style.display = 'block';
        clearTimeout(window.__toastT);
        window.__toastT = setTimeout(() => { if (t) t.style.display = 'none'; }, 2400);
    }

    function measureHeights() {
        const k = document.getElementById('kpiBar');
        const n = document.getElementById('navbar');
        if (k) document.documentElement.style.setProperty('--kpi-h', Math.round(k.getBoundingClientRect().height) + 'px');
        if (n) document.documentElement.style.setProperty('--navbar-h', Math.round(n.getBoundingClientRect().height) + 'px');
    }

    function toMsSafe(v) {
        if (v == null) return null;
        if (typeof v === 'number' && Number.isFinite(v)) return v > 1e12 ? v : v * 1000;
        if (typeof v === 'string') {
            const s = v.trim();
            if (!s) return null;
            if (/^\d+$/.test(s)) { const n = Number(s); return n > 1e12 ? n : n * 1000; }
            const parsed = Date.parse(s.replace(' ', 'T'));
            return Number.isFinite(parsed) ? parsed : null;
        }
        return null;
    }

    function humanDurationFromSeconds(sec) {
        sec = parseInt(sec || 0, 10);
        if (sec < 0) sec = 0;
        const d = Math.floor(sec / 86400), h = Math.floor((sec % 86400) / 3600),
              m = Math.floor((sec % 3600) / 60), s = sec % 60;
        if (d > 0) return `${d}j ${h}h ${m}min`;
        if (h > 0) return `${h}h ${m}min`;
        if (m > 0) return `${m}min${s > 0 ? ` ${s}s` : ''}`;
        return `${s}s`;
    }

    function computeBearing(lat1, lon1, lat2, lon2) {
        const toRad = d => d * Math.PI / 180;
        const toDeg = r => r * 180 / Math.PI;
        const φ1 = toRad(lat1), φ2 = toRad(lat2), λ1 = toRad(lon1), λ2 = toRad(lon2);
        const y = Math.sin(λ2 - λ1) * Math.cos(φ2);
        const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(λ2 - λ1);
        return (toDeg(Math.atan2(y, x)) + 360) % 360;
    }

    function setDateRangeInputs(fromId, toId, from, to) {
        const a = document.getElementById(fromId);
        const b = document.getElementById(toId);
        if (a) a.value = from || '';
        if (b) b.value = to || '';
    }

    /* ─────────────────────────────────────────────
       ALERT META
    ───────────────────────────────────────────── */
    function normalizeAlertType(type) {
        const t = String(type || '').trim().toLowerCase();
        if (!t) return 'unknown';
        if (['overspeed','speeding','speed'].includes(t)) return 'speed';
        if (['geo_fence','geofence_enter','geofence_exit','geofence_breach','geofence'].includes(t)) return 'geofence';
        if (['safezone','safe-zone','safe_zone'].includes(t)) return 'safe_zone';
        if (['battery_low','lowbattery','low_battery'].includes(t)) return 'low_battery';
        if (['timezone','time-zone','time_zone'].includes(t)) return 'time_zone';
        if (['unauthorized','offline'].includes(t)) return 'offline';
        return t;
    }

    function getAlertMeta(type) {
        return ALERT_META[normalizeAlertType(type)] || ALERT_META.unknown;
    }

    /* ─────────────────────────────────────────────
       VEHICLE HELPERS
    ───────────────────────────────────────────── */
    function getVehicleLiveStatus(v) { return v?.live_status || {}; }

    function enrichVehicleLiveStatus(v) {
        if (!v) return v;
        const ls = { ...(v.live_status || {}) };
        const now = Date.now();
        const thresholdMs = parseInt(ls.offline_threshold_minutes || 10, 10) * 60 * 1000;
        const lastSeenMs =
            toMsSafe(ls.heart_time_ms) || toMsSafe(ls.datetime_ms) ||
            toMsSafe(ls.sys_time_ms)   || toMsSafe(v?.gps?.last_seen);
        const isOnline = lastSeenMs ? (now - lastSeenMs) < thresholdMs : false;

        if (!isOnline) {
            const offlineSinceMs = toMsSafe(ls.offline_since_ms) || lastSeenMs || now;
            ls.is_online = false; ls.is_moving = null;
            ls.ui_status = 'OFFLINE'; ls.movement_state = 'OFFLINE';
            ls.connectivity_state = 'OFFLINE'; ls.offline_since_ms = offlineSinceMs;
            ls.offline_since_seconds = Math.max(0, Math.floor((now - offlineSinceMs) / 1000));
            ls.offline_since_human = humanDurationFromSeconds(ls.offline_since_seconds);
        } else {
            ls.is_online = true; ls.offline_since_ms = null;
            ls.offline_since_seconds = null; ls.offline_since_human = null;
            if (ls.movement_state === 'STOPPED') {
                ls.ui_status = 'ONLINE_STOPPED'; ls.connectivity_state = 'ONLINE_STATIONARY'; ls.is_moving = false;
            } else if (ls.movement_state === 'MOVING') {
                ls.ui_status = 'ONLINE_MOVING'; ls.connectivity_state = 'ONLINE_MOVING'; ls.is_moving = true;
            } else {
                ls.ui_status = 'UNKNOWN'; ls.connectivity_state = 'UNKNOWN';
            }
        }

        if (ls.movement_state === 'STOPPED' && ls.stopped_since_ms) {
            const stoppedSinceMs = toMsSafe(ls.stopped_since_ms);
            if (stoppedSinceMs) {
                ls.stopped_since_seconds = Math.max(0, Math.floor((now - stoppedSinceMs) / 1000));
                ls.stopped_since_human = humanDurationFromSeconds(ls.stopped_since_seconds);
            }
        }

        return {
            ...v,
            gps: { ...(v.gps || {}), online: !!ls.is_online, state: ls.is_online ? 'ONLINE' : 'OFFLINE', last_seen: v?.gps?.last_seen ?? ls.heart_time ?? ls.datetime ?? ls.sys_time ?? '—' },
            live_status: ls,
        };
    }

    function isVehicleOnline(v) {
        const ls = getVehicleLiveStatus(v);
        if (typeof ls.is_online === 'boolean') return ls.is_online;
        return !!v?.gps?.online;
    }

    function isVehicleMoving(v) {
        const ls = getVehicleLiveStatus(v);
        if (typeof ls.is_moving === 'boolean') return ls.is_moving;
        return isVehicleOnline(v) && (parseInt(ls.speed ?? v.speed ?? 0, 10) > 0);
    }

    function getVehicleUiStatus(v) { return getVehicleLiveStatus(v).ui_status || 'UNKNOWN'; }
    function getVehicleSpeed(v) { return parseInt(getVehicleLiveStatus(v).speed ?? v?.speed ?? 0, 10) || 0; }

    function getVehicleStatusColor(v) {
        const ui = getVehicleUiStatus(v);
        if (ui === 'ONLINE_MOVING') return COLORS.moving;
        if (ui === 'ONLINE_STOPPED') return COLORS.idle;
        if (ui === 'OFFLINE') return COLORS.offline;
        if (isVehicleOnline(v)) return isVehicleMoving(v) ? COLORS.moving : COLORS.online;
        return COLORS.offline;
    }

    function getVehicleStatusText(v) {
        const ls = getVehicleLiveStatus(v);
        if (ls.ui_status === 'ONLINE_MOVING') return `${getVehicleSpeed(v)} km/h • En mouvement`;
        if (ls.ui_status === 'ONLINE_STOPPED') return ls.stopped_since_human ? `À l'arrêt depuis ${ls.stopped_since_human}` : 'À l\'arrêt';
        if (ls.ui_status === 'OFFLINE') return ls.offline_since_human ? `Offline depuis ${ls.offline_since_human}` : 'Offline';
        if (isVehicleOnline(v)) return 'En ligne';
        return 'Statut inconnu';
    }

    function getVehicleUpdatedAt(v) {
        const ls = getVehicleLiveStatus(v);
        return ls.heart_time ?? ls.sys_time ?? ls.datetime ?? v?.gps?.last_seen ?? v?.updated_at ?? '—';
    }

    function getVehicleHeading(v) {
        const explicit = Number(v?.direction);
        if (Number.isFinite(explicit)) return explicit;
        const cached = vehicleHeadingCache.get(String(v?.id));
        return Number.isFinite(cached) ? cached : 0;
    }

    function updateVehicleHeadingCache(nextFleet) {
        const prevById = new Map(vehicles.map(v => [String(v.id), v]));
        (Array.isArray(nextFleet) ? nextFleet : []).forEach(v => {
            const prev = prevById.get(String(v.id));
            const exp = Number(v?.direction);
            if (Number.isFinite(exp)) { vehicleHeadingCache.set(String(v.id), exp); return; }
            const lat1 = Number(prev?.lat), lng1 = Number(prev?.lon);
            const lat2 = Number(v?.lat),   lng2 = Number(v?.lon);
            if (Number.isFinite(lat1) && Number.isFinite(lng1) && Number.isFinite(lat2) && Number.isFinite(lng2) && (lat1 !== lat2 || lng1 !== lng2)) {
                vehicleHeadingCache.set(String(v.id), computeBearing(lat1, lng1, lat2, lng2));
            }
        });
    }

    function mergeFleetRealtime(nextFleet) {
        updateVehicleHeadingCache(nextFleet);
        return (Array.isArray(nextFleet) ? nextFleet : []).map(enrichVehicleLiveStatus);
    }

    function getAlertVehicleId(a) {
        if (!a) return null;
        const direct = a.vehicle_id ?? a.vehicule_id ?? a.car_id ?? null;
        if (direct != null && String(direct).trim() !== '') return String(direct);
        const nested = a.vehicle?.id ?? a.voiture?.id ?? null;
        if (nested != null && String(nested).trim() !== '') return String(nested);
        const imm = a.vehicle?.label ?? a.immatriculation ?? null;
        if (imm) {
            const found = vehicles.find(x => String(x.immatriculation || '').trim().toLowerCase() === String(imm).trim().toLowerCase());
            if (found?.id != null) return String(found.id);
        }
        return null;
    }

    /* ─────────────────────────────────────────────
       ALERT BRIEF POPUP
    ───────────────────────────────────────────── */
    function showAlertBrief(a) {
        currentAlertForBrief = a;
        const box = document.getElementById('alertFlashBrief');
        if (!box) return;

        const meta = getAlertMeta(a.type ?? a.alert_type);
        const userName = a.user?.full_name || a.driver || 'Non assigné';
        const userPhone = a.user?.phone || null;
        const callUrl = a.user?.call_url || (userPhone ? `tel:${String(userPhone).replace(/\s+/g, '')}` : null);
        const imm = a.vehicle?.label ?? a.immatriculation ?? 'Véhicule inconnu';

        const icon = document.getElementById('afbIcon');
        if (icon) { icon.style.background = `${meta.color}1A`; icon.style.color = meta.color; icon.innerHTML = `<i class="fas ${meta.icon}"></i>`; }

        const t = document.getElementById('afbTitle'); if (t) t.textContent = meta.label;
        const vh = document.getElementById('afbVeh');  if (vh) vh.textContent = imm;
        const dr = document.getElementById('afbDrv');  if (dr) dr.textContent = `👤 ${userName}`;
        const tm = document.getElementById('afbTime'); if (tm) tm.textContent = a.created_at ? `🕐 ${a.created_at}` : '';

        const callBtn = document.getElementById('afbCallBtn');
        if (callBtn) {
            if (callUrl) { callBtn.href = callUrl; callBtn.style.display = 'inline-flex'; }
            else { callBtn.href = 'javascript:void(0)'; callBtn.style.display = 'none'; }
        }

        box.classList.add('show');
        clearTimeout(alertBriefTimer);
        alertBriefTimer = setTimeout(() => box.classList.remove('show'), 15000);
    }

    window.closeAlertBrief = () => {
        clearTimeout(alertBriefTimer);
        document.getElementById('alertFlashBrief')?.classList.remove('show');
    };

    window.openAlertDetailFromBrief = () => {
        window.closeAlertBrief();
        if (currentAlertForBrief) openAlertDetail(currentAlertForBrief);
    };

    /* ─────────────────────────────────────────────
       ALERT REALTIME
    ───────────────────────────────────────────── */
    function hydrateSeenAlerts(list) {
        // Populate snapshotAlertIds (for list dedup) but NOT seenAlertIds.
        // seenAlertIds must stay empty until real SSE events arrive, otherwise
        // any alert already in the day snapshot will have its sound+modal silently
        // blocked by the guard in handleRealtimeNewAlert.
        (Array.isArray(list) ? list : []).forEach(a => {
            if (a?.id != null) snapshotAlertIds.add(String(a.id));
        });
    }

    function replaceAlertsFromSnapshot(list) {
        alerts = Array.isArray(list) ? list : [];
        hydrateSeenAlerts(alerts);
        renderAlertList();
        const b = document.getElementById('bAlerts');
        if (b) b.textContent = alerts.length;
    }

    function normalizeRealtimeAlert(payload) {
        if (!payload || payload.id == null) return null;
        const phone = payload.user?.phone || payload.phone || null;
        const fullName = payload.user?.full_name || payload.driver || payload.user_name || 'Non assigné';
        return {
            ...payload,
            id: Number(payload.id),
            type: payload.type ?? payload.alert_type ?? 'unknown',
            alert_type: payload.alert_type ?? payload.type ?? 'unknown',
            immatriculation: payload.immatriculation ?? payload.vehicle?.label ?? payload.vehicle_label ?? payload.vehicle_name ?? 'Véhicule inconnu',
            driver: fullName,
            user: { ...(payload.user || {}), full_name: fullName, phone, call_url: payload.user?.call_url || (phone ? `tel:${String(phone).replace(/\s+/g, '')}` : null) },
        };
    }

    function upsertAlertInMemory(alert) {
        if (!Array.isArray(alerts)) alerts = [];
        const key = String(alert.id);
        const idx = alerts.findIndex(a => String(a?.id ?? '') === key);
        if (idx >= 0) { alerts[idx] = { ...alerts[idx], ...alert }; }
        else { alerts.unshift(alert); }
        alerts = alerts.slice(0, 50);
    }

    function showRealtimeAlertModal(alert) {
        // Open alert detail panel immediately
        openAlertDetail(alert);

        // Auto-close after 15s if the user hasn't interacted with it
        clearTimeout(realtimeAlertModalTimer);
        realtimeAlertModalTimer = setTimeout(() => {
            const ad = document.getElementById('alertDetail');
            // Only auto-close if still showing the same alert
            if (currentAlert && String(currentAlert.id) === String(alert.id) && ad && ad.style.display !== 'none') {
                window.closeAlertDetail();
            }
        }, 15000);
    }

    function handleRealtimeNewAlert(payload) {
        const normalized = normalizeRealtimeAlert(payload);
        if (!normalized) return;
        const key = String(normalized.id);

        // Guard only against the same SSE event being fired twice in rapid
        // succession (e.g. duplicate push). We do NOT check snapshotAlertIds here —
        // an alert that was already in the day snapshot must still trigger
        // sound+modal when its alert.new SSE event arrives.
        if (seenAlertIds.has(key)) return;
        seenAlertIds.add(key);

        upsertAlertInMemory(normalized);
        renderAlertList();
        playAlertSoundForType(normalized.type);
        showAlertBrief(normalized);
        showRealtimeAlertModal(normalized);
    }

    /* ─────────────────────────────────────────────
       TABS / PANES
    ───────────────────────────────────────────── */
    function activeTabName() {
        if (document.getElementById('pane-trajets')?.classList.contains('active')) return 'trajets';
        if (document.getElementById('pane-alertes')?.classList.contains('active')) return 'alertes';
        return 'flotte';
    }

    window.switchTab = (t) => {
        ['flotte','trajets','alertes'].forEach(x => {
            document.getElementById('tab-' + x)?.classList.toggle('active', x === t);
            document.getElementById('pane-' + x)?.classList.toggle('active', x === t);
        });

        document.getElementById('topTripsKpis')?.classList.toggle('show', t === 'trajets');

        if (t !== 'trajets') {
            document.getElementById('tripModal')?.classList.remove('show');
            const rp = document.getElementById('tripReplay');
            if (rp) rp.style.display = 'none';
            window.replayPause();
        }

        if (t === 'trajets' && currentTrip) {
            if (tripPolyline)    tripPolyline.setMap(map);
            if (tripCursor)      tripCursor.setMap(map);
            if (tripStartMarker) tripStartMarker.setMap(map);
            if (tripEndMarker)   tripEndMarker.setMap(map);
            document.getElementById('tripModal')?.classList.add('show');
            if (replayPoints.length > 0) {
                const rp = document.getElementById('tripReplay');
                if (rp) rp.style.display = 'block';
            }
            if (currentTrip.bounds) map?.fitBounds(currentTrip.bounds, 60);
        } else {
            if (tripPolyline)    tripPolyline.setMap(null);
            if (tripCursor)      tripCursor.setMap(null);
            if (tripStartMarker) tripStartMarker.setMap(null);
            if (tripEndMarker)   tripEndMarker.setMap(null);
        }

        if (t !== 'flotte') document.getElementById('vehicleModal')?.classList.remove('show');
        if (t !== 'alertes') { const ad = document.getElementById('alertDetail'); if (ad) ad.style.display = 'none'; }

        const q = document.getElementById('q');
        if (q) q.value = '';
        document.getElementById('qClear')?.classList.remove('show');
        window.doSearch();

        if (t === 'flotte') { renderVehicleList(); updateSelectedVehicleIndicator(); }
        if (t === 'trajets') loadTrips();
        if (t === 'alertes') loadAlerts();
    };

    window.togglePaneFilters = (pane) => {
        if (pane === 'flotte') document.getElementById('vf')?.classList.toggle('show');
        if (pane === 'trajets') { document.getElementById('tQuick')?.classList.toggle('show'); document.getElementById('tf')?.classList.toggle('show'); }
        if (pane === 'alertes') { document.getElementById('aQuick')?.classList.toggle('show'); document.getElementById('af')?.classList.toggle('show'); }
    };

    window.doSearch = () => {
        const qEl = document.getElementById('q');
        const q = (qEl?.value || '').toLowerCase().trim();
        document.getElementById('qClear')?.classList.toggle('show', q.length > 0);
        const activePane = document.querySelector('.pane.active')?.id || 'pane-flotte';
        const sel = activePane === 'pane-flotte' ? '#vehList .item' : activePane === 'pane-trajets' ? '#tripList .item' : '#alertList .item';
        document.querySelectorAll(sel).forEach(el => {
            el.style.display = (!q || (el.dataset.s || '').includes(q)) ? '' : 'none';
        });
    };

    window.clearSearch = () => {
        const q = document.getElementById('q');
        if (q) q.value = '';
        document.getElementById('qClear')?.classList.remove('show');
        window.doSearch();
    };

    window.setMode = (tab, mode) => {
        viewMode[tab] = mode;
        ['simple','detailed'].forEach(m => document.getElementById(`mode-${tab}-${m}`)?.classList.toggle('active', m === mode));
        if (tab === 'flotte')  renderVehicleList();
        if (tab === 'trajets') renderTripList();
        if (tab === 'alertes') renderAlertList();
    };

    /* ─────────────────────────────────────────────
       DATE QUICK RANGES
    ───────────────────────────────────────────── */
    function rangeForQuick(q) {
        const now = new Date(); let from, to;
        if (q === 'today') { from = to = ymd(now); }
        else if (q === 'yesterday') { const d = new Date(now); d.setDate(d.getDate() - 1); from = to = ymd(d); }
        else if (q === 'this_week') { const d = new Date(now); const day = (d.getDay() + 6) % 7; const s = new Date(d); s.setDate(d.getDate() - day); const e = new Date(s); e.setDate(s.getDate() + 6); from = ymd(s); to = ymd(e); }
        else if (q === 'this_month') { from = ymd(new Date(now.getFullYear(), now.getMonth(), 1)); to = ymd(new Date(now.getFullYear(), now.getMonth() + 1, 0)); }
        else if (q === 'this_year') { from = ymd(new Date(now.getFullYear(), 0, 1)); to = ymd(new Date(now.getFullYear(), 11, 31)); }
        else { from = to = ymd(now); }
        return { from, to };
    }

    window.setTripsQuick = (el, q) => {
        document.querySelectorAll('#tQuick .qc').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        document.getElementById('tDateBox')?.classList.remove('show');
        const r = rangeForQuick(q);
        setDateRangeInputs('tFrom', 'tTo', r.from, r.to);
        tripsQuick = (q === 'this_year') ? 'range' : q;
        loadTrips();
    };

    window.setAlertsQuick = (el, q) => {
        document.querySelectorAll('#aQuick .qc').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        document.getElementById('aDateBox')?.classList.remove('show');
        const r = rangeForQuick(q);
        setDateRangeInputs('aFrom', 'aTo', r.from, r.to);
        alertsQuick = (q === 'this_year') ? 'range' : q;
        loadAlerts();
    };

    window.toggleTripsCustom = () => {
        const box = document.getElementById('tDateBox');
        if (!box) return;
        box.classList.toggle('show');
        if (box.classList.contains('show')) { tripsQuick = 'range'; loadTrips(); }
    };

    window.toggleAlertsCustom = () => {
        const box = document.getElementById('aDateBox');
        if (!box) return;
        box.classList.toggle('show');
        if (box.classList.contains('show')) { alertsQuick = 'range'; loadAlerts(); }
    };

    /* ─────────────────────────────────────────────
       VEHICLE LIST + FILTERS
    ───────────────────────────────────────────── */
    window.resetFleetFilters = () => {
        vehFilter = 'all';
        document.querySelectorAll('#vf .f').forEach(x => x.classList.remove('active'));
        document.querySelector('#vf .f[data-f="all"]')?.classList.add('active');
        renderVehicleList();
    };

    window.setVehFilter = (el, f) => {
        vehFilter = f;
        document.querySelectorAll('#vf .f').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        renderVehicleList();
    };

    function vehMatchesFilter(v) {
        const online = isVehicleOnline(v), moving = isVehicleMoving(v);
        if (vehFilter === 'all')     return true;
        if (vehFilter === 'moving')  return online && moving;
        if (vehFilter === 'idle')    return online && !moving;
        if (vehFilter === 'online')  return online;
        if (vehFilter === 'offline') return !online;
        return true;
    }

    function renderVehicleList() {
        const box = document.getElementById('vehList');
        if (!box) return;
        const list = vehicles.filter(vehMatchesFilter);
        if (!list.length) {
            box.innerHTML = `<div class="empty"><i class="fas fa-filter"></i><div style="margin-top:.6rem">Aucun résultat</div></div>`;
            return;
        }
        box.innerHTML = list.map((v, i) => {
            const uiStatus = getVehicleUiStatus(v);
            const spd = getVehicleSpeed(v);
            const dotC = getVehicleStatusColor(v);
            const dotA = uiStatus === 'ONLINE_MOVING' ? 'animation:pulse 1.5s infinite' : '';
            const statusText = getVehicleStatusText(v);
            const imm = esc(v.immatriculation || '—');
            const drv = esc(v.driver?.label ?? v.users ?? v.driver_label ?? 'Non associé');
            const brand = esc((`${v.marque || ''} ${v.model || ''}`).trim() || '—');
            const s = `${imm} ${drv} ${statusText}`.toLowerCase();
            const sel = (String(selectedVehicleId) === String(v.id)) ? ' sel' : '';

            if (viewMode.flotte === 'simple') {
                return `<div class="item${sel}" data-id="${v.id}" data-s="${s}"><div class="hrow"><div class="title">${imm}</div><div class="dot" style="background:${dotC};${dotA}"></div></div><div class="sub">👤 ${drv}</div><div class="sub">${esc(statusText)}</div></div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
            }

            let tagHtml = '';
            if (uiStatus === 'ONLINE_MOVING')
                tagHtml = `<span class="tag" style="background:rgba(22,163,74,.12);color:${COLORS.moving}"><span class="dot" style="background:${COLORS.moving};${dotA}"></span>${spd} km/h • En mouvement</span>`;
            else if (uiStatus === 'ONLINE_STOPPED')
                tagHtml = `<span class="tag" style="background:rgba(217,119,6,.12);color:${COLORS.idle}"><span class="dot" style="background:${COLORS.idle}"></span>${esc(statusText)}</span>`;
            else if (uiStatus === 'OFFLINE')
                tagHtml = `<span class="tag" style="background:rgba(107,114,128,.12);color:${COLORS.offline}"><span class="dot" style="background:${COLORS.offline}"></span>${esc(statusText)}</span>`;
            else if (isVehicleOnline(v))
                tagHtml = `<span class="tag" style="background:rgba(37,99,235,.12);color:${COLORS.online}"><span class="dot" style="background:${COLORS.online}"></span>En ligne</span>`;
            else
                tagHtml = `<span class="tag" style="background:rgba(107,114,128,.12);color:${COLORS.offline}"><span class="dot" style="background:${COLORS.offline}"></span>Statut inconnu</span>`;

            return `<div class="item${sel}" data-id="${v.id}" data-s="${s}"><div class="hrow"><div class="title">${imm}</div><div class="dot" style="background:${dotC};${dotA}"></div></div><div class="sub">${brand}</div><div class="sub">👤 ${drv}</div><div class="tags">${tagHtml}</div></div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
        }).join('');

        box.querySelectorAll('.item').forEach(el => {
            el.addEventListener('click', () => {
                box.querySelectorAll('.item').forEach(x => x.classList.remove('sel'));
                el.classList.add('sel');
                selectedVehicleId = parseInt(el.dataset.id, 10);
                followSelectedVehicle = true;
                updateFollowSelectedPill();
                focusVehicle(selectedVehicleId, true);
                openVehicleModal(selectedVehicleId);
                if (document.getElementById('pane-alertes')?.classList.contains('active')) loadAlerts();
                if (document.getElementById('pane-trajets')?.classList.contains('active')) loadTrips();
            });
        });
        window.doSearch();
    }

    function selectVehicleInList(id) {
        document.querySelectorAll('#vehList .item').forEach(x => x.classList.remove('sel'));
        document.querySelector(`#vehList .item[data-id="${id}"]`)?.classList.add('sel');
    }

    /* ─────────────────────────────────────────────
       VEHICLE MODAL
    ───────────────────────────────────────────── */
    function openVehicleModal(id) {
        const v = vehicles.find(x => String(x.id) === String(id));
        if (!v) return;
        const spd = getVehicleSpeed(v);
        const imm = v.immatriculation || '—';
        const brand = (`${v.marque || ''} ${v.model || ''}`).trim() || '—';
        const drv = v.driver?.label ?? v.users ?? v.driver_label ?? 'Non associé';
        const lat = v.lat != null ? parseFloat(v.lat).toFixed(5) : '—';
        const lng = v.lon != null ? parseFloat(v.lon).toFixed(5) : '—';

        const set = (elId, val) => { const el = document.getElementById(elId); if (el) el.textContent = val; };
        const setHTML = (elId, val) => { const el = document.getElementById(elId); if (el) el.innerHTML = val; };

        set('vmTitle', `Véhicule • ${imm}`); set('vmSub', brand);
        set('vmImmat', imm); set('vmBrand', brand); set('vmDriver', drv);
        set('vmSpeed', `${spd} km/h`);
        setHTML('vmStatus', `<span style="color:${getVehicleStatusColor(v)}">${esc(getVehicleStatusText(v))}</span>`);
        set('vmUpdated', getVehicleUpdatedAt(v));
        set('vmPos', lat !== '—' ? `${lat}, ${lng}` : '—');

        const modal = document.getElementById('vehicleModal');
        if (modal) { modal.dataset.vid = id; modal.classList.add('show'); }
    }

    window.closeVehicleModal = () => document.getElementById('vehicleModal')?.classList.remove('show');

    window.locateVehicleFromModal = () => {
        const id = document.getElementById('vehicleModal')?.dataset?.vid;
        if (id) focusVehicle(id, true);
    };

    /* ─────────────────────────────────────────────
       MAP INIT
    ───────────────────────────────────────────── */
    window.initFleetMap = function () {
        map = new google.maps.Map(document.getElementById('fleetMap'), {
            center: { lat: 4.0511, lng: 9.7679 },
            zoom: 7,
            disableDefaultUI: true,
            gestureHandling: 'greedy',
            mapTypeId: 'roadmap',
        });

        ensureSelectedVehicleIndicator();
        renderVehicleMarkers(true);
        updateFollowSelectedPill();
        startSSE();
        setTimeout(measureHeights, 250);
        map.addListener('click', () => document.getElementById('mapTypeMenu')?.classList.remove('show'));
        map.addListener('zoom_changed', () => updateSelectedVehicleIndicator());
        map.addListener('center_changed', () => updateSelectedVehicleIndicator());
        google.maps.event.addListenerOnce(map, 'idle', () => updateSelectedVehicleIndicator());
    };

    function loadGoogleMaps() {
        if (window.google?.maps) { window.initFleetMap(); return; }
        const s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initFleetMap';
        s.async = true; s.defer = true;
        document.head.appendChild(s);
    }

    function carIcon() {
        return { url: @json(asset('assets/icons/car_icon.png')), scaledSize: new google.maps.Size(34, 34), anchor: new google.maps.Point(17, 17) };
    }

    /* ─────────────────────────────────────────────
       MAP ZOOM (accessible from all tabs)
    ───────────────────────────────────────────── */
    window.zoomInMap  = () => { if (map) map.setZoom((map.getZoom() || 10) + 1); };
    window.zoomOutMap = () => { if (map) map.setZoom(Math.max(1, (map.getZoom() || 10) - 1)); };

    /* ─────────────────────────────────────────────
       SELECTED VEHICLE INDICATOR (overlay)
    ───────────────────────────────────────────── */
    function ensureSelectedVehicleIndicator() {
        if (!map || selectedVehicleIndicator) return;
        const el = document.createElement('div');
        el.className = 'selected-vehicle-indicator';
        el.style.cssText = 'position:absolute;width:30px;height:42px;display:none;pointer-events:none;transform:translate(-50%,-100%);z-index:1000';
        el.innerHTML = `<div style="position:absolute;left:50%;top:0;transform:translateX(-50%);animation:selectedVehicleBounce 1.1s ease-in-out infinite;display:flex;flex-direction:column;align-items:center;gap:2px"><div style="width:26px;height:26px;border-radius:9999px;background:rgba(17,24,39,.78);border:2px solid #fff;box-shadow:0 8px 24px rgba(0,0,0,.28);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;line-height:1"><i class="fas fa-location-arrow"></i></div><div style="width:0;height:0;border-left:7px solid transparent;border-right:7px solid transparent;border-top:10px solid rgba(17,24,39,.78);filter:drop-shadow(0 4px 8px rgba(0,0,0,.2))"></div></div>`;

        selectedVehicleIndicator = new google.maps.OverlayView();
        selectedVehicleIndicator.onAdd = function () { this.getPanes().overlayMouseTarget.appendChild(el); };
        selectedVehicleIndicator.draw = function () { updateSelectedVehicleIndicator(); };
        selectedVehicleIndicator.onRemove = function () { el.parentNode?.removeChild(el); };
        selectedVehicleIndicator.setMap(map);
        selectedVehicleIndicatorEl = el;
    }

    function updateSelectedVehicleIndicator() {
        if (!map || !selectedVehicleIndicatorEl || !selectedVehicleIndicator) return;
        if (!selectedVehicleId) { selectedVehicleIndicatorEl.style.display = 'none'; return; }
        const v = vehicles.find(x => String(x.id) === String(selectedVehicleId));
        if (!v || v.lat == null || v.lon == null) { selectedVehicleIndicatorEl.style.display = 'none'; return; }
        const projection = selectedVehicleIndicator.getProjection();
        if (!projection) { selectedVehicleIndicatorEl.style.display = 'none'; return; }
        const point = projection.fromLatLngToDivPixel(new google.maps.LatLng(parseFloat(v.lat), parseFloat(v.lon)));
        if (!point) { selectedVehicleIndicatorEl.style.display = 'none'; return; }
        selectedVehicleIndicatorEl.style.display = 'block';
        selectedVehicleIndicatorEl.style.left = `${point.x}px`;
        selectedVehicleIndicatorEl.style.top  = `${point.y - 24}px`;
        const color = getVehicleStatusColor(v);
        const bubble = selectedVehicleIndicatorEl.querySelector('div > div');
        const arrow  = bubble?.querySelector('i');
        if (bubble) bubble.style.boxShadow = `0 8px 24px rgba(0,0,0,.28), 0 0 0 3px ${color}33`;
        if (arrow)  { arrow.style.color = color; arrow.style.transform = `rotate(${getVehicleHeading(v)}deg)`; arrow.style.display = 'inline-block'; }
    }

    /* ─────────────────────────────────────────────
       FOLLOW PILL
    ───────────────────────────────────────────── */
    function updateFollowSelectedPill() {
        const pill = document.getElementById('followSelectedPill');
        const txt  = document.getElementById('followSelectedTxt');
        if (!pill || !txt) return;
        pill.style.display = selectedVehicleId ? 'inline-flex' : 'none';
        pill.classList.toggle('off', !followSelectedVehicle);
        txt.textContent = followSelectedVehicle ? 'Suivi véhicule : ON' : 'Suivi véhicule : OFF';
    }

    window.toggleSelectedVehicleFollow = () => {
        followSelectedVehicle = !followSelectedVehicle;
        updateFollowSelectedPill();
        if (followSelectedVehicle && selectedVehicleId && activeTabName() === 'flotte') focusVehicle(selectedVehicleId, false);
    };

    /* ─────────────────────────────────────────────
       MAP MARKERS
    ───────────────────────────────────────────── */
    function renderVehicleMarkers(fit) {
        if (!map) return;
        const bounds = new google.maps.LatLngBounds();
        const seen = new Set();
        vehicles.forEach(v => {
            if (v.lat == null || v.lon == null) return;
            const id = String(v.id);
            seen.add(id);
            const pos = { lat: parseFloat(v.lat), lng: parseFloat(v.lon) };
            let m = markers[id];
            if (!m) {
                m = new google.maps.Marker({ map, position: pos, title: v.immatriculation || '', icon: carIcon(), zIndex: 20 });
                m.addListener('click', () => {
                    selectedVehicleId = parseInt(v.id, 10);
                    followSelectedVehicle = true;
                    updateFollowSelectedPill();
                    selectVehicleInList(v.id);
                    focusVehicle(v.id, true);
                    openVehicleModal(v.id);
                    if (document.getElementById('pane-alertes')?.classList.contains('active')) loadAlerts();
                    if (document.getElementById('pane-trajets')?.classList.contains('active')) loadTrips();
                });
                markers[id] = m;
            } else { m.setPosition(pos); m.setIcon(carIcon()); }
            bounds.extend(pos);
        });
        Object.keys(markers).forEach(id => { if (!seen.has(id)) { markers[id].setMap(null); delete markers[id]; } });
        updateSelectedVehicleIndicator();
        if (fit && !bounds.isEmpty()) {
            map.fitBounds(bounds);
            const l = google.maps.event.addListener(map, 'idle', () => {
                if ((map.getZoom() || 0) > 14) map.setZoom(14);
                google.maps.event.removeListener(l);
                updateSelectedVehicleIndicator();
            });
        }
    }

    function focusVehicle(id, forceFollow = false) {
        // Do NOT recentre if user is currently in the trip tab — avoids breaking trip view
        if (activeTabName() === 'trajets') return;
        const m = markers[String(id)];
        if (!m || !map) return;
        if (forceFollow) { followSelectedVehicle = true; updateFollowSelectedPill(); }
        map.setCenter(m.getPosition());
        map.setZoom(15);
        updateSelectedVehicleIndicator();
    }

    window.toggleMapTypeMenu = () => document.getElementById('mapTypeMenu')?.classList.toggle('show');

    window.setMapType = (el, type) => {
        if (!map) return;
        map.setMapTypeId(type);
        document.querySelectorAll('#mapTypeMenu .it').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        document.getElementById('mapTypeMenu')?.classList.remove('show');
    };

    /* ─────────────────────────────────────────────
       SSE
    ───────────────────────────────────────────── */
    function sseState(state) {
        const dot = document.getElementById('sseDot');
        const txt = document.getElementById('sseTxt');
        const m = { connected:{ c:'#22c55e', t:'Connecté' }, connecting:{ c:'#eab308', t:'Connexion…' }, reconnecting:{ c:'#f97316', t:'Reconnexion…' }, paused:{ c:'#9ca3af', t:'En pause' } }[state] || { c:'#9ca3af', t:'—' };
        if (dot) dot.style.background = m.c;
        if (txt) txt.textContent = m.t;
    }

    function handleFleetUpdate(fleet) {
        vehicles = mergeFleetRealtime(fleet);
        renderVehicleList();
        renderVehicleMarkers(false);
        if (!selectedVehicleId) return;
        const still = vehicles.some(v => String(v.id) === String(selectedVehicleId));
        if (!still) {
            selectedVehicleId = null; updateFollowSelectedPill(); updateSelectedVehicleIndicator();
            document.getElementById('vehicleModal')?.classList.remove('show');
        } else {
            updateSelectedVehicleIndicator();
            if (document.getElementById('vehicleModal')?.classList.contains('show')) openVehicleModal(selectedVehicleId);
            // Only follow live vehicle when NOT in trip tab
            if (followSelectedVehicle && activeTabName() !== 'trajets') focusVehicle(selectedVehicleId, false);
        }
    }

    function startSSE() {
        clearTimeout(sseReconnectTimer);
        try {
            if (sse) { try { sse.close(); } catch (_) {} sse = null; }
            sse = new EventSource(R.stream, { withCredentials: true });
            sseState('connecting');

            sse.addEventListener('hello', () => { sseState('connected'); sseReconnectDelay = 2000; });

            // dashboard.init
            sse.addEventListener('dashboard.init', (e) => {
                sseState('connected'); sseReconnectDelay = 2000;
                let p; try { p = JSON.parse(e.data); } catch { return; }
                if (p.ts) { const el = document.getElementById('lastUp'); if (el) el.textContent = 'Maj: ' + p.ts; }
                updateStatsFromPayload(p.stats);
                if (Array.isArray(p.fleet)) handleFleetUpdate(p.fleet);
                if (Array.isArray(p.alerts)) replaceAlertsFromSnapshot(p.alerts);
            });

            // fleet.reset
            sse.addEventListener('fleet.reset', (e) => {
                sseState('connected');
                let p; try { p = JSON.parse(e.data); } catch { return; }
                if (Array.isArray(p.fleet)) handleFleetUpdate(p.fleet);
            });

            // vehicle.updated / vehicle_patch / vehicle.patch
            const handleVehiclePatch = (e) => {
                sseState('connected');
                let p; try { p = JSON.parse(e.data); } catch { return; }
                const vData = p?.vehicle ?? p;
                if (!vData?.id) return;
                const next = enrichVehicleLiveStatus(vData);
                const idx = vehicles.findIndex(v => String(v.id) === String(next.id));
                if (idx >= 0) vehicles[idx] = next; else vehicles.push(next);
                renderVehicleList(); renderVehicleMarkers(false);
                if (selectedVehicleId && String(selectedVehicleId) === String(next.id)) {
                    updateSelectedVehicleIndicator();
                    if (document.getElementById('vehicleModal')?.classList.contains('show')) openVehicleModal(selectedVehicleId);
                    if (followSelectedVehicle && activeTabName() !== 'trajets') focusVehicle(selectedVehicleId, false);
                }
            };
            sse.addEventListener('vehicle.updated', handleVehiclePatch);
            sse.addEventListener('vehicle_patch',   handleVehiclePatch);
            sse.addEventListener('vehicle.patch',   handleVehiclePatch);

            // alert.new / alert_new
            const handleAlertNew = (e) => {
                sseState('connected');
                let p; try { p = JSON.parse(e.data); } catch { return; }
                handleRealtimeNewAlert(p);
            };
            sse.addEventListener('alert.new',  handleAlertNew);
            sse.addEventListener('alert_new',  handleAlertNew);

            // alert.processed / alert_processed
            const handleAlertProcessed = (e) => {
                sseState('connected');
                let p; try { p = JSON.parse(e.data); } catch { return; }
                if (!p?.id) return;
                const idx = alerts.findIndex(a => String(a?.id) === String(p.id));
                if (idx >= 0) { alerts[idx] = { ...alerts[idx], ...p, is_processed: true, processed: true }; renderAlertList(); }
                if (currentAlert && String(currentAlert.id) === String(p.id)) window.closeAlertDetail();
            };
            sse.addEventListener('alert.processed',  handleAlertProcessed);
            sse.addEventListener('alert_processed',  handleAlertProcessed);

            // alerts.updated
            sse.addEventListener('alerts.updated', (e) => {
                sseState('connected');
                let p; try { p = JSON.parse(e.data); } catch { return; }
                if (Array.isArray(p.alerts)) replaceAlertsFromSnapshot(p.alerts);
            });

            // stats.updated
            sse.addEventListener('stats.updated', (e) => {
                sseState('connected');
                let p; try { p = JSON.parse(e.data); } catch { return; }
                updateStatsFromPayload(p.stats);
            });

            sse.onerror = () => {
                sseState('reconnecting');
                try { sse.close(); } catch (_) {}
                sse = null;
                sseReconnectTimer = setTimeout(() => {
                    sseReconnectDelay = Math.min(sseReconnectDelay * 1.5, 30000);
                    startSSE();
                }, sseReconnectDelay);
            };

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    try { sse?.close(); } catch (_) {}; sse = null; sseState('paused');
                } else if (!sse) { startSSE(); }
            });

            window.addEventListener('beforeunload', () => { try { sse?.close(); } catch (_) {} });

            // Unlock audio on first user gesture
            ['click','keydown','touchstart'].forEach(ev => {
                document.addEventListener(ev, unlockAlertAudio, { once: true, passive: true });
            });

        } catch (_) { sseState('paused'); }
    }

    function updateStatsFromPayload(stats) {
        if (!stats) return;
        const setN = (id, val) => { const el = document.getElementById(id); if (el && val != null) el.textContent = parseInt(val, 10); };
        setN('kUsers', stats.usersCount);
        setN('kVeh', stats.vehiclesCount);
        setN('bAlerts', stats.alertsCount);
        if (stats.alertsByType) {
            Object.entries(stats.alertsByType).forEach(([type, count]) => setN('kA_' + type, count));
        }
    }

    /* ─────────────────────────────────────────────
       TRIPS
    ───────────────────────────────────────────── */
    function setTopTripsKpis({ count = 0, dist = 0, durMin = 0, max = 0 }) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('tkCount', String(count));
        set('tkDist',  `${Number(dist || 0).toFixed(1)} km`);
        set('tkDur',   fmtMin(durMin || 0));
        set('tkMax',   `${Math.round(max || 0)} km/h`);
    }

    function computeTripAgg(list) {
        return {
            count: list.length,
            dist:   list.reduce((s, t) => s + (parseFloat(t.total_distance_km || 0) || 0), 0),
            durMin: list.reduce((s, t) => s + (parseInt(t.duration_minutes || 0, 10) || 0), 0),
            max:    list.reduce((m, t) => Math.max(m, parseFloat(t.max_speed_kmh || 0) || 0), 0),
        };
    }

    function filterTripsForUi(list) {
        let out = list.slice();
        if (tripFilter === 'active') out = out.filter(t => !(t.end_time || t.end_at));
        if (tripFilter === 'done')   out = out.filter(t => !!(t.end_time || t.end_at));
        return out;
    }

    window.setTripFilter = (el, f) => {
        tripFilter = f;
        document.querySelectorAll('#tf .f').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        renderTripList();
    };

    window.loadTrips = () => {
        const box = document.getElementById('tripList');
        if (box) box.innerHTML = `<div class="empty"><i class="fas fa-circle-notch fa-spin"></i><div style="margin-top:.6rem">Chargement…</div></div>`;
        const from = document.getElementById('tFrom')?.value || '';
        const to   = document.getElementById('tTo')?.value   || '';
        const url = new URL(R.trajetsList);
        url.searchParams.set('format', 'json');
        url.searchParams.set('per_page', '50');
        if (selectedVehicleId) url.searchParams.set('vehicle_id', String(selectedVehicleId));
        if (tripsQuick && tripsQuick !== 'range') { url.searchParams.set('quick', tripsQuick); }
        else { if (from) url.searchParams.set('start_date', from); if (to) url.searchParams.set('end_date', to); url.searchParams.set('quick', 'range'); }
        fetch(url.toString(), { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' } })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(json => { trips = json.data || []; renderTripList(); if (!currentTrip) setTopTripsKpis(computeTripAgg(filterTripsForUi(trips))); })
            .catch(() => { if (box) box.innerHTML = `<div class="empty"><i class="fas fa-exclamation-triangle"></i><div style="margin-top:.6rem">Erreur endpoint trajets</div></div>`; });
    };

    function renderTripList() {
        const box = document.getElementById('tripList');
        if (!box) return;
        const list = filterTripsForUi(trips);
        if (!currentTrip) setTopTripsKpis(computeTripAgg(list));
        if (!list.length) { box.innerHTML = `<div class="empty"><i class="fas fa-route"></i><div style="margin-top:.6rem">Aucun trajet</div></div>`; return; }
        box.innerHTML = list.map((t, i) => {
            const imm = esc(t.immatriculation || '—');
            const drv = esc(t.driver_label || '—');
            const isAct = !(t.end_time || t.end_at);
            const s = `${imm} ${drv}`.toLowerCase();
            const km = parseFloat(t.total_distance_km || 0).toFixed(1);
            const dur = fmtMin(t.duration_minutes || 0);
            const mx = Math.round(t.max_speed_kmh || 0);
            const isCurrent = currentTrip && String(currentTrip.id) === String(t.id);
            const dotStyle = `background:${isAct ? COLORS.moving : COLORS.offline};${isAct ? 'animation:pulse 1.5s infinite' : ''}`;
            if (viewMode.trajets === 'simple') {
                return `<div class="item${isCurrent?' sel':''}" data-s="${s}" data-trip="${t.id}" data-veh="${t.vehicle_id}"><div class="hrow"><div class="title">${imm}</div><div class="dot" style="${dotStyle}"></div></div><div class="sub">👤 ${drv}</div></div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
            }
            return `<div class="item${isCurrent?' sel':''}" data-s="${s}" data-trip="${t.id}" data-veh="${t.vehicle_id}"><div class="hrow"><div class="title">${imm}</div><div class="dot" style="${dotStyle}"></div></div><div class="sub">👤 ${drv}</div><div class="tags"><span class="tag" style="background:var(--color-primary-light);color:var(--color-primary)">📏 ${km} km</span><span class="tag" style="background:rgba(37,99,235,.12);color:#2563eb">⏱ ${dur}</span><span class="tag" style="background:rgba(245,158,11,.12);color:#d97706">🏁 ${mx} km/h</span></div></div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
        }).join('');
        box.querySelectorAll('.item').forEach(el => {
            el.addEventListener('click', async () => {
                const tripId = el.dataset.trip, vehId = el.dataset.veh;
                if (!tripId || !vehId) return;
                if (currentTrip && String(currentTrip.id) === String(tripId)) { document.getElementById('tripModal')?.classList.add('show'); return; }
                box.querySelectorAll('.item').forEach(x => x.classList.remove('sel'));
                el.classList.add('sel');
                await openTrip(vehId, tripId);
            });
        });
        window.doSearch();
    }

    async function openTrip(vehicleId, trajetId) {
        document.getElementById('tripModal')?.classList.remove('show');
        try {
            const res = await fetch(R.trajetDetail(vehicleId, trajetId), { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            const data = json?.data ?? json ?? {};
            const trajet = data?.trajet ?? data ?? {};
            const track = data?.track ?? {};
            const pointsRaw = Array.isArray(track?.points) ? track.points : Array.isArray(data?.points) ? data.points : [];
            const points = pointsRaw.map(p => ({
                lat: parseFloat(p.lat ?? p.latitude ?? NaN),
                lng: parseFloat(p.lng ?? p.longitude ?? p.lon ?? NaN),
                ts:  p.ts ?? p.time ?? p.created_at ?? '',
                spd: parseFloat(p.speed ?? p.vitesse ?? 0),
            })).filter(p => isFinite(p.lat) && isFinite(p.lng));
            if (!points.length) { toast('Aucun point GPS pour ce trajet', false); }
            const dist = Number(trajet.stats?.distance ?? trajet.total_distance_km ?? trajet.distance ?? 0);
            const dur  = Number(trajet.stats?.duration  ?? trajet.duration_minutes  ?? trajet.duration  ?? 0);
            const max  = Number(trajet.stats?.max_speed ?? trajet.max_speed_kmh     ?? trajet.max_speed  ?? 0);
            const ptCount = track?.count ?? points.length;
            currentTrip = { vehicle_id: Number(vehicleId), id: Number(trajetId), points, bounds: null };
            setTopTripsKpis({ count:1, dist, durMin:dur, max });
            const imm = esc(trajet.immatriculation ?? '');
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            set('tmTitle', imm ? `Trajet • ${imm}` : `Trajet #${trajetId}`);
            set('tmSub',   `${trajet.start_time ?? trajet.start_at ?? '—'} → ${trajet.end_time ?? trajet.end_at ?? '—'}`);
            set('tmDist',  `${Number(dist).toFixed(2)} km`);
            set('tmDur',   fmtMin(dur));
            set('tmMax',   `${Math.round(max)} km/h`);
            set('tmPts',   String(ptCount));
            set('tmStart', trajet.start_time ?? trajet.start_at ?? '—');
            set('tmEnd',   trajet.end_time   ?? trajet.end_at   ?? '—');
            document.getElementById('tripModal')?.classList.add('show');
            if (points.length >= 2) { drawTrip(points); toast('Trajet affiché sur la carte'); }
            else { toast('Trajet chargé (pas assez de points GPS)', false); }
        } catch (e) {
            console.error('[openTrip]', e);
            toast('Erreur lors du chargement du trajet', false);
            document.getElementById('tripModal')?.classList.add('show');
        }
    }

    window.closeTripModal = () => document.getElementById('tripModal')?.classList.remove('show');
    window.focusTrip = () => { if (!map || !currentTrip?.bounds) return; map.fitBounds(currentTrip.bounds, 60); };

    /* ─────────────────────────────────────────────
       REPLAY
    ───────────────────────────────────────────── */
    function drawTrip(points) {
        if (!map) return;
        tripPolyline?.setMap(null); tripCursor?.setMap(null);
        tripStartMarker?.setMap(null); tripEndMarker?.setMap(null);
        window.replayPause();
        const path = points.map(p => ({ lat: p.lat, lng: p.lng }));
        tripPolyline = new google.maps.Polyline({ map, path, geodesic:true, strokeColor:'#2563eb', strokeOpacity:.95, strokeWeight:5, zIndex:120 });
        const b = new google.maps.LatLngBounds();
        path.forEach(pt => b.extend(pt));
        if (currentTrip) currentTrip.bounds = b;
        map.fitBounds(b, 60);
        tripStartMarker = new google.maps.Marker({ map, position:path[0], title:'Départ', label:{ text:'D', color:'#ffffff', fontWeight:'700' }, zIndex:220 });
        tripEndMarker   = new google.maps.Marker({ map, position:path[path.length-1], title:'Arrivée', label:{ text:'A', color:'#ffffff', fontWeight:'700' }, zIndex:221 });
        tripCursor      = new google.maps.Marker({ map, position:path[0], title:'Replay', icon:carIcon(), zIndex:230 });
        replayPoints = points; replayIndex = 0; replaySpeedFactor = 1;
        updateSpeedUI(1);
        const rg = document.getElementById('rpRange');
        if (rg) { rg.min = 0; rg.max = Math.max(0, points.length - 1); rg.value = 0; }
        const meta = document.getElementById('rpMeta');
        if (meta) meta.textContent = `1/${points.length}`;
        const rp = document.getElementById('tripReplay');
        if (rp) rp.style.display = 'block';
    }

    function updateSpeedUI(spd) {
        const el = document.getElementById('rpSpeed');
        if (el) el.textContent = spd % 1 === 0 ? String(spd) : spd.toString();
        document.querySelectorAll('.speed-chip').forEach(c => c.classList.toggle('active-chip', parseFloat(c.dataset.spd) === spd));
    }

    function setReplayIndex(i) {
        i = Math.max(0, Math.min(replayPoints.length - 1, parseInt(i, 10) || 0));
        replayIndex = i;
        const p = replayPoints[replayIndex];
        tripCursor?.setPosition({ lat: p.lat, lng: p.lng });
        // replayFollow: fit to trip bounds, never pan to cursor only (avoids breaking zoom)
        if (replayFollow && map && currentTrip?.bounds) map.fitBounds(currentTrip.bounds, 60);
        const range = document.getElementById('rpRange');
        const meta  = document.getElementById('rpMeta');
        if (range) range.value = replayIndex;
        if (meta)  meta.textContent = `${replayIndex + 1}/${replayPoints.length}`;
    }

    window.replayPlay = () => {
        if (!replayPoints.length) return;
        clearInterval(replayTimer);
        replayTimer = setInterval(() => {
            if (replayIndex >= replayPoints.length - 1) { window.replayPause(); return; }
            setReplayIndex(replayIndex + 1);
        }, Math.max(30, 200 / replaySpeedFactor));
    };
    window.replayPause = () => { clearInterval(replayTimer); replayTimer = null; };
    window.replayStop  = () => { clearInterval(replayTimer); replayTimer = null; setReplayIndex(0); };
    window.replaySeek  = (v) => { window.replayPause(); setReplayIndex(v); };
    window.replaySetSpeed = (spd) => { replaySpeedFactor = spd; updateSpeedUI(spd); if (replayTimer) window.replayPlay(); };
    window.replayFaster = () => { const idx = SPEED_STEPS.indexOf(replaySpeedFactor); window.replaySetSpeed(SPEED_STEPS[Math.min(SPEED_STEPS.length - 1, idx < 0 ? 2 : idx + 1)]); };
    window.replaySlower = () => { const idx = SPEED_STEPS.indexOf(replaySpeedFactor); window.replaySetSpeed(SPEED_STEPS[Math.max(0, idx < 0 ? 2 : idx - 1)]); };

    window.toggleFollow = () => {
        replayFollow = !replayFollow;
        const btn = document.getElementById('rpFollow');
        if (btn) { btn.classList.toggle('active-btn', replayFollow); btn.textContent = replayFollow ? '🎯 Suivre' : '🎯 Libre'; }
    };

    window.closeReplay = () => {
        window.replayPause();
        const rp = document.getElementById('tripReplay');
        if (rp) rp.style.display = 'none';
        tripPolyline?.setMap(null); tripPolyline = null;
        tripCursor?.setMap(null);   tripCursor = null;
        tripStartMarker?.setMap(null); tripStartMarker = null;
        tripEndMarker?.setMap(null);   tripEndMarker = null;
        replayPoints = []; replayIndex = 0; currentTrip = null;
        document.getElementById('tripModal')?.classList.remove('show');
        if (document.getElementById('pane-trajets')?.classList.contains('active')) {
            setTopTripsKpis(computeTripAgg(filterTripsForUi(trips)));
            renderTripList();
        }
    };

    /* ─────────────────────────────────────────────
       ALERTS
    ───────────────────────────────────────────── */
    window.setAlertType = (el, t) => {
        alertType = t;
        document.querySelectorAll('#af .f').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        loadAlerts();
    };

    window.filterAlertsByType = (t) => {
        alertType = t;
        window.switchTab('alertes');
        document.querySelectorAll('#af .f').forEach(x => x.classList.toggle('active', x.dataset.at === t));
        loadAlerts();
    };

    window.loadAlerts = () => {
        const box = document.getElementById('alertList');
        if (box) box.innerHTML = `<div class="empty"><i class="fas fa-circle-notch fa-spin"></i><div style="margin-top:.6rem">Chargement…</div></div>`;
        const dFrom = document.getElementById('aFrom')?.value || '';
        const dTo   = document.getElementById('aTo')?.value   || '';
        const hFrom = document.getElementById('aHFrom')?.value || '';
        const hTo   = document.getElementById('aHTo')?.value   || '';
        const url = new URL(R.alertsDay);
        url.searchParams.set('per_page', '50');
        if (alertsQuick && alertsQuick !== 'range') { url.searchParams.set('quick', alertsQuick); }
        else { if (dFrom) url.searchParams.set('date_from', dFrom); if (dTo) url.searchParams.set('date_to', dTo); if (hFrom) url.searchParams.set('hour_from', hFrom); if (hTo) url.searchParams.set('hour_to', hTo); url.searchParams.set('quick', 'range'); }
        if (selectedVehicleId) url.searchParams.set('vehicle_id', String(selectedVehicleId));
        if (alertType !== 'all' && ALLOWED_ALERT_TYPES.has(alertType)) url.searchParams.set('alert_type', alertType);
        fetch(url.toString(), { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' } })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(json => {
                replaceAlertsFromSnapshot(json.data || []);
                const by = json.stats?.by_type || {};
                Object.entries(by).forEach(([type, count]) => { const el = document.getElementById('kA_' + type); if (el) el.textContent = count; });
                const b = document.getElementById('bAlerts');
                if (b) b.textContent = json.meta?.total ?? alerts.length;
            })
            .catch(() => { if (box) box.innerHTML = `<div class="empty"><i class="fas fa-exclamation-triangle"></i><div style="margin-top:.6rem">Erreur endpoint alertes</div></div>`; });
    };

    function renderAlertList() {
        const box = document.getElementById('alertList');
        if (!box) return;
        if (!alerts.length) { box.innerHTML = `<div class="empty"><i class="fas fa-bell-slash"></i><div style="margin-top:.6rem">Aucune alerte</div></div>`; return; }
        const simple = viewMode.alertes === 'simple';
        box.innerHTML = alerts.map((a, i) => {
            const type = normalizeAlertType(a.type ?? a.alert_type);
            const meta = getAlertMeta(type);
            const imm  = esc(a.vehicle?.label ?? a.immatriculation ?? '—');
            const s    = `${imm} ${meta.label} ${type} ${a.message ?? ''} ${a.description ?? ''}`.toLowerCase();
            const tss  = esc(a.created_at ?? '—');
            const processed = !!(a.is_processed ?? a.processed);
            if (simple) {
                return `<div class="item" data-s="${s}" data-id="${a.id}"><div class="hrow"><div class="title">${imm}</div><div class="dot" style="background:${meta.color};animation:pulse 1.2s infinite"></div></div><div class="sub"><i class="fas ${meta.icon}"></i> ${esc(meta.label)}</div></div>${i < alerts.length - 1 ? '<div class="sep"></div>' : ''}`;
            }
            const badgeP = processed ? `<span class="tag" style="background:rgba(37,99,235,.12);color:#2563eb">Traitée</span>` : `<span class="tag" style="background:rgba(220,38,38,.12);color:#dc2626">Ouverte</span>`;
            return `<div class="item" data-s="${s}" data-id="${a.id}"><div class="hrow"><div class="title">${imm}</div><div class="dot" style="background:${meta.color};animation:pulse 1.2s infinite"></div></div><div class="sub"><i class="fas ${meta.icon}" style="color:${meta.color}"></i> ${esc(meta.label)} • <span style="font-family:var(--font-mono,monospace)">${tss}</span></div><div class="sub" style="margin-top:.25rem">${esc(a.message ?? a.description ?? a.location ?? '—')}</div><div class="tags"><span class="tag" style="background:${meta.color}1A;color:${meta.color}">${esc(type)}</span>${badgeP}</div></div>${i < alerts.length - 1 ? '<div class="sep"></div>' : ''}`;
        }).join('');
        box.querySelectorAll('.item').forEach(el => {
            el.addEventListener('click', () => {
                box.querySelectorAll('.item').forEach(x => x.classList.remove('sel'));
                el.classList.add('sel');
                const a = alerts.find(x => String(x.id) === String(el.dataset.id));
                if (a) openAlertDetail(a);
            });
        });
        window.doSearch();
    }

    /* ─────────────────────────────────────────────
       ALERT DETAIL PANEL
    ───────────────────────────────────────────── */
    function openAlertDetail(a) {
        const type = normalizeAlertType(a.type ?? a.alert_type);
        const meta = getAlertMeta(type);
        const imm  = a.vehicle?.label ?? a.immatriculation ?? '—';
        const userName  = a.user?.full_name || a.driver || 'Non assigné';
        const userPhone = a.user?.phone || null;
        const callUrl   = a.user?.call_url || (userPhone ? `tel:${String(userPhone).replace(/\s+/g, '')}` : null);

        const adTitle = document.getElementById('adTitle');
        const adVeh   = document.getElementById('adVeh');
        const adMeta  = document.getElementById('adMeta');
        const adUserCard  = document.getElementById('adUserCard');
        const adUserName  = document.getElementById('adUserName');
        const adUserPhone = document.getElementById('adUserPhone');
        const adCallBtn   = document.getElementById('adCallBtn');
        const adProcessCallBtn = document.getElementById('adProcessCallBtn');
        const ad = document.getElementById('alertDetail');

        if (adTitle) adTitle.innerHTML = `<i class="fas ${meta.icon}" style="color:${meta.color}"></i> ${esc(meta.label)}`;
        if (adVeh) adVeh.textContent = imm;

        // Compact meta grid: type / vehicle / driver / phone / time
        if (adMeta) {
            const cards = [
                { label:'Type',     value: meta.label },
                { label:'Véhicule', value: imm },
                { label:'Chauffeur',value: userName },
                { label:'Heure',    value: a.created_at || '—' },
            ];
            adMeta.innerHTML = cards.map(c => `
                <div style="border:1px solid var(--color-border-subtle);border-radius:12px;padding:.55rem .65rem;background:rgba(0,0,0,.02)">
                    <div style="font-family:var(--font-display);font-size:.58rem;font-weight:900;color:var(--color-secondary-text,#8b949e);margin-bottom:.18rem;text-transform:uppercase;letter-spacing:.05em">${esc(c.label)}</div>
                    <div style="font-size:.74rem;font-weight:800;color:var(--color-text)">${esc(c.value || '—')}</div>
                </div>`).join('');
        }

        // Driver card
        if (adUserCard) {
            adUserCard.style.display = 'block';
            if (adUserName)  adUserName.textContent  = userName;
            if (adUserPhone) adUserPhone.textContent = userPhone || 'Téléphone indisponible';
        }
        if (adCallBtn) {
            if (callUrl) { adCallBtn.href = callUrl; adCallBtn.style.display = 'inline-flex'; adCallBtn.textContent = '📞 Appeler'; }
            else { adCallBtn.href = 'javascript:void(0)'; adCallBtn.style.display = 'none'; }
        }
        if (adProcessCallBtn) {
            if (callUrl) { adProcessCallBtn.href = callUrl; adProcessCallBtn.style.display = 'inline-flex'; }
            else { adProcessCallBtn.href = 'javascript:void(0)'; adProcessCallBtn.style.display = 'none'; }
        }

        currentAlert = a;

        // Reset report field
        const report = document.getElementById('adReport');
        const err    = document.getElementById('adReportError');
        if (report) report.value = '';
        if (err)    err.style.display = 'none';

        if (ad) ad.style.display = 'block';

        // If alert has coords, pan map (but do not break trip view)
        if (a.lat && a.lng && map && activeTabName() !== 'trajets') {
            map.setCenter({ lat: parseFloat(a.lat), lng: parseFloat(a.lng) });
            if ((map.getZoom() || 0) < 14) map.setZoom(14);
        }
    }

    window.closeAlertDetail = () => {
        const ad = document.getElementById('alertDetail');
        if (ad) ad.style.display = 'none';
        document.querySelectorAll('#alertList .item').forEach(x => x.classList.remove('sel'));
        clearTimeout(realtimeAlertModalTimer);
        currentAlert = null;
        const report = document.getElementById('adReport');
        const err    = document.getElementById('adReportError');
        if (report) report.value = '';
        if (err)    err.style.display = 'none';
    };

    window.markAlertProcessed = async () => {
        if (!currentAlert?.id) return;
        const report = document.getElementById('adReport');
        const err    = document.getElementById('adReportError');
        const commentaire = String(report?.value || '').trim();
        if (!commentaire) {
            if (err) err.style.display = 'block';
            if (report) report.focus();
            toast('Le rapport est obligatoire', false);
            return;
        }
        if (err) err.style.display = 'none';
        try {
            const res = await fetch(R.processAlert(currentAlert.id), {
                method: 'PATCH',
                headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ commentaire }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            toast('Alerte traitée');
            await loadAlerts();
            window.closeAlertDetail();
        } catch (e) {
            console.error('[markAlertProcessed]', e);
            toast('Erreur lors du traitement', false);
        }
    };

    window.locateAlert = () => {
        if (!currentAlert) return;
        const vehicleId = getAlertVehicleId(currentAlert);
        if (vehicleId) {
            selectedVehicleId = parseInt(vehicleId, 10);
            followSelectedVehicle = true;
            updateFollowSelectedPill();
            window.switchTab('flotte');
            requestAnimationFrame(() => {
                renderVehicleList(); selectVehicleInList(vehicleId);
                focusVehicle(vehicleId, true); openVehicleModal(vehicleId);
                window.closeAlertDetail(); toast('Véhicule localisé');
            });
            return;
        }
        if (currentAlert?.lat && currentAlert?.lng && map) {
            window.switchTab('flotte');
            map.setCenter({ lat: parseFloat(currentAlert.lat), lng: parseFloat(currentAlert.lng) });
            map.setZoom(16);
            window.closeAlertDetail(); toast('Position de l\'alerte localisée');
            return;
        }
        toast('Impossible de localiser ce véhicule', false);
    };

    /* ─────────────────────────────────────────────
       AUTO-FILTERS BIND
    ───────────────────────────────────────────── */
    function bindAutoFilters() {
        const loadTripsDeb  = debounce(() => loadTrips(), 250);
        const loadAlertsDeb = debounce(() => loadAlerts(), 250);
        ['tFrom','tTo'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => { tripsQuick = 'range'; document.getElementById('tDateBox')?.classList.add('show'); loadTripsDeb(); });
        });
        ['aFrom','aTo','aHFrom','aHTo'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => { alertsQuick = 'range'; document.getElementById('aDateBox')?.classList.add('show'); loadAlertsDeb(); });
        });
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('mapTypeMenu');
            const ctrl = document.getElementById('mapTypeCtrl');
            if (menu && ctrl && !ctrl.contains(e.target)) menu.classList.remove('show');
        });
    }

    function initDates() {
        const today = new Date().toISOString().slice(0, 10);
        ['tFrom','tTo','aFrom','aTo'].forEach(id => { const el = document.getElementById(id); if (el) el.value = today; });
        ['aHFrom','aHTo'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        ['vf','tQuick','tf','tDateBox','aQuick','af','aDateBox'].forEach(id => document.getElementById(id)?.classList.remove('show'));
    }

    /* ─────────────────────────────────────────────
       LIVE TICKER (1s) — only when in fleet tab
    ───────────────────────────────────────────── */
    setInterval(() => {
        if (!vehicles.length) return;
        vehicles = vehicles.map(enrichVehicleLiveStatus);
        const inFleet = activeTabName() === 'flotte';
        if (inFleet) renderVehicleList();
        if (selectedVehicleId) {
            updateSelectedVehicleIndicator();
            if (inFleet && document.getElementById('vehicleModal')?.classList.contains('show')) openVehicleModal(selectedVehicleId);
            if (followSelectedVehicle && inFleet && map && markers[String(selectedVehicleId)]) focusVehicle(selectedVehicleId, false);
        }
    }, 1000);






    /* ─────────────────────────────────────────────
       INIT
    ───────────────────────────────────────────── */
    window.addEventListener('resize', () => { measureHeights(); updateSelectedVehicleIndicator(); });

    document.addEventListener('DOMContentLoaded', () => {
        vehicles = mergeFleetRealtime(vehicles);
        hydrateSeenAlerts(alerts);
        initDates();
        bindAutoFilters();
        renderVehicleList();
        updateFollowSelectedPill();
        loadGoogleMaps();
        measureHeights();
        loadAlerts();
    });

    /* Expose fonctions internes sur window
       — nécessaire car tout est dans une IIFE isolée.
       Sans ça, handleRealtimeNewAlert est inaccessible
       depuis la console et depuis tout appel externe. */
    window.handleRealtimeNewAlert  = handleRealtimeNewAlert;
    window.showAlertBrief          = showAlertBrief;
    window.showRealtimeAlertModal  = showRealtimeAlertModal;
    window.playAlertSoundForType   = playAlertSoundForType;
    window.openAlertDetail         = openAlertDetail;

})();
</script>
@endpush