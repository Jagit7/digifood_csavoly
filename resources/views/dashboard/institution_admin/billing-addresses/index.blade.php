@extends('layouts.superadmin')

@section('title', 'Alapadatok')

@push('styles')
    <style>
        .table-compact th,
        .table-compact td {
            padding-top: .4rem !important;
            padding-bottom: .4rem !important;
            vertical-align: middle;
        }
        .df-modal-section:not(:last-child) {
            border-bottom: 1px solid #eceef2;
            padding-bottom: 1rem;
        }
        .df-quality-legend {
            row-gap: .35rem;
        }
        .df-quality-legend .df-legend-item {
            cursor: help;
            white-space: nowrap;
        }
        .df-legend-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
        }
        .df-readiness-ok {
            color: #2a9d5c;
        }
        .df-readiness-issue {
            color: #c0392b;
        }

        /*
         * Széles, oldalra görgethető tábla - ugyanaz a minta, mint a
         * Gyermeklistán: minden cella egysoros marad (nincs egymás alá
         * tördelt tartalom), a névoszlop görgetés közben is a helyén
         * marad, a fejléc pedig két sorban (csoport + mező) mutatja, melyik
         * adatblokkban jár a szem.
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
            min-width: 160px;
        }
        .row-number {
            font-size: 11px;
            margin-right: 3px;
        }
    </style>
@endpush

@section('content')
@php
    $returnList = $listState['list'] ?? \App\Services\Navigation\ChildListReturnService::LIST_BASICS;
    $baseReturnParams = $listState['params'] ?? [];
@endphp
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Alapadatok',
        'subtitle' => 'Indulás előtti adategyeztetés - gyermekenként egy helyen minden, ami a Digifood biztonságos induláshoz kell.',
    ])

    {{-- Áttekintő számok - a "Hiányosság" szűrő mögötti darabszámok, hogy
         induláskor egy pillantással látszódjon, mennyi tennivaló van.
         Ugyanaz a kártya-komponens, mint a Gyermeklistán. --}}
    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív gyermekek',
            'value' => $totalActiveChildren,
            'subtitle' => 'Jelenleg aktív rekordok',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'green',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Hiányos adat',
            'value' => $qualityCounts['any_issue'],
            'subtitle' => 'Induláshoz szükséges adat hiányzik',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'color' => 'red',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Nincs kijelölt fizető',
            'value' => $qualityCounts['no_payer'],
            'subtitle' => 'Ellenőrzést igénylő rekordok',
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'color' => 'orange',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Gondviselői fiók nincs aktiválva',
            'value' => $qualityCounts['not_activated'],
            'subtitle' => 'Meghívó vár elfogadásra, vagy még el sem ment',
            'icon' => 'fa-solid fa-user-clock',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.billing-addresses.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Keresés (név vagy osztály)</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Pl. Kovács vagy 3.A">
                </div>
                <input type="hidden" name="sort" value="{{ $sort }}">

                <div class="col-md-3">
                    <label class="form-label">Hiányosság</label>
                    <select name="quality" class="form-control">
                        <option value="">Összes gyermek</option>
                        <option value="any_issue" @selected($quality === 'any_issue')>Bármilyen hiányosság ({{ $qualityCounts['any_issue'] }})</option>
                        <option value="no_guardian" @selected($quality === 'no_guardian')>Nincs gondviselő ({{ $qualityCounts['no_guardian'] }})</option>
                        <option value="no_email" @selected($quality === 'no_email')>Nincs gondviselői e-mail ({{ $qualityCounts['no_email'] }})</option>
                        <option value="no_payer" @selected($quality === 'no_payer')>Nincs kijelölt fizető ({{ $qualityCounts['no_payer'] }})</option>
                        <option value="not_activated" @selected($quality === 'not_activated')>Gondviselői fiók nincs aktiválva ({{ $qualityCounts['not_activated'] }})</option>
                        <option value="no_meal_setting" @selected($quality === 'no_meal_setting')>Nincs étkezési beállítás ({{ $qualityCounts['no_meal_setting'] }})</option>
                        <option value="no_group" @selected($quality === 'no_group')>Nincs osztály / csoport ({{ $qualityCounts['no_group'] }})</option>
                        <option value="no_bank_account" @selected($quality === 'no_bank_account')>Van fizető, de nincs bankszámlaszám ({{ $qualityCounts['no_bank_account'] }})</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Kedvezmény</label>
                    <select name="discount_type_id" class="form-control">
                        <option value="">Összes gyermek (nincs kedvezmény-szűrés)</option>
                        <option value="any" @selected($discountTypeId === 'any')>Bármilyen kedvezmény – {{ $discountCounts['any'] ?? 0 }} fő</option>
                        @foreach($discounts as $discount)
                            <option value="{{ $discount->id }}" @selected((string) $discountTypeId === (string) $discount->id)>
                                {{ $discount->name }} ({{ $discount->percentage }}%) – {{ $discountCounts[$discount->id] ?? 0 }} fő
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Allergén / étel-érzékenység</label>
                    <select name="dietary_restriction_id" class="form-control">
                        <option value="">Összes gyermek (nincs érzékenység-szűrés)</option>
                        <option value="any" @selected($dietaryRestrictionId === 'any')>Bármilyen érzékenység / allergén – {{ $dietaryRestrictionCounts['any'] ?? 0 }} fő</option>
                        @foreach($allDietaryRestrictions as $restriction)
                            <option value="{{ $restriction->id }}" @selected((string) $dietaryRestrictionId === (string) $restriction->id)>
                                {{ $restriction->name }} ({{ $restriction->type === 'allergen' ? 'allergén' : 'érzékenység' }}) – {{ $dietaryRestrictionCounts[$restriction->id] ?? 0 }} fő
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                    </button>
                    @if(request()->hasAny(['search', 'quality', 'discount_type_id', 'dietary_restriction_id']))
                        <a href="{{ route('dashboard.institution.billing-addresses.index', ['sort' => $sort]) }}" class="btn btn-light">
                            Szűrés törlése
                        </a>
                    @endif
                </div>

                <div class="col-md-auto ms-md-auto">
                    <label class="form-label d-block">Rendezés</label>
                    <div class="btn-group" role="group">
                        <a href="{{ route('dashboard.institution.billing-addresses.index', array_filter(['search' => request('search'), 'quality' => $quality, 'discount_type_id' => $discountTypeId, 'dietary_restriction_id' => $dietaryRestrictionId, 'sort' => 'class'])) }}"
                           class="btn btn-sm {{ $sort === 'class' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Osztály, azon belül ABC
                        </a>
                        <a href="{{ route('dashboard.institution.billing-addresses.index', array_filter(['search' => request('search'), 'quality' => $quality, 'discount_type_id' => $discountTypeId, 'dietary_restriction_id' => $dietaryRestrictionId, 'sort' => 'name'])) }}"
                           class="btn btn-sm {{ $sort === 'name' ? 'btn-primary' : 'btn-outline-primary' }}">
                            ABC sorrend
                        </a>
                    </div>
                </div>
            </form>

            <div class="df-quality-legend mt-3 small text-muted d-flex flex-wrap align-items-center gap-3">
                <span class="df-legend-item" title="A lenti táblázat Osztály, Állapot, Készültség, Étkező, Fiók, Fizető és Bankszámla oszlopainak jelvényszíne minden sorban ugyanezt jelenti. A Kedvezmény és Érzékenység oszlop nem állapotjelzés, ezért ezekre nem vonatkozik - ott a jelvény csak azt mutatja, hogy be van-e állítva valami.">
                    <i class="fa-solid fa-circle-info me-1"></i>Színek jelentése:
                </span>
                <span class="df-legend-item" title="Rendben, nincs teendő - pl. aktivált gondviselői fiók, kész adatlap.">
                    <span class="df-legend-dot bg-success"></span>zöld = rendben
                </span>
                <span class="df-legend-item" title="Van még teendő, de indulást nem akadályozza - pl. meghívó elküldve, de a gondviselő még nem aktiválta.">
                    <span class="df-legend-dot bg-warning"></span>sárga = teendő van
                </span>
                <span class="df-legend-item" title="Hiányzik egy, az induláshoz szükséges adat - pl. nincs gondviselői e-mail, nincs kijelölt fizető.">
                    <span class="df-legend-dot bg-danger"></span>piros = hiányzik
                </span>
                <span class="df-legend-item" title="Nincs beállítva, vagy a gyermeknél jelenleg nem értelmezhető - pl. nem étkező, ezért nincs fizetési adata.">
                    <span class="df-legend-dot bg-secondary"></span>szürke = nem releváns
                </span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">

            @if($children->count())

                @php
                    // Rövid, egysoros jelvény egy gondviselő e-mail/aktiválási
                    // állapotára - a Gondviselő 1 és Gondviselő 2 oszlopban is
                    // ugyanezt használjuk, hogy a sor egysoros maradjon.
                    $guardianStatusBadge = function ($g) {
                        if (! $g) {
                            return null;
                        }
                        if (blank($g->email)) {
                            return ['label' => 'Nincs e-mail', 'class' => 'badge-danger', 'title' => 'Nincs megadva e-mail cím ennél a gondviselőnél, ezért szülői fiók sem hozható létre neki.'];
                        }
                        if ($g->user_id) {
                            return ['label' => 'Aktivált', 'class' => 'badge-success', 'title' => 'A gondviselő aktiválta a szülői fiókját, be tud lépni.'];
                        }
                        if ($g->activation_email_sent_at) {
                            return ['label' => 'Meghívva', 'class' => 'badge-warning', 'title' => 'Az aktiváló meghívó elküldve, de a gondviselő még nem állította be a jelszavát.'];
                        }
                        return ['label' => 'Nincs meghívva', 'class' => 'badge-secondary', 'title' => 'Van e-mail cím, de még nem lett elküldve az aktiváló meghívó.'];
                    };
                @endphp

                <div class="wide-table-wrap">
                    <table class="table table-hover align-middle table-compact wide-table">
                        <thead>
                        <tr class="group-row">
                            <th class="sticky-col"></th>
                            <th></th>
                            <th colspan="3">Alapadatok</th>
                            <th class="group-start">Étkezés</th>
                            <th colspan="3" class="group-start" data-group="gondviselo1">Gondviselő 1</th>
                            <th colspan="3" class="group-start" data-group="gondviselo2">Gondviselő 2</th>
                            <th colspan="2" class="group-start">Számlázás</th>
                            <th colspan="2" class="group-start">Étkeztetés</th>
                        </tr>
                        <tr class="field-row">
                            <th class="sticky-col">Diák neve</th>
                            <th width="90" class="text-end">Művelet</th>
                            <th>Osztály</th>
                            <th>Állapot</th>
                            <th>Készültség</th>
                            <th class="group-start">Étkező</th>
                            <th class="group-start" data-group="gondviselo1">Név</th>
                            <th data-group="gondviselo1">E-mail</th>
                            <th data-group="gondviselo1">Fiók</th>
                            <th class="group-start" data-group="gondviselo2">Név</th>
                            <th data-group="gondviselo2">E-mail</th>
                            <th data-group="gondviselo2">Fiók</th>
                            <th class="group-start">Fizető</th>
                            <th>Bankszámla</th>
                            <th class="group-start">Kedvezmény</th>
                            <th>Érzékenység</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($children as $child)
                                @php($guardian1 = $child->display_guardian_1)
                                @php($guardian2 = $child->display_guardian_2)
                                @php($billing = $child->display_billing_profile)
                                @php($billingGuardian = $child->display_billing_guardian)
                                @php($billingGuardianSlot = $child->display_billing_guardian_slot)
                                @php($billingGuardianNote = $child->display_billing_guardian_note)
                                @php($mealParticipationStatus = $child->meal_participation_status)
                                @php($upcomingStartsOnLabel = $mealParticipationStatus['starts_on_label'] ?? null)
                                @php($hasGuardian = $child->guardians->isNotEmpty())
                                @php($hasEmail = $child->guardians->contains(fn ($g) => filled($g->email)))
                                @php($rowIssues = collect([
                                    ! $hasGuardian ? 'Nincs gondviselő' : null,
                                    $hasGuardian && ! $hasEmail ? 'Nincs gondviselői e-mail' : null,
                                    ! $billing ? 'Nincs kijelölt fizető' : null,
                                    ($mealParticipationStatus['is_missing'] ?? true) ? 'Nincs étkezési beállítás' : null,
                                    blank($child->group_name) ? 'Nincs osztály' : null,
                                    // Bankszámla csak akkor számít hiányzónak, ha VAN kijelölt
                                    // fizető - ha nincs, azt már a "Nincs kijelölt fizető" jelzi,
                                    // nem duplikáljuk ugyanazt a problémát két sorként.
                                    $billingGuardian && blank($billingGuardian->bank_account_number) ? 'Nincs bankszámlaszám' : null,
                                ])->filter()->values())
                                @php($g1Status = $guardianStatusBadge($guardian1))
                                @php($g2Status = $guardianStatusBadge($guardian2))
                                {{-- A ma érvényes kedvezmény: ha van ma érvényes periódus, azt
                                     mutatjuk, egyébként a Child::discountType esik vissza -
                                     ld. Child::discountTypeForDate() azonos logikáját. --}}
                                @php($currentDiscount = $child->discountPeriods->first()?->discountType ?? $child->discountType)
                                <tr>
                                    <td class="sticky-col name-cell">
                                        <span class="text-muted row-number">{{ ($children->firstItem() ?? 0) + $loop->index }}.</span>
                                        <strong>{{ $child->name }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-xs btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#childModal-{{ $child->id }}">
                                            <i class="fa fa-edit"></i> Szerkesztés
                                        </button>
                                    </td>
                                    <td>
                                        @if($child->group_name)
                                            <span class="badge badge-success light">{{ $child->group_name }}</span>
                                        @else
                                            <span class="badge badge-danger light">Nincs</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($child->active)
                                            <span class="badge badge-success light">Aktív</span>
                                        @else
                                            <span class="badge badge-secondary light">Inaktív</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($rowIssues->isEmpty())
                                            <span class="badge badge-success light"><i class="fa-solid fa-circle-check me-1"></i>Kész</span>
                                        @else
                                            <span class="badge badge-danger light" title="{{ $rowIssues->implode(', ') }}">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i>Hiányos ({{ $rowIssues->count() }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="group-start">
                                        @if(! $child->active)
                                            <span class="badge badge-secondary light">-</span>
                                        @elseif($mealParticipationStatus['is_current'] ?? false)
                                            <span class="badge badge-success light">Étkező</span>
                                        @elseif($mealParticipationStatus['is_upcoming'] ?? false)
                                            <span class="badge badge-info light"
                                                  title="{{ $upcomingStartsOnLabel ? $upcomingStartsOnLabel.'-től' : '' }}">
                                                Étkező (ütemezve)
                                            </span>
                                            @if($upcomingStartsOnLabel)
                                                <div class="small text-muted mt-1">
                                                    {{ $upcomingStartsOnLabel }}-től
                                                </div>
                                            @endif
                                        @else
                                            <span class="badge badge-warning light">Nincs beállítva</span>
                                        @endif
                                    </td>
                                    <td class="group-start">
                                        {{ $guardian1?->full_name ?: '—' }}
                                        @if($billingGuardianSlot === 1)
                                            <span class="badge bg-success ms-1">Számlázó</span>
                                        @endif
                                    </td>
                                    <td>{{ $guardian1?->email ?: '—' }}</td>
                                    <td>
                                        @if($g1Status)
                                            <span class="badge {{ $g1Status['class'] }} light" title="{{ $g1Status['title'] }}">{{ $g1Status['label'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="group-start">
                                        {{ $guardian2?->full_name ?: '—' }}
                                        @if($billingGuardianSlot === 2)
                                            <span class="badge bg-success ms-1">Számlázó</span>
                                        @endif
                                    </td>
                                    <td>{{ $guardian2?->email ?: '—' }}</td>
                                    <td>
                                        @if($g2Status)
                                            <span class="badge {{ $g2Status['class'] }} light" title="{{ $g2Status['title'] }}">{{ $g2Status['label'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="group-start">
                                        @if($billingGuardian)
                                            <span class="badge badge-success light">{{ $billingGuardian->full_name }}</span>
                                            @if($billingGuardianNote)
                                                <div class="small text-warning mt-1">{{ $billingGuardianNote }}</div>
                                            @endif
                                        @else
                                            <span class="badge badge-danger light">Nincs kijelölve</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(! $billingGuardian)
                                            <span class="badge badge-secondary light">-</span>
                                        @elseif(filled($billingGuardian->bank_account_number))
                                            <span class="badge badge-success light">{{ $billingGuardian->bank_account_number }}</span>
                                        @else
                                            <span class="badge badge-danger light">Hiányzik</span>
                                        @endif
                                    </td>
                                    {{-- A Kedvezmény és Érzékenység nem induláshoz szükséges
                                         állapot, csak tájékoztató adat - ezért nem a
                                         zöld/sárga/piros készültség-jelvényrendszert
                                         használják: üres/alapértelmezett esetben egyszerű
                                         szürke szöveg, beállított értéknél semleges (kék)
                                         jelvény, ami csak azt jelzi, hogy be van állítva. --}}
                                    <td class="group-start">
                                        @if($currentDiscount && $currentDiscount->name !== 'Kedvezmény nélkül')
                                            <span class="badge badge-primary light">{{ $currentDiscount->name }}</span>
                                        @else
                                            <span class="text-muted small">Nincs kedvezmény</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($child->dietaryRestrictions->isNotEmpty())
                                            <span class="badge badge-primary light">
                                                {{ $child->dietaryRestrictions->pluck('name')->implode(', ') }}
                                            </span>
                                        @else
                                            <span class="text-muted small">Nincs</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $children->links('vendor.pagination.digifood') }}
                </div>

                {{-- A modalokat a táblán kívül, külön ciklusban rendereljük - egy modal
                     div közvetlenül egy tbody elem alatt érvénytelen HTML lenne. --}}
                @foreach($children as $child)
                    @php($guardians = $child->guardians)
                    @php($billing = $child->display_billing_profile)
                    @php($billingGuardian = $child->display_billing_guardian)
                    @php($billingGuardianSlot = $child->display_billing_guardian_slot)
                    @php($billingGuardianNote = $child->display_billing_guardian_note)
                    @php($payerGuardianId = $billingGuardian?->id)
                    @php($mealParticipationStatus = $child->meal_participation_status)
                    @php($upcomingStartsOnLabel = $mealParticipationStatus['starts_on_label'] ?? null)
                    @php($currentDiscount = $child->discountPeriods->first()?->discountType ?? $child->discountType)
                    @php($childReturnQuery = http_build_query(array_filter(array_merge($baseReturnParams, ['opened_child' => $child->id]), fn ($value) => filled($value))))
                    @php($returnHidden = '
                        <input type="hidden" name="return_list" value="'.e($returnList).'">
                        <input type="hidden" name="return_query" value="'.e($childReturnQuery).'">
                    ')

                    <div class="modal fade" id="childModal-{{ $child->id }}" tabindex="-1"
                         aria-labelledby="childModalLabel-{{ $child->id }}" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="childModalLabel-{{ $child->id }}">
                                        Alapadatok – {{ $child->name }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                                </div>
                                <div class="modal-body">

                                    {{-- 1. Alapadatok --}}
                                    <div class="df-modal-section mb-4">
                                        <h6 class="text-uppercase text-muted small mb-2">Alapadatok</h6>
                                        <form method="POST" action="{{ route('dashboard.institution.billing-addresses.children.basics.update', $child) }}" class="row g-2 align-items-end">
                                            @csrf
                                            @method('PUT')
                                            {!! $returnHidden !!}
                                            <div class="col-md-6">
                                                <label class="form-label">Diák neve</label>
                                                <input type="text" name="name" class="form-control form-control-sm" value="{{ $child->name }}" maxlength="191" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Osztály / csoport</label>
                                                <select name="group_name" class="form-control form-control-sm">
                                                    <option value="">Nincs megadva</option>
                                                    @foreach($groups as $groupName)
                                                        <option value="{{ $groupName }}" @selected($child->group_name === $groupName)>{{ $groupName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-check form-switch">
                                                    <input type="hidden" name="active" value="0">
                                                    <input type="checkbox" class="form-check-input" id="active_{{ $child->id }}"
                                                           name="active" value="1" @checked($child->active)>
                                                    <label class="form-check-label" for="active_{{ $child->id }}">Aktív</label>
                                                </div>
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fa fa-save me-1"></i>Alapadatok mentése
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    {{-- 2. Étkezés --}}
                                    <div class="df-modal-section mb-4">
                                        <h6 class="text-uppercase text-muted small mb-2">Étkezés</h6>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div>
                                                @if($mealParticipationStatus['is_current'] ?? false)
                                                    <span class="badge badge-success light">Étkező - ma érvényes beállítás</span>
                                                @elseif($mealParticipationStatus['is_upcoming'] ?? false)
                                                    <span class="badge badge-info light"
                                                          title="{{ $upcomingStartsOnLabel ? $upcomingStartsOnLabel.'-től' : '' }}">
                                                        Étkező (ütemezve)
                                                    </span>
                                                    @if($upcomingStartsOnLabel)
                                                        <div class="small text-muted mt-1">
                                                            {{ $upcomingStartsOnLabel }}-től
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="badge badge-warning light">Nincs beállítva</span>
                                                @endif
                                            </div>
                                            <a href="{{ route('dashboard.institution.children.meal-settings.index', $child) }}?{{ http_build_query(['return_list' => $returnList, 'return_query' => $childReturnQuery]) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-utensils me-1"></i>Étkezési beállítások megnyitása
                                            </a>
                                        </div>
                                    </div>

                                    {{-- 3. Gondviselők: e-mail + aktiváltság + fizető kijelölés --}}
                                    <div class="df-modal-section mb-4">
                                        <h6 class="text-uppercase text-muted small mb-2">Gondviselők</h6>

                                        <form id="guardianEmailsForm-{{ $child->id }}" method="POST"
                                              action="{{ route('dashboard.institution.billing-addresses.children.guardian-emails.update', $child) }}">
                                            @csrf
                                            @method('PUT')
                                            {!! $returnHidden !!}
                                        </form>
                                        <form id="payerForm-{{ $child->id }}" method="POST"
                                              action="{{ route('dashboard.institution.billing-addresses.children.payer.update', $child) }}">
                                            @csrf
                                            @method('PUT')
                                            {!! $returnHidden !!}
                                        </form>

                                        @forelse($guardians as $gIndex => $guardian)
                                            <div class="border rounded p-2 mb-2">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div>
                                                        <strong>{{ $guardian->full_name }}</strong>
                                                        @if(($billingGuardianSlot === 1 && $gIndex === 0) || ($billingGuardianSlot === 2 && $gIndex === 1))
                                                            <span class="badge bg-success ms-1">Számlázó</span>
                                                        @endif
                                                        <span class="badge badge-secondary light">{{ $guardian->pivot->relationship_type ?? 'Gondviselő' }}</span>
                                                        <a href="{{ route('dashboard.institution.parents.edit', $guardian) }}" class="ms-1" title="Teljes adatlap (bankszámla, cím)">
                                                            <i class="fa-solid fa-up-right-from-square"></i>
                                                        </a>
                                                    </div>
                                                    <div class="form-check">
                                                        <input type="radio" class="form-check-input" form="payerForm-{{ $child->id }}"
                                                               name="guardian_id" id="payer_{{ $child->id }}_{{ $guardian->id }}"
                                                               value="{{ $guardian->id }}" @checked($payerGuardianId === $guardian->id)>
                                                        <label class="form-check-label small" for="payer_{{ $child->id }}_{{ $guardian->id }}">
                                                            Ő legyen a fizető
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                                    <input type="hidden" form="guardianEmailsForm-{{ $child->id }}"
                                                           name="guardians[{{ $gIndex }}][id]" value="{{ $guardian->id }}">
                                                    <input type="email" form="guardianEmailsForm-{{ $child->id }}"
                                                           name="guardians[{{ $gIndex }}][email]" class="form-control form-control-sm"
                                                           style="max-width: 260px;" value="{{ $guardian->email }}" maxlength="191"
                                                           placeholder="E-mail cím">
                                                    @if(blank($guardian->email))
                                                        <span class="badge badge-danger light">Hiányzik az e-mail</span>
                                                    @elseif($guardian->user_id)
                                                        <span class="badge badge-success light">Aktivált fiók</span>
                                                    @elseif($guardian->activation_email_sent_at)
                                                        <span class="badge badge-warning light">Meghívó elküldve, nincs aktiválva</span>
                                                    @else
                                                        <span class="badge badge-secondary light">Nincs meghívva</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-muted small mb-2">
                                                Nincs gondviselő kapcsolva ehhez a gyermekhez. Új gondviselő hozzáadása a
                                                <a href="{{ route('dashboard.institution.parents.index') }}">Szülők / gondviselők</a> menüpontban lehetséges.
                                            </div>
                                        @endforelse

                                        @if($guardians->isNotEmpty())
                                            @if($billingGuardianNote)
                                                <div class="alert alert-warning py-2 px-3 small">
                                                    {{ $billingGuardianNote }}
                                                </div>
                                            @endif
                                            <div class="form-check mb-2">
                                                <input type="radio" class="form-check-input" form="payerForm-{{ $child->id }}"
                                                       name="guardian_id" id="payer_{{ $child->id }}_none"
                                                       value="" @checked(is_null($payerGuardianId))>
                                                <label class="form-check-label small" for="payer_{{ $child->id }}_none">
                                                    Nincs kijelölt fizető
                                                </label>
                                            </div>
                                            <div class="text-end">
                                                <button type="submit" form="guardianEmailsForm-{{ $child->id }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fa fa-save me-1"></i>E-mail címek mentése
                                                </button>
                                                <button type="submit" form="payerForm-{{ $child->id }}" class="btn btn-sm btn-primary">
                                                    <i class="fa fa-save me-1"></i>Fizető kijelölése
                                                </button>
                                            </div>
                                        @endif

                                        <div class="small text-muted mt-2">
                                            A gondviselő az e-mail címére kapott meghívóval saját maga is beléphet, és ott
                                            véglegesítheti a számlázási nevét, adószámát és címét - a bankszámla-adatait
                                            csak az intézmény adminisztrátora módosíthatja.
                                        </div>
                                    </div>

                                    {{-- 4. Kedvezmény --}}
                                    <div class="df-modal-section mb-4">
                                        <h6 class="text-uppercase text-muted small mb-2">Kedvezmény</h6>
                                        <form method="POST" action="{{ route('dashboard.institution.children.discount.update', $child) }}"
                                              class="d-flex gap-2 align-items-end flex-wrap">
                                            @csrf
                                            {!! $returnHidden !!}
                                            <div>
                                                <select name="discount_type_id" class="form-control form-control-sm">
                                                    @foreach($discounts as $discount)
                                                        <option value="{{ $discount->id }}" @selected($currentDiscount?->id === $discount->id)>
                                                            {{ $discount->name }} – {{ $discount->percentage }}%
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-save me-1"></i>Kedvezmény mentése
                                            </button>
                                        </form>
                                    </div>

                                    {{-- 5. Étel-érzékenységek (csak megjelenítés + link) --}}
                                    <div class="df-modal-section mb-4">
                                        <h6 class="text-uppercase text-muted small mb-2">Étel-érzékenységek / allergének</h6>
                                        <div class="mb-2">
                                            @forelse($child->dietaryRestrictions as $restriction)
                                                <span class="badge badge-warning light me-1 mb-1">{{ $restriction->name }}</span>
                                            @empty
                                                <span class="text-muted small">Nincs jelölve.</span>
                                            @endforelse
                                        </div>
                                        <a href="{{ route('dashboard.institution.children.edit', $child) }}?{{ http_build_query(['return_list' => $returnList, 'return_query' => $childReturnQuery]) }}" class="small">
                                            Szerkesztés a teljes gyermek-adatlapon <i class="fa-solid fa-up-right-from-square"></i>
                                        </a>
                                    </div>

                                    {{-- 6. Számlázási cím - admin-tartalék, jellemzően a szülő tölti ki saját magának --}}
                                    <div class="df-modal-section">
                                        <h6 class="text-uppercase text-muted small mb-2">Számlázási cím</h6>
                                        <div class="small text-muted mb-2">
                                            Ezt jellemzően a szülő tölti ki a saját Digifood-fiókjában, miután a meghívóval
                                            aktiválta azt. Itt csak vészhelyzeti / ideiglenes rögzítésre szolgál.
                                        </div>
                                        @if($billing)
                                            <form method="POST" action="{{ route('dashboard.institution.billing-addresses.update', $billing) }}"
                                                  class="row g-2 align-items-end">
                                                @csrf
                                                @method('PUT')
                                                {!! $returnHidden !!}
                                                <div class="col-md-3">
                                                    <label class="form-label">Irányítószám</label>
                                                    <input type="text" name="postal_code" class="form-control form-control-sm" value="{{ $billing->postal_code }}" maxlength="10">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Település</label>
                                                    <input type="text" name="city" class="form-control form-control-sm" value="{{ $billing->city }}" maxlength="100">
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Cím</label>
                                                    <input type="text" name="address" class="form-control form-control-sm" value="{{ $billing->address }}" maxlength="255">
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Cím mentése</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="text-muted small">Előbb jelölj ki egy fizetőt fent - utána itt megadhatod a címét.</div>
                                        @endif
                                    </div>

                                    {{-- 7. Bankszámla - kizárólag admin módosíthatja. A szülő saját
                                         fiókjában ("Fiókom") ezek a mezők "prohibited"-ek, ott csak
                                         megjelennek, nem szerkeszthetők - ld. ParentAccountController. --}}
                                    <div class="df-modal-section">
                                        <h6 class="text-uppercase text-muted small mb-2">Bankszámla</h6>
                                        @if($billing && $billingGuardian)
                                            <div class="small text-muted mb-2">
                                                A fizetőnek kijelölt gondviselő ({{ $billingGuardian->full_name }}) bankszámla-adatai.
                                                Ezt kizárólag adminisztrátor módosíthatja - a szülő a saját fiókjában csak látja, nem szerkesztheti.
                                            </div>
                                            <form method="POST" action="{{ route('dashboard.institution.billing-addresses.guardian.bank-account.update', $billingGuardian) }}"
                                                  class="row g-2 align-items-end">
                                                @csrf
                                                @method('PUT')
                                                {!! $returnHidden !!}
                                                <div class="col-md-6">
                                                    <label class="form-label">Számlatulajdonos neve</label>
                                                    <input type="text" name="bank_account_holder" class="form-control form-control-sm" value="{{ $billingGuardian->bank_account_holder }}" maxlength="200">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Bankszámlaszám</label>
                                                    <input type="text" name="bank_account_number" class="form-control form-control-sm" value="{{ $billingGuardian->bank_account_number }}" maxlength="64">
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Bankszámla mentése</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="text-muted small">Előbb jelölj ki egy fizetőt fent - utána itt rögzítheted a bankszámla-adatait.</div>
                                        @endif
                                    </div>

                                </div>
                                <div class="modal-footer justify-content-between">
                                    <a href="{{ route('dashboard.institution.children.edit', $child) }}?{{ http_build_query(['return_list' => $returnList, 'return_query' => $childReturnQuery]) }}" class="btn btn-outline-secondary">
                                        <i class="fa-solid fa-user me-1"></i>Teljes gyermek-adatlap
                                    </a>
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Bezárás</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

            @else

                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => $quality !== '' ? 'fa-solid fa-circle-check' : 'fa-solid fa-clipboard-list',
                    'title' => $quality !== '' ? 'Nincs hiányzó adat' : 'Nincs találat',
                    'text' => $quality !== ''
                        ? 'A keresésnek megfelelő diákok mindegyikénél rendben van ez az adat.'
                        : 'A keresésnek megfelelő gyermek nem található.',
                ])

            @endif

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var params = new URLSearchParams(window.location.search);
    var openedChild = params.get('opened_child');

    if (openedChild) {
        var modalEl = document.getElementById('childModal-' + openedChild);

        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            new window.bootstrap.Modal(modalEl).show();
        }
    }
});
</script>
@endpush
