@extends('layouts.superadmin')

@push('styles')
    <style>
        /* Ez a stílusblokk kizárólag az étlap-részletező (menus/show) oldalra
           vonatkozik, a .df-menu-show gyökér osztályra korlátozva - nem globális CSS. */

        .df-menu-show .df-menu-show-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
        }

        @media (min-width: 992px) {
            .df-menu-show .df-menu-show-grid {
                /* Adatok kártya: kb. 300-350px, Előnézet: a fennmaradó teljes szélesség */
                grid-template-columns: minmax(300px, 350px) minmax(0, 1fr);
            }
        }

        .df-menu-show .df-menu-info-card,
        .df-menu-show .df-menu-preview-card {
            /* min-width:0 nélkül a grid elem a benne lévő tartalom (pl. hosszú
               fájlnév vagy nagy kép) miatt kinőhetne a rácsrekeszéből, és az
               egész oldal vízszintesen túlcsordulna. */
            min-width: 0;
        }

        .df-menu-show .df-menu-info-table {
            table-layout: fixed;
            width: 100%;
        }

        .df-menu-show .df-menu-info-table th {
            width: 100px;
            white-space: nowrap;
        }

        .df-menu-show .df-menu-info-table td {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .df-menu-show .df-menu-preview-body {
            min-width: 0;
            overflow: auto;
        }

        .df-menu-show .df-menu-preview-body img {
            display: block;
            max-width: 100%;
            width: auto;
            height: auto;
            margin: 0 auto;
        }

        .df-menu-show .df-menu-preview-body iframe {
            display: block;
            width: 100%;
            min-height: 75vh;
            border: 0;
        }
    </style>
@endpush

@section('content')

<div class="container-fluid df-menu-show">

    <div class="row mb-4">
        <div class="col-12">

            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>
                        <h3 class="mb-1">{{ $menu->title }}</h3>

                        <div class="text-muted">
                            {{ $institution->name }}
                        </div>
                    </div>

                    <div>

                        <a href="{{ route('dashboard.institution.menus.edit',$menu) }}"
                           class="btn btn-warning">
                            <i class="fa fa-edit me-1"></i>
                            Szerkesztés
                        </a>

                        <a href="{{ route('dashboard.institution.menus.index') }}"
                           class="btn btn-light">
                            Vissza
                        </a>

                    </div>

                </div>
            </div>

        </div>
    </div>

    <div class="df-menu-show-grid">

        <div class="card df-menu-info-card">
            <div class="card-header">
                <h5 class="mb-0">Adatok</h5>
            </div>

            <div class="card-body">

                <table class="table table-sm mb-0 df-menu-info-table">

                    <tr>
                        <th>Típus</th>
                        <td>{{ ucfirst($menu->type) }}</td>
                    </tr>

                    <tr>
                        <th>Kezdete</th>
                        <td>{{ $menu->week_start->format('Y.m.d.') }}</td>
                    </tr>

                    <tr>
                        <th>Vége</th>
                        <td>{{ $menu->week_end->format('Y.m.d.') }}</td>
                    </tr>

                    <tr>
                        <th>Szombat</th>
                        <td>
                            {!! $menu->has_saturday
                                ? '<span class="badge bg-success">Van</span>'
                                : '<span class="badge bg-secondary">Nincs</span>' !!}
                        </td>
                    </tr>

                    <tr>
                        <th>Állapot</th>
                        <td>
                            {!! $menu->active
                                ? '<span class="badge bg-success">Aktív</span>'
                                : '<span class="badge bg-danger">Inaktív</span>' !!}
                        </td>
                    </tr>

                    <tr>
                        <th>Fájl</th>
                        <td>{{ $menu->file_name }}</td>
                    </tr>

                </table>

            </div>
        </div>

        <div class="card df-menu-preview-card">

            <div class="card-header">
                <h5 class="mb-0">Előnézet</h5>
            </div>

            <div class="card-body p-0 df-menu-preview-body">

                @php
                    $fileUrl = route('dashboard.institution.menus.preview', $menu);
                @endphp

                @if($menu->isPreviewableImage())

                    <img src="{{ $fileUrl }}" alt="{{ $menu->file_name }}">

                @elseif($menu->isPreviewablePdf())

                    <iframe src="{{ $fileUrl }}" title="{{ $menu->file_name }}"></iframe>

                @else

                    <div class="p-5 text-center">
                        Ez a fájltípus nem jeleníthető meg.
                    </div>

                @endif

            </div>

        </div>

    </div>

</div>

@endsection