@extends('layouts.superadmin')

@section('title', 'Kapcsolattartó szerkesztése')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Kapcsolattartó szerkesztése',
        'subtitle' => $contact->name,
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.contacts.index')
    ])

    <div class="card">
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

            <form method="POST" action="{{ route('dashboard.institution.contacts.update', $contact) }}">
                @csrf
                @method('PUT')

                @include('dashboard.institution_admin.contacts.partials.form', [
                    'contact' => $contact,
                    'submitLabel' => 'Mentés',
                ])
            </form>
        </div>
    </div>

</div>
@endsection
