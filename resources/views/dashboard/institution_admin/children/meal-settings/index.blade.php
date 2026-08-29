@extends('layouts.superadmin')

@section('title', 'Étkezési beállítások')

@section('content')
@php
    $isUpcomingSetting = ! $currentSetting
        && $latestSetting
        && ! $latestSetting->wasClosedManually()
        && $latestSetting->valid_from->toDateString() > ($today ?? now()->toDateString());
    $returnList = $returnList ?? null;
    $returnQuery = $returnQuery ?? '';
    $returnParams = array_filter([
        'return_list' => $returnList,
        'return_query' => $returnQuery,
    ], fn ($value) => filled($value));
    $mealSettingsReturnQuery = $returnParams !== [] ? '?'.http_build_query($returnParams) : '';
    $sourceLabel = match ($returnList) {
        \App\Services\Navigation\ChildListReturnService::LIST_BASICS => 'Vissza az Alapadatokhoz',
        \App\Services\Navigation\ChildListReturnService::LIST_EATERS => 'Vissza az Étkező gyermekekhez',
        default => 'Vissza a gyermeklistához',
    };
    $sourceUrl = $returnUrl ?? route('dashboard.institution.children.index');
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Étkezési beállítások',
        'subtitle' => $child->name . ' · aktuális és korábbi étkezési szabályok',
        'buttonText' => $currentSetting
            ? 'Új beállítás'
            : ($isUpcomingSetting ? 'Ütemezett dátum javítása' : 'Étkeztetés bekapcsolása'),
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.children.meal-settings.create', $child) . $mealSettingsReturnQuery,
    ])

    <div class="mb-3">
        <a href="{{ $sourceUrl }}" class="btn btn-light">
            <i class="fa-solid fa-arrow-left me-1"></i>{{ $sourceLabel }}
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
            'value' => $currentSetting ? $currentSetting->valid_from->format('Y.m.d.') : '—',
            'subtitle' => $currentSetting ? ($currentSetting->valid_to?->format('Y.m.d.') ?? 'nyitott időszak') : 'Nincs aktuális beállítás',
            'icon' => 'fa-solid fa-calendar-days',
            'color' => 'green',
            'size' => 'small',
        ])
    </div>

    @include('dashboard.institution_admin.children.meal-settings.partials.closure-panel', [
        'child' => $child,
        'currentSetting' => $currentSetting,
        'latestSetting' => $latestSetting,
        'closureReasonLabels' => $closureReasonLabels,
        'returnList' => $returnList,
        'returnQuery' => $returnQuery,
    ])

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Jelenlegi beállítás</h4>
            @if($currentSetting)
                <a href="{{ route('dashboard.institution.children.meal-settings.edit', [$child, $currentSetting]) }}{{ $mealSettingsReturnQuery }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-pen me-1"></i>Szerkesztés
                </a>
            @endif
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
                        <div class="small text-muted">– {{ $currentSetting->valid_to?->format('Y.m.d.') ?? 'nyitott' }}</div>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <div class="text-muted small">Csomag / étkezések</div>
                        @include('dashboard.institution_admin.children.meal-settings.partials.summary', [
                            'mealSetting' => $currentSetting,
                            'defaultPackage' => $defaultPackage,
                        ])
                    </div>
                </div>
            @elseif($isUpcomingSetting ?? false)
                {{--
                    Van már egy jövőben induló, rögzített beállítás
                    (ld. lent az $isUpcomingSetting számítását) - ilyenkor
                    félrevezető lenne azt írni, hogy a gyermek "nem vesz
                    részt" az étkeztetésben, hiszen már be van ütemezve.
                    Itt is jelezzük a pontos kezdődátumot, és lehetőséget
                    adunk egy korrigált/másik beállítás felvételére -
                    ez automatikusan lezárja/összeköti a meglévő ütemezett
                    időszakkal, nem kell előbb törölni semmit.
                --}}
                <div class="alert alert-info mb-0">
                    A gyermek étkeztetése <strong>{{ $latestSetting->valid_from->format('Y.m.d.') }}-től</strong> van beütemezve, jelenleg (ma) még nem étkező.
                    <a href="{{ route('dashboard.institution.children.meal-settings.edit', [$child, $latestSetting]) }}{{ $mealSettingsReturnQuery }}" class="alert-link">
                        Ütemezett dátum javítása
                    </a>
                    ·
                    <a href="{{ route('dashboard.institution.children.meal-settings.create', $child) }}{{ $mealSettingsReturnQuery }}" class="alert-link">
                        másik beállítás felvétele
                    </a>
                </div>
            @else
                <div class="alert alert-warning mb-0">
                    A gyermek jelenleg nem vesz részt az intézményi étkeztetésben.
                    <a href="{{ route('dashboard.institution.children.meal-settings.create', $child) }}{{ $mealSettingsReturnQuery }}" class="alert-link">
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
                                    <div class="small text-muted">– {{ $mealSetting->valid_to?->format('Y.m.d.') ?? 'nyitott' }}</div>
                                </td>
                                <td>{{ $modeLabels[$mealSetting->mode] ?? $mealSetting->mode }}</td>
                                <td>
                                    @include('dashboard.institution_admin.children.meal-settings.partials.summary', [
                                        'mealSetting' => $mealSetting,
                                        'defaultPackage' => $defaultPackage,
                                    ])
                                </td>
                                <td>{{ $mealSetting->createdBy?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.children.meal-settings.show', [$child, $mealSetting]) }}{{ $mealSettingsReturnQuery }}"
                                       class="btn btn-xs btn-outline-secondary"
                                       title="Megtekintés">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="{{ route('dashboard.institution.children.meal-settings.edit', [$child, $mealSetting]) }}{{ $mealSettingsReturnQuery }}"
                                       class="btn btn-xs btn-outline-primary"
                                       title="Szerkesztés">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-muted">Még nincs megjeleníthető előzmény.</div>
            @endif
        </div>
    </div>
</div>
@endsection
