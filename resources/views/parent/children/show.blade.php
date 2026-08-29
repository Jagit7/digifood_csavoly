@extends('layouts.parent')

@section('page_title', 'Gyermek adatlap')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => $child->name,
        'subtitle' => 'Gyermek adatlap a szülői felületen',
        'buttonText' => 'Vissza a gyermekekhez',
        'buttonUrl' => route('parent.children.index'),
    ])

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header border-0">
                    <h4 class="card-title mb-0">Alapadatok</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><strong>Intézmény:</strong><br>{{ $child->institution?->name ?? 'Nincs megadva' }}</div>
                        <div class="col-md-6 mb-3"><strong>Csoport / osztály:</strong><br>{{ $child->group_name ?: 'Nincs megadva' }}</div>
                        <div class="col-md-6 mb-3"><strong>Tanév:</strong><br>{{ $child->school_year ?: 'Nincs megadva' }}</div>
                        <div class="col-md-6 mb-3"><strong>Kedvezmény:</strong><br>{{ $child->discountType?->name ?? 'Nincs megadva' }}</div>
                        <div class="col-md-6 mb-3"><strong>Oktatási azonosító:</strong><br>{{ $child->educational_identifier ?: 'Nincs megadva' }}</div>
                        <div class="col-md-6 mb-3"><strong>Státusz:</strong><br>{{ $child->active ? 'Aktív' : 'Inaktív' }}</div>
                    </div>
                    <div class="mt-4">
                @include('parent.children.partials.meal-data-card', [
                    'mealData' => $mealData,
                ])
            </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header border-0">
                    <h4 class="card-title mb-0">Kapcsolódó adatok</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Kapcsolt gondviselők</strong>
                        <div class="text-muted small mt-1">
                            {{ $child->guardians->pluck('full_name')->filter()->implode(', ') ?: 'Nincs megadva' }}
                        </div>
                    </div>
                    <div>
                        <strong>Diétás megkötések</strong>
                        <div class="text-muted small mt-1">
                            {{ $child->dietaryRestrictions->pluck('name')->filter()->implode(', ') ?: 'Nincs rögzítve' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
