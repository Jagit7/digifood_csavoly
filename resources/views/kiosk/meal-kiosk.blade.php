<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Beléptető kioszk</title>
    <link rel="stylesheet" href="{{ asset('dashboard/icons/fontawesome/css/all.min.css') }}">
    <style>
        :root {
            --navy-950: #060d18;
            --navy-900: #0a1729;
            --navy-800: #102544;
            --navy-700: #163a63;
            --navy-600: #1d4f82;
            --orange-400: #ffb35c;
            --orange-500: #ff8a1f;
            --orange-600: #f2720a;
            --orange-700: #d9600a;
            --orange-glow: rgba(255, 138, 31, 0.38);
            --cream: #fdf9f2;
            --cream-soft: #f4ede1;
            --line: #e6dfd0;
            --ink: #16212f;
            --ink-soft: #5b6577;
            --success: #1f9d55;
            --danger: #d9362c;
            --warning: #e08a1e;
            --review: #c2760a;
            --dietary: #b42318;
            --shadow-shell: 0 26px 60px rgba(4, 9, 18, 0.55);
            --shadow-card: 0 16px 34px rgba(20, 32, 47, 0.12);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            height: 100%;
            font-family: "Segoe UI", "Inter", Tahoma, sans-serif;
            color: var(--ink);
            overflow: hidden;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 15% -8%, rgba(255, 138, 31, 0.24), transparent 42%),
                radial-gradient(circle at 88% 4%, rgba(29, 79, 130, 0.55), transparent 55%),
                linear-gradient(180deg, var(--navy-950) 0%, var(--navy-900) 45%, #04070d 100%);
        }

        .kiosk-shell {
            height: 100vh;
            padding: 20px;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 18px;
        }

        /* Topbar - dark brand chrome, echoes the logo's navy + glowing orange.
           Stacked into two rows so it stays readable on a narrow portrait screen. */
        .topbar {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(16, 37, 68, 0.94), rgba(8, 16, 30, 0.96));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 26px;
            box-shadow: var(--shadow-shell), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 16px 20px;
        }

        .topbar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .topbar-row + .topbar-row {
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.09);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
            flex: 1 1 auto;
        }

        .brand-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 168px;
            padding: 10px 14px;
            border-radius: 18px;
            background: linear-gradient(160deg, #ffffff, #f2ece0);
            box-shadow: 0 12px 26px rgba(255, 138, 31, 0.22), 0 10px 22px rgba(0, 0, 0, 0.3);
            flex: 0 0 auto;
        }

        .logo {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.12));
        }

        .brand-copy {
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .institution-name {
            font-size: clamp(21px, 2.2vw, 31px);
            line-height: 1.12;
            font-weight: 800;
            color: #fbf7f0;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }

        .meta-label {
            font-size: 13px;
            line-height: 1.1;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--orange-500);
            margin-top: 6px;
            margin-bottom: 0;
        }

        .meta-value {
            font-size: 22px;
            line-height: 1.1;
            font-weight: 800;
            color: #fff;
        }

        .meta-value.is-time {
            font-size: 27px;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.02em;
        }

        .topbar-tools {
            justify-content: flex-start;
        }

        .topbar-tools .toolbar:last-child {
            margin-left: auto;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 46px;
            padding: 8px 14px;
            border-radius: 14px;
            border: 1px solid rgba(31, 157, 85, 0.4);
            background: rgba(31, 157, 85, 0.16);
            font-size: 16px;
            font-weight: 800;
            color: #eafff0;
            white-space: nowrap;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 4px rgba(31, 157, 85, 0.22);
            flex: 0 0 auto;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 0 0 auto;
        }

        .select,
        .button,
        .pin-input {
            min-height: 46px;
            border-radius: 14px;
            border: 2px solid var(--line);
            background: var(--cream);
            color: var(--ink);
            padding: 8px 14px;
            font-size: 16px;
            font-weight: 800;
            font-family: inherit;
        }

        .select {
            min-width: 168px;
        }

        .button {
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        /* Topbar controls sit on dark chrome, so they get a translucent glass look */
        .topbar .select,
        .topbar .button {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.16);
            color: #fff;
        }

        .topbar .select:focus,
        .topbar .button:hover {
            border-color: var(--orange-500);
        }

        .topbar .select option {
            color: #16212f;
        }

        #toggle-sound {
            background: rgba(255, 138, 31, 0.14);
            border-color: rgba(255, 138, 31, 0.4);
        }

        /* Content lists - A and B menus stack on top of each other for portrait screens */
        .lists {
            min-height: 0;
            display: grid;
            grid-template-columns: 1fr;
            grid-template-rows: minmax(0, 1fr);
            gap: 18px;
        }

        .lists.is-ab {
            grid-template-rows: repeat(2, minmax(0, 1fr));
        }

        .panel {
            min-height: 0;
            background: linear-gradient(180deg, var(--cream) 0%, var(--cream-soft) 100%);
            border: 1px solid var(--line);
            border-top: 6px solid #c9c3b8;
            border-radius: 26px;
            box-shadow: var(--shadow-card);
            padding: 16px 18px 18px;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            align-content: start;
            gap: 12px;
        }

        .panel:has(.panel-title.is-a) { border-top-color: var(--navy-700); }
        .panel:has(.panel-title.is-b) { border-top-color: var(--orange-600); }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border-radius: 16px;
            background: rgba(20, 32, 47, 0.04);
        }

        .panel-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: clamp(21px, 2.4vw, 28px);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: var(--ink);
        }

        .panel-title::before {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            color: #fff;
            font-size: 18px;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .panel-title.is-a::before {
            content: "A";
            background: linear-gradient(135deg, var(--navy-600), var(--navy-800));
            box-shadow: 0 8px 16px rgba(16, 37, 68, 0.35);
        }

        .panel-title.is-b::before {
            content: "B";
            background: linear-gradient(135deg, var(--orange-500), var(--orange-700));
            box-shadow: 0 8px 16px rgba(217, 96, 10, 0.35);
        }

        .panel-subtitle {
            font-size: 12.5px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--ink-soft);
            background: rgba(20, 32, 47, 0.07);
            padding: 6px 12px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .list {
            min-height: 0;
            display: grid;
            gap: 10px;
            align-content: start;
            align-items: start;
            overflow-y: auto;
            padding-right: 4px;
            scrollbar-width: thin;
            scrollbar-color: var(--line) transparent;
        }

        .list::-webkit-scrollbar {
            width: 8px;
        }

        .list::-webkit-scrollbar-thumb {
            background: var(--line);
            border-radius: 8px;
        }

        .list.is-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-auto-rows: max-content;
        }

        .list.is-column {
            grid-template-columns: 1fr;
            grid-auto-rows: max-content;
        }

        .entry {
            min-width: 0;
            min-height: 108px;
            background: #ffffff;
            border: 1px solid #e4ded2;
            border-left: 9px solid #c9c3b8;
            border-radius: 18px;
            padding: 12px 14px;
            display: grid;
            grid-template-rows: auto auto auto;
            align-content: start;
            gap: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(20, 32, 47, 0.05);
        }

        .entry.is-success { border-left-color: var(--success); }
        .entry.is-rejected { border-left-color: var(--danger); }
        .entry.is-duplicate { border-left-color: var(--warning); }
        .entry.is-review { border-left-color: var(--review); }
        .entry.is-dietary {
            border-top: 3px solid #f3b3ae;
            box-shadow: inset 0 0 0 1px rgba(180, 35, 24, 0.12);
        }

        .entry.is-fresh {
            border-color: var(--orange-500);
            animation: freshPulse 1.6s ease-in-out infinite;
        }

        @keyframes freshPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 138, 31, 0.5), 0 4px 12px rgba(20, 32, 47, 0.05); }
            50% { box-shadow: 0 0 0 9px rgba(255, 138, 31, 0), 0 4px 12px rgba(20, 32, 47, 0.05); }
        }

        .entry-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .status-main {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .status-icon {
            font-size: 26px;
            flex: 0 0 auto;
        }

        .entry.is-success .status-icon,
        .entry.is-success .status-title {
            color: var(--success);
        }

        .entry.is-rejected .status-icon,
        .entry.is-rejected .status-title {
            color: var(--danger);
        }

        .entry.is-duplicate .status-icon,
        .entry.is-duplicate .status-title {
            color: var(--warning);
        }

        .entry.is-review .status-icon,
        .entry.is-review .status-title {
            color: var(--review);
        }

        .status-title {
            font-size: 19px;
            line-height: 1.05;
            font-weight: 900;
        }

        .entry-time {
            font-size: 17px;
            line-height: 1;
            font-weight: 900;
            color: var(--ink-soft);
            flex: 0 0 auto;
        }

        .entry-name {
            font-size: 26px;
            line-height: 1.05;
            font-weight: 900;
            color: var(--ink);
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .entry-meta {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            align-content: flex-start;
            gap: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: auto;
            width: auto;
            min-height: 0;
            flex: 0 0 auto;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid #d8d1c3;
            background: #fff;
            font-size: 15px;
            line-height: 1.1;
            font-weight: 800;
            color: var(--ink);
            white-space: nowrap;
        }

        .badge.is-group {
            background: #f4f2ec;
        }

        .badge.is-meal {
            background: #eef5ff;
            border-color: #b8d0ff;
        }

        .badge.is-menu-a {
            background: #e8f0ff;
            border-color: #9db8e6;
            color: var(--navy-700);
        }

        .badge.is-menu-b {
            background: #fff1e2;
            border-color: #f2c48f;
            color: var(--orange-700);
        }

        .badge.is-dietary {
            background: #fff0ef;
            border: 2px solid #e78f89;
            color: var(--dietary);
        }

        .badge.is-alert {
            background: #fff5f5;
            border-color: #efb1b1;
            color: #9f1d1d;
        }

        .empty-state {
            min-height: 0;
            border: 2px dashed var(--line);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            text-align: center;
            font-size: 22px;
            font-weight: 800;
            color: var(--ink-soft);
            background: rgba(20, 32, 47, 0.03);
        }

        .kiosk-input {
            position: absolute;
            opacity: 0.01;
            pointer-events: none;
        }

        .lock-modal {
            position: fixed;
            inset: 0;
            background: rgba(4, 9, 18, 0.55);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .lock-modal.is-open {
            display: flex;
        }

        .lock-card {
            width: min(520px, 100%);
            padding: 30px;
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: 26px;
            box-shadow: var(--shadow-shell);
            border-top: 6px solid var(--orange-600);
        }

        .lock-card h2 {
            margin: 0 0 10px;
            font-size: 32px;
            font-weight: 900;
            color: var(--ink);
        }

        .lock-card p {
            margin: 0 0 16px;
            font-size: 17px;
            font-weight: 700;
            color: var(--ink-soft);
        }

        .lock-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .lock-actions .button[type="submit"] {
            background: linear-gradient(135deg, var(--orange-500), var(--orange-700));
            border-color: transparent;
            color: #fff;
        }

        /* A vezérlő kártyával beolvasott zárolás - a teljes kioszkot
           eltakarja, amíg újra be nem olvassák a vezérlő kártyát. A
           beolvasó mező (barcode-input) eközben is fókuszban marad, hogy
           az aktiváló beolvasás megérkezhessen. */
        .lock-overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            background:
                radial-gradient(circle at 15% -8%, rgba(255, 138, 31, 0.18), transparent 42%),
                linear-gradient(180deg, var(--navy-950) 0%, var(--navy-900) 55%, #04070d 100%);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .lock-overlay.is-open {
            display: flex;
        }

        .lock-overlay-card {
            text-align: center;
            color: #fbf7f0;
            max-width: 520px;
        }

        .lock-overlay-icon {
            font-size: 64px;
            color: var(--orange-500);
            margin-bottom: 18px;
        }

        .lock-overlay-card h2 {
            margin: 0 0 10px;
            font-size: 34px;
            font-weight: 900;
        }

        .lock-overlay-card p {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.75);
        }
    </style>
</head>
<body>
<div class="kiosk-shell">
    <div class="topbar">
        <div class="topbar-row">
            <div class="brand">
                <div class="brand-mark">
                    <img src="{{ asset('/home/logo.png') }}" alt="DigiFood" class="logo">
                </div>
                <div class="brand-copy">
                    <div class="institution-name">{{ $institution->name }}</div>
                    <div class="meta-label">KONYHAI KIOSZK</div>
                </div>
            </div>

            <div class="meta-value is-time" id="clock"></div>
        </div>

        <div class="topbar-row topbar-tools">
            <span id="selected-meal-type-label" style="display:none;">{{ $sessionPayload['selected_meal_type'] ?? 'Nincs kiválasztva' }}</span>

            <div class="toolbar">
                <select id="meal-type" class="select">
                    <option value="">Válassz étkezéstípust</option>
                    @foreach($mealTypes as $mealType)
                        <option value="{{ $mealType->id }}" @selected($selectedMealTypeId === $mealType->id)>{{ $mealType->mealType->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="status-pill">
                <span class="status-dot" id="online-dot"></span>
                <span id="online-label">Kioszk aktív</span>
            </div>

            <div class="toolbar">
                <button type="button" class="button" id="toggle-sound">Hang: be</button>
                <button type="button" class="button" id="open-lock">Kioszk lezárása</button>
            </div>
        </div>
    </div>

    <div class="lists {{ $sessionPayload['ab_mode'] ? 'is-ab' : '' }}" id="lists"></div>
</div>

<form method="POST" action="{{ route('kiosk.lock') }}" class="lock-modal" id="lock-modal">
    @csrf
    <div class="lock-card">
        <h2>Kioszk lezárása</h2>
        <p>Add meg az admin PIN-kódot a kijelentkezéshez.</p>
        <input type="password" name="admin_pin" class="pin-input" placeholder="PIN-kód" inputmode="numeric" required>
        <div class="lock-actions">
            <button type="submit" class="button">Lezárás</button>
            <button type="button" class="button" id="close-lock">Mégsem</button>
        </div>
    </div>
</form>

<div class="lock-overlay" id="lock-overlay">
    <div class="lock-overlay-card">
        <i class="fas fa-lock lock-overlay-icon" aria-hidden="true"></i>
        <h2>Kioszk zárolva</h2>
        <p>Olvasd be a vezérlő kártyát az aktiváláshoz.</p>
    </div>
</div>

<input id="barcode-input" class="kiosk-input" autocomplete="off" autofocus>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const state = {
    soundEnabled: true,
    session: @json($sessionPayload),
    highlightedEventId: @json($sessionPayload['latest_event_id'] ?? null),
};

const barcodeInput = document.getElementById('barcode-input');
const mealTypeSelect = document.getElementById('meal-type');
const lists = document.getElementById('lists');
const lockModal = document.getElementById('lock-modal');
const lockPinInput = lockModal.querySelector('input[name="admin_pin"]');
const lockOverlay = document.getElementById('lock-overlay');
const selectedMealTypeLabel = document.getElementById('selected-meal-type-label');
let isInteractionOpen = false;
let isLocking = false;
let highlightTimer = null;
let fullscreenRequested = false;

// A böngésző csak "valódi" (felhasználó által kiváltott) esemény hatására
// enged teljes képernyőre váltani. Egy vonalkód-olvasó a beolvasott
// karaktereket billentyűleütésként ("trusted" KeyboardEvent) küldi a
// böngészőnek, ezért az első ilyen leütéskor megpróbáljuk a teljes
// képernyőt - ha ez valamiért nem sikerül (pl. a böngésző már enélkül is
// kiosk-módban fut), egyszerűen nem történik semmi. Ez a fizetési/
// számlázási működést egyáltalán nem érinti.
function requestKioskFullscreenIfNeeded(event) {
    if (fullscreenRequested || !event.isTrusted || document.fullscreenElement) {
        return;
    }

    fullscreenRequested = true;

    const target = document.documentElement;
    if (target.requestFullscreen) {
        target.requestFullscreen().catch(() => {});
    }
}

// A vezérlő kártyával történő zárolás (nem a PIN-kódos "Kioszk lezárása"
// gombbal) csak egy felületi átfedést nyit meg - a beolvasó mező
// szándékosan fókuszban marad, hogy a kioszkot aktiváló beolvasás
// (ugyanaz a vezérlő kártya) megérkezhessen.
function renderLockState() {
    lockOverlay.classList.toggle('is-open', !!state.session.locked);
}

function isEditableElement(element) {
    if (!element) return false;

    return element.matches('input, textarea, select, [contenteditable=""], [contenteditable="true"]');
}

function canFocusScanner() {
    if (isInteractionOpen || isLocking || lockModal.classList.contains('is-open')) {
        return false;
    }

    return !isEditableElement(document.activeElement);
}

function keepFocus() {
    window.setTimeout(() => {
        if (!canFocusScanner()) {
            return;
        }

        barcodeInput.focus();
    }, 30);
}

function openLockModal({ clearPin = false } = {}) {
    isInteractionOpen = true;
    lockModal.classList.add('is-open');

    if (clearPin) {
        lockPinInput.value = '';
    }

    window.setTimeout(() => {
        lockPinInput.focus();
        lockPinInput.select();
    }, 30);
}

function closeLockModal({ restoreScannerFocus = true } = {}) {
    isInteractionOpen = false;
    isLocking = false;
    lockModal.classList.remove('is-open');

    if (restoreScannerFocus) {
        keepFocus();
    }
}

function beep(kind) {
    if (!state.soundEnabled) return;

    const context = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.type = 'sine';
    oscillator.frequency.value = kind === 'success' ? 880 : kind === 'duplicate' ? 540 : 220;
    gain.gain.value = 0.08;
    oscillator.start();
    oscillator.stop(context.currentTime + 0.12);
}

function createBadge(text, className = '', iconClass = '') {
    const badge = document.createElement('span');
    badge.className = 'badge' + (className ? ' ' + className : '');

    if (iconClass) {
        const icon = document.createElement('i');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');
        badge.appendChild(icon);
    }

    const label = document.createElement('span');
    label.textContent = text;
    badge.appendChild(label);

    return badge;
}

function syncSessionUi() {
    selectedMealTypeLabel.textContent = state.session.selected_meal_type || 'Nincs kiválasztva';
    mealTypeSelect.value = state.session.selected_meal_type_id || '';
}

function eventGroups() {
    if (state.session.ab_mode) {
        return [
            { key: 'A', title: 'A MENÜ', subtitle: 'A oszlop', listClass: 'is-column' },
            { key: 'B', title: 'B MENÜ', subtitle: 'B oszlop és diétások', listClass: 'is-column' },
        ];
    }

    return [
        { key: 'default', title: 'Legutóbbi események', subtitle: 'Munkamenet eseményei', listClass: 'is-grid' },
    ];
}

function renderLists() {
    lists.className = 'lists' + (state.session.ab_mode ? ' is-ab' : '');
    lists.innerHTML = '';

    eventGroups().forEach(group => {
        const panel = document.createElement('section');
        panel.className = 'panel';

        const header = document.createElement('div');
        header.className = 'panel-header';

        const heading = document.createElement('h2');
        heading.className = 'panel-title' + (group.key === 'A' ? ' is-a' : group.key === 'B' ? ' is-b' : '');
        heading.textContent = group.title;

        const subtitle = document.createElement('div');
        subtitle.className = 'panel-subtitle';
        subtitle.textContent = group.subtitle;

        header.appendChild(heading);
        header.appendChild(subtitle);
        panel.appendChild(header);

        const list = document.createElement('div');
        list.className = 'list ' + group.listClass;
        const items = state.session.recent[group.key] || [];

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            empty.textContent = 'Még nincs megjeleníthető beolvasás ebben a munkamenetben.';
            list.appendChild(empty);
        }

        items.forEach(item => {
            const entry = document.createElement('article');
            entry.className = 'entry ' + item.status_class + (item.is_dietary ? ' is-dietary' : '');

            if (state.highlightedEventId && item.id === state.highlightedEventId) {
                entry.classList.add('is-fresh');
            }

            const status = document.createElement('div');
            status.className = 'entry-status';

            const statusMain = document.createElement('div');
            statusMain.className = 'status-main';

            const icon = document.createElement('i');
            icon.className = 'status-icon ' + item.icon;
            icon.setAttribute('aria-hidden', 'true');

            const title = document.createElement('div');
            title.className = 'status-title';
            title.textContent = item.headline;

            statusMain.appendChild(icon);
            statusMain.appendChild(title);

            const time = document.createElement('div');
            time.className = 'entry-time';
            time.textContent = item.scanned_at || '—';

            status.appendChild(statusMain);
            status.appendChild(time);
            entry.appendChild(status);

            const name = document.createElement('div');
            name.className = 'entry-name';
            name.textContent = item.child_name || 'Ismeretlen személy';
            entry.appendChild(name);

            const meta = document.createElement('div');
            meta.className = 'entry-meta';

            if (item.is_employee) {
                meta.appendChild(createBadge('Dolgozó', 'is-group', 'fas fa-id-badge'));
            } else if (item.group_name) {
                meta.appendChild(createBadge(item.group_name, 'is-group', 'fas fa-users'));
            }

            if (item.display_menu_choice === 'A') {
                meta.appendChild(createBadge('A menü', 'is-menu-a', 'fas fa-check-circle'));
            }

            if (item.display_menu_choice === 'B') {
                meta.appendChild(createBadge('B menü', 'is-menu-b', 'fas fa-check-circle'));
            }

            if (item.is_dietary) {
                meta.appendChild(createBadge('DIÉTÁS', 'is-dietary', 'fas fa-exclamation-triangle'));
            }

            if ((item.dietary || []).length) {
                item.dietary.forEach(label => {
                    meta.appendChild(createBadge(label, 'is-alert', 'fas fa-exclamation-triangle'));
                });
            }

            entry.appendChild(meta);

            list.appendChild(entry);
        });

        panel.appendChild(list);
        lists.appendChild(panel);
    });
}

function setLatestHighlight(eventId) {
    state.highlightedEventId = eventId || null;

    if (highlightTimer) {
        window.clearTimeout(highlightTimer);
    }

    if (state.highlightedEventId) {
        highlightTimer = window.setTimeout(() => {
            state.highlightedEventId = null;
            renderLists();
        }, 5000);
    }
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
        throw data;
    }

    return data;
}

barcodeInput.addEventListener('keydown', async (event) => {
    requestKioskFullscreenIfNeeded(event);

    if (event.key !== 'Enter') return;

    event.preventDefault();
    const token = barcodeInput.value.trim();
    barcodeInput.value = '';

    if (!token) {
        keepFocus();
        return;
    }

    try {
        const payload = await postJson('{{ route('kiosk.scan') }}', { barcode_token: token });
        state.session = payload.session;
        renderLockState();

        if (payload.result === 'control') {
            // Vezérlő kártya beolvasása - nem étkezés-esemény, csak a
            // zárolt/aktív állapot vált, nincs "esemény" a listákban.
            syncSessionUi();
            renderLists();
            beep(state.session.locked ? 'duplicate' : 'success');
            return;
        }

        setLatestHighlight(payload.event?.id || payload.session.latest_event_id || null);
        syncSessionUi();
        renderLists();
        beep(payload.result);
    } catch (error) {
        if (error && error.session) {
            state.session = error.session;
            renderLockState();
        }
        beep('error');
    } finally {
        keepFocus();
    }
});

mealTypeSelect.addEventListener('change', async () => {
    if (!mealTypeSelect.value) return;

    if (!window.confirm('Biztosan erre az étkezéstípusra váltasz?')) {
        mealTypeSelect.value = state.session.selected_meal_type_id || '';
        return;
    }

    try {
        const payload = await postJson('{{ route('kiosk.meal-type.update') }}', { meal_type_id: mealTypeSelect.value });
        state.session = payload.session;
        syncSessionUi();
        renderLists();
    } catch (error) {
        mealTypeSelect.value = state.session.selected_meal_type_id || '';
    } finally {
        keepFocus();
    }
});

document.getElementById('toggle-sound').addEventListener('click', function () {
    state.soundEnabled = !state.soundEnabled;
    this.textContent = 'Hang: ' + (state.soundEnabled ? 'be' : 'ki');
    keepFocus();
});

document.getElementById('open-lock').addEventListener('click', () => {
    openLockModal();
});

document.getElementById('close-lock').addEventListener('click', () => {
    closeLockModal();
});

lockModal.addEventListener('click', (event) => {
    if (event.target !== lockModal) {
        return;
    }

    closeLockModal();
});

lockPinInput.addEventListener('keydown', (event) => {
    event.stopPropagation();

    if (event.key === 'Escape') {
        event.preventDefault();
        closeLockModal();
    }
});

lockPinInput.addEventListener('focus', () => {
    isInteractionOpen = true;
});

lockModal.addEventListener('submit', () => {
    isLocking = true;
    isInteractionOpen = true;
});

function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('hu-HU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function updateOnlineStatus() {
    const online = navigator.onLine;
    document.getElementById('online-dot').style.background = online ? 'var(--success)' : 'var(--danger)';
    document.getElementById('online-label').textContent = online ? 'Kioszk aktív' : 'Nincs kapcsolat';
}

window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);
window.addEventListener('click', (event) => {
    if (lockModal.contains(event.target)) {
        return;
    }

    keepFocus();
});

syncSessionUi();
renderLists();
renderLockState();
setLatestHighlight(state.highlightedEventId);
updateClock();
updateOnlineStatus();
setInterval(updateClock, 1000);

@if(session('error'))
openLockModal({ clearPin: true });
@else
keepFocus();
@endif
</script>
</body>
</html>
