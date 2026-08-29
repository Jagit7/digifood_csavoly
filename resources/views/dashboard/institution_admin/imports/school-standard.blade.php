@extends('layouts.superadmin')

@section('title', 'Iskolai Excel import')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Iskolai Excel import',
        'subtitle' => 'Kötött szerkezetű tanuló- és törvényes képviselői adatok feltöltése',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.imports.index'),
    ])

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Excel-fájl feltöltése</h4>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <strong>A feltöltés nem indítható.</strong>
                            <ul class="mb-0 mt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('dashboard.institution.imports.school-standard.store') }}"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Osztály / csoport <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="group_name"
                                       class="form-control"
                                       value="{{ old('group_name') }}"
                                       placeholder="Például: 5.a"
                                       required>
                                <small class="text-muted">Az Excel ezt az adatot nem tartalmazza.</small>
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Tanév <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="school_year"
                                       class="form-control"
                                       value="{{ old('school_year', $defaultSchoolYear) }}"
                                       placeholder="2026/2027"
                                       required>
                            </div>

                            <div class="col-12 mb-4">
                                <label class="form-label">Excel-fájl <span class="text-danger">*</span></label>
                                <input type="file"
                                       name="import_file"
                                       class="form-control"
                                       accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                       required>
                                <small class="text-muted">Engedélyezett formátum: .xlsx, legfeljebb 10 MB.</small>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="{{ route('dashboard.institution.imports.index') }}" class="btn btn-light">
                                Mégsem
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-file-import me-1"></i>
                                Importálás indítása
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h4 class="card-title mb-0">Elvárt Excel-szerkezet</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        A feldolgozás a <strong>Szülő, törvényes képviselő</strong> munkalapot olvassa.
                    </div>
                    <p class="mb-2"><strong>Kötelező alapadatok:</strong></p>
                    <ul class="text-muted ps-3">
                        <li>tanuló neve;</li>
                        <li>11 jegyű oktatási azonosító;</li>
                        <li>szülő vagy képviselő vezeték- és keresztneve;</li>
                        <li>a sablon eredeti oszlopfejlécei.</li>
                    </ul>
                    <hr>
                    <p class="mb-0 small text-muted">
                        Egy tanuló több sorban is szerepelhet. A rendszer az oktatási azonosító alapján egy tanulóhoz kapcsolja a különböző gondviselőket.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
