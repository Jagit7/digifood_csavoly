@extends('layouts.superadmin')

@section('title', 'Szülők')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Szülők',
        'subtitle' => 'Összesített szülői és gondviselői lista minden intézményből.',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes szülő',
            'value' => $stats['total'],
            'subtitle' => 'Nyilvántartott szülő és gondviselő',
            'icon' => 'fa-solid fa-users',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív szülői fiókok',
            'value' => $stats['active_accounts'],
            'subtitle' => 'Belépésre jogosult szülői fiókok',
            'icon' => 'fa-solid fa-user-check',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Fiók nélküli gondviselők',
            'value' => $stats['without_account'],
            'subtitle' => 'Még nem kapcsolt felhasználói fiókkal',
            'icon' => 'fa-solid fa-user-slash',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Több gyermekhez kapcsolódó szülők',
            'value' => $stats['multiple_children'],
            'subtitle' => 'Kettő vagy több gyermekhez rendelve',
            'icon' => 'fa-solid fa-children',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.superadmin.parents.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">Intézmény</label>
                        <select name="institution_id" class="form-control">
                            <option value="">Minden intézmény</option>
                            @foreach($institutions as $institution)
                                <option value="{{ $institution->id }}" @selected((string) request('institution_id') === (string) $institution->id)>
                                    {{ $institution->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-4 col-lg-6 mb-3">
                        <label class="form-label">Név vagy e-mail</label>
                        <input type="search" name="search" class="form-control"
                               value="{{ request('search') }}" placeholder="Keresés...">
                    </div>
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">Fiókstátusz</label>
                        <select name="account_status" class="form-control">
                            <option value="">Minden állapot</option>
                            <option value="active" @selected(request('account_status') === 'active')>Aktív fiók</option>
                            <option value="inactive" @selected(request('account_status') === 'inactive')>Inaktív fiók</option>
                            <option value="none" @selected(request('account_status') === 'none')>Nincs fiók</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-6 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->hasAny(['institution_id', 'search', 'account_status']))
                            <a href="{{ route('dashboard.superadmin.parents.index') }}"
                               class="btn btn-light" title="Szűrők törlése">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Szülők és gondviselők</h4>
            <span class="text-muted">Találatok: {{ $guardians->total() }}</span>
        </div>
        <div class="card-body">
            @if($guardians->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>Szülő / gondviselő neve</th>
                            <th>E-mail-cím</th>
                            <th>Intézmény</th>
                            <th>Kapcsolódó gyermekek</th>
                            <th>Osztály/csoport</th>
                            <th>Fiókstátusz</th>
                            <th width="110" class="text-end">Részletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($guardians as $guardian)
                            @php
                                $accountUser = $guardian->user;
                                $hasAccount = $accountUser && $accountUser->role === \App\Models\User::ROLE_PARENT;
                                $isActiveAccount = $hasAccount && $accountUser->is_active && !$accountUser->trashed();
                                $groupNames = $guardian->children
                                    ->pluck('group_name')
                                    ->filter()
                                    ->unique()
                                    ->values();
                            @endphp
                            <tr>
                                <td>{{ ($guardians->firstItem() ?? 0) + $loop->index }}</td>
                                <td><strong>{{ $guardian->full_name }}</strong></td>
                                <td>{{ $guardian->email ?: 'Nincs megadva' }}</td>
                                <td>{{ $guardian->institution?->name ?: 'Nincs intézmény' }}</td>
                                <td>
                                    @forelse($guardian->children as $child)
                                        <span class="badge badge-primary light me-1 mb-1">{{ $child->name }}</span>
                                    @empty
                                        <span class="badge badge-warning light">Nincs gyermekkapcsolat</span>
                                    @endforelse
                                </td>
                                <td>
                                    @forelse($groupNames as $groupName)
                                        <span class="badge badge-info light me-1 mb-1">{{ $groupName }}</span>
                                    @empty
                                        <span class="text-muted">Nincs megadva</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($isActiveAccount)
                                        <span class="badge badge-success light">Aktív fiók</span>
                                    @elseif($hasAccount)
                                        <span class="badge badge-secondary light">Inaktív fiók</span>
                                    @else
                                        <span class="badge badge-warning light">Nincs fiók</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.superadmin.parents.show', $guardian) }}"
                                       class="btn btn-xs btn-outline-primary" title="Részletek">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $guardians->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-users',
                    'title' => 'Nincs a szűrésnek megfelelő gondviselő',
                    'text' => 'Módosítsd a keresési feltételeket, és próbáld meg újra.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
