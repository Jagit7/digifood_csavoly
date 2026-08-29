@extends('layouts.superadmin')

@section('title', 'Új kapcsolattartó')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új kapcsolattartó',
        'subtitle' => 'Intézményi kapcsolattartó rögzítése',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.contacts.index')
    ])

    <div class="row">
        <div class="col-12">
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

                    <form method="POST" action="{{ route('dashboard.institution.contacts.store') }}">
                        @csrf

                        @include('dashboard.institution_admin.contacts.partials.form', [
                            'contact' => null,
                            'submitLabel' => 'Mentés',
                        ])
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
