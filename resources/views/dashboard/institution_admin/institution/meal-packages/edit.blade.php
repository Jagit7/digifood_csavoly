@extends('layouts.superadmin')

@section('title', 'Menücsomag szerkesztése')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $package->name . ' szerkesztése',
        'subtitle' => $institution->name . ' · menücsomag beállításainak módosítása',
        'buttonText' => 'Megtekintés',
        'buttonIcon' => 'fa-solid fa-eye',
        'buttonUrl' => route('dashboard.institution.meal-packages.show', $package),
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Menücsomag adatai</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.meal-packages.update', $package) }}">
                @csrf
                @method('PUT')
                @include('dashboard.institution_admin.institution.meal-packages._form')
            </form>
        </div>
    </div>
</div>
@endsection
