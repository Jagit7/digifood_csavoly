@extends('layouts.superadmin')

@section('title', 'Menücsomag')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $package->name,
        'subtitle' => $institution->name . ' · menücsomag részletei',
        'buttonText' => 'Szerkesztés',
        'buttonIcon' => 'fa-solid fa-pen',
        'buttonUrl' => route('dashboard.institution.meal-packages.edit', $package),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Állapot',
            'value' => $package->is_active ? 'Aktív' : 'Inaktív',
            'subtitle' => 'Jelenlegi használhatóság',
            'icon' => 'fa-solid fa-circle-check',
            'color' => $package->is_active ? 'green' : 'orange',
            'size' => 'small',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Alapértelmezett',
            'value' => $package->is_default ? 'Igen' : 'Nem',
            'subtitle' => 'Intézményi alapcsomag',
            'icon' => 'fa-solid fa-star',
            'color' => $package->is_default ? 'orange' : 'blue',
            'size' => 'small',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Árképzés',
            'value' => $pricingModeLabels[$package->pricing_mode] ?? $package->pricing_mode,
            'subtitle' => 'A csomag későbbi árlogikája',
            'icon' => 'fa-solid fa-tags',
            'color' => 'purple',
            'size' => 'small',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összetevők',
            'value' => $package->mealTypes->count(),
            'subtitle' => 'Csomagban szereplő étkezés',
            'icon' => 'fa-solid fa-layer-group',
            'color' => 'blue',
            'size' => 'small',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Alapadatok</h4>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Csomag neve</dt>
                <dd class="col-sm-9">{{ $package->name }}</dd>

                <dt class="col-sm-3">Leírás</dt>
                <dd class="col-sm-9">{{ $package->description ?: '—' }}</dd>

                <dt class="col-sm-3">Árképzési mód</dt>
                <dd class="col-sm-9">{{ $pricingModeLabels[$package->pricing_mode] ?? $package->pricing_mode }}</dd>

                @if($package->usesCustomPrice())
                    <dt class="col-sm-3">Egyedi csomagár</dt>
                    <dd class="col-sm-9">
                        {{ $package->custom_price !== null ? number_format($package->custom_price, 0, ',', ' ') . ' Ft' : '—' }}
                    </dd>
                @endif

                <dt class="col-sm-3">Létrehozta</dt>
                <dd class="col-sm-9">{{ $package->createdBy?->name ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Összetevők</h4>
            <a href="{{ route('dashboard.institution.meal-packages.index') }}" class="btn btn-light btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Vissza a listához
            </a>
        </div>
        <div class="card-body">
            @if($package->mealTypes->count())
                <ol class="mb-0 ps-3">
                    @foreach($package->mealTypes as $mealType)
                        <li class="mb-2">{{ $mealType->mealType->name }}</li>
                    @endforeach
                </ol>
            @else
                <div class="text-muted">Ehhez a csomaghoz még nincs összetevő rögzítve.</div>
            @endif
        </div>
    </div>
</div>
@endsection
