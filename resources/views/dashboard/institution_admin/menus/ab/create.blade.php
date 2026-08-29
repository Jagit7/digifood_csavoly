@extends('layouts.superadmin')
@section('title', 'A/B menü import')
@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'A/B + diétás menü import',
        'subtitle' => 'Excel vagy CSV fájl feltöltése',
        'buttonText' => 'Vissza a listához',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.menus.ab.index')
    ])
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Excel import</h4>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <strong>Importálási hiba</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="alert alert-info">
                Az Excel oszlopai legyenek:
                <strong>Dátum, A menü, B menü, Diétás menü, Megjegyzés</strong>.
            </div>
            <div class="mb-4">
                <a href="{{ route('dashboard.institution.menus.ab.sample') }}"
                   class="btn btn-outline-success">
                    <i class="fa-solid fa-download me-1"></i>
                    Minta Excel letöltése
                </a>
            </div>
            <form method="POST"
                  action="{{ route('dashboard.institution.menus.ab.store') }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Excel / CSV fájl</label>
                    <input type="file"
                           name="file"
                           class="form-control @error('file') is-invalid @enderror"
                           accept=".xlsx,.xls,.csv"
                           required>
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-file-import me-1"></i>
                    Import indítása
                </button>
            </form>
        </div>
    </div>
</div>
@endsection