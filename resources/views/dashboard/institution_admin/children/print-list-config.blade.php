@extends('layouts.superadmin')

@section('title', 'Nyomtatható diáklisták')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Nyomtatható diáklisták',
        'subtitle' => 'Válaszd ki az osztályt és a listán megjelenítendő adatokat, majd készítsd el a nyomtatható listát',
    ])

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('dashboard.institution.children.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>Vissza a gyermeklistához
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.children.print-list.generate') }}" target="_blank">

                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Osztály / csoport</label>
                    </div>
                    <div class="col-md-3">
                        <select name="group_name" class="form-control">
                            <option value="">Összes osztály (osztályonként külön oldalon)</option>
                            @foreach($groups as $group)
                                <option value="{{ $group }}">{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <label class="form-label fw-bold mb-0">Megjelenítendő adatok</label>
                        <div>
                            <button type="button" id="select-all-columns" class="btn btn-sm btn-light">Összes kijelölése</button>
                            <button type="button" id="select-none-columns" class="btn btn-sm btn-light">Kijelölés törlése</button>
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-3">
                        A sorszám és a gyermek neve mindig szerepel a listán. Az alábbiakból tetszőleges számú
                        további oszlop választható - minél több oszlopot jelölsz ki, annál szélesebb lesz a
                        nyomtatott táblázat.
                    </p>
                    <div class="row">
                        @foreach($columns as $key => $label)
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input type="checkbox" name="columns[]" value="{{ $key }}" id="col-{{ $key }}"
                                           class="form-check-input js-print-column"
                                           {{ in_array($key, $defaultColumns, true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="col-{{ $key }}">{{ $label }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-print me-1"></i>Nyomtatható lista elkészítése
                </button>
                <span class="text-muted small ms-2">A lista új böngészőfülön nyílik meg, ahonnan közvetlenül nyomtatható.</span>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllBtn = document.getElementById('select-all-columns');
    const selectNoneBtn = document.getElementById('select-none-columns');
    const checkboxes = Array.from(document.querySelectorAll('.js-print-column'));

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = true;
            });
        });
    }

    if (selectNoneBtn) {
        selectNoneBtn.addEventListener('click', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });
        });
    }
});
</script>
@endpush
