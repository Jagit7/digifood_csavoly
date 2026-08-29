@extends('layouts.superadmin')

@section('title', 'Gyermeklista')

@push('styles')
    <style>
        .table-compact th,
        .table-compact td {
            padding-top: .3rem !important;
            padding-bottom: .3rem !important;
            vertical-align: middle;
            line-height: 1.2;
        }
        .table-compact .badge {
            white-space: nowrap;
        }
        .table-compact .icon-slash {
            position: relative;
            display: inline-block;
        }
        .table-compact .icon-slash::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 5%;
            right: 5%;
            border-top: 2px solid currentColor;
            transform: rotate(-45deg);
            transform-origin: center;
        }

        /*
         * Széles, oldalra görgethető tábla: a névoszlop görgetés közben is
         * a helyén marad, a fejléc pedig két sorban (csoport + mező) mutatja,
         * melyik adatblokkban jár a szem. Minden cella egysoros marad
         * (white-space: nowrap) - a kapcsolat-típus (Édesanya/Édesapa/stb.)
         * ezért külön oszlopban van, nem a névvel egy cellába zsúfolva.
         */
        .wide-table-wrap {
            overflow-x: auto;
        }
        .wide-table {
            white-space: nowrap;
        }
        .wide-table thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background: #f4f5f7;
        }
        .wide-table thead tr.group-row th {
            top: 0;
            z-index: 4;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #6c757d;
            background: #eceef2;
        }
        .wide-table thead tr.field-row th {
            top: 27px;
        }
        .wide-table th.group-start,
        .wide-table td.group-start {
            border-left: 2px solid #adb5bd;
        }
        .wide-table .sticky-col {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fff;
            box-shadow: 2px 0 4px -2px rgba(0,0,0,.15);
        }
        .wide-table thead .sticky-col {
            z-index: 5;
            background: #f4f5f7;
        }
        .wide-table thead tr.group-row .sticky-col {
            background: #eceef2;
        }
        .wide-table tbody tr:hover td {
            background: #f8f7fc;
        }
        .wide-table tbody tr:hover td.sticky-col {
            background: #f1eefb;
        }
        .name-cell {
            min-width: 200px;
        }
        .row-number {
            font-size: 11px;
            margin-right: 3px;
        }
        .rel-badge {
            font-size: 10px;
        }
        td.data-mismatch {
            background: #fff3cd !important;
        }
        .table-toolbar {
            background: #f8f8fb;
            border: 1px solid #ececf3;
            border-radius: 8px;
            padding: .6rem .9rem;
        }
        .column-toggle {
            gap: 6px;
        }
        .column-toggle .toggle-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #9297a3;
        }
        .column-toggle label {
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            user-select: none;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid #d8dae2;
            background: #fff;
            color: #6c757d;
            transition: background .12s, border-color .12s, color .12s;
        }
        .column-toggle label:hover {
            border-color: #b9bdcc;
        }
        .column-toggle label:has(input:checked) {
            background: #eef0fc;
            border-color: #7c83db;
            color: #40448f;
            font-weight: 600;
        }
        .column-toggle input {
            display: none;
        }
        .col-hidden {
            display: none;
        }
        .mismatch-legend {
            font-size: 12.5px;
            color: #8a7530;
            background: #fffaf0;
            border: 1px solid #f0e2b8;
            border-radius: 20px;
            padding: 4px 12px;
        }
        .mismatch-legend .swatch {
            width: 10px;
            height: 10px;
            border-radius: 2px;
        }
        .edit-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            color: #adb5bd;
            font-size: 11px;
            margin-left: 4px;
            vertical-align: middle;
            transition: background .12s, color .12s;
        }
        .edit-link:hover {
            background: #eef0fc;
            color: #5b60b0;
        }
        .verify-toggle-form {
            display: inline-block;
        }
        .verify-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: none;
            background: none;
            color: #c3c6d1;
            font-size: 16px;
            padding: 0;
            line-height: 1;
            transition: color .12s, background .12s;
        }
        .verify-toggle:hover {
            background: #f1f1f6;
            color: #9297a3;
        }
        .verify-toggle.is-verified {
            color: #2fa84f;
        }
        .verify-toggle.is-verified:hover {
            background: #e8f7ec;
            color: #1f7a38;
        }
        .duplicate-flag {
            color: #d68a1f;
            margin-left: 3px;
            cursor: default;
        }
        .billing-guardian-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: 600;
            color: #7a5b00;
            background: #fff3cd;
            border: 1px solid #e0c36a;
            border-radius: 10px;
            padding: 1px 6px;
            margin-left: 4px;
            cursor: default;
            white-space: nowrap;
        }
    </style>
