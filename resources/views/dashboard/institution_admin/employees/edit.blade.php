@extends('layouts.superadmin')

@section('title', 'Dolgozó szerkesztése')

@section('content')
@php
    $employeesIndexUrl = route('dashboard.institution.employees.index')
        . (($returnQuery ?? '') !== '' ? '?' . $returnQuery : '');
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Dolgozó szerkesztése',
        'subtitle' => $employee->name,
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => $employeesIndexUrl,
    ])

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Vonalkód</h4>
        </div>
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge {{ $employee->hasActiveBarcode() ? 'badge-success' : ($employee->hasDisabledBarcode() ? 'badge-warning' : 'badge-secondary') }} light">
                        {{ $employee->barcodeStatusLabel() }}
                    </span>
                    @if($employee->barcode_token)
                        <span class="fw-semibold">{{ $employee->barcode_token }}</span>
                    @endif
                </div>
                @if($employee->barcodeGeneratedAtLabel())
                    <div class="small text-muted mt-2">Generálva: {{ $employee->barcodeGeneratedAtLabel() }}</div>
                @endif
            </div>

            <div class="d-flex gap-2 flex-wrap">
                @if(! $employee->hasBarcode())
                    <form method="POST" action="{{ route('dashboard.institution.employees.barcode.store', $employee) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-barcode me-1"></i>Vonalkód létrehozása
                        </button>
                    </form>
                @endif

                @if($employee->hasActiveBarcode())
                    <a href="{{ route('dashboard.institution.employees.barcode.print', $employee) }}" class="btn btn-outline-success">
                        <i class="fa-solid fa-print me-1"></i>Vonalkód nyomtatása
                    </a>

                    <form method="POST" action="{{ route('dashboard.institution.employees.barcode.destroy', $employee) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fa-solid fa-ban me-1"></i>Vonalkód letiltása
                        </button>
                    </form>
                @endif

                @if($employee->hasDisabledBarcode())
                    <form method="POST" action="{{ route('dashboard.institution.employees.barcode.activate', $employee) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-success">
                            <i class="fa-solid fa-check me-1"></i>Vonalkód aktiválása
                        </button>
                    </form>
                @endif

                @if($employee->hasBarcode())
                    <form method="POST" action="{{ route('dashboard.institution.employees.barcode.regenerate', $employee) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="fa-solid fa-rotate me-1"></i>Vonalkód újragenerálása
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

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

            <form method="POST" action="{{ route('dashboard.institution.employees.update', $employee) }}">
                @csrf
                @method('PUT')

                <input type="hidden" name="return_query" value="{{ old('return_query', $returnQuery ?? '') }}">

                @include('dashboard.institution_admin.employees.partials.form', [
                    'employee' => $employee,
                    'submitLabel' => 'Mentés',
                ])
            </form>
        </div>
    </div>
</div>
@endsection
