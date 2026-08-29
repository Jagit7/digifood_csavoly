@extends('layouts.superadmin')

@section('title', 'Adatimport')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Adatimport',
        'subtitle' => 'Tanulók, gyermekek és törvényes képviselők betöltése Excel-fájlból',
        'buttonText' => $institution->type === 'iskola'
            ? 'Iskolai Excel feltöltése'
            : ($institution->type === 'ovoda' ? 'Óvodai Excel feltöltése' : null),
        'buttonIcon' => 'fa-solid fa-file-arrow-up',
        'buttonUrl' => match ($institution->type) {
            'iskola' => route('dashboard.institution.imports.school-standard.create'),
            'ovoda' => route('dashboard.institution.imports.kindergarten.create'),
            default => null,
        },
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Feltöltött gyermek/tanuló',
            'value' => $stats['children'],
            'subtitle' => 'Aktív, importált rekordok',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'green',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Osztályok / csoportok',
            'value' => $stats['groups'],
            'subtitle' => 'Tanulókhoz rendelt egyedi csoportok',
            'icon' => 'fa-solid fa-users',
            'color' => 'blue',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Gondviselők',
            'value' => $stats['guardians'],
            'subtitle' => 'Szülők és törvényes képviselők',
            'icon' => 'fa-solid fa-users',
            'color' => 'purple',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Importált fájlok',
            'value' => $stats['imports'],
            'subtitle' => 'Eddigi Excel-feltöltések',
            'icon' => 'fa-solid fa-file-excel',
            'color' => 'orange',
        ])
    </div>

    <div class="row">
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary-light rounded p-3 me-3">
                            <i class="fa-solid fa-school text-primary fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-1">Iskolai Excel sablon</h4>
                            <span class="badge badge-success light">Elérhető</span>
                        </div>
                    </div>
                    <p class="text-muted flex-grow-1">
                        A kötött szerkezetű iskolai Excelből tanulókat, szülőket és törvényes képviselői kapcsolatokat importál.
                    </p>
                    @if($institution->type === 'iskola')
                        <a href="{{ route('dashboard.institution.imports.school-standard.create') }}"
                           class="btn btn-primary align-self-start">
                            <i class="fa-solid fa-file-arrow-up me-1"></i>
                            Feltöltés
                        </a>
                    @else
                        <div class="alert alert-warning mb-0 py-2">
                            Csak iskolai intézménynél használható.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning-light rounded p-3 me-3">
                            <i class="fa-solid fa-table-columns text-warning fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-1">Egyedi iskolai Excel</h4>
                            <span class="badge badge-warning light">Előkészítve</span>
                        </div>
                    </div>
                    <p class="text-muted flex-grow-1">
                        Saját oszlopstruktúrával rendelkező iskolai exportok külön leképezési profillal.
                    </p>
                    <button type="button" class="btn btn-light align-self-start" disabled>
                        A minta Excelre vár
                    </button>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info-light rounded p-3 me-3">
                            <i class="fa-solid fa-shapes text-info fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-1">Óvodai Excel</h4>
                            <span class="badge badge-success light">Elérhető</span>
                        </div>
                    </div>
                    <p class="text-muted flex-grow-1">
                        Gyermek neve, anyja/apja neve, lakóhelye, csoportja és e-mail címe alapján importál gyermeket, gondviselőt és számlázási adatot.
                    </p>
                    @if($institution->type === 'ovoda')
                        <a href="{{ route('dashboard.institution.imports.kindergarten.create') }}"
                           class="btn btn-primary align-self-start">
                            <i class="fa-solid fa-file-arrow-up me-1"></i>
                            Feltöltés
                        </a>
                    @else
                        <div class="alert alert-warning mb-0 py-2">
                            Csak óvodai intézménynél használható.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Importelőzmények</h4>
        </div>
        <div class="card-body">
            @if($imports->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>Fájl</th>
                            <th>Importtípus</th>
                            <th>Osztály / csoport</th>
                            <th>Eredmény</th>
                            <th>Feltöltő</th>
                            <th>Időpont</th>
                            <th class="text-end">Művelet</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($imports as $import)
                            <tr>
                                <td>{{ ($imports->firstItem() ?? 0) + $loop->index }}</td>
                                <td><strong>{{ $import->original_file_name }}</strong></td>
                                <td>{{ $import->profile_label }}</td>
                                <td>{{ $import->group_name ?: '-' }}</td>
                                <td>
                                    @if($import->status === 'completed')
                                        <span class="badge badge-success light">Sikeres</span>
                                    @elseif($import->status === 'completed_with_errors')
                                        <span class="badge badge-warning light">Részben sikeres</span>
                                    @elseif($import->status === 'failed')
                                        <span class="badge badge-danger light">Sikertelen</span>
                                    @else
                                        <span class="badge badge-info light">Feldolgozás alatt</span>
                                    @endif
                                </td>
                                <td>{{ $import->creator?->name ?? '-' }}</td>
                                <td>{{ $import->created_at?->format('Y.m.d. H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.imports.show', $import) }}"
                                       class="btn btn-xs btn-outline-primary" title="Részletek">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $imports->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-file-import',
                    'title' => 'Még nem történt adatimport',
                    'text' => 'Az első feltöltés eredménye és hibái itt jelennek majd meg.',
                    'buttonText' => $institution->type === 'iskola'
                        ? 'Iskolai Excel feltöltése'
                        : ($institution->type === 'ovoda' ? 'Óvodai Excel feltöltése' : null),
                    'buttonIcon' => 'fa-solid fa-file-arrow-up',
                    'buttonUrl' => match ($institution->type) {
                        'iskola' => route('dashboard.institution.imports.school-standard.create'),
                        'ovoda' => route('dashboard.institution.imports.kindergarten.create'),
                        default => null,
                    },
                ])
            @endif
        </div>
    </div>
</div>
@endsection
