@extends('layouts.superadmin')

@section('title', 'Dolgozók')

@push('styles')
    <style>
        .table-compact th,
        .table-compact td {
            padding-top: .4rem !important;
            padding-bottom: .4rem !important;
        }
        .employee-table-wrap {
            overflow-x: auto;
        }
        .employee-table th,
        .employee-table td {
            white-space: nowrap;
        }
        .employee-table .employee-name-column {
            position: sticky;
            left: 0;
            min-width: 220px;
            max-width: 320px;
            background: #fff;
            z-index: 2;
            box-shadow: 4px 0 6px -6px rgba(0, 0, 0, 0.45);
        }
        .employee-table thead .employee-name-column {
            z-index: 3;
            background: #f4f5f7;
        }
        .employee-table tbody tr:nth-child(even) .employee-name-column {
            background: #fdfdfd;
        }
        .employee-table tbody tr:hover .employee-name-column {
            background: #f1eefb;
        }
        .employee-table .employee-name-text {
            display: inline-block;
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: bottom;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Dolgozók',
        'subtitle' => 'Az intézmény dolgozóinak törzsadat-kezelése',
        'buttonText' => 'Új dolgozó',
        'buttonIcon' => 'fa-solid fa-user-plus',
        'buttonUrl' => route('dashboard.institution.employees.create'),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes dolgozó',
            'value' => $stats['total'],
            'subtitle' => 'Rögzített dolgozói rekordok',
            'icon' => 'fa-solid fa-id-badge',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív dolgozók',
            'value' => $stats['active'],
            'subtitle' => 'Jelenleg használatban',
            'icon' => 'fa-solid fa-user-check',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Inaktív dolgozók',
            'value' => $stats['inactive'],
            'subtitle' => 'Archivált vagy szüneteltetett',
            'icon' => 'fa-solid fa-user-slash',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Kedvezménnyel',
            'value' => $stats['with_discount'],
            'subtitle' => 'Kedvezménytípussal rögzítve',
            'icon' => 'fa-solid fa-percent',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.employees.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">Név</label>
                        <input type="search"
                               name="search"
                               class="form-control"
                               value="{{ request('search') }}"
                               placeholder="Keresés dolgozó névre">
                    </div>
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">E-mail-cím</label>
                        <input type="search"
                               name="email"
                               class="form-control"
                               value="{{ request('email') }}"
                               placeholder="Keresés e-mail-címre">
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Diéta</label>
                        <select name="diet_filter" class="form-control">
                            <option value="">Minden dolgozó</option>
                            <option value="with_diet" @selected(request('diet_filter') === 'with_diet')>Diétás</option>
                            <option value="without_diet" @selected(request('diet_filter') === 'without_diet')>Nem diétás</option>
                            @if($dietaryRestrictions->isNotEmpty())
                                <optgroup label="Konkrét diéták">
                                    @foreach($dietaryRestrictions as $restriction)
                                        <option value="restriction_{{ $restriction->id }}" @selected(request('diet_filter') === 'restriction_' . $restriction->id)>
                                            {{ $restriction->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Kedvezmény</label>
                        <select name="discount_filter" class="form-control">
                            <option value="">Minden dolgozó</option>
                            <option value="with_discount" @selected(request('discount_filter') === 'with_discount')>Kedvezménnyel rendelkező</option>
                            <option value="without_discount" @selected(request('discount_filter') === 'without_discount')>Kedvezmény nélküli</option>
                            @if($discountTypes->isNotEmpty())
                                <optgroup label="Konkrét kedvezmények">
                                    @foreach($discountTypes as $discountType)
                                        <option value="discount_{{ $discountType->id }}" @selected(request('discount_filter') === 'discount_' . $discountType->id)>
                                            {{ $discountType->name }} ({{ $discountType->percentage }}%)
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>
                    <div class="col-xl-1 col-lg-2 mb-3">
                        <label class="form-label">Állapot</label>
                        <select name="status" class="form-control">
                            <option value="">Mind</option>
                            <option value="active" @selected(request('status') === 'active')>Aktív</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inaktív</option>
                        </select>
                    </div>
                </div>
                <div>
                    <div class="col-xl-3 col-lg-6 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Szűrés
                        </button>
                        @if(request()->hasAny(['search', 'email', 'status', 'diet_filter', 'discount_filter']))
                            <a href="{{ route('dashboard.institution.employees.index') }}" class="btn btn-light" title="Szűrők törlése">
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
            <h4 class="card-title mb-0">Dolgozói lista</h4>
            <span class="text-muted">Találatok: {{ $employees->total() }}</span>
        </div>
        <div class="card-body">
            @if($employees->count())
                <div class="employee-table-wrap">
                    <table class="table table-hover table-responsive-md align-middle table-compact employee-table">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th class="employee-name-column">Név</th>
                            <th width="170" class="text-end">Műveletek</th>
                            <th>E-mail</th>
                            <th>Telefonszám</th>
                            <th>Kedvezmény</th>
                            <th>Diéta</th>
                            <th>Vonalkód</th>
                            <th>Állapot</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($employees as $employee)
                            <tr>
                                <td>{{ ($employees->firstItem() ?? 0) + $loop->index }}</td>
                                <td class="employee-name-column">
                                    <strong class="employee-name-text" title="{{ $employee->name }}">{{ $employee->name }}</strong>
                                </td>
                                <td class="text-end">
                                    @if($employee->hasActiveBarcode())
                                        <a href="{{ route('dashboard.institution.employees.barcode.print', $employee) }}"
                                           class="btn btn-xs btn-outline-success"
                                           title="Vonalkód nyomtatása">
                                            <i class="fa-solid fa-barcode"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('dashboard.institution.employees.meal-settings.index', $employee) }}"
                                       class="btn btn-xs btn-outline-primary"
                                       title="Étkezési beállítások">
                                        <i class="fa-solid fa-utensils"></i>
                                    </a>
                                    <a href="{{ route('dashboard.institution.employees.meal-cancellations.index', ['institution_employee_id' => $employee->id]) }}"
                                       class="btn btn-xs btn-outline-danger"
                                       title="Étkezéslemondások">
                                        <i class="fa-solid fa-calendar-xmark"></i>
                                    </a>
                                    <form method="POST"
                                          action="{{ route('dashboard.institution.employees.toggle-active', $employee) }}"
                                          class="d-inline">
                                        @csrf
                                        <input type="hidden" name="page" value="{{ $employees->currentPage() }}">
                                        <input type="hidden" name="search" value="{{ request('search') }}">
                                        <input type="hidden" name="email" value="{{ request('email') }}">
                                        <input type="hidden" name="status" value="{{ request('status') }}">
                                        <input type="hidden" name="diet_filter" value="{{ request('diet_filter') }}">
                                        <input type="hidden" name="discount_filter" value="{{ request('discount_filter') }}">
                                        <button type="submit"
                                                class="btn btn-xs {{ $employee->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $employee->active ? 'Inaktiválás' : 'Aktiválás' }}">
                                            <i class="fa-solid {{ $employee->active ? 'fa-pause' : 'fa-play' }}"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('dashboard.institution.employees.edit', $employee) }}{{ request()->getQueryString() ? '?return_query=' . urlencode(request()->getQueryString()) : '' }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                </td>
                                <td>{{ $employee->email ?: '—' }}</td>
                                <td>{{ $employee->phone ?: '—' }}</td>
                                <td>
                                    @if($employee->discountType)
                                        <span class="badge badge-primary light">{{ $employee->discountType->percentage }}%</span>
                                        <div class="small text-muted mt-1">{{ $employee->discountType->name }}</div>
                                    @else
                                        <span class="text-muted">Nincs megadva</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($employee->dietaryRestrictions as $restriction)
                                        <span class="badge {{ $restriction->type === \App\Models\DietaryRestriction::TYPE_ALLERGEN ? 'badge-warning' : 'badge-info' }} light me-1 mb-1">
                                            {{ $restriction->name }}
                                        </span>
                                    @empty
                                        <span class="text-muted">Nincs</span>
                                    @endforelse
                                </td>
                                <td>
                                    <span class="badge {{ $employee->hasActiveBarcode() ? 'badge-success' : ($employee->hasDisabledBarcode() ? 'badge-warning' : 'badge-secondary') }} light">
                                        {{ $employee->barcodeStatusLabel() }}
                                    </span>
                                    @if($employee->barcodeGeneratedAtLabel())
                                        <div class="small text-muted mt-1">{{ $employee->barcodeGeneratedAtLabel() }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $employee->active ? 'badge-success' : 'badge-secondary' }} light">
                                        {{ $employee->active ? 'Aktív' : 'Inaktív' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $employees->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-id-badge',
                    'title' => 'Még nincs dolgozó',
                    'text' => 'Vedd fel az első intézményi dolgozót.',
                    'buttonText' => 'Új dolgozó',
                    'buttonIcon' => 'fa-solid fa-plus',
                    'buttonUrl' => route('dashboard.institution.employees.create'),
                ])
            @endif
        </div>
    </div>
</div>
@endsection
