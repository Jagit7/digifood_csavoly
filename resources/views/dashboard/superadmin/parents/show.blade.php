@extends('layouts.superadmin')

@section('title', 'Szülő részletei')

@section('content')
@php
    $accountUser = $guardian->user;
    $hasAccount = $accountUser && $accountUser->role === \App\Models\User::ROLE_PARENT;
    $isActiveAccount = $hasAccount && $accountUser->is_active && !$accountUser->trashed();
@endphp

<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $guardian->full_name,
        'subtitle' => 'Szülői / gondviselői adatok csak olvasható nézetben.',
        'buttonText' => 'Vissza a listához',
        'buttonUrl' => route('dashboard.superadmin.parents.index'),
    ])

    <div class="row">
        <div class="col-xl-4 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Alapadatok</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Név</div>
                        <div class="fw-semibold">{{ $guardian->full_name }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">E-mail-cím</div>
                        <div>{{ $guardian->email ?: 'Nincs megadva' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Telefonszám</div>
                        <div>{{ $guardian->phone ?: 'Nincs megadva' }}</div>
                    </div>
                    <div>
                        <div class="text-muted small">Intézmény</div>
                        <div>{{ $guardian->institution?->name ?: 'Nincs intézmény' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Státusz</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Szülői fiók</div>
                        <div>
                            @if($isActiveAccount)
                                <span class="badge badge-success light">Aktív</span>
                            @elseif($hasAccount)
                                <span class="badge badge-secondary light">Inaktív</span>
                            @else
                                <span class="badge badge-warning light">Nincs fiók</span>
                            @endif
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Gondviselő rekord</div>
                        <div>
                            @if($guardian->active)
                                <span class="badge badge-success light">Aktív</span>
                            @else
                                <span class="badge badge-secondary light">Inaktív</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-muted small">Kapcsolt gyermekek száma</div>
                        <div class="fw-semibold">{{ $guardian->children->count() }} fő</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Műveletek</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        Ezen a superadmin oldalon a szülői adatok csak megtekinthetők. Szerkesztés, törlés, jelszómódosítás
                        és más felhasználóként történő belépés itt nem érhető el.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Kapcsolódó gyermekek</h4>
        </div>
        <div class="card-body">
            @if($guardian->children->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Gyermek neve</th>
                            <th>Osztály/csoport</th>
                            <th>Kapcsolat típusa</th>
                            <th>Törvényes képviselő</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($guardian->children as $child)
                            <tr>
                                <td>{{ $child->name }}</td>
                                <td>{{ $child->group_name ?: 'Nincs megadva' }}</td>
                                <td>{{ $child->pivot->relationship_type ?: 'Nincs megadva' }}</td>
                                <td>
                                    @if($child->pivot->is_legal_representative)
                                        <span class="badge badge-success light">Igen</span>
                                    @else
                                        <span class="badge badge-light">Nem</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-child',
                    'title' => 'Nincs kapcsolódó gyermek',
                    'text' => 'Ehhez a gondviselőhöz jelenleg nincs gyermek rendelve.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
