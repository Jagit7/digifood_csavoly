@extends('layouts.superadmin')

@section('title', 'Óvodai Excel import')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Óvodai Excel import',
        'subtitle' => 'Gyermek-, gondviselő- és számlázási adatok feltöltése egy egyszerű, saját fejlécű Excelből',
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
                          action="{{ route('dashboard.institution.imports.kindergarten.store') }}"
                          enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Nevelési év <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="school_year"
                                       class="form-control"
                                       value="{{ old('school_year', $defaultSchoolYear) }}"
                                       placeholder="2026/2027"
                                       required>
                                <small class="text-muted">A csoportok neve az Excelből jön - egy fájlban több csoport gyermekei is szerepelhetnek.</small>
                            </div>

                            <div class="col-12 mb-4">
                                <label class="form-label">Excel-fájl <span class="text-danger">*</span></label>
                                <input type="file"
                                       name="import_file"
                                       class="form-control"
                                       accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                       required>
                                <small class="text-muted">Engedélyezett formátum: .xlsx, legfeljebb 10 MB. Az első munkalapot olvassuk be, a lap neve tetszőleges lehet.</small>
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
                        Az első sor legyen a fejléc, ezekkel a pontos oszlopnevekkel (bármilyen sorrendben):
                    </div>
                    <ul class="text-muted ps-3">
                        <li><strong>Gyermek neve</strong></li>
                        <li><strong>Anyja neve</strong></li>
                        <li><strong>Apja neve</strong></li>
                        <li><strong>Lakóhely</strong> - ir. szám, település és cím együtt, pl. „1025 Budapest, Szilfa u. 4.”</li>
                        <li><strong>Csoport neve</strong></li>
                        <li><strong>Email</strong></li>
                    </ul>
                    <hr>
                    <p class="mb-2 small text-muted">
                        Anyja és/vagy apja neve közül legalább az egyik kötelező. Az elsőként megadott szülő (anya, ha szerepel) kapja a lakóhelyet és az e-mail címet - ő lesz az 1. gondviselő, és az ő adataiból jön létre a gyermek alapértelmezett számlázási profilja is. A másik szülő is bekerül gondviselőként, de cím/e-mail/számlázás nélkül.
                    </p>
                    <p class="mb-0 small text-muted">
                        Nincs oktatási azonosító oszlop, ezért a gyermek azonosítása intézményen belül a neve alapján történik - azonos nevű gyermek újbóli feltöltésnél frissül, nem duplikálódik.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
