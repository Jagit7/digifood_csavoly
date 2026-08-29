@extends('layouts.superadmin')

@section('title', 'Riportok')

@section('content')
@php
    $formatAverage = function ($value) {
        return number_format((float) $value, ((float) $value === floor((float) $value)) ? 0 : 1, ',', ' ');
    };

    $formatSignedPercent = function ($value) {
        if ($value > 0) {
            return '+' . number_format((float) $value, 0, ',', ' ') . '%';
        }

        return number_format((float) $value, 0, ',', ' ') . '%';
    };

    $changeClass = function ($value) {
        if ($value > 0) {
            return 'text-success';
        }

        if ($value < 0) {
            return 'text-danger';
        }

        return 'text-muted';
    };
@endphp

<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Riportok',
        'subtitle' => $institution->name . ' · ' . $selectedMonthLabel,
    ])

    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <a href="{{ route('dashboard.institution.reports.index', ['month' => $previousMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-chevron-left me-1"></i>Előző hónap
        </a>
        <div class="btn btn-light btn-sm disabled">{{ $selectedMonthLabel }}</div>
        <a href="{{ route('dashboard.institution.reports.index', ['month' => $nextMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
            Következő hónap<i class="fa-solid fa-chevron-right ms-1"></i>
        </a>
        @if($selectedMonthQuery !== $currentMonthQuery)
            <a href="{{ route('dashboard.institution.reports.index', ['month' => $currentMonthQuery]) }}" class="btn btn-outline-primary btn-sm">
                Aktuális hónap
            </a>
        @endif
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív gyermekek',
            'value' => $statCards['active_children'],
            'subtitle' => 'Aktív státuszú gyermekek az intézményben',
            'icon' => 'fa-solid fa-children',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív dolgozók',
            'value' => $statCards['active_employees'],
            'subtitle' => 'Aktív státuszú dolgozók az intézményben',
            'icon' => 'fa-solid fa-user-tie',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív osztályok / csoportok',
            'value' => $statCards['active_groups'],
            'subtitle' => 'Aktív gyermekekhez tartozó csoportnevek alapján',
            'icon' => 'fa-solid fa-layer-group',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Havi étkezések',
            'value' => $statCards['monthly_meals'],
            'subtitle' => 'Gyermek: '.$statCards['monthly_child_meals'].' · Dolgozó: '.$statCards['monthly_employee_meals'],
            'icon' => 'fa-solid fa-utensils',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Havi lemondások',
            'value' => $statCards['monthly_cancellations'],
            'subtitle' => 'Gyermek: '.$statCards['monthly_child_cancellations'].' · Dolgozó: '.$statCards['monthly_employee_cancellations'],
            'icon' => 'fa-solid fa-ban',
            'color' => 'purple',
        ])
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Étkezési összesítés</h4>
                        <p class="text-muted mb-0">A havi étkezések a sikeres `meal_check_ins` rekordokból számolódnak.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Havi étkezések összesen</div>
                                <div class="h3 mb-1">{{ $mealSummary['total'] }}</div>
                                <div class="small text-muted">Gyermek: {{ $mealSummary['child_total'] }} · Dolgozó: {{ $mealSummary['employee_total'] }}</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Napi átlag</div>
                                <div class="h3 mb-1">{{ $formatAverage($mealSummary['daily_average']) }}</div>
                                <div class="small text-muted">Adatot tartalmazó napok alapján</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Változás</div>
                                <div class="h3 mb-1 {{ $changeClass($mealSummary['change_percent']) }}">{{ $formatSignedPercent($mealSummary['change_percent']) }}</div>
                                <div class="small text-muted">Előző hónaphoz képest</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5 class="mb-3">Étkezéstípusonkénti bontás</h5>
                        @if($mealSummary['type_breakdown']->isEmpty())
                            <div class="text-muted">Nincs megjeleníthető adat.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th>Étkezéstípus</th>
                                        <th class="text-end">Gyermek</th>
                                        <th class="text-end">Dolgozó</th>
                                        <th class="text-end">Összesen</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($mealSummary['type_breakdown'] as $mealType)
                                        <tr>
                                            <td>{{ $mealType['meal_type_name'] }}</td>
                                            <td class="text-end">{{ $mealType['child_count'] }}</td>
                                            <td class="text-end">{{ $mealType['employee_count'] }}</td>
                                            <td class="text-end fw-semibold">{{ $mealType['meals_count'] }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Lemondási összesítés</h4>
                        <p class="text-muted mb-0">A lemondási arány a jelenlegi adatokból nem számolható megbízhatóan.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Havi lemondások összesen</div>
                                <div class="h3 mb-1">{{ $cancellationSummary['total'] }}</div>
                                <div class="small text-muted">Gyermek: {{ $cancellationSummary['child_total'] }} · Dolgozó: {{ $cancellationSummary['employee_total'] }}</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Napi átlag</div>
                                <div class="h3 mb-1">{{ $formatAverage($cancellationSummary['daily_average']) }}</div>
                                <div class="small text-muted">Adatot tartalmazó napok alapján</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Változás</div>
                                <div class="h3 mb-1 {{ $changeClass($cancellationSummary['change_percent']) }}">{{ $formatSignedPercent($cancellationSummary['change_percent']) }}</div>
                                <div class="small text-muted">Előző hónaphoz képest</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Lemondási arány</div>
                                <div class="h3 mb-1">—</div>
                                <div class="small text-muted">A jelenlegi adatokból nem számolható megbízhatóan.</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Legtöbb lemondást tartalmazó nap</div>
                                @if($cancellationSummary['top_day'])
                                    <div class="h5 mb-1">{{ $cancellationSummary['top_day']['date']->format('Y. m. d.') }}</div>
                                    <div class="small text-muted">{{ $cancellationSummary['top_day']['cancellations'] }} lemondás</div>
                                @else
                                    <div class="h3 mb-1">—</div>
                                    <div class="small text-muted">Nincs lemondási adat a hónapban.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Napi bontás</h4>
        </div>
        <div class="card-body">
            @if($dailyRows->isEmpty())
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-calendar-days',
                    'title' => 'Nincs megjeleníthető adat',
                    'text' => 'A kiválasztott hónapra nincs étkezési vagy lemondási adat.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Nap</th>
                            <th>Étkezések</th>
                            <th class="text-muted">ebből dolgozó</th>
                            <th>Lemondások</th>
                            <th class="text-muted">ebből dolgozó</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($dailyRows as $row)
                            <tr>
                                <td>{{ $row['date']->format('Y. m. d.') }}</td>
                                <td>{{ mb_convert_case($row['date']->locale('hu')->translatedFormat('l'), MB_CASE_TITLE, 'UTF-8') }}</td>
                                <td>{{ $row['meals'] }}</td>
                                <td class="text-muted">{{ $row['employee_meals'] }}</td>
                                <td>{{ $row['cancellations'] }}</td>
                                <td class="text-muted">{{ $row['employee_cancellations'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Osztály / csoport bontás</h4>
            <p class="text-muted mb-0">Ez a bontás csak a gyermekekre vonatkozik, a dolgozók nem tartoznak osztályhoz/csoporthoz.</p>
        </div>
        <div class="card-body">
            @if($groupRows->isEmpty())
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-layer-group',
                    'title' => 'Nincs megjeleníthető adat',
                    'text' => 'A kiválasztott hónapra vagy az aktív gyermekekhez nincs csoportadat.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Osztály / csoport neve</th>
                            <th>Aktív gyermekek</th>
                            <th>Havi étkezések</th>
                            <th>Havi lemondások</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($groupRows as $row)
                            <tr>
                                <td><span class="badge badge-primary light">{{ $row['group_name'] }}</span></td>
                                <td>{{ $row['active_children'] }}</td>
                                <td>{{ $row['meals'] }}</td>
                                <td>{{ $row['cancellations'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
