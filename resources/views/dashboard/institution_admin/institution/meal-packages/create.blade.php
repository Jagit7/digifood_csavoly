@extends('layouts.superadmin')

@section('title', 'Új menücsomag')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új menücsomag',
        'subtitle' => $institution->name . ' · étkezéstípusokból összeállított intézményi csomag',
        'buttonText' => 'Vissza a listához',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.meal-packages.index'),
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Menücsomag adatai</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.meal-packages.store') }}">
                @csrf
                @include('dashboard.institution_admin.institution.meal-packages._form')
            </form>
        </div>
    </div>
</div>
@endsection
