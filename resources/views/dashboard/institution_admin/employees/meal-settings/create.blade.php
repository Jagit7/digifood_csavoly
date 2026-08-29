@extends('layouts.superadmin')

@section('title', 'Új étkezési beállítás')

@section('content')
@php
    $selectedMealTypeIds = collect(old('institution_meal_type_ids', $selectedMealTypeIds))->map(fn ($id) => (string) $id);
@endphp

<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új étkezési beállítás',
        'subtitle' => $employee->name . ' - dolgozói étkezési szabály létrehozása',
        'buttonText' => 'Vissza az előzményekhez',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.employees.meal-settings.index', $employee),
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Beállítás adatai</h4>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.employees.meal-settings.store', $employee) }}">
                @csrf

                <div class="mb-4">
                    <label class="form-label d-block">Mód</label>

                    @foreach($modeLabels as $value => $label)
                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="radio"
                                   name="mode"
                                   value="{{ $value }}"
                                   id="mode_{{ $value }}"
                                   @checked(old('mode', $setting->mode) === $value)>

                            <label class="form-check-label" for="mode_{{ $value }}">
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach

                    @error('mode')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-lg-4 mb-3">
                        <label class="form-label" for="valid_from">Érvényes ettől</label>

                        <input id="valid_from"
                               type="date"
                               name="valid_from"
                               class="form-control @error('valid_from') is-invalid @enderror"
                               value="{{ old('valid_from', now()->toDateString()) }}"
                               required>

                        @error('valid_from')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-lg-8 mb-3">
                        <label class="form-label" for="note">Megjegyzés</label>

                        <input id="note"
                               type="text"
                               name="note"
                               class="form-control @error('note') is-invalid @enderror"
                               value="{{ old('note') }}">

                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="card border mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-1">Intézményi alapértelmezett</h5>

                        <div class="small text-muted">
                            Jelenlegi aktív alapcsomag:
                            <strong>{{ $defaultPackage?->name ?? 'Nincs beállítva' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="card border mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-1">Másik menücsomag kiválasztása</h5>
                    </div>

                    <div class="card-body">
                        <label class="form-label" for="institution_meal_package_id">
                            Menücsomag
                        </label>

                        <select id="institution_meal_package_id"
                                name="institution_meal_package_id"
                                class="form-control @error('institution_meal_package_id') is-invalid @enderror">

                            <option value="">Nincs kiválasztva</option>

                            @foreach($availablePackages as $package)
                                <option value="{{ $package->id }}"
                                    @selected((string) old('institution_meal_package_id') === (string) $package->id)>
                                    {{ $package->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('institution_meal_package_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="card border mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-1">Egyedi étkezések</h5>
                    </div>

                    <div class="card-body">
                        @error('institution_meal_type_ids')
                            <div class="alert alert-danger py-2">
                                {{ $message }}
                            </div>
                        @enderror

                        <div class="row">
                            @forelse($availableMealTypes as $mealType)
                                <div class="col-xl-4 col-lg-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="institution_meal_type_ids[]"
                                               value="{{ $mealType->id }}"
                                               id="meal_type_{{ $mealType->id }}"
                                               @checked($selectedMealTypeIds->contains((string) $mealType->id))>

                                        <label class="form-check-label" for="meal_type_{{ $mealType->id }}">
                                            {{ $mealType->mealType->name }}
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-muted">
                                    Nincs választható aktív étkezéstípus.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="{{ route('dashboard.institution.employees.meal-settings.index', $employee) }}"
                       class="btn btn-light">
                        Mégsem
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Mentés
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection