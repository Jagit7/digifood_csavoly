@extends('layouts.superadmin')

@section('title', 'Új étkezési ár')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új ár · ' . $institutionMealType->mealType->name,
        'subtitle' => $institution->name . ' · új intézményi étkezési ár rögzítése',
        'buttonText' => 'Ártörténet',
        'buttonIcon' => 'fa-solid fa-clock-rotate-left',
        'buttonUrl' => route('dashboard.institution.meal-types.prices.history', $institutionMealType),
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Új ár felvitele</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.meal-types.prices.store', $institutionMealType) }}">
                @csrf
                @php($priceModel = new \App\Models\InstitutionMealPrice())
                @include('dashboard.institution_admin.institution.meal-types.prices._form')
            </form>
        </div>
    </div>
</div>
@endsection
