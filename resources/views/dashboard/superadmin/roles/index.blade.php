@extends('layouts.superadmin')

@section('title', 'Szerepkörök és jogosultságok')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Szerepkörök és jogosultságok',
        'subtitle' => 'Csak olvasható áttekintés a rendszerben kóddal rögzített szerepkörökről és hozzáférési szintekről.',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes felhasználó',
            'value' => $stats['total_users'],
            'subtitle' => 'Regisztrált felhasználói rekordok',
            'icon' => 'fa-solid fa-users',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív felhasználók',
            'value' => $stats['active_users'],
            'subtitle' => 'Bejelentkezésre jogosult felhasználók',
            'icon' => 'fa-solid fa-user-check',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Intézményi szerepkörök',
            'value' => $stats['institution_roles'],
            'subtitle' => 'Intézményhez kötött működési hozzáférések',
            'icon' => 'fa-solid fa-building',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Szülői fiókok',
            'value' => $stats['parent_accounts'],
            'subtitle' => 'Szülő / gondviselő szerepkörű felhasználók',
            'icon' => 'fa-solid fa-user-group',
            'color' => 'purple',
        ])
    </div>

    <div class="alert alert-info mb-4">
        <h5 class="alert-heading mb-2">Fontos tudnivaló</h5>
        <p class="mb-1">A szerepkörök jelenleg a rendszer kódjában rögzítettek.</p>
        <p class="mb-1">Ezen az oldalon nem módosíthatók.</p>
        <p class="mb-0">A jogosultságok módosítása fejlesztést és biztonsági felülvizsgálatot igényel.</p>
    </div>

    <div class="row">
        @foreach($roleCards as $role)
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="card-title mb-1">{{ $role['label'] }}</h4>
                            <div class="text-muted small">{{ $role['key'] }}</div>
                        </div>
                        <span class="badge badge-light">{{ $role['access_level'] }} hozzáférés</span>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">{{ $role['description'] }}</p>

                        <div class="row mb-3">
                            <div class="col-xl-2 col-md-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Összes felhasználó</div>
                                    <div class="h4 mb-0">{{ $role['total'] }}</div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Aktív</div>
                                    <div class="h4 mb-0 text-success">{{ $role['active_total'] }}</div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Inaktív</div>
                                    <div class="h4 mb-0 text-secondary">{{ $role['inactive_total'] }}</div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Intézményhez kötött</div>
                                    <div class="fw-semibold">{{ $role['institution_bound'] ? 'Igen' : 'Nem' }}</div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Felhasználók megjelenítve</div>
                                    <div class="fw-semibold">{{ min($role['users_total'], 10) }} / {{ $role['users_total'] }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="text-muted small mb-2">Fő rendszerterületek</div>
                            @foreach($role['areas'] as $area)
                                <span class="badge badge-primary light me-1 mb-1">{{ $area }}</span>
                            @endforeach
                        </div>

                        <div>
                            <h5 class="mb-3">Felhasználók megtekintése</h5>
                            @if($role['users']->count())
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th>Név</th>
                                            <th>E-mail</th>
                                            <th>Intézmény</th>
                                            <th>Státusz</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($role['users'] as $user)
                                            <tr>
                                                <td>{{ $user->name ?: 'Nincs megadva' }}</td>
                                                <td>{{ $user->email }}</td>
                                                <td>{{ $user->institution?->name ?: 'Nem intézményhez kötött' }}</td>
                                                <td>
                                                    @if($user->is_active)
                                                        <span class="badge badge-success light">Aktív</span>
                                                    @else
                                                        <span class="badge badge-secondary light">Inaktív</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <span class="text-muted">Nincs ehhez a szerepkörhöz felhasználó.</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
