@extends('layouts.superadmin')

@section('title', 'Új iskolai szünet')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új iskolai szünet',
        'subtitle' => 'Új intézményi szünet felvétele',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.school-breaks.index')
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

            <form method="POST"
                  action="{{ route('dashboard.institution.school-breaks.store') }}">

                @csrf

                <div class="row">

                    <div class="col-lg-6 mb-3">
                        <label class="form-label">
                            Megnevezés
                        </label>

                        <input
                            type="text"
                            name="title"
                            class="form-control"
                            value="{{ old('title') }}"
                            required>
                    </div>

                    <div class="col-lg-6 mb-3">
                        <label class="form-label">
                            Szünet típusa
                        </label>

                        <select
                            name="type"
                            class="form-control"
                            required>

                            <option value="">-- válassz --</option>

                            @foreach($types as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(old('type') == $value)>
                                    {{ $label }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-lg-6 mb-3">
                        <label class="form-label">
                            Első nap
                        </label>

                        <input
                            type="date"
                            name="start_date"
                            class="form-control"
                            value="{{ old('start_date') }}"
                            required>
                    </div>

                    <div class="col-lg-6 mb-3">
                        <label class="form-label">
                            Utolsó nap
                        </label>

                        <input
                            type="date"
                            name="end_date"
                            class="form-control"
                            value="{{ old('end_date') }}"
                            required>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">
                            Megjegyzés
                        </label>

                        <textarea
                            name="description"
                            rows="4"
                            class="form-control">{{ old('description') }}</textarea>
                    </div>

                </div>

                <div class="text-end">

                    <a href="{{ route('dashboard.institution.school-breaks.index') }}"
                       class="btn btn-light">
                        Mégsem
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="fa-solid fa-floppy-disk me-1"></i>

                        Mentés

                    </button>

                </div>

            </form>

        </div>
    </div>

</div>
@endsection