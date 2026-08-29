@extends('layouts.superadmin')

@section('title', 'Vonalkódos kártyák')

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
        'title' => 'Vonalkódos kártyák',
        'subtitle' => 'Aktív, étkező gyermekek vonalkódjainak kezelése és nyomtatása',
        'buttons' => [
            [
                'text' => 'Vissza a gyermeklistához',
                'url' => route('dashboard.institution.children.index'),
                'icon' => 'fa-solid fa-arrow-left',
                'class' => 'btn btn-light',
            ],
            [
                'text' => 'Összes aktív kártya nyomtatása',
                'url' => route('dashboard.institution.children.barcodes.print-active'),
                'icon' => 'fa-solid fa-print',
                'class' => 'btn btn-primary',
            ],
            [
                'text' => 'Beléptető kioszk',
                'url' => route('dashboard.institution.children.barcodes.kiosk.edit'),
                'icon' => 'fa-solid fa-desktop',
                'class' => 'btn btn-outline-primary',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív étkezők',
            'value' => $stats['participants'],
            'subtitle' => 'A listában szereplő gyermekek',
            'icon' => 'fa-solid fa-users',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív vonalkódok',
            'value' => $stats['active_barcodes'],
            'subtitle' => 'Azonnal nyomtatható kártyák',
            'icon' => 'fa-solid fa-barcode',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Hiányzó vonalkódok',
            'value' => $stats['missing_barcodes'],
            'subtitle' => 'Még nem generált gyermekek',
            'icon' => 'fa-solid fa-circle-exclamation',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Letiltott kártyák',
            'value' => $stats['disabled_barcodes'],
            'subtitle' => 'Újragenerálásra váró kódok',
            'icon' => 'fa-solid fa-ban',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.children.barcodes.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-4 col-lg-4 mb-3">
                        <label class="form-label">Név vagy oktatási azonosító</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Keresés...">
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <label class="form-label">Osztály / csoport</label>
                        <select name="group_name" class="form-control">
                            <option value="">Összes osztály / csoport</option>
                            @foreach($groups as $group)
                                <option value="{{ $group }}" @selected(request('group_name') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <label class="form-label">Vonalkód állapota</label>
                        <select name="barcode_status" class="form-control">
                            <option value="">Mindegyik</option>
                            <option value="missing" @selected(request('barcode_status') === 'missing')>Nincs létrehozva</option>
                            <option value="active" @selected(request('barcode_status') === 'active')>Aktív</option>
                            <option value="disabled" @selected(request('barcode_status') === 'disabled')>Letiltva</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-2 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->hasAny(['search', 'group_name', 'barcode_status']))
                            <a href="{{ route('dashboard.institution.children.barcodes.index') }}" class="btn btn-light" title="Szűrők törlése">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Vonalkódkezelő lista</h4>
            <div class="d-flex gap-2 flex-wrap">
                <form method="POST" action="{{ route('dashboard.institution.children.barcodes.generate-missing') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Összes hiányzó vonalkód létrehozása
                    </button>
                </form>
                <span class="text-muted">Találatok: {{ $children->total() }}</span>
            </div>
        </div>
        <div class="card-body">
            @if($children->count())
                <form id="generate-selected-barcodes-form" method="POST" action="{{ route('dashboard.institution.children.barcodes.bulk-generate') }}" class="d-none js-bulk-barcode-form">
                    @csrf
                </form>

                <form id="print-selected-barcodes-form" method="POST" action="{{ route('dashboard.institution.children.barcodes.print-selected') }}" class="d-none js-bulk-barcode-form">
                    @csrf
                </form>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="select-all-visible">
                        <label class="form-check-label" for="select-all-visible">Ezen az oldalon mindet kijelöli</label>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary" form="generate-selected-barcodes-form">
                            <i class="fa-solid fa-barcode me-1"></i>Kijelöltek vonalkódjának generálása
                        </button>
                        <button type="submit" class="btn btn-outline-primary" form="print-selected-barcodes-form">
                            <i class="fa-solid fa-print me-1"></i>Kijelöltek nyomtatása
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-compact">
                        <thead>
                        <tr>
                            <th width="44"></th>
                            <th>Gyermek neve</th>
                            <th>Osztály / csoport</th>
                            <th>Menücsomag</th>
                            <th>Vonalkód állapota</th>
                            <th>Generálás dátuma</th>
                            <th width="240" class="text-end">Műveletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($children as $child)
                            @php($currentMealSetting = $child->getRelation('currentMealSetting'))
                            <tr>
                                <td>
                                    <input type="checkbox" name="children[]" value="{{ $child->id }}" class="form-check-input barcode-child-checkbox">
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $child->name }}</div>
                                    @if($child->educational_identifier)
                                        <div class="small text-muted">{{ $child->educational_identifier }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($child->group_name)
                                        <span class="badge badge-primary light">{{ $child->group_name }}</span>
                                    @else
                                        <span class="text-muted">Nincs megadva</span>
                                    @endif
                                </td>
                                <td class="text-wrap">
                                    @if(!$currentMealSetting)
                                        <span class="text-muted">—</span>
                                    @elseif($currentMealSetting->mode === \App\Models\StudentMealSetting::MODE_INSTITUTION_DEFAULT)
                                        Alapértelmezett – {{ $defaultMealPackage?->name ?? 'Nincs aktív alapértelmezett csomag' }}
                                    @elseif($currentMealSetting->mode === \App\Models\StudentMealSetting::MODE_PACKAGE)
                                        {{ $currentMealSetting->mealPackage?->name ?? '—' }}
                                    @else
                                        Egyedi – {{ $currentMealSetting->mealTypes->map(fn ($mealType) => $mealType->mealType->name)->implode(', ') ?: '—' }}
                                    @endif
                                </td>
                                <td>
                                    @if($child->hasActiveBarcode())
                                        <span class="badge badge-success light">Aktív</span>
                                    @elseif($child->hasDisabledBarcode())
                                        <span class="badge badge-warning light">Letiltva</span>
                                    @else
                                        <span class="badge badge-secondary light">Nincs létrehozva</span>
                                    @endif
                                </td>
                                <td>{{ $child->barcodeGeneratedAtLabel() ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                        @if(!$child->hasBarcode())
                                            <form method="POST" action="{{ route('dashboard.institution.children.barcode.store', $child) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-primary" title="Vonalkód létrehozása">
                                                    <i class="fa-solid fa-barcode"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @if($child->hasActiveBarcode())
                                            <a href="{{ route('dashboard.institution.children.barcode.print', $child) }}" class="btn btn-xs btn-outline-success" title="Kártya nyomtatása">
                                                <i class="fa-solid fa-print"></i>
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('dashboard.institution.children.barcode.destroy', $child) }}"
                                                  class="confirm-form"
                                                  data-title="Letiltod ezt a vonalkódot?"
                                                  data-text="A kártya a későbbi beléptetőrendszerben nem lesz használható, amíg újra nem generálod."
                                                  data-confirm-button-text="Igen, letiltom"
                                                  data-confirm-button-color="#dc3545">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Vonalkód letiltása">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @if($child->hasBarcode())
                                            <form method="POST"
                                                  action="{{ route('dashboard.institution.children.barcode.regenerate', $child) }}"
                                                  class="confirm-form"
                                                  data-title="Újragenerálod a vonalkódot?"
                                                  data-text="A korábbi kártya a későbbi beléptetőrendszerben már nem lesz használható."
                                                  data-confirm-button-text="Igen, újragenerálom">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-warning" title="Vonalkód újragenerálása">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
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
                    'icon' => 'fa-solid fa-barcode',
                    'title' => 'Nincs megjeleníthető gyermek',
                    'text' => 'A szűrésnek megfelelő aktív, étkező gyermek nem található.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all-visible');
    const checkboxes = Array.from(document.querySelectorAll('.barcode-child-checkbox'));
    const bulkForms = Array.from(document.querySelectorAll('.js-bulk-barcode-form'));

    if (!selectAll || checkboxes.length === 0) {
        return;
    }

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
    });

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            selectAll.checked = checkboxes.every(function (item) {
                return item.checked;
            });
        });
    });

    bulkForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('input[name="children[]"]').forEach(function (input) {
                input.remove();
            });

            checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).forEach(function (checkbox) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'children[]';
                hiddenInput.value = checkbox.value;
                form.appendChild(hiddenInput);
            });
        });
    });
});
</script>
@endpush
