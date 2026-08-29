@extends('layouts.superadmin')

@section('title', 'Étkező gyermekek')

@push('styles')
    <style>
        .table-compact th,
        .table-compact td {
            padding-top: .4rem !important;
            padding-bottom: .4rem !important;
        }
        .table-compact .badge {
            white-space: nowrap;
        }
        .wide-table-wrap {
            overflow-x: auto;
        }
        .wide-table th,
        .wide-table td {
            white-space: nowrap;
        }
        .wide-table .sticky-col {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fff;
        }
        .wide-table .sticky-col-2 {
            position: sticky;
            left: 50px;
            z-index: 2;
            background: #fff;
            box-shadow: 2px 0 4px -2px rgba(0,0,0,.15);
            min-width: 160px;
        }
        .wide-table thead .sticky-col,
        .wide-table thead .sticky-col-2 {
            z-index: 3;
            background: #f4f5f7;
        }
        .wide-table tbody tr:hover td.sticky-col,
        .wide-table tbody tr:hover td.sticky-col-2 {
            background: #f1eefb;
        }
        .discount-inline-select {
            min-width: 150px;
            font-size: 12.5px;
        }
    </style>
@endpush

@section('content')
@php
    $selectedFilters = request()->only(['search', 'group_name', 'meal_status', 'status', 'diet_filter', 'discount_filter', 'page']);
    $returnList = $listState['list'] ?? \App\Services\Navigation\ChildListReturnService::LIST_EATERS;
    $returnQuery = $listState['query'] ?? request()->getQueryString() ?? '';
    $returnPayload = http_build_query(['return_list' => $returnList, 'return_query' => $returnQuery]);
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Étkező gyermekek',
        'subtitle' => 'Az intézmény teljes gyermeklistájából külön kezelhető, ki vesz részt az étkeztetésben.',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Étkező gyermekek',
            'value' => $stats['participants'],
            'subtitle' => 'Ma érvényes beállítással',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Nem étkező gyermekek',
            'value' => $stats['non_participants'],
            'subtitle' => 'Nincs aktuális beállításuk',
            'icon' => 'fa-solid fa-user-slash',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív gyermekek',
            'value' => $stats['active_children'],
            'subtitle' => 'Intézményi aktív státusz',
            'icon' => 'fa-solid fa-user-check',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Alapértelmezett csomag',
            'value' => $stats['default_package'],
            'subtitle' => 'Bekapcsoláskor ezt használja a rendszer',
            'icon' => 'fa-solid fa-star',
            'color' => 'purple',
        ])
    </div>

    <div class="alert alert-info">
        A nem étkező gyermek ettől még maradhat aktív intézményi gyermek. Az étkezési részvétel és az intézményi aktív státusz külön kezelhető.
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.children.meal-participants.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Név vagy azonosító</label>
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
                                <option value="{{ $group }}" @selected(request('group_name') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Étkezési státusz</label>
                        <select name="meal_status" class="form-control">
                            <option value="">Összes</option>
                            <option value="participant" @selected(request('meal_status') === 'participant')>Étkező</option>
                            <option value="non_participant" @selected(request('meal_status') === 'non_participant')>Nem étkező</option>
                        </select>
                    </div>

                    <div class="col-xl-1 col-lg-2 mb-3">
                        <label class="form-label">Intézményi státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Minden állapot</option>
                            <option value="active" @selected(request('status') === 'active')>Aktív</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inaktív</option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Allergia / érzékenység</label>
                        <select name="diet_filter" class="form-control">
                            <option value="">Összes gyermek</option>
                            <option value="any_allergen" @selected(request('diet_filter') === 'any_allergen')>Bármilyen allergia</option>
                            <option value="any_intolerance" @selected(request('diet_filter') === 'any_intolerance')>Bármilyen érzékenység</option>
                            @if($allergens->isNotEmpty())
                                <optgroup label="Allergia">
                                    @foreach($allergens as $restriction)
                                        <option value="restriction_{{ $restriction->id }}" @selected(request('diet_filter') === 'restriction_' . $restriction->id)>
                                            {{ $restriction->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                            @if($intolerances->isNotEmpty())
                                <optgroup label="Érzékenység">
                                    @foreach($intolerances as $restriction)
                                        <option value="restriction_{{ $restriction->id }}" @selected(request('diet_filter') === 'restriction_' . $restriction->id)>
                                            {{ $restriction->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>

                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Kedvezmény</label>
                        <select name="discount_filter" class="form-control">
                            <option value="">Összes gyermek</option>
                            @foreach($discountTypes as $discountType)
                                <option value="{{ $discountType->id }}" @selected(request('discount_filter') == $discountType->id)>
                                    {{ $discountType->name }} – {{ $discountType->percentage }}%
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Csak az aktuálisan étkező gyermekek közül szűr.</small>
                    </div>

                    <div class="col-xl-1 col-lg-1 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        @if(request()->hasAny(['search', 'group_name', 'meal_status', 'status', 'diet_filter', 'discount_filter']))
                            <a href="{{ route('dashboard.institution.children.meal-participants.index') }}"
                               class="btn btn-light"
                               title="Szűrők törlése">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Tömeges műveletek</h4>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-xl-6">
                    <form method="POST"
                          action="{{ route('dashboard.institution.children.meal-participants.bulk-enable') }}"
                          class="bulk-child-form"
                          data-empty-message="Jelölj ki legalább egy gyermeket a tömeges bekapcsoláshoz.">
                        @csrf
                        @foreach($selectedFilters as $filterName => $filterValue)
                            @if(filled($filterValue))
                                <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                            @endif
                        @endforeach
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Étkeztetés bekapcsolása</h5>
                            <p class="text-muted small mb-3">A kijelölt, jelenleg nem étkező gyermekek institution_default módban kerülnek bekapcsolásra.</p>
                            <label class="form-label" for="bulk_enable_valid_from">Kezdődátum</label>
                            <input id="bulk_enable_valid_from"
                                   type="date"
                                   name="valid_from"
                                   class="form-control mb-3 @error('valid_from') is-invalid @enderror"
                                   value="{{ old('valid_from', $today) }}"
                                   required>
                            @error('valid_from') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-power-off me-1"></i>Bekapcsolás a kijelölteknek
                            </button>
                        </div>
                    </form>
                </div>

                <div class="col-xl-6">
                    <form method="POST"
                          action="{{ route('dashboard.institution.children.meal-participants.bulk-disable') }}"
                          class="bulk-child-form"
                          data-empty-message="Jelölj ki legalább egy gyermeket a tömeges megszüntetéshez."
                          data-confirm-title="Biztosan meg szeretné megszüntetni a kijelölt gyermekek étkeztetését?"
                          data-confirm-text="A kijelölt gyermekek aktuális étkezési időszaka lezárul, de a rekordok megmaradnak."
                          data-confirm-button-text="Igen, megszüntetem">
                        @csrf
                        @foreach($selectedFilters as $filterName => $filterValue)
                            @if(filled($filterValue))
                                <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                            @endif
                        @endforeach
                        <div class="border rounded p-3 h-100">
                            <h5 class="mb-2">Étkeztetés megszüntetése</h5>
                            <p class="text-muted small mb-3">Csak a jelenleg étkező gyermekek aktuális időszakát zárja le, rekordtörlés nélkül.</p>
                            <label class="form-label" for="bulk_disable_last_meal_day">Utolsó étkezési nap</label>
                            <input id="bulk_disable_last_meal_day"
                                   type="date"
                                   name="last_meal_day"
                                   class="form-control mb-3 @error('last_meal_day') is-invalid @enderror"
                                   value="{{ old('last_meal_day', $today) }}"
                                   required>
                            @error('last_meal_day') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="fa-solid fa-ban me-1"></i>Megszüntetés a kijelölteknél
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Gyermekek</h4>
            <span class="text-muted">Találatok: {{ $children->total() }}</span>
        </div>
        <div class="card-body">
            @if($children->count())
                {{--
                    A táblázat oldalanként max. 50 gyermeket mutat
                    ($children->total() lehet ennél sokkal nagyobb). A fejléc
                    checkbox csak a DOM-ban éppen látható sorokat tudja
                    bejelölni - enélkül a figyelmeztetés nélkül könnyű azt
                    hinni, hogy "az összes kijelölése" tényleg mindenkire
                    vonatkozik, miközben a többi oldalon lévő gyermeknél a
                    tömeges Bekapcsolás/Megszüntetés némán semmit nem csinál.
                --}}
                @if($children->total() > $children->count())
                    <div id="select-all-matching-bar" class="alert alert-light border d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span id="select-all-matching-prompt">
                            <i class="fa-solid fa-circle-info me-1 text-muted"></i>
                            Ez az oldal csak {{ $children->count() }} gyermeket mutat a {{ $children->total() }} találatból.
                            Az "összes kijelölése" checkbox alapból csak ezt az oldalt jelöli ki.
                            <button type="button" id="select-all-matching-btn" class="btn btn-link btn-sm p-0 align-baseline">
                                Mind a {{ $children->total() }} találat kijelölése a tömeges művelethez
                            </button>
                        </span>
                        <span id="select-all-matching-banner" class="d-none fw-semibold text-success">
                            <i class="fa-solid fa-circle-check me-1"></i>
                            Mind a {{ $children->total() }} találat kijelölve (nem csak ez az oldal).
                            <button type="button" id="clear-select-all-matching-btn" class="btn btn-link btn-sm p-0 align-baseline">
                                Mégsem, csak ez az oldal
                            </button>
                        </span>
                    </div>
                @endif
                <div class="wide-table-wrap">
                    <table class="table table-hover align-middle table-compact wide-table">
                        <thead>
                        <tr>
                            <th width="50" class="sticky-col">
                                <input type="checkbox" class="form-check-input" id="toggle-all-children">
                            </th>
                            <th class="sticky-col-2">Gyermek neve</th>
                            <th width="260" class="text-end">Műveletek</th>
                            <th>Oktatási azonosító</th>
                            <th>Osztály</th>
                            <th>Intézményi státusz</th>
                            <th>Étkezési státusz</th>
                            <th>Érvényes ettől</th>
                            <th>Aktuális menübeállítás</th>
                            <th>Kedvezmény</th>
                            <th>Allergia / érzékenység</th>
                            <th>Rendszeres lemondás</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($children as $child)
                            @php($currentMealSetting = $child->getRelation('currentMealSetting'))
                            @php($upcomingMealSetting = $child->getRelation('upcomingMealSetting'))
                            @php($mealParticipationStatus = $child->meal_participation_status)
                            @php($isUpcomingMealRelationship = (bool) ($mealParticipationStatus['is_upcoming'] ?? false))
                            @php($activeDiscountType = $child->discountTypeForDate($today))
                            @php($mealSettingSummary = $child->getRelation('mealSettingSummary'))
                            <tr>
                                <td class="sticky-col">
                                    <input type="checkbox"
                                           class="form-check-input child-selector"
                                           value="{{ $child->id }}">
                                </td>
                                <td class="sticky-col-2"><strong>{{ $child->name }}</strong></td>
                                <td class="text-end">
                                    @if($currentMealSetting)
                                        <div class="d-flex justify-content-end flex-wrap gap-2">
                                            <a href="{{ route('dashboard.institution.children.meal-settings.index', $child) }}?{{ $returnPayload }}"
                                               class="btn btn-xs btn-outline-primary"
                                               title="Étkezési beállítások">
                                                <i class="fa-solid fa-sliders me-1"></i>Beállítások
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('dashboard.institution.children.meal-participants.disable', $child) }}"
                                                  class="d-inline-flex align-items-center gap-2 confirm-form"
                                                  data-title="Biztosan meg szeretné szüntetni ezt a beállítást?"
                                                  data-text="A gyermek étkeztetési időszaka az utolsó étkezési nappal lezárul."
                                                  data-confirm-button-text="Igen, megszüntetem">
                                                @csrf
                                                @foreach($selectedFilters as $filterName => $filterValue)
                                                    @if(filled($filterValue))
                                                        <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                                                    @endif
                                                @endforeach
                                                <input type="date"
                                                       name="last_meal_day"
                                                       value="{{ $today }}"
                                                       class="form-control form-control-sm"
                                                       style="max-width: 135px;"
                                                       required>
                                                <button type="submit"
                                                        class="btn btn-xs btn-outline-danger"
                                                        title="Étkeztetés megszüntetése">
                                                    <i class="fa-solid fa-ban me-1"></i>Megszüntetés
                                                </button>
                                            </form>
                                        </div>
                                    @elseif($isUpcomingMealRelationship)
                                        {{--
                                            Ilyenkor MÁR VAN egy jövőben induló, rögzített
                                            beállítása a gyermeknek (ld. $isUpcomingMealRelationship
                                            fent) - a gyors "Bekapcsolás" form itt garantáltan az
                                            "átfedésben van" hibával futna, hiszen a rendszer nem
                                            enged egy már ütemezett jövőbeli időszak elé/mellé egy
                                            újabb gyors-bekapcsolást felvenni. Ezért közvetlenül a már
                                            meglévő ütemezett beállítás szerkesztő oldalára visznek,
                                            ahol a kezdődátum vagy a mód/csomag közvetlenül javítható -
                                            a rendszer az esetleges szomszédos időszakokat automatikusan
                                            lezárja/összeköti az új adatokkal.
                                        --}}
                                        <a href="{{ route('dashboard.institution.children.meal-settings.edit', [$child, $upcomingMealSetting]) }}?{{ $returnPayload }}"
                                           class="btn btn-xs btn-outline-info"
                                           title="A már ütemezett kezdés dátuma itt javítható, vagy itt vehető fel helyette másik beállítás">
                                            <i class="fa-solid fa-calendar-days me-1"></i>Ütemezés kezelése
                                        </a>
                                    @else
                                        <form method="POST"
                                              action="{{ route('dashboard.institution.children.meal-participants.enable', $child) }}"
                                              class="d-inline-flex align-items-center gap-2">
                                            @csrf
                                            @foreach($selectedFilters as $filterName => $filterValue)
                                                @if(filled($filterValue))
                                                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                                                @endif
                                            @endforeach
                                            <input type="date"
                                                   name="valid_from"
                                                   value="{{ $today }}"
                                                   class="form-control form-control-sm"
                                                   style="max-width: 135px;"
                                                   required>
                                            <button type="submit"
                                                    class="btn btn-xs btn-outline-success"
                                                    title="Étkeztetés bekapcsolása">
                                                <i class="fa-solid fa-power-off me-1"></i>Bekapcsolás
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td>{{ $child->educational_identifier ?: '—' }}</td>
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
                                <td>
                                    @if($currentMealSetting)
                                        <span class="badge badge-success light">Étkező</span>
                                    @elseif($isUpcomingMealRelationship)
                                        <span class="badge badge-info light">Étkező (ütemezve)</span>
                                    @else
                                        <span class="badge badge-secondary light">Nem étkező</span>
                                    @endif
                                </td>
                                <td>
                                    @if($currentMealSetting)
                                        {{ $currentMealSetting->valid_from->format('Y.m.d.') }}
                                    @elseif($isUpcomingMealRelationship)
                                        {{ $upcomingMealSetting?->valid_from?->format('Y.m.d.') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{--
                                        Egy soros, minél rövidebb cella: csak a konkrét tartalom
                                        (csomagnév, vagy az egyedi étkezések listája) jelenik meg -
                                        az általános mód-címke (pl. "Menücsomag") elhagyva, mert az
                                        nem mond többet a konkrét névnél; csak akkor esik vissza rá,
                                        ha nincs megadható konkrét részlet. A teljes szöveg hover-
                                        tooltipben (title) érhető el, ha rövidítve lenne kiírva. Az
                                        összeállítást (mealSettingSummary) a controller végzi. Az
                                        "ütemezve" jelzést itt szándékosan NEM ismételjük meg - azt
                                        már az Étkezési státusz oszlop badge-je mutatja ugyanebben a
                                        sorban.
                                    --}}
                                    @if($mealSettingSummary)
                                        @php($mealSettingText = $mealSettingSummary['detail'] ?: $mealSettingSummary['mode_label'])
                                        <span title="{{ $mealSettingText }}">{{ Str::limit($mealSettingText, 28) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php($discountOptions = ($activeDiscountType && ! $discountTypes->contains('id', $activeDiscountType->id)) ? $discountTypes->concat([$activeDiscountType]) : $discountTypes)
                                    <form method="POST" action="{{ route('dashboard.institution.children.discount.update', $child) }}">
                                        @csrf
                                        <input type="hidden" name="return_list" value="{{ $returnList }}">
                                        <input type="hidden" name="return_query" value="{{ $returnQuery }}">
                                        <select name="discount_type_id" class="form-control form-control-sm discount-inline-select" onchange="this.form.submit()" title="Kedvezmény módosítása">
                                            @foreach($discountOptions as $discountOption)
                                                <option value="{{ $discountOption->id }}" @selected($activeDiscountType && $activeDiscountType->id === $discountOption->id)>
                                                    {{ $discountOption->percentage }}% – {{ Str::limit($discountOption->name, 18) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    @forelse($child->dietaryRestrictions as $restriction)
                                        <span class="badge {{ $restriction->type === 'allergen' ? 'badge-warning' : 'badge-info' }} light me-1 mb-1">
                                            {{ $restriction->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted">Nincs</span>
                                    @endforelse
                                </td>
                                <td>
                                    @forelse($child->recurringCancellationRules as $rule)
                                        <span class="badge {{ $rule->starts_on->isFuture() ? 'badge-info' : 'badge-warning' }} light me-1 mb-1"
                                              title="{{ $rule->starts_on->format('Y.m.d.') }} – {{ $rule->ends_on?->format('Y.m.d.') ?? 'visszavonásig' }}">
                                            {{ $weekdayLabels[$rule->weekday] ?? 'Ismeretlen nap' }}
                                            @if($rule->starts_on->isFuture())
                                                – {{ $rule->starts_on->format('m.d.') }}-től
                                            @endif
                                        </span>
                                    @empty
                                        <span class="text-muted">Nincs</span>
                                    @endforelse
                                </td>
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
                    'icon' => 'fa-solid fa-utensils',
                    'title' => 'Nincs a szűrésnek megfelelő gyermek',
                    'text' => 'Módosítsd a keresési feltételeket vagy töröld a szűrőket.',
                ])
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleAll = document.getElementById('toggle-all-children');
    const selectors = Array.from(document.querySelectorAll('.child-selector'));
    const bulkForms = Array.from(document.querySelectorAll('.bulk-child-form'));

    const selectAllMatchingBtn = document.getElementById('select-all-matching-btn');
    const clearSelectAllMatchingBtn = document.getElementById('clear-select-all-matching-btn');
    const selectAllMatchingPrompt = document.getElementById('select-all-matching-prompt');
    const selectAllMatchingBanner = document.getElementById('select-all-matching-banner');
    let selectAllMatchingActive = false;

    function setSelectAllMatching(active) {
        selectAllMatchingActive = active;

        selectors.forEach((checkbox) => {
            checkbox.checked = active || checkbox.checked;
            checkbox.disabled = active;
        });

        if (toggleAll) {
            toggleAll.checked = active || toggleAll.checked;
            toggleAll.disabled = active;
        }

        if (selectAllMatchingPrompt) {
            selectAllMatchingPrompt.classList.toggle('d-none', active);
        }

        if (selectAllMatchingBanner) {
            selectAllMatchingBanner.classList.toggle('d-none', !active);
        }
    }

    if (selectAllMatchingBtn) {
        selectAllMatchingBtn.addEventListener('click', function () {
            setSelectAllMatching(true);
        });
    }

    if (clearSelectAllMatchingBtn) {
        clearSelectAllMatchingBtn.addEventListener('click', function () {
            setSelectAllMatching(false);
            selectors.forEach((checkbox) => { checkbox.checked = false; });
            if (toggleAll) {
                toggleAll.checked = false;
            }
        });
    }

    if (toggleAll) {
        toggleAll.addEventListener('change', function () {
            selectors.forEach((checkbox) => {
                checkbox.checked = toggleAll.checked;
            });
        });
    }

    selectors.forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            if (!toggleAll) {
                return;
            }

            toggleAll.checked = selectors.length > 0 && selectors.every((item) => item.checked);
        });
    });

    bulkForms.forEach((form) => {
        form.addEventListener('submit', function (event) {
            form.querySelectorAll('input[name="child_ids[]"]').forEach((input) => input.remove());

            const existingFlag = form.querySelector('input[name="select_all_matching"]');
            if (existingFlag) {
                existingFlag.remove();
            }

            if (selectAllMatchingActive) {
                const flagInput = document.createElement('input');
                flagInput.type = 'hidden';
                flagInput.name = 'select_all_matching';
                flagInput.value = '1';
                form.appendChild(flagInput);
            } else {
                const selectedIds = selectors
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.value);

                if (selectedIds.length === 0) {
                    event.preventDefault();
                    Swal.fire({
                        title: 'Nincs kijelölés',
                        text: form.dataset.emptyMessage || 'Jelölj ki legalább egy gyermeket.',
                        icon: 'warning',
                        confirmButtonText: 'Rendben',
                        confirmButtonColor: '#886CC0'
                    });
                    return;
                }

                selectedIds.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'child_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
            }

            if (form.dataset.confirmTitle) {
                event.preventDefault();

                Swal.fire({
                    title: form.dataset.confirmTitle,
                    text: selectAllMatchingActive
                        ? 'Ez a MIND A {{ $children->total() }} találatra vonatkozik, nem csak az ezen az oldalon látható gyermekekre. ' + (form.dataset.confirmText || 'A művelet nem vonható vissza.')
                        : (form.dataset.confirmText || 'A művelet nem vonható vissza.'),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: form.dataset.confirmButtonText || 'Igen, folytatom',
                    cancelButtonText: 'Mégsem',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
        });
    });
});
</script>
@endsection