@endpush

@section('content')
@php
    $returnList = $listState['list'] ?? \App\Services\Navigation\ChildListReturnService::LIST_CHILDREN;
    $returnQuery = $listState['query'] ?? request()->getQueryString() ?? '';
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Gyermeklista',
        'subtitle' => 'Az intézményhez tartozó gyermekek, tanulók, gondviselőik és számlázási adataik',
    ])

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('dashboard.institution.children.create') }}" class="btn btn-outline-primary me-2">
            <i class="fa-solid fa-user-plus me-1"></i>Új gyermek
        </a>
        <a href="{{ route('dashboard.institution.children.barcodes.index') }}" class="btn btn-primary">
            <i class="fa-solid fa-barcode me-1"></i>Vonalkódos kártyák
        </a>
        <a href="{{ route('dashboard.institution.children.print-list.index') }}" class="btn btn-outline-primary ms-2">
            <i class="fa-solid fa-print me-1"></i>Nyomtatható lista
        </a>
        <a href="{{ route('dashboard.institution.children.export', request()->query()) }}" class="btn btn-outline-success ms-2">
            <i class="fa-solid fa-file-csv me-1"></i>CSV export
        </a>
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív gyermekek',
            'value' => $stats['active'],
            'subtitle' => 'Jelenleg aktív rekordok',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'green',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Gondviselő nélkül',
            'value' => $stats['without_guardian'],
            'subtitle' => 'Ellenőrzést igénylő rekordok',
            'icon' => 'fa-solid fa-user-shield',
            'color' => 'orange',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Inaktív gyermekek',
            'value' => $stats['inactive'],
            'subtitle' => 'Korábbi vagy archivált rekordok',
            'icon' => 'fa-solid fa-user-clock',
            'color' => 'purple',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Még nem ellenőrzött',
            'value' => $stats['unverified'],
            'subtitle' => 'Aktív gyermek, adatai még nincsenek átnézve',
            'icon' => 'fa-solid fa-clipboard-check',
            'color' => 'red',
        ])
    </div>

    <div class="alert alert-info">
        Az étkezéssel kapcsolatos adatok (menücsomag, kedvezmény, allergia/diéta, rendszeres lemondás, étkezési
        státusz) az <a href="{{ route('dashboard.institution.children.meal-participants.index') }}">Étkező
        gyermekek</a> listán találhatók.
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.children.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Név vagy oktatási azonosító</label>
                        <input type="search"
                               name="search"
                               class="form-control"
                               value="{{ request('search') }}"
                               placeholder="Keresés...">
                    </div>

                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Osztály / csoport</label>
                        <select name="group_name" class="form-control">
                            <option value="">Összes osztály / csoport</option>
                            @foreach($groups as $group)
                                <option value="{{ $group }}" @selected(request('group_name') === $group)>
                                    {{ $group }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-xl-1 col-lg-2 mb-3">
                        <label class="form-label">Állapot</label>
                        <select name="status" class="form-control">
                            <option value="">Minden</option>
                            <option value="active" @selected(request('status') === 'active')>Aktív</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inaktív</option>
                        </select>
                    </div>

                    <div class="col-xl-3 col-lg-3 mb-3">
                        <label class="form-label">Hiányosság</label>
                        <select name="data_quality" class="form-control">
                            <option value="">Összes gyermek</option>
                            <option value="any_issue" @selected(request('data_quality') === 'any_issue')>Bármilyen hiányosság ({{ $dataQualityCounts['any_issue'] }})</option>
                            <option value="no_guardian" @selected(request('data_quality') === 'no_guardian')>Nincs gondviselő ({{ $dataQualityCounts['no_guardian'] }})</option>
                            <option value="no_billing" @selected(request('data_quality') === 'no_billing')>Nincs számlázási profil ({{ $dataQualityCounts['no_billing'] }})</option>
                            <option value="no_email" @selected(request('data_quality') === 'no_email')>Nincs e-mail-cím ({{ $dataQualityCounts['no_email'] }})</option>
                            <option value="no_phone" @selected(request('data_quality') === 'no_phone')>Nincs telefonszám ({{ $dataQualityCounts['no_phone'] }})</option>
                            <option value="no_address" @selected(request('data_quality') === 'no_address')>Nincs cím ({{ $dataQualityCounts['no_address'] }})</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Ellenőrzés</label>
                        <select name="verified_status" class="form-control">
                            <option value="">Összes gyermek</option>
                            <option value="unverified" @selected(request('verified_status') === 'unverified')>Még nem ellenőrzött</option>
                            <option value="verified" @selected(request('verified_status') === 'verified')>Már ellenőrzött</option>
                        </select>
                    </div>

                    <div class="col-xl-1 col-lg-1 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        @if(request()->hasAny(['search', 'group_name', 'status', 'data_quality', 'verified_status']))
                            <a href="{{ route('dashboard.institution.children.index') }}"
                               class="btn btn-light" title="Szűrők törlése">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Gyermekek és tanulók</h4>
            <span class="text-muted">Találatok: {{ $children->total() }}</span>
        </div>
        <div class="card-body">
            <div class="table-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="column-toggle d-flex flex-wrap align-items-center">
                    <span class="toggle-label me-2">Oszlopcsoportok</span>
                    <label><input type="checkbox" class="col-toggle" data-group="szamlazas" checked><span>Számlázás</span></label>
                    <label><input type="checkbox" class="col-toggle" data-group="cim" checked><span>Cím</span></label>
                    <label><input type="checkbox" class="col-toggle" data-group="gondviselo1" checked><span>Gondviselő 1</span></label>
                    <label><input type="checkbox" class="col-toggle" data-group="gondviselo2" checked><span>Gondviselő 2</span></label>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <div class="mismatch-legend d-flex align-items-center gap-2">
                        <span class="d-inline-block swatch" style="background: #fff3cd; border: 1px solid #e0c36a;"></span>
                        Sárga = eltér a Számlázási név mellett jelölt gondviselő saját adatától (vidd rá az egeret a pontos eltérésért).
                    </div>
                    <div class="mismatch-legend d-flex align-items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation" style="color: #d68a1f;"></i>
                        Lehetséges duplikált gondviselő.
                    </div>
                    <div class="mismatch-legend d-flex align-items-center gap-2">
                        <i class="fa-solid fa-circle-check" style="color: #2fa84f;"></i>
                        A név melletti pipa: adatok ellenőrizve (kattints a jelöléshez / visszavonáshoz).
                    </div>
                </div>
            </div>
            @if($children->count())
                <div class="wide-table-wrap">
                    <table class="table table-hover align-middle table-compact wide-table">
                        <thead>
                        <tr class="group-row">
                            <th class="sticky-col"></th>
                            <th></th>
                            <th colspan="2">Alapadatok</th>
                            <th colspan="3" class="group-start" data-group="szamlazas">Számlázás</th>
                            <th colspan="3" class="group-start" data-group="cim">Cím</th>
                            <th colspan="4" class="group-start" data-group="gondviselo1">Gondviselő 1</th>
                            <th colspan="4" class="group-start" data-group="gondviselo2">Gondviselő 2</th>
                            <th colspan="2" class="group-start">Tanuló adatai</th>
                        </tr>
                        <tr class="field-row">
                            <th class="sticky-col">Név</th>
                            <th width="120" class="text-end">Művelet</th>
                            <th>Osztály</th>
                            <th>Állapot</th>
                            <th class="group-start" data-group="szamlazas">Számlázási név</th>
                            <th data-group="szamlazas">Cím</th>
                            <th data-group="szamlazas">E-mail</th>
                            <th class="group-start" data-group="cim">Irsz.</th>
                            <th data-group="cim">Település</th>
                            <th data-group="cim">Utca, hsz.</th>
                            <th class="group-start" data-group="gondviselo1">Kapcsolat</th>
                            <th data-group="gondviselo1">Név</th>
                            <th data-group="gondviselo1">Telefon</th>
                            <th data-group="gondviselo1">E-mail</th>
                            <th class="group-start" data-group="gondviselo2">Kapcsolat</th>
                            <th data-group="gondviselo2">Név</th>
                            <th data-group="gondviselo2">Telefon</th>
                            <th data-group="gondviselo2">E-mail</th>
                            <th class="group-start">Oktatási azonosító</th>
                            <th>Tanév</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            // Az egyezés-ellenőrzésnél az elütés-szintű különbségeket (pl.
                            // záró pont, dupla szóköz, vessző megléte/hiánya) nem tekintjük
                            // valódi eltérésnek - csak a tényleges tartalmat vetjük össze.
                            $normalizeAddress = function (?string $value): string {
                                $value = mb_strtolower((string) $value);
                                $value = str_replace(['.', ','], ' ', $value);
                                $value = preg_replace('/\s+/', ' ', $value);

                                return trim($value);
                            };
                        @endphp
                        @foreach($children as $child)
                            {{-- Globális sorszám: a gyermek hányadik a TELJES (szűrt) listában, nem
                                 csak az aktuális oldalon - ezért az oldal elejét ($children->firstItem())
                                 adjuk hozzá a ciklusbeli pozícióhoz ($loop->index), nem csak $loop->iteration-t
                                 használunk. --}}
                            @php($rowNumber = ($children->firstItem() ?? 0) + $loop->index)
                            @php($guardians = $child->guardians)
                            @php($guardian1 = $child->display_guardian_1)
                            @php($guardian2 = $child->display_guardian_2)
                            @php($billing = $child->display_billing_profile)
                            @php($billingAddress = $billing ? trim(($billing->postal_code ?? '') . ' ' . ($billing->city ?? '') . ($billing->address ? ', ' . $billing->address : '')) : null)
                            @php($billingMismatch = [])
                            {{-- $bg: az a gondviselő, akihez a számlázási profil TÉNYLEGESEN hozzá van
                                 rendelve (billing_profiles.guardian_id). Ez NEM feltétlenül egyezik a
                                 lenti "Gondviselő 1" / "Gondviselő 2" oszlopban látható személlyel, ha a
                                 gyermeknek több gondviselője van - ezért jelöljük külön a Számlázási név
                                 mellett egy kis címkével. Minden ciklusban nullázzuk, nehogy az előző
                                 gyermek adata "átlógjon" ide. --}}
                            @php($bg = $child->display_billing_guardian)
                            @php($billingGuardianSlot = $child->display_billing_guardian_slot)
                            @php($billingGuardianNote = $child->display_billing_guardian_note)
                            @if($billing && $bg)
                                @php($guardianAddress = trim(($bg->postal_code ?? '') . ' ' . ($bg->city ?? '') . ', ' . trim(($bg->street_name ?? '') . ' ' . ($bg->street_type ?? '') . ' ' . ($bg->house_number ?? ''))))
                                @if($billing->email && $bg->email && mb_strtolower(trim($billing->email)) !== mb_strtolower(trim($bg->email)))
                                    @php($billingMismatch['email'] = "Gondviselő ({$bg->full_name}) e-mailje: {$bg->email} — Számlázási e-mail: {$billing->email}")
                                @endif
                                @if($billing->address && $bg->street_name && $normalizeAddress($billingAddress) !== $normalizeAddress($guardianAddress))
                                    @php($billingMismatch['address'] = "Gondviselő ({$bg->full_name}) címe: {$guardianAddress} — Számlázási cím: {$billingAddress}")
                                @endif
                            @endif
                            <tr>
                                <td class="sticky-col name-cell">
                                    <div class="d-flex align-items-start justify-content-between gap-1">
                                        <div>
                                            <span class="text-muted row-number">{{ $rowNumber }}.</span>
                                            <strong>{{ $child->name }}</strong>
                                            @if($child->hasActiveBarcode())
                                                <span class="badge badge-success light" title="Vonalkód aktív"><i class="fa-solid fa-barcode"></i></span>
                                            @elseif($child->hasDisabledBarcode())
                                                <span class="badge badge-warning light" title="Vonalkód letiltva"><i class="fa-solid fa-barcode"></i></span>
                                            @endif
                                        </div>
                                        <form method="POST"
                                              action="{{ $child->isDataVerified() ? route('dashboard.institution.children.unverify', $child) : route('dashboard.institution.children.verify', $child) }}"
                                              class="verify-toggle-form">
                                            @csrf
                                            @if($child->isDataVerified()) @method('DELETE') @endif
                                            <input type="hidden" name="return_list" value="{{ $returnList }}">
                                            <input type="hidden" name="return_query" value="{{ $returnQuery }}">
                                            <button type="submit"
                                                    class="verify-toggle {{ $child->isDataVerified() ? 'is-verified' : '' }}"
                                                    title="{{ $child->isDataVerified() ? 'Ellenőrizve ' . $child->data_verified_at->format('Y.m.d.') . ' - kattints a visszavonáshoz' : 'Jelölés ellenőrzöttként' }}">
                                                <i class="fa-solid {{ $child->isDataVerified() ? 'fa-circle-check' : 'fa-circle' }}"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.children.edit', $child) }}?{{ http_build_query(['return_list' => $returnList, 'return_query' => $returnQuery]) }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                </td>
                                <td>
                                    @if($child->group_name)
                                        <span class="badge badge-primary light">{{ $child->group_name }}</span>
                                    @else
                                        <span class="text-muted">Nincs megadva</span>
                                    @endif
                                </td>
                                <td>
                                    @if($child->active)
                                        <span class="badge badge-success light">Aktív</span>
                                    @else
                                        <span class="badge badge-secondary light">Inaktív</span>
                                    @endif
                                </td>
                                <td data-group="szamlazas"
                                    class="group-start {{ isset($billingMismatch['address']) ? 'data-mismatch' : '' }}"
                                    @if(isset($billingMismatch['address'])) title="{{ $billingMismatch['address'] }}" @endif>
                                    {{ $billing->billing_name ?? '—' }}
                                    <a href="{{ route('dashboard.institution.billing-addresses.index', ['search' => $child->name]) }}"
                                       class="edit-link" title="Számlázási cím szerkesztése">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    @if($bg)
                                        <span class="billing-guardian-badge"
                                              title="{{ $billingGuardianNote ?: ('Számlázási gondviselő: ' . $bg->full_name) }}">
                                            {{ $billingGuardianSlot ? 'Gondviselő '.$billingGuardianSlot : 'Kapcsolati hiba' }}
                                        </span>
                                    @endif
                                    @if($billingGuardianNote)
                                        <div class="small text-warning mt-1">{{ $billingGuardianNote }}</div>
                                    @endif
                                </td>
                                <td data-group="szamlazas"
                                    class="{{ isset($billingMismatch['address']) ? 'data-mismatch' : '' }}"
                                    @if(isset($billingMismatch['address'])) title="{{ $billingMismatch['address'] }}" @endif>
                                    {{ $billingAddress ?: '—' }}
                                </td>
                                <td data-group="szamlazas"
                                    class="{{ isset($billingMismatch['email']) ? 'data-mismatch' : '' }}"
                                    @if(isset($billingMismatch['email'])) title="{{ $billingMismatch['email'] }}" @endif>
                                    {{ $billing->email ?? '—' }}
                                </td>
                                <td class="group-start" data-group="cim">{{ $billing->postal_code ?? $guardian1?->postal_code ?? '—' }}</td>
                                <td data-group="cim">{{ $billing->city ?? $guardian1?->city ?? '—' }}</td>
                                <td data-group="cim">{{ $billing->address ?? trim(($guardian1?->street_name ?? '') . ' ' . ($guardian1?->street_type ?? '') . ' ' . ($guardian1?->house_number ?? '')) ?: '—' }}</td>
                                <td class="group-start" data-group="gondviselo1">
                                    @if($guardian1)
                                        <span class="badge badge-secondary light rel-badge">{{ $guardian1->pivot->relationship_type ?? 'Gondviselő' }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-group="gondviselo1">
                                    {{ $guardian1?->full_name ?: '—' }}
                                    @if($billingGuardianSlot === 1)
                                        <span class="badge bg-success ms-1">Számlázó</span>
                                    @endif
                                    @if($guardian1 && $duplicateGuardianIds->contains($guardian1->id))
                                        <i class="fa-solid fa-triangle-exclamation duplicate-flag" title="Lehetséges duplikátum: egy másik gondviselő ugyanazzal a névvel és telefonszámmal/e-maillel szerepel"></i>
                                    @endif
                                </td>
                                <td data-group="gondviselo1">{{ $guardian1?->phone ?: '—' }}</td>
                                <td data-group="gondviselo1">{{ $guardian1?->email ?: '—' }}</td>
                                <td class="group-start" data-group="gondviselo2">
                                    @if($guardian2)
                                        <span class="badge badge-secondary light rel-badge">{{ $guardian2->pivot->relationship_type ?? 'Gondviselő' }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-group="gondviselo2">
                                    {{ $guardian2?->full_name ?: '—' }}
                                    @if($billingGuardianSlot === 2)
                                        <span class="badge bg-success ms-1">Számlázó</span>
                                    @endif
                                    @if($guardian2 && $duplicateGuardianIds->contains($guardian2->id))
                                        <i class="fa-solid fa-triangle-exclamation duplicate-flag" title="Lehetséges duplikátum: egy másik gondviselő ugyanazzal a névvel és telefonszámmal/e-maillel szerepel"></i>
                                    @endif
                                </td>
                                <td data-group="gondviselo2">{{ $guardian2?->phone ?: '—' }}</td>
                                <td data-group="gondviselo2">{{ $guardian2?->email ?: '—' }}</td>
                                <td class="group-start">{{ $child->educational_identifier ?: '-' }}</td>
                                <td>{{ $child->school_year ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $children->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-user-graduate',
                    'title' => 'Nincs a szűrésnek megfelelő gyermek',
                    'text' => 'Módosítsd a keresési feltételeket, vagy tölts fel intézményi Excel-fájlt.',
                ])
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.col-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var group = checkbox.dataset.group;
            document.querySelectorAll('[data-group="' + group + '"]').forEach(function (el) {
                el.classList.toggle('col-hidden', !checkbox.checked);
            });
        });
    });
});
</script>
@endsection
