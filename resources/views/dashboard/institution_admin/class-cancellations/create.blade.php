@extends('layouts.superadmin')

@php
    $isKindergarten = $institution->type === 'ovoda';
    $title = $isKindergarten ? 'Új csoportszintű lemondás' : 'Új osztályszintű lemondás';
@endphp

@section('title', $title)

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $title,
        'subtitle' => ($isKindergarten ? 'Óvodai csoport' : 'Iskolai osztály').' étkezésének lemondása egy időszakra',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.class-cancellations.index'),
    ])

    @include('dashboard.institution_admin.class-cancellations.form', [
        'classCancellation' => null,
        'classGroups' => $classGroups,
        'isKindergarten' => $isKindergarten,
        'formAction' => route('dashboard.institution.class-cancellations.store'),
        'formMethod' => 'POST',
    ])
</div>
@endsection
