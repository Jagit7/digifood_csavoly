@extends('layouts.superadmin')

@section('title', 'Mai létszám')

@php
    use App\Services\DailyMealHeadcountService;

    $statusLabels = [
        DailyMealHeadcountService::STATUS_EATING => 'Étkezik',
        DailyMealHeadcountService::STATUS_CANCELLED => 'Lemondva',
        DailyMealHeadcountService::STATUS_NO_ACTIVE_MEAL => 'Kimarad',
        DailyMealHeadcountService::STATUS_NO_SERVICE => 'Nincs étkeztetés',
    ];
    $statusBadgeClasses = [
        DailyMealHeadcountService::STATUS_EATING => 'badge-success',
        DailyMealHeadcountService::STATUS_CANCELLED => 'badge-danger',
        DailyMealHeadcountService::STATUS_NO_ACTIVE_MEAL => 'badge-warning',
        DailyMealHeadcountService::STATUS_NO_SERVICE => 'badge-secondary',
    ];
    $menuCategoryLabels = [
        'A' => 'A menü',
        'B' => 'B menü',
        'DIETARY' => 'Diétás',
    ];
    $menuCategoryBadgeClasses = [
        'A' => 'badge-primary',
        'B' => 'badge-info',
        'DIETARY' => 'badge-success',
    ];
    $weekdayLabel = $date->locale('hu')->translatedFormat('l');
