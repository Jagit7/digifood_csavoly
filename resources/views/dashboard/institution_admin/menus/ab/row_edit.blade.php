@extends('layouts.superadmin')

@section('title', 'A/B menüsor szerkesztése')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'A/B menüsor szerkesztése',
        'subtitle' => $abMenu->title ?: 'A/B menüterv',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.menus.ab.show', $abMenu)
    ])

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Hiba történt.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">
                {{ $item->menu_date?->format('Y.m.d.') ?? 'Menüsor' }}
            </h4>
        </div>

        <div class="card-body">
            <form method="POST"
                  action="{{ route('dashboard.institution.menus.ab.rows.update', $item) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Dátum</label>
                    <input type="date"
                           name="menu_date"
                           class="form-control"
                           value="{{ old('menu_date', $item->menu_date?->format('Y-m-d')) }}"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">A menü</label>
                    <textarea name="menu_a"
                              class="form-control"
                              rows="4">{{ old('menu_a', $item->menu_a) }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">B menü</label>
                    <textarea name="menu_b"
                              class="form-control"
                              rows="4">{{ old('menu_b', $item->menu_b) }}</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Diétás menü</label>
                    <textarea name="menu_dietary"
                              class="form-control"
                              rows="4">{{ old('menu_dietary', $item->menu_dietary) }}</textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label">Megjegyzés</label>
                    <textarea name="note"
                              class="form-control"
                              rows="3">{{ old('note', $item->note) }}</textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('dashboard.institution.menus.ab.show', $abMenu) }}"
                       class="btn btn-light">
                        Mégsem
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i>
                        Menüsor mentése
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection