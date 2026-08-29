@extends('layouts.superadmin')

@section('title', 'Diétás lista')

@php
    $weekdayLabel = $date->locale('hu')->translatedFormat('l');
@endphp

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Diétás lista',
        'subtitle' => $institution->name.' · '.$date->format('Y. m. d.').' · '.mb_convert_case($weekdayLabel, MB_CASE_TITLE, 'UTF-8'),
        'buttons' => [
            [
                'url' => route('dashboard.institution.daily.today-counts', $filterQuery),
                'class' => 'btn btn-light',
                'icon' => 'fa-solid fa-arrow-left',
                'text' => 'Vissza a Mai létszámhoz',
            ],
            [
                'url' => route('dashboard.institution.daily.dietary-children.print', $filterQuery),
                'class' => 'btn btn-outline-primary',
                'icon' => 'fa-solid fa-print',
                'text' => 'Nyomtatás',
                'target' => '_blank',
                'rel' => 'noopener',
            ],
            [
                'url' => route('dashboard.institution.daily.dietary-children.export', $filterQuery),
                'class' => 'btn btn-outline-success',
                'icon' => 'fa-solid fa-file-csv',
                'text' => 'CSV export',
            ],
        ],
    ])

    <div class="row mt-2 df-stats-row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Mai diétás étkezők',
            'value' => $dietaryStats['daily_dietary_eaters'] ?? 0,
            'subtitle' => 'A kiválasztott napon ténylegesen étkezők',
            'icon' => 'fa-solid fa-notes-medical',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lemondott diétás étkezések',
            'value' => $dietaryStats['cancelled_dietary_meals'] ?? 0,
            'subtitle' => 'Lemondott diétás gyermekek a napon',
            'icon' => 'fa-solid fa-ban',
            'color' => 'red',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Diétás gyermekek összesen',
            'value' => $dietaryStats['total_dietary_children'] ?? 0,
            'subtitle' => 'Aktív, diétás gyermekek az intézményben',
            'icon' => 'fa-solid fa-users',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Diétatípusok száma',
            'value' => $dietaryStats['diet_type_count'] ?? 0,
            'subtitle' => 'A napi listában előforduló eltérő típusok',
            'icon' => 'fa-solid fa-layer-group',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-body p-4">
            @include('layouts.partials.components.ui.period-navigation', [
                'items' => [
                    [
                        'url' => route('dashboard.institution.daily.dietary-children', array_merge($navigationQuery, ['date' => $previousDate])),
                        'label' => 'Előző nap',
                        'value' => \Carbon\CarbonImmutable::parse($previousDate)->locale('hu')->translatedFormat('Y. m. d.'),
                        'icon' => 'fa-solid fa-chevron-left',
                        'icon_position' => 'left',
                    ],
                    [
                        'url' => route('dashboard.institution.daily.dietary-children', array_merge($navigationQuery, ['date' => $date->toDateString()])),
                        'label' => $isTodaySelected ? 'Ma' : 'Kiválasztott nap',
                        'value' => $date->locale('hu')->translatedFormat('Y. m. d.'),
                        'icon' => 'fa-solid fa-calendar-day',
                        'icon_position' => 'left',
                        'active' => true,
                    ],
                    [
                        'url' => route('dashboard.institution.daily.dietary-children', array_merge($navigationQuery, ['date' => $nextDate])),
                        'label' => 'Következő nap',
                        'value' => \Carbon\CarbonImmutable::parse($nextDate)->locale('hu')->translatedFormat('Y. m. d.'),
                        'icon' => 'fa-solid fa-chevron-right',
                        'icon_position' => 'right',
                    ],
                ],
            ])
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('dashboard.institution.daily.dietary-children') }}">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Dátum</label>
                        <input type="date" name="date" class="form-control" value="{{ request('date', $date->toDateString()) }}">
                    </div>
                    <div class="col-xl-4 col-lg-4 mb-3">
                        <label class="form-label">Keresés</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Gyermek vagy dolgozó neve">
                    </div>
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Osztály / csoport</label>
                        <select name="group_name" class="form-control">
                            <option value="">Összes</option>
                            @foreach($groups as $group)
                                <option value="{{ $group }}" @selected(request('group_name') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 mb-3 d-flex gap-2 justify-content-xl-end">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Szűrés
                        </button>
                        @if(request()->hasAny(['date', 'search', 'group_name']))
                            <a href="{{ route('dashboard.institution.daily.dietary-children', ['date' => $date->toDateString()]) }}"
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

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Diétás étkezők listája</h4>
            <span class="text-muted">Találatok: {{ $rows->total() }}</span>
        </div>
        <div class="card-body p-4">
            @if($rows->total() === 0)
                <div class="text-center py-5 text-muted">
                    A kiválasztott napra nincs diétás étkező.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Név</th>
                            <th>Osztály / csoport</th>
                            <th>Menücsomag</th>
                            <th>Diéta</th>
                            <th>Napi menü</th>
                            <th>Megjegyzés</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['display_name'] }}</strong>
                                    @if($row['display_identifier'])
                                        <div class="small text-muted">{{ $row['display_identifier'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($row['type'] === 'employee')
                                        <span class="badge badge-warning light">Dolgozó</span>
                                    @elseif($row['display_group'])
                                        <span class="badge badge-primary light">{{ $row['display_group'] }}</span>
                                    @else
                                        <span class="text-muted">Nincs megadva</span>
                                    @endif
                                </td>
                                <td>{{ $row['menu_package'] }}</td>
                                <td>
                                    @foreach($row['diet_names'] as $dietName)
                                        <span class="badge badge-info light me-1 mb-1">{{ $dietName }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($row['daily_menu'])
                                        {{ $row['daily_menu'] }}
                                    @else
                                        <span class="text-muted">Nem meghatározható</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row['notes']->isEmpty())
                                        <span class="text-muted">—</span>
                                    @else
                                        {{ $row['notes']->implode(' · ') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if($rows->hasPages())
                    <div class="mt-4">
                        {{ $rows->links('vendor.pagination.digifood') }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