@endphp

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Mai létszám',
        'subtitle' => $institution->name.' · '.$date->format('Y. m. d.').' · '.mb_convert_case($weekdayLabel, MB_CASE_TITLE, 'UTF-8'),
        'buttons' => $headerButtons,
    ])

    <div class="row mt-2 df-stats-row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes étkező',
            'value' => $stats['daily_eaters'] ?? 0,
            'subtitle' => 'Ténylegesen számolandó adagok',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Gyermekek',
            'value' => $stats['child_daily_eaters'] ?? 0,
            'subtitle' => 'Gyermek étkezők',
            'icon' => 'fa-solid fa-children',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lemondott étkezések',
            'value' => $stats['cancelled_meals'] ?? 0,
            'subtitle' => 'Szabályos napi lemondások',
            'icon' => 'fa-solid fa-ban',
            'color' => 'red',
        ])
        @if($stats['has_ab_menu'] ?? false)
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'A menü adag',
                'value' => $stats['menu_a_count'] ?? 0,
                'subtitle' => 'Gyermekek: alapértelmezett vagy explicit A választás',
                'icon' => 'fa-solid fa-a',
                'color' => 'orange',
            ])
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'B menü adag',
                'value' => $stats['menu_b_count'] ?? 0,
                'subtitle' => 'Gyermekek: explicit B választás',
                'icon' => 'fa-solid fa-b',
                'color' => 'purple',
            ])
        @endif
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Diétás adag',
            'value' => $stats['dietary_count'] ?? 0,
            'subtitle' => 'Gyermek + dolgozó diétás étkezők',
            'icon' => 'fa-solid fa-leaf',
            'color' => 'orange',
        ])
    </div>
    
    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                @include('layouts.partials.components.ui.period-navigation', [
                    'items' => [
                        [
                            'url' => route('dashboard.institution.daily.today-counts', array_merge($navigationQuery, ['date' => $previousDate])),
                            'label' => 'Előző nap',
                            'value' => \Carbon\CarbonImmutable::parse($previousDate)->locale('hu')->translatedFormat('Y. m. d.'),
                            'icon' => 'fa-solid fa-chevron-left',
                            'icon_position' => 'left',
                        ],
                        [
                            'url' => route('dashboard.institution.daily.today-counts', array_merge($navigationQuery, ['date' => $date->toDateString()])),
                            'label' => $isTodaySelected ? 'Ma' : 'Kiválasztott nap',
                            'value' => $date->locale('hu')->translatedFormat('Y. m. d.'),
                            'icon' => 'fa-solid fa-calendar-day',
                            'icon_position' => 'left',
                            'active' => true,
                        ],
                        [
                            'url' => route('dashboard.institution.daily.today-counts', array_merge($navigationQuery, ['date' => $nextDate])),
                            'label' => 'Következő nap',
                            'value' => \Carbon\CarbonImmutable::parse($nextDate)->locale('hu')->translatedFormat('Y. m. d.'),
                            'icon' => 'fa-solid fa-chevron-right',
                            'icon_position' => 'right',
                        ],
                    ],
                ])

                <span class="badge badge-info light px-3 py-2">
                    <i class="fa-solid fa-notes-medical me-1"></i>
                    Diétás étkezők ezen a napon: <strong class="ms-1">{{ $stats['dietary_eaters'] ?? 0 }}</strong>
                </span>
                @if(($stats['employee_daily_eaters'] ?? 0) > 0)
                    <span class="badge badge-secondary light px-3 py-2">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Az A/B menü bontás továbbra is csak a gyermekeket tartalmazza.
                    </span>
                @endif
            </div>

            <form method="GET" action="{{ route('dashboard.institution.daily.today-counts') }}" class="mt-4">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Dátum</label>
                        <input type="date" name="date" class="form-control" value="{{ request('date', $date->toDateString()) }}">
                    </div>
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Keresés</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Gyermek neve vagy azonosítója">
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">{{ $isKindergarten ? 'Csoport' : 'Osztály / csoport' }}</label>
                        <select name="group_name" class="form-control">
                            <option value="">Összes</option>
                            @foreach($groups as $group)
                                <option value="{{ $group }}" @selected(request('group_name') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Napi státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Összes</option>
                            @foreach($statusOptions as $option)
                                <option value="{{ $option }}" @selected(request('status') === $option)>{{ $statusLabels[$option] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Diéta</label>
                        <select name="dietary_filter" class="form-control">
                            @foreach($dietaryFilterOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('dietary_filter', '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-12 d-flex gap-2 justify-content-xl-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Szűrés
                        </button>
                        @if(request()->hasAny(['date', 'search', 'group_name', 'status', 'dietary_filter']))
                            <a href="{{ route('dashboard.institution.daily.today-counts') }}" class="btn btn-light" title="Szűrők törlése">
                                <i class="fa-solid fa-xmark me-1"></i>
                                Szűrők törlése
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(!$dayMeta['is_service_day'])
        <div class="alert alert-warning mb-4">
            <strong>Erre a napra nincs intézményi étkeztetés.</strong>
            @if($dayMeta['school_break'])
                Szünet oka: {{ $dayMeta['school_break']->title }}.
            @elseif($dayMeta['holiday'])
                Ünnepnap: {{ $dayMeta['holiday'] }}.
            @elseif($dayMeta['working_day'])
                Ez a nap külön rögzített munkanap, de a rendszerben nem étkezési napként szerepel.
            @else
                A meglévő naptárszabályok szerint ez nem szolgáltatási nap.
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h4 class="card-title mb-0">{{ $isKindergarten ? 'Óvodai gyermeklista' : 'Napi gyermeklista' }}</h4>
            <span class="text-muted">Találatok: {{ $rows->total() }}</span>
            <a href="{{ route('dashboard.institution.daily.today-counts.print', $printRouteParameters) }}"
               class="btn btn-outline-primary btn-sm"
               target="_blank"
               rel="noopener">
                <i class="fa-solid fa-print me-1"></i>Lista nyomtatása
            </a>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover table-responsive-md align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Gyermek neve</th>
                        <th>{{ $isKindergarten ? 'Csoport' : 'Osztály / csoport' }}</th>
                        <th>Menücsomag</th>
                        <th>Diéta</th>
                        <th>Effektív menü</th>
                        <th>Napi státusz</th>
                        <th>Megjegyzés</th>
                        <th class="text-end">Művelet</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php
                            $child = $row['child'];
                            $mealSetting = $row['meal_setting'];
                            $allergens = $child->dietaryRestrictions->where('type', $allergenType)->pluck('name')->values();
                            $otherRestrictions = $child->dietaryRestrictions->where('type', '!=', $allergenType)->pluck('name')->values();
                            $notes = collect();

                            if ($row['status'] === DailyMealHeadcountService::STATUS_CANCELLED) {
                                $notes->push('Szabályosan lemondva');
                            }

                            if ($row['status'] === DailyMealHeadcountService::STATUS_NO_SERVICE) {
                                $notes->push('Az adott nap nem szolgáltatási nap');
                            }

                            if (!$mealSetting) {
                                $notes->push('Nincs érvényes étkezési beállítás');
                            }
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $child->name }}</strong>
                                @if($child->educational_identifier)
                                    <div class="small text-muted">{{ $child->educational_identifier }}</div>
                                @endif
                            </td>
                            <td>
                                @if($child->group_name)
                                    <span class="badge badge-primary light">{{ $child->group_name }}</span>
                                @else
                                    <span class="text-muted">Nincs megadva</span>
                                @endif
                            </td>
                            <td class="text-wrap">
                                @if(!$mealSetting)
                                    <span class="text-muted">—</span>
                                @elseif($mealSetting->mode === \App\Models\StudentMealSetting::MODE_INSTITUTION_DEFAULT)
                                    <strong>{{ $modeLabels[$mealSetting->mode] ?? $mealSetting->mode }}</strong>
                                    <div class="small text-muted">{{ $defaultPackage?->name ?? 'Nincs aktív alapértelmezett csomag' }}</div>
                                @elseif($mealSetting->mode === \App\Models\StudentMealSetting::MODE_PACKAGE)
                                    <strong>{{ $mealSetting->mealPackage?->name ?? '—' }}</strong>
                                    <div class="small text-muted">{{ $modeLabels[$mealSetting->mode] ?? $mealSetting->mode }}</div>
                                @else
                                    <strong>{{ $modeLabels[$mealSetting->mode] ?? $mealSetting->mode }}</strong>
                                    <div class="small text-muted">
                                        {{ $mealSetting->mealTypes->map(fn ($mealType) => $mealType->mealType->name)->implode(', ') ?: '—' }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($allergens->isEmpty() && $otherRestrictions->isEmpty())
                                    <span class="text-muted">Nincs</span>
                                @else
                                    @foreach($allergens as $restriction)
                                        <span class="badge badge-warning light me-1 mb-1">{{ $restriction }}</span>
                                    @endforeach
                                    @foreach($otherRestrictions as $restriction)
                                        <span class="badge badge-info light me-1 mb-1">{{ $restriction }}</span>
                                    @endforeach
                                @endif
                            </td>
                            <td>
                                @if($row['effective_menu_category'])
                                    <span class="badge {{ $menuCategoryBadgeClasses[$row['effective_menu_category']] ?? 'badge-secondary' }} light">
                                        {{ $menuCategoryLabels[$row['effective_menu_category']] ?? $row['effective_menu_category'] }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $statusBadgeClasses[$row['status']] ?? 'badge-secondary' }} light">
                                    {{ $statusLabels[$row['status']] ?? $row['status'] }}
                                </span>
                            </td>
                            <td>
                                @if($notes->isEmpty())
                                    <span class="text-muted">—</span>
                                @else
                                    {{ $notes->implode(' · ') }}
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    @if($isKindergarten)
                                        <a href="{{ route('dashboard.institution.daily.today-counts.attendance-sheet', array_merge($printRouteParameters, ['group_name' => $child->group_name])) }}"
                                           class="btn btn-xs btn-outline-primary"
                                           target="_blank"
                                           rel="noopener"
                                           title="Jelenléti ív nyomtatása">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('dashboard.institution.children.meal-settings.index', $child) }}"
                                           class="btn btn-xs btn-outline-primary"
                                           title="Étkezési beállítások">
                                            <i class="fa-solid fa-sliders"></i>
                                        </a>
                                    @endif

                                    <a href="{{ route('dashboard.institution.children.edit', $child) }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Gyermek adatlap">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                Nincs a szűrésnek megfelelő gyermek ezen az oldalon.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
                <div class="mt-4">
                    {{ $rows->links('vendor.pagination.digifood') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
