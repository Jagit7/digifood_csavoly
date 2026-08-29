@extends('layouts.superadmin')

@section('title', 'Egyéni lemondások')

@push('styles')
    <style>
        .table-compact th,
        .table-compact td {
            padding-top: .4rem !important;
            padding-bottom: .4rem !important;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Egyéni lemondások',
        'subtitle' => 'Telefonon vagy személyesen jelzett gyermekenkénti étkezéslemondások kezelése',
        'buttonText' => 'Új lemondás',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.meal-cancellations.create'),
    ])

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('dashboard.institution.meal-cancellations.bulk.create') }}" class="btn btn-outline-primary">
            <i class="fa-solid fa-users-viewfinder me-1"></i>Csoportos lemondás
        </a>
        <a href="{{ route('dashboard.institution.meal-cancellations.bulk.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-list me-1"></i>Csoportos műveletek
        </a>
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Ma rögzítve', 'value' => $stats['recorded_today'],
            'subtitle' => 'Egyedi és rendszeres', 'icon' => 'fa-solid fa-save', 'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Közelgő egyedi', 'value' => $stats['upcoming'],
            'subtitle' => 'Aktív napi lemondások', 'icon' => 'fa-solid fa-calendar-check', 'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Rendszeres szabályok', 'value' => $stats['recurring'],
            'subtitle' => 'Jelenleg aktív', 'icon' => 'fa-solid fa-history', 'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Következő étkezési nap', 'value' => $stats['next_day_total'],
            'subtitle' => ($window['next_service_day']?->format('Y.m.d.') ?? 'Nincs').' – összes lemondás',
            'icon' => 'fa-solid fa-users', 'color' => 'orange',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header"><h4 class="card-title mb-0">Keresés és szűrés</h4></div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.meal-cancellations.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-4 col-lg-4 mb-3">
                        <label class="form-label">Gyermek neve vagy oktatási azonosítója</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}"
                               placeholder="A név elejétől keress...">
                    </div>
                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Dátumtól</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Dátumig</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Állapot</label>
                        <select name="status" class="form-control">
                            <option value="active" @selected($status === 'active')>Aktív</option>
                            <option value="revoked" @selected($status === 'revoked')>Lezárt / visszavont</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-2 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        <a href="{{ route('dashboard.institution.meal-cancellations.index') }}" class="btn btn-light" title="Szűrők törlése">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Napi lemondások</h4>
            <span class="text-muted">Találatok: {{ $mealCancellations->total() }}</span>
        </div>
        <div class="card-body">
            @if($mealCancellations->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle table-compact">
                        <thead><tr>
                            <th width="70">#</th><th>Gyermek</th><th>Osztály / csoport</th><th>Étkezési nap</th>
                            <th>Forrás</th><th>Megjegyzés</th><th>Rögzítette</th><th>Állapot</th>
                            <th width="100" class="text-end">Művelet</th>
                        </tr></thead>
                        <tbody>
                        @foreach($mealCancellations as $cancellation)
                            <tr>
                                <td>{{ ($mealCancellations->firstItem() ?? 0) + $loop->index }}</td>
                                <td><strong>{{ $cancellation->child?->name ?? '—' }}</strong></td>
                                <td><span class="badge badge-primary light">{{ $cancellation->child?->group_name ?: '—' }}</span></td>
                                <td>{{ $cancellation->service_date->format('Y.m.d.') }}</td>
                                <td>{{ $cancellation->source === 'parent' ? 'Szülő' : 'Admin' }}</td>
                                <td>{{ $cancellation->reason ? Str::limit($cancellation->reason, 55) : '—' }}</td>
                                <td>{{ $cancellation->creator?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $cancellation->status === 'active' ? 'badge-success' : 'badge-secondary' }} light">
                                        {{ $cancellation->status === 'active' ? 'Aktív' : 'Visszavont' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if($cancellation->status === 'active' && $window['earliest_cancellable_day'] && $cancellation->service_date->gte($window['earliest_cancellable_day']))
                                        <form method="POST" action="{{ route('dashboard.institution.meal-cancellations.destroy', $cancellation) }}"
                                              class="d-inline confirm-form" data-title="Visszavonod ezt a lemondást?"
                                              data-text="A gyermek étkezése ismét megrendeltnek számít ezen a napon."
                                              data-confirm-button-text="Igen, visszavonom">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-warning" title="Lemondás visszavonása">
                                                <i class="fa fa-undo"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $mealCancellations->links('vendor.pagination.digifood') }}</div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-ban', 'title' => 'Nincs a szűrésnek megfelelő napi lemondás',
                    'text' => 'Rögzíts egy gyermekhez egyszeri vagy többnapos lemondást.',
                    'buttonText' => 'Új lemondás', 'buttonIcon' => 'fa-solid fa-plus',
                    'buttonUrl' => route('dashboard.institution.meal-cancellations.create'),
                ])
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Rendszeres lemondások</h4>
            <span class="text-muted">Találatok: {{ $recurringRules->total() }}</span>
        </div>
        <div class="card-body">
            @php($weekdayLabels = [1 => 'Hétfő', 2 => 'Kedd', 3 => 'Szerda', 4 => 'Csütörtök', 5 => 'Péntek', 6 => 'Szombat', 7 => 'Vasárnap'])
            @if($recurringRules->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle table-compact">
                        <thead><tr>
                            <th width="70">#</th><th>Gyermek</th><th>Osztály / csoport</th><th>Nap</th>
                            <th>Érvényesség</th><th>Megjegyzés</th><th>Rögzítette</th><th>Állapot</th>
                            <th width="100" class="text-end">Művelet</th>
                        </tr></thead>
                        <tbody>
                        @foreach($recurringRules as $rule)
                            <tr>
                                <td>{{ ($recurringRules->firstItem() ?? 0) + $loop->index }}</td>
                                <td><strong>{{ $rule->child?->name ?? '—' }}</strong></td>
                                <td><span class="badge badge-primary light">{{ $rule->child?->group_name ?: '—' }}</span></td>
                                <td>{{ $weekdayLabels[$rule->weekday] ?? '—' }}</td>
                                <td>{{ $rule->starts_on->format('Y.m.d.') }} – {{ $rule->ends_on?->format('Y.m.d.') ?? 'visszavonásig' }}</td>
                                <td>{{ $rule->reason ? Str::limit($rule->reason, 55) : '—' }}</td>
                                <td>{{ $rule->creator?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $rule->status === 'active' && !$rule->ends_on?->isPast() ? 'badge-success' : 'badge-secondary' }} light">
                                        {{ $rule->status === 'active'
                                            ? ($rule->ends_on?->isPast() ? 'Lejárt' : 'Aktív')
                                            : ($rule->status === 'ended' ? 'Lezárt' : 'Visszavont') }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if($rule->status === 'active')
                                        <form method="POST" action="{{ route('dashboard.institution.meal-cancellations.recurring.destroy', $rule) }}"
                                              class="d-inline confirm-form" data-title="Lezárod a rendszeres lemondást?"
                                              data-text="A már határidőn belüli alkalmak megmaradnak, a későbbiek megszűnnek."
                                              data-confirm-button-text="Igen, lezárom">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-warning" title="Rendszeres lemondás lezárása">
                                                <i class="fa fa-stop"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $recurringRules->links('vendor.pagination.digifood') }}</div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-history', 'title' => 'Nincs a szűrésnek megfelelő rendszeres lemondás',
                    'text' => 'Például minden keddi étkezés egyetlen szabállyal lemondható.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
