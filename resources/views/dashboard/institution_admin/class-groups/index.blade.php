@extends('layouts.superadmin')

@section('title', $institution->type === 'ovoda' ? 'Csoportok' : 'Osztályok')

@section('content')
@php
    $isKindergarten = $institution->type === 'ovoda';
    $groupPlural = $isKindergarten ? 'Csoportok' : 'Osztályok';
    $groupSingularLower = $isKindergarten ? 'csoport' : 'osztály';
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $groupPlural,
        'subtitle' => $isKindergarten
            ? 'Tanévenkénti óvodai csoportok és gyermeknévsorok'
            : 'Tanévenkénti osztályok és diáknévsorok',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => $groupPlural,
            'value' => $stats['groups'],
            'subtitle' => $selectedSchoolYear?->name ?? 'Nincs kiválasztott tanév',
            'icon' => 'fa-solid fa-users',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Besorolt '.($isKindergarten ? 'gyermekek' : 'diákok'),
            'value' => $stats['assigned'],
            'subtitle' => 'Aktív osztálytagságok',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Besorolatlan '.($isKindergarten ? 'gyermekek' : 'diákok'),
            'value' => $stats['unassigned'],
            'subtitle' => 'Ellenőrzést igénylő rekordok',
            'icon' => 'fa-solid fa-user-slash',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Átlaglétszám',
            'value' => $stats['average'],
            'subtitle' => ucfirst($isKindergarten ? 'gyermek / csoport' : 'diák / osztály'),
            'icon' => 'fa-solid fa-chart-bar',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header"><h4 class="card-title mb-0">Tanév kiválasztása</h4></div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.class-groups.index') }}">
                <div class="row align-items-end">
                    <div class="col-lg-5 mb-3">
                        <label class="form-label">Tanév</label>
                        <select name="school_year" class="form-control" onchange="this.form.submit()">
                            @forelse($schoolYears as $schoolYear)
                                <option value="{{ $schoolYear->id }}" @selected($selectedSchoolYear?->id === $schoolYear->id)>
                                    {{ $schoolYear->name }}
                                    @if($schoolYear->is_current)
                                        – aktuális
                                    @endif
                                </option>
                            @empty
                                <option value="">Nincs rögzített tanév</option>
                            @endforelse
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">{{ $selectedSchoolYear?->name ?? 'Tanév' }} {{ $isKindergarten ? 'csoportjai' : 'osztályai' }}</h4>
            <span class="text-muted">{{ $groups->count() }} {{ $groupSingularLower }}</span>
        </div>
        <div class="card-body">
            @if($groups->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>{{ $isKindergarten ? 'Csoport' : 'Osztály' }}</th>
                            <th width="120" class="text-end">Művelet</th>
                            @unless($isKindergarten)
                                <th>Évfolyam</th>
                            @endunless
                            <th>Típus</th>
                            <th>Tanév</th>
                            <th>Létszám</th>
                            <th>Állapot</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($groups as $group)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $group->name }}</strong></td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.class-groups.show', $group) }}"
                                       class="btn btn-xs btn-outline-primary" title="Diáknévsor">
                                        <i class="fa-solid fa-users me-1"></i>Megnyitás
                                    </a>
                                </td>
                                @unless($isKindergarten)
                                    <td>{{ $group->grade_level ? $group->grade_level.'. évfolyam' : '-' }}</td>
                                @endunless
                                <td>
                                    @if($group->group_type === 'kindergarten_group')
                                        <span class="badge badge-info light">Óvodai csoport</span>
                                    @else
                                        <span class="badge badge-primary light">Iskolai osztály</span>
                                    @endif
                                </td>
                                <td>{{ $selectedSchoolYear->name }}</td>
                                <td><span class="badge badge-success light">{{ $group->active_children_count }} fő</span></td>
                                <td>
                                    @if($group->active)
                                        <span class="badge badge-success light">Aktív</span>
                                    @else
                                        <span class="badge badge-secondary light">Lezárt</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-users',
                    'title' => 'Ebben a tanévben még nincs '.$groupSingularLower,
                    'text' => $isKindergarten
                        ? 'Az óvodai importból létrejövő csoportok itt jelennek majd meg.'
                        : 'Az importált gyermekadatokból vagy tanévváltás során hozhatók létre.',
                ])
            @endif
        </div>
    </div>

    @unless($isKindergarten)
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-start">
                    <div class="bg-info-light rounded p-3 me-3">
                        <i class="fas fa-chart-line text-info fs-3"></i>
                    </div>
                    <div>
                        <h4 class="mb-1">Tanévváltás és osztályléptetés</h4>
                        <p class="text-muted mb-0">
                            Előnézettel ellenőrizheted, majd a következő tanévbe léptetheted a kijelölt osztályokat.
                            A végzős vagy távozó osztályok kihagyhatók.
                        </p>
                    </div>
                </div>
                <a href="{{ route('dashboard.institution.class-groups.promotion', array_filter([
                    'source_school_year' => $selectedSchoolYear?->id,
                ])) }}" class="btn btn-primary flex-shrink-0">
                    <i class="fa-solid fa-arrow-up-right-dots me-1"></i>
                    Osztályléptetés megnyitása
                </a>
            </div>
        </div>
    @endunless
</div>
@endsection
