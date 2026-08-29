@extends('layouts.superadmin')

@section('title', 'Étkezés beállításai')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Étkezés beállításai',
        'subtitle' => $institution->name . ' · intézményi étkezéstípusok és árak kezelése',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Étkezéstípusok',
            'value' => $stats['total'],
            'subtitle' => 'Központi törzsadatból létrehozva',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív',
            'value' => $stats['active'],
            'subtitle' => 'Jelenleg engedélyezett',
            'icon' => 'fa-solid fa-circle-check',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Szülő választhatja',
            'value' => $stats['selectable'],
            'subtitle' => 'Szülői felületen elérhető',
            'icon' => 'fa-solid fa-hand-pointer',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktuális árral',
            'value' => $stats['priced'],
            'subtitle' => 'Van ma érvényes ár',
            'icon' => 'fa-solid fa-tags',
            'color' => 'purple',
        ])
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Intézményi étkezéstípusok</h4>
        </div>
        <div class="card-body">
            @if($mealTypes->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th>Étkezés</th>
                            <th width="120">Aktív</th>
                            <th width="170">Szülő választhatja</th>
                            <th width="120">Kötelező</th>
                            <th width="180">Aktuális ár</th>
                            <th width="200">Következő ár</th>
                            <th width="140" class="text-end">Műveletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($mealTypes as $institutionMealType)
                            @php
                                $currentPrice = $institutionMealType->getRelation('currentPrice');
                                $nextPrice = $institutionMealType->getRelation('nextPrice');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $institutionMealType->mealType->name }}</strong>
                                </td>
                                <td>
                                    <span class="badge {{ $institutionMealType->is_active ? 'badge-success' : 'badge-secondary' }} light">
                                        {{ $institutionMealType->is_active ? 'Igen' : 'Nem' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $institutionMealType->is_parent_selectable ? 'badge-success' : 'badge-secondary' }} light">
                                        {{ $institutionMealType->is_parent_selectable ? 'Igen' : 'Nem' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $institutionMealType->is_required ? 'badge-warning' : 'badge-secondary' }} light">
                                        {{ $institutionMealType->is_required ? 'Igen' : 'Nem' }}
                                    </span>
                                </td>
                                <td>
                                    @if($currentPrice)
                                        <strong>{{ number_format($currentPrice->price, 0, ',', ' ') }} Ft</strong>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($nextPrice)
                                        <strong>{{ number_format($nextPrice->price, 0, ',', ' ') }} Ft</strong>
                                        <div class="small text-muted">{{ $nextPrice->valid_from->format('Y.m.d.') }}-től</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.meal-types.edit', $institutionMealType) }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Szerkesztés (beállítások és ár egy helyen)">
                                        <i class="fa fa-pen"></i> Szerkesztés
                                    </a>
                                    <a href="{{ route('dashboard.institution.meal-types.prices.history', $institutionMealType) }}"
                                       class="btn btn-xs btn-outline-secondary"
                                       title="Ártörténet">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-utensils',
                    'title' => 'Nincs elérhető étkezéstípus',
                    'text' => 'A központi meal_types törzsadat jelenleg üres.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
