@extends('layouts.superadmin')

@section('title', 'Riportok')

@section('content')
@php
    $formatMoney = function ($amount) {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $numeric = (float) $amount;
        $decimals = floor($numeric) == $numeric ? 0 : 2;

        return number_format($numeric, $decimals, ',', ' ') . ' Ft';
    };

    $formatPercent = function ($value) {
        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, 0, ',', ' ') . '%';
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

    $isCurrentMonth = $selectedMonthQuery === $currentMonthQuery;
@endphp

<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Riportok',
        'subtitle' => 'SuperAdmin szintű üzleti és használati összesítések a DigiFood adatai alapján.',
    ])

    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <a href="{{ route('dashboard.superadmin.reports.index', ['month' => $previousMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-chevron-left me-1"></i>Előző hónap
        </a>
        <div class="btn btn-success btn-sm disabled">{{ $selectedMonthLabel }}</div>
        <a href="{{ route('dashboard.superadmin.reports.index', ['month' => $nextMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
            Következő hónap<i class="fa-solid fa-chevron-right ms-1"></i>
        </a>
        @unless($isCurrentMonth)
            <a href="{{ route('dashboard.superadmin.reports.index', ['month' => $currentMonthQuery]) }}" class="btn btn-outline-primary btn-sm">
                Aktuális hónap
            </a>
        @endunless
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív intézmények',
            'value' => $topStats['active_institutions'],
            'subtitle' => 'Jelenleg aktív státuszú intézmények',
            'icon' => 'fa-solid fa-building-circle-check',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív gyermekek',
            'value' => $topStats['active_children'],
            'subtitle' => 'Aktív gyermek- és tanulórekordok',
            'icon' => 'fa-solid fa-children',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív dolgozók',
            'value' => $topStats['active_employees'],
            'subtitle' => 'Aktív státuszú intézményi dolgozói rekordok',
            'icon' => 'fa-solid fa-user-tie',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív szülők',
            'value' => $topStats['active_parents'],
            'subtitle' => 'Aktív szülői felhasználói fiókok',
            'icon' => 'fa-solid fa-users',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Havi étkezések',
            'value' => $topStats['monthly_meals'],
            'subtitle' => 'Gyermek: '.($topStats['monthly_meals'] - $topStats['monthly_employee_meals']).' · Dolgozó: '.$topStats['monthly_employee_meals'],
            'icon' => 'fa-solid fa-utensils',
            'color' => 'blue',
        ])
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Étkezések és lemondások</h4>
                        <p class="text-muted mb-0">Kiválasztott hónap: {{ $selectedMonthLabel }}</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Havi étkezések</div>
                                <div class="h3 mb-1">{{ $summary['monthly_meals'] }}</div>
                                <div class="small text-muted">Gyermek: {{ $summary['monthly_child_meals'] }} · Dolgozó: {{ $summary['monthly_employee_meals'] }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Havi lemondások</div>
                                <div class="h3 mb-1">{{ $summary['monthly_cancellations'] }}</div>
                                <div class="small text-muted">Gyermek: {{ $summary['monthly_child_cancellations'] }} · Dolgozó: {{ $summary['monthly_employee_cancellations'] }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Lemondási arány</div>
                                <div class="h3 mb-1">{{ $formatPercent($summary['cancellation_ratio']) }}</div>
                                <div class="small text-muted">Biztos tervezett étkezési alap hiányában nem számolható</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Étkezésszám változása</div>
                                <div class="h3 mb-1 {{ $changeClass($summary['meal_change_percent']) }}">{{ $formatSignedPercent($summary['meal_change_percent']) }}</div>
                                <div class="small text-muted">Előző hónaphoz viszonyítva</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Lemondásszám változása</div>
                                <div class="h3 mb-1 {{ $changeClass($summary['cancellation_change_percent']) }}">{{ $formatSignedPercent($summary['cancellation_change_percent']) }}</div>
                                <div class="small text-muted">Előző hónaphoz viszonyítva</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Pénzügyi összesítés</h4>
                        <p class="text-muted mb-0">A havi ügyfél-számlázási snapshotok alapján</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Teljes havi számlázandó</div>
                                <div class="h3 mb-1">{{ $formatMoney($financialSummary['total_amount']) }}</div>
                                <div class="small text-muted">Bruttó partneri összesítés</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Kifizetett összeg</div>
                                <div class="h3 mb-1">{{ $formatMoney($financialSummary['paid_amount']) }}</div>
                                <div class="small text-muted">Fizetett státuszú snapshotok</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Függőben lévő összeg</div>
                                <div class="h3 mb-1">{{ $formatMoney($financialSummary['pending_amount']) }}</div>
                                <div class="small text-muted">Még nem fizetett snapshotok</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Lejárt összeg</div>
                                <div class="h3 mb-1">{{ $formatMoney($financialSummary['overdue_amount']) }}</div>
                                <div class="small text-muted">A jelenlegi partneri snapshotokból nem állapítható meg</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted small mb-1">Kézzel kezelt tételek</div>
                                <div class="h3 mb-1">{{ $financialSummary['manual_items_count'] ?? '—' }}</div>
                                <div class="small text-muted">A jelenlegi partneri snapshotokból nem állapítható meg</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-1">Intézményi összesítő</h4>
                <p class="text-muted mb-0">Havi intézményi mutatók és ügyfél-számlázási állapot</p>
            </div>
            <span class="text-muted">Intézmények: {{ $institutions->total() }}</span>
        </div>
        <div class="card-body">
            @if($institutions->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th>Intézmény neve</th>
                            <th>Aktív gyermekek</th>
                            <th>Aktív dolgozók</th>
                            <th>Aktív szülők</th>
                            <th>Havi étkezések</th>
                            <th>Havi lemondások</th>
                            <th>Lemondási arány</th>
                            <th>Havi ügyfél-számlázási összeg</th>
                            <th>Számlázási státusz</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($institutions as $institution)
                            @php
                                $status = $statusMeta[$institution->monthly_billing_status] ?? $statusMeta['missing'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $institution->name }}</div>
                                    @if(!$institution->active)
                                        <div class="small text-muted">Inaktív intézmény</div>
                                    @endif
                                </td>
                                <td>{{ (int) $institution->active_children_count }}</td>
                                <td>{{ (int) $institution->active_employees_count }}</td>
                                <td>{{ (int) $institution->active_parents_count }}</td>
                                <td>
                                    {{ (int) $institution->monthly_meals_count }}
                                    <div class="small text-muted">ebből dolgozó: {{ (int) $institution->monthly_employee_meals_count }}</div>
                                </td>
                                <td>
                                    {{ (int) $institution->monthly_cancellations_count }}
                                    <div class="small text-muted">ebből dolgozó: {{ (int) $institution->monthly_employee_cancellations_count }}</div>
                                </td>
                                <td>—</td>
                                <td>{{ $institution->monthly_billing_amount !== null ? $formatMoney($institution->monthly_billing_amount) : '—' }}</td>
                                <td><span class="{{ $status['class'] }}">{{ $status['label'] }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $institutions->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-chart-column',
                    'title' => 'Nincs megjeleníthető adat',
                    'text' => 'A kiválasztott hónapra még nincs olyan intézményi adat, amely itt összesíthető lenne.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
