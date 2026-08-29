@extends('layouts.superadmin')

@section('title', 'Befizetés szerkesztése')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Befizetés szerkesztése',
        'subtitle' => 'A rögzített befizetés adatainak módosítása intézményi hatókörön belül.',
        'buttons' => [
            [
                'url' => route('dashboard.institution.finance.payments.show', $payment),
                'text' => 'Részletek',
                'icon' => 'fa-solid fa-eye',
                'class' => 'btn btn-light',
            ],
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
            <h4 class="card-title mb-0">Befizetési adatok frissítése</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.finance.payments.update', $payment) }}">
                @csrf
                @method('PUT')
                @include('dashboard.institution_admin.finance.payments._form')

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
                    </button>
                    <a href="{{ route('dashboard.institution.finance.payments.show', $payment) }}" class="btn btn-light">Mégsem</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
