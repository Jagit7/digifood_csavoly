@extends('layouts.superadmin')

@section('title', 'Ártörténet')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Ártörténet · ' . $institutionMealType->mealType->name,
        'subtitle' => $institution->name . ' · korábban és jelenleg érvényes árak',
        'buttonText' => 'Új ár',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.meal-types.prices.create', $institutionMealType),
    ])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Ártörténeti lista</h4>
            <a href="{{ route('dashboard.institution.meal-types.index') }}" class="btn btn-light btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Vissza a listához
            </a>
        </div>
        <div class="card-body">
            @if($prices->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th>Ár</th>
                            <th>Érvényes ettől</th>
                            <th>Érvényes eddig</th>
                            <th>Létrehozta</th>
                            <th>Megjegyzés</th>
                            <th>Létrehozás ideje</th>
                            <th width="100" class="text-end">Művelet</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($prices as $price)
                            <tr>
                                <td><strong>{{ number_format($price->price, 0, ',', ' ') }} Ft</strong></td>
                                <td>{{ $price->valid_from->format('Y.m.d.') }}</td>
                                <td>{{ $price->valid_to?->format('Y.m.d.') ?? '—' }}</td>
                                <td>{{ $price->createdBy?->name ?? '—' }}</td>
                                <td>{{ $price->note ?: '—' }}</td>
                                <td>{{ $price->created_at?->format('Y.m.d. H:i') ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.meal-types.prices.edit', [$institutionMealType, $price]) }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-tags',
                    'title' => 'Még nincs rögzített ár',
                    'text' => 'Ehhez az étkezéstípushoz még nem vettetek fel egyetlen árat sem.',
                    'buttonText' => 'Új ár felvitele',
                    'buttonIcon' => 'fa-solid fa-plus',
                    'buttonUrl' => route('dashboard.institution.meal-types.prices.create', $institutionMealType),
                ])
            @endif
        </div>
    </div>
</div>
@endsection
