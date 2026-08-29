@extends('layouts.superadmin')

@section('content')

<div class="container-fluid">

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

    <div class="row">

        <div class="col-xl-3">

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Adatok</h5>
                </div>

                <div class="card-body">

                    <table class="table table-sm mb-0">

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

        </div>

        <div class="col-xl-9">

            <div class="card">

                <div class="card-header">
                    <h5 class="mb-0">Előnézet</h5>
                </div>

                <div class="card-body p-0">

                    @php
                        $fileUrl = asset('storage/'.$menu->file_path);
                        $extension = strtolower(pathinfo($menu->file_path, PATHINFO_EXTENSION));
                    @endphp

                    @if(in_array($extension, ['jpg', 'jpeg', 'png', 'webp']))

                        <img src="{{ $fileUrl }}" class="img-fluid w-100">

                    @elseif($extension === 'pdf')

                        <iframe src="{{ $fileUrl }}"
                                width="100%"
                                height="900"
                                style="border:0;">
                        </iframe>

                    @else

                        <div class="p-5 text-center">
                            Ez a fájltípus nem jeleníthető meg.
                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>

</div>

@endsection