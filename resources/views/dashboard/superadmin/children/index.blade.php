@extends('layouts.superadmin')

@section('title', 'Gyerekek / tanulók')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Gyerekek / tanulók',
        'subtitle' => 'Összesített, csak olvasható lista minden intézmény gyermek- és tanulóadatairól.',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes gyermek',
            'value' => $stats['total'],
            'subtitle' => 'Nyilvántartott gyermek és tanuló',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív gyermekek',
            'value' => $stats['active'],
            'subtitle' => 'Aktív státuszú rekordok',
            'icon' => 'fa-solid fa-user-check',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Étkező gyermekek',
            'value' => $stats['eating'],
            'subtitle' => 'Aktív étkezési beállítással',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Kedvezményes vagy diétás gyermekek',
            'value' => $stats['discount_or_dietary'],
            'subtitle' => 'Kedvezménnyel vagy diétás korlátozással',
            'icon' => 'fa-solid fa-notes-medical',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Digifood havidíj',
            'value' => number_format($digifoodFee['total'], 0, ',', ' ').' Ft',
            'subtitle' => $digifoodFee['uniform_rate'] !== null
                ? number_format($digifoodFee['eating_count'], 0, ',', ' ').' fő × '.number_format($digifoodFee['uniform_rate'], 0, ',', ' ').' Ft'
                : number_format($digifoodFee['eating_count'], 0, ',', ' ').' étkező gyermek × intézményenkénti havidíj, összesítve',
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'color' => 'red',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.superadmin.children.index') }}">
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
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">Név</label>
                        <input type="search" name="search" class="form-control"
                               value="{{ request('search') }}" placeholder="Keresés...">
                    </div>
                    <div class="col-xl-2 col-lg-6 mb-3">
                        <label class="form-label">Osztály / csoport</label>
                        <select name="group_name" class="form-control">
                            <option value="">Összes</option>
                            @foreach($groups as $group)
                                <option value="{{ $group }}" @selected(request('group_name') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-6 mb-3">
                        <label class="form-label">Státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Minden állapot</option>
                            <option value="active" @selected(request('status') === 'active')>Aktív</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inaktív</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-6 mb-3">
                        <label class="form-label">Étkezési státusz</label>
                        <select name="meal_status" class="form-control">
                            <option value="">Minden állapot</option>
                            <option value="eating" @selected(request('meal_status') === 'eating')>Étkező</option>
                            <option value="not_eating" @selected(request('meal_status') === 'not_eating')>Nem étkező</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Keresés
                        </button>
                        @if(request()->hasAny(['institution_id', 'search', 'group_name', 'status', 'meal_status']))
                            <a href="{{ route('dashboard.superadmin.children.index') }}" class="btn btn-light">
                                Szűrők törlése
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Gyermekek és tanulók</h4>
            <span class="text-muted">Találatok: {{ $children->total() }}</span>
        </div>
        <div class="card-body">
            @if($children->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="90">Azonosító</th>
                            <th>Gyermek / tanuló neve</th>
                            <th>Intézmény</th>
                            <th>Osztály vagy csoport</th>
                            <th>Kapcsolódó gondviselők</th>
                            <th>Étkezési státusz</th>
                            <th>Kedvezmény / diéta</th>
                            <th>Aktív státusz</th>
                            <th width="110" class="text-end">Műveletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($children as $child)
                            @php
                                $currentMealSetting = $child->getRelation('currentMealSetting');
                                $currentClassGroup = $child->getRelation('currentClassGroup');
                                $groupLabel = $child->institution?->type === 'ovoda' ? 'Csoport' : 'Osztály';
                                $groupName = $currentClassGroup?->name ?: $child->group_name;
                            @endphp
                            <tr>
                                <td>{{ $child->id }}</td>
                                <td>
                                    <strong>{{ $child->name }}</strong>
                                    @if($child->educational_identifier)
                                        <div class="small text-muted mt-1">Oktatási azonosító: {{ $child->educational_identifier }}</div>
                                    @endif
                                </td>
                                <td>{{ $child->institution?->name ?: 'Nincs intézmény' }}</td>
                                <td>
                                    @if($groupName)
                                        <span class="badge badge-primary light">{{ $groupLabel }}: {{ $groupName }}</span>
                                    @else
                                        <span class="text-muted">Nincs megadva</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($child->guardians as $guardian)
                                        <span class="badge badge-info light me-1 mb-1">{{ $guardian->full_name }}</span>
                                    @empty
                                        <span class="badge badge-warning light">Nincs kapcsolódó gondviselő</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($currentMealSetting)
                                        <span class="badge badge-success light">Étkező</span>
                                    @else
                                        <span class="badge badge-secondary light">Nem étkező</span>
                                    @endif
                                </td>
                                <td>
                                    @if($child->discountType && $child->discountType->percentage > 0)
                                        <span class="badge badge-primary light me-1 mb-1">{{ $child->discountType->name }}</span>
                                    @endif
                                    @foreach($child->dietaryRestrictions as $restriction)
                                        <span class="badge badge-warning light me-1 mb-1">{{ $restriction->name }}</span>
                                    @endforeach
                                    @if((!$child->discountType || $child->discountType->percentage <= 0) && $child->dietaryRestrictions->isEmpty())
                                        <span class="text-muted">Nincs</span>
                                    @endif
                                </td>
                                <td>
                                    @if($child->active)
                                        <span class="badge badge-success light">Aktív</span>
                                    @else
                                        <span class="badge badge-secondary light">Inaktív</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.superadmin.children.show', $child) }}"
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
                    {{ $children->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-user-graduate',
                    'title' => 'Nincs a szűrésnek megfelelő gyermek',
                    'text' => 'Módosítsd a keresési feltételeket, és próbáld meg újra.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
