@extends('layouts.superadmin')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>A/B menü szerkesztése</h4>

        <a href="{{ route('dashboard.institution.menus.ab.index') }}"
           class="btn btn-sm btn-secondary">
            Vissza
        </a>
    </div>

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
        <div class="card-body">

            <form method="POST"
                  action="{{ route('dashboard.institution.menus.ab.update', $abMenu) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Megnevezés</label>
                    <input type="text"
                           name="title"
                           class="form-control"
                           value="{{ old('title', $abMenu->title) }}"
                           required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Érvényesség kezdete</label>
                        <input type="date"
                               name="valid_from"
                               class="form-control"
                               value="{{ old('valid_from', optional($abMenu->valid_from)->format('Y-m-d')) }}"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Érvényesség vége</label>
                        <input type="date"
                               name="valid_to"
                               class="form-control"
                               value="{{ old('valid_to', optional($abMenu->valid_to)->format('Y-m-d')) }}"
                               required>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox"
                           name="active"
                           value="1"
                           class="form-check-input"
                           id="active"
                           @checked(old('active', $abMenu->active))>

                    <label class="form-check-label" for="active">
                        Aktív
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    Mentés
                </button>

                <a href="{{ route('dashboard.institution.menus.ab.index') }}"
                   class="btn btn-light">
                    Mégsem
                </a>
            </form>

        </div>
    </div>

</div>
@endsection