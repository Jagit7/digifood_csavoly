@extends('layouts.superadmin')

@section('title', 'Új egyéni lemondás')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új egyéni lemondás',
        'subtitle' => 'Egyszeri, többnapos vagy rendszeres étkezéslemondás rögzítése',
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.meal-cancellations.index'),
    ])

    @if(!$window['configured'])
        <div class="alert alert-warning">
            <strong>Nincs beállítva lemondási határidő.</strong>
            Lemondás rögzítése előtt add meg az intézményi határidőt a
            <a href="{{ route('dashboard.institution.reference-data.index') }}">Kedvezmények és szabályok</a> oldalon.
        </div>
    @else
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-muted small">Következő napi lemondási határidő</div>
                    <strong>{{ sprintf('%02d:%02d', $window['setting']->cancellation_hour, $window['setting']->cancellation_minute) }}</strong>
                </div>
                <div>
                    <div class="text-muted small">Következő étkezési nap</div>
                    <strong>{{ $window['next_service_day']?->format('Y.m.d.') ?? 'Nincs' }}</strong>
                </div>
                <div>
                    <div class="text-muted small">Legkorábban lemondható nap</div>
                    <strong>{{ $window['earliest_cancellable_day']?->format('Y.m.d.') ?? 'Nincs' }}</strong>
                </div>
            </div>
        </div>
    @endif

    @if(!$child)
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-1">Gyermek kiválasztása</h4>
                <div class="small text-muted">A gyors kereső egyszerre legfeljebb 20 találatot tölt be.</div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('dashboard.institution.meal-cancellations.create') }}" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-lg-9 mb-3">
                            <label for="child_search" class="form-label">Név vagy oktatási azonosító</label>
                            <input type="search" name="child_search" id="child_search" class="form-control"
                                   minlength="2" value="{{ $childSearch }}" placeholder="Legalább 2 karakter, a név elejétől..." autofocus>
                        </div>
                        <div class="col-lg-3 mb-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Keresés
                            </button>
                        </div>
                    </div>
                </form>

                @if(mb_strlen($childSearch) >= 2)
                    @if($childResults->count())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead><tr><th>Gyermek</th><th>Osztály / csoport</th><th>Oktatási azonosító</th><th></th></tr></thead>
                                <tbody>
                                @foreach($childResults as $result)
                                    <tr>
                                        <td><strong>{{ $result->name }}</strong></td>
                                        <td>{{ $result->group_name ?: '—' }}</td>
                                        <td>{{ $result->educational_identifier ?: '—' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('dashboard.institution.meal-cancellations.create', ['child_id' => $result->id]) }}"
                                               class="btn btn-sm btn-primary">Kiválasztás</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        @include('layouts.partials.components.ui.empty-state', [
                            'icon' => 'fa-solid fa-user', 'title' => 'Nincs találat',
                            'text' => 'Próbáld meg a gyermek nevének vagy oktatási azonosítójának elejével.',
                        ])
                    @endif
                @endif
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Lemondás adatai</h4>
                    <div class="small text-muted">
                        <strong>{{ $child->name }}</strong>
                        @if($child->group_name) – {{ $child->group_name }} @endif
                    </div>
                </div>
                <a href="{{ route('dashboard.institution.meal-cancellations.create') }}" class="btn btn-sm btn-light">
                    Másik gyermek
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('dashboard.institution.meal-cancellations.store') }}">
                    @csrf
                    <input type="hidden" name="child_id" value="{{ $child->id }}">

                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <label for="mode" class="form-label">Lemondás típusa</label>
                            <select name="mode" id="mode" class="form-control" @disabled(!$window['configured'])>
                                <option value="single" @selected(old('mode', 'single') === 'single')>Egy nap</option>
                                <option value="range" @selected(old('mode') === 'range')>Többnapos időszak</option>
                                <option value="recurring" @selected(old('mode') === 'recurring')>Rendszeres, heti lemondás</option>
                            </select>
                        </div>
                    </div>

                    <div id="single-fields" class="cancellation-mode-fields">
                        <div class="row"><div class="col-lg-4 mb-3">
                            <label for="service_date" class="form-label">Étkezési nap</label>
                            <input type="date" name="service_date" id="service_date" class="form-control"
                                   min="{{ $window['earliest_cancellable_day']?->toDateString() }}" value="{{ old('service_date') }}">
                        </div></div>
                    </div>

                    <div id="range-fields" class="cancellation-mode-fields d-none">
                        <div class="row">
                            <div class="col-lg-4 mb-3">
                                <label for="date_from" class="form-label">Időszak kezdete</label>
                                <input type="date" name="date_from" id="date_from" class="form-control"
                                       min="{{ $window['earliest_cancellable_day']?->toDateString() }}" value="{{ old('date_from') }}">
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label for="date_to" class="form-label">Időszak vége</label>
                                <input type="date" name="date_to" id="date_to" class="form-control"
                                       min="{{ $window['earliest_cancellable_day']?->toDateString() }}" value="{{ old('date_to') }}">
                            </div>
                        </div>
                        <div class="small text-muted mb-3">
                            A hétvégéket és szüneteket a rendszer automatikusan kihagyja, a rögzített szombati munkanapokat viszont figyelembe veszi.
                        </div>
                    </div>

                    <div id="recurring-fields" class="cancellation-mode-fields d-none">
                        <div class="row">
                            <div class="col-lg-3 mb-3">
                                <label for="weekday" class="form-label">Minden</label>
                                <select name="weekday" id="weekday" class="form-control">
                                    @foreach($weekdays as $number => $label)
                                        <option value="{{ $number }}" @selected((string) old('weekday') === (string) $number)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3">
                                <label for="starts_on" class="form-label">Kezdőnap</label>
                                <input type="date" name="starts_on" id="starts_on" class="form-control"
                                       min="{{ $window['earliest_cancellable_day']?->toDateString() }}" value="{{ old('starts_on') }}">
                            </div>
                            <div class="col-lg-3 mb-3">
                                <label for="ends_on" class="form-label">Végdátum</label>
                                <input type="date" name="ends_on" id="ends_on" class="form-control" value="{{ old('ends_on') }}">
                                <div class="small text-muted">Üresen hagyva visszavonásig.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-9 mb-3">
                            <label for="reason" class="form-label">Megjegyzés</label>
                            <textarea name="reason" id="reason" rows="3" maxlength="191" class="form-control"
                                      placeholder="Pl. a szülő telefonon jelezte">{{ old('reason') }}</textarea>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        Osztály- vagy csoportszintű lemondással már érintett napra nem készül külön egyéni lemondás.
                        Rendszeres szabálynál az ilyen alkalmakat a rendszer automatikusan az osztálylemondásból számolja.
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('dashboard.institution.meal-cancellations.index') }}" class="btn btn-light">Mégsem</a>
                        <button type="submit" class="btn btn-primary" @disabled(!$window['configured'] || !$window['earliest_cancellable_day'])>
                            <i class="fa-solid fa-save me-1"></i>Lemondás rögzítése
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mode = document.getElementById('mode');
    if (!mode) return;

    const fields = {
        single: document.getElementById('single-fields'),
        range: document.getElementById('range-fields'),
        recurring: document.getElementById('recurring-fields')
    };
    const requiredByMode = {
        single: ['service_date'],
        range: ['date_from', 'date_to'],
        recurring: ['weekday', 'starts_on']
    };

    function updateMode() {
        Object.entries(fields).forEach(([key, element]) => element.classList.toggle('d-none', key !== mode.value));
        Object.values(requiredByMode).flat().forEach(id => document.getElementById(id).required = false);
        requiredByMode[mode.value].forEach(id => document.getElementById(id).required = true);
    }

    mode.addEventListener('change', updateMode);
    updateMode();
});
</script>
@endpush
