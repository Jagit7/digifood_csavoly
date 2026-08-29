@extends('layouts.superadmin')

@section('title', 'Menücsomagok')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Menücsomagok',
        'subtitle' => $institution->name . ' · intézményspecifikus menücsomagok kezelése',
        'buttonText' => 'Új menücsomag',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.meal-packages.create'),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes csomag',
            'value' => $stats['total'],
            'subtitle' => 'Rögzített menücsomag',
            'icon' => 'fa-solid fa-box-open',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív',
            'value' => $stats['active'],
            'subtitle' => 'Jelenleg használható',
            'icon' => 'fa-solid fa-circle-check',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Alapértelmezett',
            'value' => $stats['default'],
            'subtitle' => 'Intézményi kiinduló csomag',
            'icon' => 'fa-solid fa-star',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Egyedi áras',
            'value' => $stats['custom_price'],
            'subtitle' => 'Fix csomagárral díjazott csomagok',
            'icon' => 'fa-solid fa-tags',
            'color' => 'purple',
        ])
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Menücsomag lista</h4>
        </div>
        <div class="card-body">
            @if($packages->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th>Csomag neve</th>
                            <th>Tartalma</th>
                            <th>Árképzés</th>
                            <th width="120">Alapértelmezett</th>
                            <th width="100">Aktív</th>
                            <th width="100">Sorrend</th>
                            <th width="170" class="text-end">Műveletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($packages as $package)
                            <tr>
                                <td>
                                    <strong>{{ $package->name }}</strong>
                                    @if($package->description)
                                        <div class="small text-muted mt-1">{{ $package->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($package->mealTypes->count())
                                        {{ $package->mealTypes->map(fn ($mealType) => $mealType->mealType->name)->implode(' + ') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $pricingModeLabels[$package->pricing_mode] ?? $package->pricing_mode }}
                                    @if($package->usesCustomPrice() && $package->custom_price !== null)
                                        <div class="small text-muted mt-1">{{ number_format($package->custom_price, 0, ',', ' ') }} Ft</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $package->is_default ? 'badge-warning' : 'badge-secondary' }} light">
                                        {{ $package->is_default ? 'Igen' : 'Nem' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $package->is_active ? 'badge-success' : 'badge-secondary' }} light">
                                        {{ $package->is_active ? 'Igen' : 'Nem' }}
                                    </span>
                                </td>
                                <td>{{ $package->display_order }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.meal-packages.show', $package) }}"
                                       class="btn btn-xs btn-outline-secondary"
                                       title="Megtekintés">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="{{ route('dashboard.institution.meal-packages.edit', $package) }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <form method="POST"
                                          action="{{ route('dashboard.institution.meal-packages.destroy', $package) }}"
                                          class="d-inline delete-form"
                                          data-title="{{ $package->is_default ? 'Biztosan törlöd az alapértelmezett menücsomagot?' : 'Biztosan törlöd ezt a menücsomagot?' }}"
                                          data-text="A törlés a csomag összetevőit is eltávolítja.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="btn btn-xs btn-outline-danger"
                                                title="Törlés">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-box-open',
                    'title' => 'Még nincs menücsomag',
                    'text' => 'Az első intézményi menücsomagot itt tudod létrehozni.',
                    'buttonText' => 'Új menücsomag',
                    'buttonIcon' => 'fa-solid fa-plus',
                    'buttonUrl' => route('dashboard.institution.meal-packages.create'),
                ])
            @endif
        </div>
    </div>
</div>
@endsection
