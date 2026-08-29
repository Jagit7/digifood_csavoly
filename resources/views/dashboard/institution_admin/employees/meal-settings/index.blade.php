@extends('layouts.superadmin')

@section('title', 'Étkezési beállítások')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Étkezési beállítások',
        'subtitle' => $employee->name . ' - aktuális és korábbi étkezési szabályok',
        'buttonText' => $currentSetting ? 'Új beállítás' : 'Étkeztetés bekapcsolása',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.employees.meal-settings.create', $employee),
    ])

    <div class="mb-3">
        <a href="{{ route('dashboard.institution.employees.index') }}" class="btn btn-light">
            <i class="fa-solid fa-arrow-left me-1"></i>Vissza a dolgozókhoz
        </a>
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Jelenlegi mód',
            'value' => $currentSetting ? ($modeLabels[$currentSetting->mode] ?? $currentSetting->mode) : 'Nincs',
            'subtitle' => 'Ma érvényes beállítás',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'blue',
            'size' => 'small',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív alapcsomag',
            'value' => $defaultPackage?->name ?? 'Nincs',
            'subtitle' => 'Intézményi alapértelmezett',
            'icon' => 'fa-solid fa-star',
            'color' => 'orange',
            'size' => 'small',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes beállítás',
            'value' => $settings->count(),
            'subtitle' => 'Teljes történet',
            'icon' => 'fa-solid fa-clock-rotate-left',
            'color' => 'purple',
            'size' => 'small',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Jelenlegi érvényesség',
            'value' => $currentSetting ? $currentSetting->valid_from->format('Y.m.d.') : '-',
            'subtitle' => $currentSetting ? ($currentSetting->valid_to?->format('Y.m.d.') ?? 'nyitott időszak') : 'Nincs aktuális beállítás',
            'icon' => 'fa-solid fa-calendar-days',
            'color' => 'green',
            'size' => 'small',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Jelenlegi beállítás</h4>
        </div>

        <div class="card-body">
            @if($currentSetting)
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="text-muted small">Mód</div>
                        <strong>{{ $modeLabels[$currentSetting->mode] ?? $currentSetting->mode }}</strong>
                    </div>

                    <div class="col-lg-3 mb-3">
                        <div class="text-muted small">Érvényesség</div>
                        <strong>{{ $currentSetting->valid_from->format('Y.m.d.') }}</strong>
                        <div class="small text-muted">
                            - {{ $currentSetting->valid_to?->format('Y.m.d.') ?? 'nyitott' }}
                        </div>
                    </div>

                    <div class="col-lg-6 mb-3">
                        <div class="text-muted small">Csomag / étkezések</div>

                        @include('dashboard.institution_admin.children.meal-settings.partials.summary', [
                            'mealSetting' => $currentSetting,
                            'defaultPackage' => $defaultPackage,
                        ])
                    </div>
                </div>
            @else
                <div class="alert alert-warning mb-0">
                    A dolgozó jelenleg nem vesz részt az intézményi étkeztetésben.

                    <a href="{{ route('dashboard.institution.employees.meal-settings.create', $employee) }}"
                       class="alert-link">
                        Étkeztetés bekapcsolása
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Korábbi beállítások</h4>
        </div>

        <div class="card-body">
            @if($settings->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                            <tr>
                                <th>Érvényesség</th>
                                <th>Mód</th>
                                <th>Csomag / egyedi étkezések</th>
                                <th>Létrehozta</th>
                                <th width="100" class="text-end">Művelet</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($settings as $mealSetting)
                                <tr>
                                    <td>
                                        <strong>{{ $mealSetting->valid_from->format('Y.m.d.') }}</strong>
                                        <div class="small text-muted">
                                            - {{ $mealSetting->valid_to?->format('Y.m.d.') ?? 'nyitott' }}
                                        </div>
                                    </td>

                                    <td>
                                        {{ $modeLabels[$mealSetting->mode] ?? $mealSetting->mode }}
                                    </td>

                                    <td>
                                        @include('dashboard.institution_admin.children.meal-settings.partials.summary', [
                                            'mealSetting' => $mealSetting,
                                            'defaultPackage' => $defaultPackage,
                                        ])
                                    </td>

                                    <td>
                                        {{ $mealSetting->createdBy?->name ?? '-' }}
                                    </td>

                                    <td class="text-end">
                                        <a href="{{ route('dashboard.institution.employees.meal-settings.show', [$employee, $mealSetting]) }}"
                                           class="btn btn-xs btn-outline-secondary"
                                           title="Megtekintés">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-muted">
                    Még nincs megjeleníthető előzmény.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection