@extends('layouts.superadmin')

@section('title', 'Csoportos étkezéslemondás')

@section('content')
@php
    $errorBag = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $selectedChildIdsForView = collect(old('selected_child_ids', $selectedChildIds->all()))
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $selectedChildrenHasError = $errorBag->has('selected_child_ids') || $errorBag->has('selected_child_ids.*');
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Csoportos étkezéslemondás',
        'subtitle' => 'Több osztályból összeválogatott gyermekek napi lemondásainak előkészítése',
        'buttonText' => 'Batch előzmények',
        'buttonIcon' => 'fa-solid fa-list',
        'buttonUrl' => route('dashboard.institution.meal-cancellations.bulk.index'),
    ])

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                A jelenlegi adatmodell gyermek + nap szintű lemondást kezel, ezért itt a
                <strong>„Minden aznapra beállított étkezés”</strong> mód használható.
            </div>
        </div>
    </div>

    <div class="alert {{ $errorBag->any() ? 'alert-danger' : 'alert-danger d-none' }}" id="bulk-validation-summary" tabindex="-1">
        @if($errorBag->any())
            <div class="fw-semibold mb-2">Az előnézet nem készíthető el, mert néhány adat hiányzik vagy hibás.</div>
            <ul class="mb-0 ps-3">
                @foreach($errorBag->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-header"><h4 class="card-title mb-0">Szűrés</h4></div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.meal-cancellations.bulk.create') }}" id="bulk-filter-form">
                <div class="row">
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Tanév</label>
                        <select name="school_year" class="form-control">
                            <option value="">Összes</option>
                            @foreach($schoolYears as $schoolYear)
                                <option value="{{ $schoolYear }}" @selected($filters['school_year'] === $schoolYear)>{{ $schoolYear }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Évfolyamok</label>
                        <select name="grades[]"
                                class="form-control selectpicker"
                                multiple
                                data-actions-box="true"
                                data-selected-text-format="count > 2"
                                data-none-selected-text="Nincs kiválasztva"
                                data-deselect-all-text="Összes törlése"
                                data-select-all-text="Összes kijelölése"
                                title="Válassz évfolyamot">
                            @foreach($availableGrades as $grade)
                                <option value="{{ $grade }}" @selected(in_array((string) $grade, $filters['grades'], true))>{{ $grade }}. évfolyam</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <label class="form-label">Osztályok / csoportok</label>
                        <select name="groups[]"
                                class="form-control selectpicker"
                                multiple
                                data-live-search="true"
                                data-actions-box="true"
                                data-selected-text-format="count > 2"
                                data-none-selected-text="Nincs kiválasztva"
                                data-deselect-all-text="Összes törlése"
                                data-select-all-text="Összes kijelölése"
                                title="Válassz osztályt vagy csoportot">
                            @foreach($availableGroups as $group)
                                <option value="{{ $group }}" @selected(in_array($group, $filters['groups'], true))>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <label class="form-label">Gyermek neve</label>
                        <input type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Név vagy azonosító">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Étkezési státusz</label>
                        <select name="meal_status" class="form-control">
                            <option value="active" @selected($filters['meal_status'] === 'active')>Csak aktív étkezők</option>
                            <option value="upcoming" @selected($filters['meal_status'] === 'upcoming')>Ütemezett étkezők</option>
                            <option value="missing" @selected($filters['meal_status'] === 'missing')>Nincs beállítva</option>
                            <option value="all" @selected($filters['meal_status'] === 'all')>Minden státusz</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Lemondás kezdete</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Lemondás vége</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-filter me-1"></i>Szűrés
                        </button>
                        <a href="{{ route('dashboard.institution.meal-cancellations.bulk.create') }}" class="btn btn-light" title="Szűrők törlése">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('dashboard.institution.meal-cancellations.bulk.preview') }}" id="bulk-preview-form">
        @csrf
        <div class="card mb-4">
            <div class="card-header"><h4 class="card-title mb-0">Lemondási adatok</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <label class="form-label" for="bulk_date_from">Lemondás kezdete <span class="text-danger">*</span></label>
                        <input type="date" id="bulk_date_from" name="date_from" class="form-control @error('date_from') is-invalid @enderror"
                               value="{{ old('date_from', $filters['date_from'] ?? now()->toDateString()) }}" required>
                        @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label" for="bulk_date_to">Lemondás vége <span class="text-danger">*</span></label>
                        <input type="date" id="bulk_date_to" name="date_to" class="form-control @error('date_to') is-invalid @enderror"
                               value="{{ old('date_to', $filters['date_to'] ?? now()->toDateString()) }}" required>
                        @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label" for="bulk_meal_scope">Lemondandó étkezés <span class="text-danger">*</span></label>
                        <select id="bulk_meal_scope" name="meal_scope" class="form-control @error('meal_scope') is-invalid @enderror">
                            @foreach($mealScopeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('meal_scope', array_key_first($mealScopeOptions)) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('meal_scope')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label" for="bulk_event_name">Esemény / csoport neve <span class="text-danger">*</span></label>
                        <input type="text" id="bulk_event_name" name="event_name" maxlength="191" class="form-control @error('event_name') is-invalid @enderror"
                               value="{{ old('event_name') }}" placeholder="Pl. Erdei iskola 3.A és 3.B" required>
                        @error('event_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-12 mb-0">
                        <label class="form-label" for="bulk_reason">Megjegyzés</label>
                        <textarea name="reason" rows="2" maxlength="191" class="form-control @error('reason') is-invalid @enderror"
                                  id="bulk_reason" placeholder="Opcionális belső megjegyzés">{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Gyermeklista</h4>
                    <div class="small text-muted">Találatok: {{ $children->count() }} | Kijelölve: <span id="selected-count">{{ count($selectedChildIdsForView) }}</span></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-light" id="select-all-btn">Összes kijelölése</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-selection-btn">Kijelölések törlése</button>
                </div>
            </div>
            <div class="card-body">
                @if($children->isEmpty())
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-users',
                        'title' => 'Nincs a szűrésnek megfelelő gyermek',
                        'text' => 'Módosítsd a tanév-, évfolyam-, csoport- vagy státuszszűrőt.',
                    ])
                @else
                    <div class="mb-3">
                        <label class="form-label mb-1">Kijelölt gyermekek <span class="text-danger">*</span></label>
                        <div class="small text-muted">Legalább egy gyermeket ki kell jelölni az előnézethez.</div>
                        @if($selectedChildrenHasError)
                            <div class="invalid-feedback d-block" id="selected-children-error">
                                {{ $errorBag->first('selected_child_ids') ?: $errorBag->first('selected_child_ids.*') }}
                            </div>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th width="60"><input type="checkbox" id="master-checkbox"></th>
                                    <th>Gyermek</th>
                                    <th>Évfolyam</th>
                                    <th>Osztály / csoport</th>
                                    <th>Étkezési státusz</th>
                                    <th>Érvényes csomag</th>
                                    <th>Diéta</th>
                                    <th>Ütközés jelzés</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($children as $child)
                                    @php($status = $statuses->get($child->id))
                                    @php($effectiveSetting = $status['effective_setting'] ?? null)
                                    @php($overlap = $overlapMap->get($child->id))
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_child_ids[]" value="{{ $child->id }}"
                                                   class="child-checkbox @if($selectedChildrenHasError) is-invalid @endif"
                                                   @checked(in_array($child->id, $selectedChildIdsForView, true))>
                                        </td>
                                        <td>
                                            <strong>{{ $child->name }}</strong>
                                            @if($child->educational_identifier)
                                                <div class="small text-muted">{{ $child->educational_identifier }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $child->resolved_grade_label ? $child->resolved_grade_label.'. évf.' : '—' }}</td>
                                        <td>{{ $child->group_name ?: '—' }}</td>
                                        <td>
                                            <span class="badge badge-{{ ($status['status'] ?? 'missing') === 'active' ? 'success' : (($status['status'] ?? 'missing') === 'upcoming' ? 'warning' : 'secondary') }} light">
                                                {{ $status['label'] ?? 'Nincs beállítva' }}
                                            </span>
                                        </td>
                                        <td>{{ $effectiveSetting?->mealPackage?->name ?? ($effectiveSetting?->modeLabel() ?? '—') }}</td>
                                        <td>
                                            @if($child->dietaryRestrictions->isNotEmpty())
                                                {{ $child->dietaryRestrictions->pluck('name')->implode(', ') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($overlap)
                                                <span class="badge badge-warning light">{{ $overlap['count'] }} meglévő lemondás</span>
                                            @else
                                                <span class="text-muted">Nincs jelzett átfedés</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-eye me-1"></i>Előnézet készítése
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0">Legutóbbi csoportos műveletek</h4></div>
        <div class="card-body">
            @if($recentBatches->isEmpty())
                <div class="text-muted">Még nincs rögzített csoportos lemondási művelet.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Esemény</th>
                                <th>Időszak</th>
                                <th>Gyermekek</th>
                                <th>Létrehozott lemondások</th>
                                <th>Rögzítette</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentBatches as $batch)
                                <tr>
                                    <td><strong>{{ $batch->event_name }}</strong></td>
                                    <td>{{ $batch->date_from->format('Y.m.d.') }} – {{ $batch->date_to->format('Y.m.d.') }}</td>
                                    <td>{{ $batch->selected_children_count }}</td>
                                    <td>{{ $batch->created_cancellation_count }}</td>
                                    <td>{{ $batch->creator?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('dashboard.institution.meal-cancellations.bulk.show', $batch) }}" class="btn btn-sm btn-outline-primary">Részletek</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('bulk-filter-form');
    const previewForm = document.getElementById('bulk-preview-form');
    const masterCheckbox = document.getElementById('master-checkbox');
    const selectedCount = document.getElementById('selected-count');
    const summary = document.getElementById('bulk-validation-summary');
    const checkboxes = () => Array.from(document.querySelectorAll('.child-checkbox'));
    const dateFromInput = document.getElementById('bulk_date_from');
    const dateToInput = document.getElementById('bulk_date_to');
    const eventNameInput = document.getElementById('bulk_event_name');

    function focusSummary() {
        if (summary) {
            summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
            summary.focus();
        }
    }

    function setFieldValidity(field, message) {
        if (!field) {
            return;
        }

        field.classList.toggle('is-invalid', message !== '');
        field.setCustomValidity(message);
    }

    function syncCount() {
        const selected = checkboxes().filter((checkbox) => checkbox.checked).length;
        selectedCount.textContent = selected;
        if (masterCheckbox) {
            masterCheckbox.checked = selected > 0 && selected === checkboxes().length;
        }
    }

    function setAll(state) {
        checkboxes().forEach((checkbox) => checkbox.checked = state);
        syncCount();
    }

    document.getElementById('select-all-btn')?.addEventListener('click', function () {
        setAll(true);
    });

    document.getElementById('clear-selection-btn')?.addEventListener('click', function () {
        setAll(false);
    });

    masterCheckbox?.addEventListener('change', function () {
        setAll(masterCheckbox.checked);
    });

    checkboxes().forEach((checkbox) => checkbox.addEventListener('change', syncCount));

    filterForm?.addEventListener('submit', function () {
        filterForm.querySelectorAll('input[name="selected_child_ids[]"]').forEach((input) => input.remove());
        checkboxes().filter((checkbox) => checkbox.checked).forEach((checkbox) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'selected_child_ids[]';
            hidden.value = checkbox.value;
            filterForm.appendChild(hidden);
        });
    });

    previewForm?.addEventListener('submit', function (event) {
        const selectedChildren = checkboxes().filter((checkbox) => checkbox.checked).length;
        const errors = [];

        setFieldValidity(dateFromInput, '');
        setFieldValidity(dateToInput, '');
        setFieldValidity(eventNameInput, '');

        if (selectedChildren === 0) {
            errors.push('Válassz ki legalább egy gyermeket az előnézethez.');
        }

        if (!dateFromInput?.value) {
            const message = 'Add meg a lemondás kezdetét.';
            setFieldValidity(dateFromInput, message);
            errors.push(message);
        }

        if (!dateToInput?.value) {
            const message = 'Add meg a lemondás végét.';
            setFieldValidity(dateToInput, message);
            errors.push(message);
        }

        if (dateFromInput?.value && dateToInput?.value && dateToInput.value < dateFromInput.value) {
            const message = 'A lemondás vége nem lehet korábbi, mint a kezdőnap.';
            setFieldValidity(dateToInput, message);
            errors.push(message);
        }

        if (!eventNameInput?.value.trim()) {
            const message = 'Add meg az esemény vagy csoport nevét.';
            setFieldValidity(eventNameInput, message);
            errors.push(message);
        }

        if (errors.length > 0) {
            event.preventDefault();

            if (summary) {
                summary.classList.remove('d-none');
                summary.classList.remove('alert-info');
                summary.classList.add('alert-danger');
                summary.innerHTML = '<div class="fw-semibold mb-2">Az előnézet nem készíthető el, mert néhány adat hiányzik vagy hibás.</div><ul class="mb-0 ps-3">' +
                    errors.map((message) => '<li>' + message + '</li>').join('') +
                    '</ul>';
            }

            focusSummary();
        }
    });

    if (window.jQuery && jQuery.fn.selectpicker) {
        jQuery('.selectpicker').selectpicker();
    }

    syncCount();

    if (summary) {
        focusSummary();
    }
});
</script>
@endpush
