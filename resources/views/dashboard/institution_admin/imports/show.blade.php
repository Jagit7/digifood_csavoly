@extends('layouts.superadmin')

@section('title', 'Import részletei')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Import részletei',
        'subtitle' => $import->original_file_name,
        'buttonText' => 'Importelőzmények',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.imports.index'),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Excel adatsorok',
            'value' => $import->row_count,
            'subtitle' => 'Fejléc nélküli sorok',
            'icon' => 'fa-solid fa-table-list',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Új gyermek/tanuló',
            'value' => $import->created_count,
            'subtitle' => 'Létrehozott rekordok',
            'icon' => 'fa-solid fa-user-plus',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Frissített gyermek/tanuló',
            'value' => $import->updated_count,
            'subtitle' => 'Meglévő rekordok',
            'icon' => 'fa-solid fa-user-pen',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Kihagyott sorok',
            'value' => $import->skipped_count,
            'subtitle' => 'Hibás adatok miatt',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'color' => 'orange',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Összegzés</h4>
            @if($import->status === 'completed')
                <span class="badge badge-success light">Sikeres</span>
            @elseif($import->status === 'completed_with_errors')
                <span class="badge badge-warning light">Részben sikeres</span>
            @elseif($import->status === 'failed')
                <span class="badge badge-danger light">Sikertelen</span>
            @else
                <span class="badge badge-info light">Feldolgozás alatt</span>
            @endif
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><strong>Importtípus</strong><br>{{ $import->profile_label }}</div>
                <div class="col-md-4 mb-3">
                    <strong>Osztály / csoport</strong><br>
                    {{ $import->group_name ?: ($import->profile === \App\Models\DataImport::PROFILE_KINDERGARTEN ? 'csoportonként az Excelben' : '-') }}
                </div>
                <div class="col-md-4 mb-3"><strong>Tanév / nevelési év</strong><br>{{ $import->school_year ?: '-' }}</div>
                <div class="col-md-4 mb-3"><strong>Feltöltő</strong><br>{{ $import->creator?->name ?? '-' }}</div>
                <div class="col-md-4 mb-3"><strong>Feltöltés</strong><br>{{ $import->created_at?->format('Y.m.d. H:i') }}</div>
                <div class="col-md-4 mb-3"><strong>Befejezés</strong><br>{{ $import->completed_at?->format('Y.m.d. H:i') ?? '-' }}</div>
            </div>
        </div>
    </div>

    @if($import->errors)
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Hibák és kihagyott sorok</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="120">Excel-sor</th>
                            <th>Hiba</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($import->errors as $error)
                            <tr>
                                <td>{{ $error['row'] ?? 'Fájl' }}</td>
                                <td>
                                    @foreach($error['messages'] ?? [] as $message)
                                        <div>{{ $message }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
