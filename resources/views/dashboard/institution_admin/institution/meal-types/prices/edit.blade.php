@extends('layouts.superadmin')

@section('title', 'Étkezési ár szerkesztése')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Ár szerkesztése · ' . $institutionMealType->mealType->name,
        'subtitle' => $institution->name . ' · meglévő intézményi étkezési ár módosítása',
        'buttonText' => 'Ártörténet',
        'buttonIcon' => 'fa-solid fa-clock-rotate-left',
        'buttonUrl' => route('dashboard.institution.meal-types.prices.history', $institutionMealType),
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Ár adatai</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.meal-types.prices.update', [$institutionMealType, $price]) }}">
                @csrf
                @method('PUT')
                @php($priceModel = $price)
                @include('dashboard.institution_admin.institution.meal-types.prices._form')
            </form>
        </div>
    </div>
</div>
@endsection
