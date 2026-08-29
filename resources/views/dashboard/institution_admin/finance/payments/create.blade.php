@extends('layouts.superadmin')

@section('title', 'Új befizetés')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új befizetés',
        'subtitle' => 'Új intézményi befizetés rögzítése a gyermekhez és az opcionális havi kötelezettséghez kapcsolva.',
        'buttons' => [
            [
                'url' => route('dashboard.institution.finance.payments'),
                'text' => 'Vissza a listához',
                'icon' => 'fa-solid fa-arrow-left',
                'class' => 'btn btn-light',
            ],
        ],
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Befizetési adatok</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.finance.payments.store') }}">
                @csrf
                @include('dashboard.institution_admin.finance.payments._form')

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
                    </button>
                    <a href="{{ route('dashboard.institution.finance.payments') }}" class="btn btn-light">Mégsem</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
