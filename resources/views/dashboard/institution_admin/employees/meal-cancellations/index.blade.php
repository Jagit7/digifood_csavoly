@extends('layouts.superadmin')

@section('title', 'Dolgozói étkezéslemondások')

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
        'title' => 'Dolgozói étkezéslemondások',
        'subtitle' => 'Egyszeri, nap alapú dolgozói lemondások kezelése',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Találatok',
            'value' => $cancellations->total(),
            'subtitle' => 'A jelenlegi szűrés eredménye',
            'icon' => 'fa-solid fa-calendar-xmark',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív dolgozók',
            'value' => $employees->count(),
            'subtitle' => 'Lemondható dolgozói rekord',
            'icon' => 'fa-solid fa-id-badge',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Ma rögzítve',
            'value' => $stats['recorded_today'],
            'subtitle' => 'Ma rögzített dolgozói lemondások',
            'icon' => 'fa-solid fa-save',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Közelgő egyedi',
            'value' => $stats['upcoming'],
            'subtitle' => 'Aktív, jövőbeli dolgozói lemondások',
            'icon' => 'fa-solid fa-calendar-check', 'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Rendszeres szabályok',
            'value' => $stats['recurring'],
            'subtitle' => 'Jelenleg aktív',
            'icon' => 'fa-solid fa-history', 'color' => 'red',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Következő étkezési nap',
            'value' => $stats['next_day_total'],
            'subtitle' => ($window['next_service_day']?->format('Y.m.d.') ?? 'Nincs').' – összes dolgozói lemondás',
            'icon' => 'fa-solid fa-users', 'color' => 'orange',
        ])
    </div>

    @if(!$window['configured'])
        <div class="alert alert-warning">
            <strong>Nincs beállítva lemondási határidő.</strong>
            Dolgozói lemondás rögzítése előtt add meg az intézményi határidőt a Kedvezmények és szabályok oldalon.
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Új lemondás rögzítése</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.employees.meal-cancellations.store') }}">
                @csrf
                <div class="row align-items-end">
                    <div class="col-lg-4 mb-3">
                        <label class="form-label">Dolgozó</label>
                        <select name="institution_employee_id" class="form-control" required>
                            <option value="">Válassz dolgozót</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('institution_employee_id', $selectedEmployeeId) === (string) $employee->id)>
                                    {{ $employee->name }}{{ $employee->email ? ' - '.$employee->email : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label for="employee-mode" class="form-label">Lemondás típusa</label>
                        <select name="mode" id="employee-mode" class="form-control">
                            <option value="single" @selected(old('mode', 'single') === 'single')>Egy nap</option>
                            <option value="range" @selected(old('mode') === 'range')>Többnapos időszak</option>
                            <option value="recurring" @selected(old('mode') === 'recurring')>Rendszeres, heti lemondás</option>
                        </select>
                    </div>
                </div>

                <div id="employee-single-fields" class="employee-cancellation-mode-fields">
                    <div class="row">
                        <div class="col-lg-3 mb-3">
                            <label class="form-label">Étkezési nap</label>
                            <input type="date" name="service_date" id="employee-service-date" class="form-control" value="{{ old('service_date') }}">
                        </div>
                    </div>
                </div>

                <div id="employee-range-fields" class="employee-cancellation-mode-fields d-none">
                    <div class="row">
                        <div class="col-lg-3 mb-3">
                            <label class="form-label">Időszak kezdete</label>
                            <input type="date" name="date_from" id="employee-date-from" class="form-control" value="{{ old('date_from') }}">
                        </div>
                        <div class="col-lg-3 mb-3">
                            <label class="form-label">Időszak vége</label>
                            <input type="date" name="date_to" id="employee-date-to" class="form-control" value="{{ old('date_to') }}">
                        </div>
                    </div>
                    <div class="small text-muted mb-3">
                        A hétvégéket és szüneteket a rendszer automatikusan kihagyja.
                    </div>
                </div>

                <div id="employee-recurring-fields" class="employee-cancellation-mode-fields d-none">
                    <div class="row">
                        <div class="col-lg-3 mb-3">
                            <label class="form-label">Minden</label>
                            <select name="weekday" id="employee-weekday" class="form-control">
                                <option value="1" @selected((string) old('weekday') === '1')>Hétfő</option>
                                <option value="2" @selected((string) old('weekday') === '2')>Kedd</option>
                                <option value="3" @selected((string) old('weekday') === '3')>Szerda</option>
                                <option value="4" @selected((string) old('weekday') === '4')>Csütörtök</option>
                                <option value="5" @selected((string) old('weekday') === '5')>Péntek</option>
                                <option value="6" @selected((string) old('weekday') === '6')>Szombat</option>
                                <option value="7" @selected((string) old('weekday') === '7')>Vasárnap</option>
                            </select>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <label class="form-label">Kezdőnap</label>
                            <input type="date" name="starts_on" id="employee-starts-on" class="form-control" value="{{ old('starts_on') }}">
                        </div>
                        <div class="col-lg-3 mb-3">
                            <label class="form-label">Végdátum</label>
                            <input type="date" name="ends_on" id="employee-ends-on" class="form-control" value="{{ old('ends_on') }}">
                            <div class="small text-muted">Üresen hagyva visszavonásig.</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <label class="form-label">Megjegyzés</label>
                        <input type="text" name="reason" class="form-control" maxlength="191" value="{{ old('reason') }}" placeholder="Opcionális megjegyzés">
                    </div>
                    <div class="col-lg-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i>Rögzítés
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.employees.meal-cancellations.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Dolgozó</label>
                        <select name="institution_employee_id" class="form-control">
                            <option value="">Összes dolgozó</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) request('institution_employee_id') === (string) $employee->id)>
                                    {{ $employee->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Név vagy e-mail</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Keresés dolgozóra">
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Összes</option>
                            <option value="active" @selected(request('status') === 'active')>Aktív</option>
                            <option value="revoked" @selected(request('status') === 'revoked')>Visszaállított</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Dátumtól</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Dátumig</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        <a href="{{ route('dashboard.institution.employees.meal-cancellations.index') }}" class="btn btn-light">
                            <i class="fa-solid fa-xmark me-1"></i>Törlés
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Rögzített dolgozói lemondások</h4>
            <span class="text-muted">Találatok: {{ $cancellations->total() }}</span>
        </div>
        <div class="card-body">
            @if($cancellations->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-compact">
                        <thead>
                        <tr>
                            <th>Dolgozó</th>
                            <th>Dátum</th>
                            <th>Státusz</th>
                            <th>Forrás</th>
                            <th>Rögzítette</th>
                            <th>Megjegyzés</th>
                            <th>Visszaállítás</th>
                            <th class="text-end">Művelet</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($cancellations as $cancellation)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $cancellation->employee?->name ?: '—' }}</div>
                                    <div class="small text-muted">{{ $cancellation->employee?->email ?: '—' }}</div>
                                </td>
                                <td>{{ $cancellation->service_date?->format('Y.m.d.') }}</td>
                                <td>
                                    <span class="badge {{ $cancellation->status === \App\Models\EmployeeMealCancellation::STATUS_ACTIVE ? 'badge-danger' : 'badge-success' }} light">
                                        {{ $cancellation->status === \App\Models\EmployeeMealCancellation::STATUS_ACTIVE ? 'Lemondva' : 'Visszaállítva' }}
                                    </span>
                                </td>
                                <td>{{ $cancellation->source === \App\Models\EmployeeMealCancellation::SOURCE_SYSTEM ? 'Rendszer' : 'Admin' }}</td>
                                <td>
                                    {{ $cancellation->creator?->name ?: '—' }}
                                    <div class="small text-muted">{{ $cancellation->created_at?->format('Y.m.d. H:i') }}</div>
                                </td>
                                <td>{{ $cancellation->reason ?: '—' }}</td>
                                <td>
                                    @if($cancellation->revoked_at)
                                        {{ $cancellation->revoker?->name ?: '—' }}
                                        <div class="small text-muted">{{ $cancellation->revoked_at?->format('Y.m.d. H:i') }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($cancellation->status === \App\Models\EmployeeMealCancellation::STATUS_ACTIVE)
                                        <form method="POST"
                                              action="{{ route('dashboard.institution.employees.meal-cancellations.restore', $cancellation) }}"
                                              class="confirm-form d-inline"
                                              data-title="Biztosan visszaállítod a dolgozói étkezést?"
                                              data-text="A rendszer visszavonja az adott nap dolgozói lemondását."
                                              data-confirm-button-text="Igen, visszaállítom">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-success">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted">Nincs teendő</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $cancellations->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-calendar-xmark',
                    'title' => 'Még nincs dolgozói lemondás',
                    'text' => 'A fenti űrlapon rögzítheted az első dolgozói étkezéslemondást.',
                ])
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Rendszeres dolgozói lemondások</h4>
            <span class="text-muted">Találatok: {{ $recurringRules->total() }}</span>
        </div>
        <div class="card-body">
            @php($weekdayLabels = [1 => 'Hétfő', 2 => 'Kedd', 3 => 'Szerda', 4 => 'Csütörtök', 5 => 'Péntek', 6 => 'Szombat', 7 => 'Vasárnap'])
            @if($recurringRules->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-compact">
                        <thead>
                        <tr>
                            <th>Dolgozó</th>
                            <th>Nap</th>
                            <th>Érvényesség</th>
                            <th>Megjegyzés</th>
                            <th>Rögzítette</th>
                            <th>Állapot</th>
                            <th class="text-end">Művelet</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($recurringRules as $rule)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $rule->employee?->name ?: '—' }}</div>
                                    <div class="small text-muted">{{ $rule->employee?->email ?: '—' }}</div>
                                </td>
                                <td>{{ $weekdayLabels[$rule->weekday] ?? '—' }}</td>
                                <td>{{ $rule->starts_on->format('Y.m.d.') }} – {{ $rule->ends_on?->format('Y.m.d.') ?? 'visszavonásig' }}</td>
                                <td>{{ $rule->reason ? Str::limit($rule->reason, 55) : '—' }}</td>
                                <td>{{ $rule->creator?->name ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $rule->status === 'active' && !$rule->ends_on?->isPast() ? 'badge-danger' : 'badge-success' }} light">
                                        {{ $rule->status === 'active'
                                            ? ($rule->ends_on?->isPast() ? 'Lejárt' : 'Aktív')
                                            : ($rule->status === 'ended' ? 'Lezárt' : 'Visszavont') }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if($rule->status === 'active')
                                        <form method="POST"
                                              action="{{ route('dashboard.institution.employees.meal-cancellations.recurring.destroy', $rule) }}"
                                              class="confirm-form d-inline"
                                              data-title="Lezárod a rendszeres dolgozói lemondást?"
                                              data-text="A már határidőn belüli alkalmak megmaradnak, a későbbiek megszűnnek."
                                              data-confirm-button-text="Igen, lezárom">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-warning" title="Rendszeres lemondás lezárása">
                                                <i class="fa fa-stop"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted">Nincs teendő</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $recurringRules->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-history',
                    'title' => 'Nincs rendszeres dolgozói lemondás',
                    'text' => 'Például minden pénteki étkezés egyetlen szabállyal lemondható.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('employee-mode');
    if (!mode) return;

    const fields = {
        single: document.getElementById('employee-single-fields'),
        range: document.getElementById('employee-range-fields'),
        recurring: document.getElementById('employee-recurring-fields')
    };
    const requiredByMode = {
        single: ['employee-service-date'],
        range: ['employee-date-from', 'employee-date-to'],
        recurring: ['employee-weekday', 'employee-starts-on']
    };

    function updateMode() {
        Object.entries(fields).forEach(([key, element]) => element.classList.toggle('d-none', key !== mode.value));
        Object.values(requiredByMode).flat().forEach(id => {
            const element = document.getElementById(id);
            if (element) element.required = false;
        });
        requiredByMode[mode.value].forEach(id => {
            const element = document.getElementById(id);
            if (element) element.required = true;
        });
    }

    mode.addEventListener('change', updateMode);
    updateMode();
});
</script>
@endpush
