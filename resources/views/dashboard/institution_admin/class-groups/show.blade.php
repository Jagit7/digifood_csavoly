@extends('layouts.superadmin')

@section('title', $classGroup->name.($institution->type === 'ovoda' ? ' csoport kezelése' : ' osztály kezelése'))

@section('content')
@php($isKindergarten = $institution->type === 'ovoda')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $classGroup->name.($isKindergarten ? ' csoport kezelése' : ' osztály kezelése'),
        'subtitle' => $classGroup->schoolYear->name.' tanév – névsor és csoporttagság kezelése',
        'buttonText' => $isKindergarten ? 'Vissza a csoportokhoz' : 'Vissza az osztályokhoz',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.class-groups.index', ['school_year' => $classGroup->school_year_id]),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => $isKindergarten ? 'Csoportlétszám' : 'Osztálylétszám',
            'value' => $stats['children'],
            'subtitle' => $isKindergarten ? 'Aktív csoporttagság' : 'Aktív osztálytagság',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Gondviselővel',
            'value' => $stats['with_guardian'],
            'subtitle' => 'Legalább egy kapcsolattal',
            'icon' => 'fa-solid fa-users',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Gondviselő nélkül',
            'value' => $stats['without_guardian'],
            'subtitle' => 'Ellenőrzést igényel',
            'icon' => 'fa-solid fa-user-shield',
            'color' => 'orange',
        ])
        @if($canViewBilling)
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Számlázás beállítva',
                'value' => $stats['with_billing'],
                'subtitle' => 'Elsődleges számlázási profillal',
                'icon' => 'fa-solid fa-file-invoice',
                'color' => 'purple',
            ])
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.class-groups.show', $classGroup) }}">
                <div class="row align-items-end">
                    <div class="col-lg-6 mb-3">
                        <label class="form-label">Név vagy oktatási azonosító</label>
                        <input type="search" name="search" class="form-control"
                               value="{{ request('search') }}" placeholder="Keresés {{ $isKindergarten ? 'a csoportban' : 'az osztályban' }}...">
                    </div>
                    <div class="col-lg-3 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->filled('search'))
                            <a href="{{ route('dashboard.institution.class-groups.show', $classGroup) }}" class="btn btn-light">
                                Törlés
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">{{ $isKindergarten ? 'Gyermekek' : 'Diákok' }}</h4>
            <span class="text-muted">Találatok: {{ $children->total() }}</span>
        </div>
        <div class="card-body">
            @if($children->count())
                <form method="POST" action="{{ route('dashboard.institution.class-groups.move-children', $classGroup) }}"
                      class="confirm-form"
                      data-title="Biztosan áthelyezed a kijelölt gyermekeket?"
                      data-text="A gyermekek jelenlegi osztály- vagy csoporttagsága lezárul, a kiválasztott új tagság pedig aktív lesz."
                      data-confirm-button-text="Igen, áthelyezem">
                    @csrf

                    @if($targetGroups->count())
                        <div class="row align-items-end mb-4">
                            <div class="col-lg-5">
                                <label class="form-label" for="target_class_group_id">
                                    {{ $isKindergarten ? 'Célcsoport' : 'Célosztály' }}
                                </label>
                                <select id="target_class_group_id" name="target_class_group_id" class="form-control">
                                    <option value="">Válassz {{ $isKindergarten ? 'csoportot' : 'osztályt' }}...</option>
                                    @foreach($targetGroups as $targetGroup)
                                        <option value="{{ $targetGroup->id }}" @selected((string) old('target_class_group_id') === (string) $targetGroup->id)>
                                            {{ $targetGroup->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4 mt-3 mt-lg-0">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-right-left me-1"></i>
                                    Kijelöltek áthelyezése
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info">
                            Ebben a tanévben nincs másik aktív {{ $isKindergarten ? 'csoport' : 'osztály' }},
                            ezért innen jelenleg nem lehet gyermeket áthelyezni.
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover table-responsive-md align-middle">
                            <thead>
                            <tr>
                                <th width="70">#</th>
                                @if($targetGroups->count())
                                    <th width="70">Kijelölés</th>
                                @endif
                                <th>{{ $isKindergarten ? 'Gyermek neve' : 'Diák neve' }}</th>
                                <th>Oktatási azonosító</th>
                                <th>Gondviselők</th>
                                <th>Adatforrás</th>
                                <th>Állapot</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($children as $child)
                                <tr>
                                    <td>{{ ($children->firstItem() ?? 0) + $loop->index }}</td>
                                    @if($targetGroups->count())
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="child_ids[]"
                                                   value="{{ $child->id }}" aria-label="{{ $child->name }} kijelölése"
                                                   @checked(in_array($child->id, old('child_ids', []))))>
                                        </td>
                                    @endif
                                    <td><strong>{{ $child->name }}</strong></td>
                                    <td>{{ $child->educational_identifier ?: '-' }}</td>
                                    <td>
                                        <span class="badge {{ $child->guardians_count ? 'badge-info' : 'badge-warning' }} light">
                                            {{ $child->guardians_count }} fő
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary light">
                                            @if($child->source_type === 'school_standard')
                                                Iskolai Excel
                                            @elseif($child->source_type === 'kindergarten')
                                                Óvodai Excel
                                            @else
                                                Egyéb
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $child->active ? 'badge-success' : 'badge-secondary' }} light">
                                            {{ $child->active ? 'Aktív' : 'Inaktív' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $children->links('vendor.pagination.digifood') }}
                    </div>
                </form>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-user-graduate',
                    'title' => 'Nincs a keresésnek megfelelő '.($isKindergarten ? 'gyermek' : 'diák'),
                    'text' => 'Módosítsd a keresési feltételeket.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
