@extends('layouts.superadmin')

@php
    $isKindergarten = $institution->type === 'ovoda';
    $title = $isKindergarten ? 'Csoportszintű lemondás szerkesztése' : 'Osztályszintű lemondás szerkesztése';
@endphp

@section('title', $title)

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $title,
        'subtitle' => 'A csoportos étkezéslemondás adatainak módosítása',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.class-cancellations.index'),
    ])

    @include('dashboard.institution_admin.class-cancellations.form', [
        'classCancellation' => $classCancellation,
        'classGroups' => $classGroups,
        'isKindergarten' => $isKindergarten,
        'formAction' => route('dashboard.institution.class-cancellations.update', $classCancellation),
        'formMethod' => 'PUT',
    ])
</div>
@endsection
