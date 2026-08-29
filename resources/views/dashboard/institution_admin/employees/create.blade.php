@extends('layouts.superadmin')

@section('title', 'Új dolgozó')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új dolgozó',
        'subtitle' => 'Intézményi dolgozó rögzítése',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.employees.index'),
    ])

    <div class="card mb-4">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Hiba történt.</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('dashboard.institution.employees.store') }}">
                @csrf

                @include('dashboard.institution_admin.employees.partials.form', [
                    'employee' => null,
                    'submitLabel' => 'Mentés',
                ])
            </form>
        </div>
    </div>
</div>
@endsection
