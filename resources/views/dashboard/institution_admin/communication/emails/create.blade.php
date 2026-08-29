@extends('layouts.superadmin')

@section('title', 'Új e-mail küldése')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új e-mail küldése',
        'subtitle' => 'Kommunikáció / E-mail küldés',
        'buttons' => [
            [
                'url' => route('dashboard.institution.communication.emails.index'),
                'text' => 'Küldési előzmények',
                'class' => 'btn btn-outline-primary',
                'icon' => 'fa-solid fa-clock-rotate-left',
            ],
        ],
    ])

    <form method="POST"
          action="{{ route('dashboard.institution.communication.emails.store') }}"
          id="campaign-form"
          class="confirm-form"
          data-title="Biztosan elindítja az e-mail küldést?"
          data-text="Az üzenetek a háttérben, küldési soron keresztül kerülnek kiküldésre."
          data-confirm-button-text="Küldés"
          data-cancel-button-text="Mégse">
        @csrf

        <div class="row">
            <div class="col-xl-8">

                <div class="card">

                    {{-- CÍMZETTEK --}}
                    <div class="card-header">
                        <h4 class="card-title mb-0">Címzettek kiválasztása</h4>
                    </div>

                    <div class="card-body">
                        <div class="row">

                            {{-- OSZTÁLYOK --}}
                            <div class="col-lg-6 mb-4">
                                <h5 class="mb-3">Osztályok</h5>

                                <div class="border rounded p-3"
                                     style="max-height: 320px; overflow:auto;">

                                    @forelse($classGroups as $classGroup)
                                        <div class="form-check mb-2">
                                            <input
                                                class="form-check-input campaign-class-group"
                                                type="checkbox"
                                                value="{{ $classGroup->id }}"
                                                id="class-group-{{ $classGroup->id }}"
                                                name="class_group_ids[]"
                                                @checked(
                                                    in_array(
                                                        $classGroup->id,
                                                        old('class_group_ids', []),
                                                        true
                                                    )
                                                )
                                            >

                                            <label
                                                class="form-check-label"
                                                for="class-group-{{ $classGroup->id }}"
                                            >
                                                {{ $classGroup->name }}

                                                <span class="text-muted">
                                                    ({{ $classGroup->children_count }} gyermek)
                                                </span>
                                            </label>
                                        </div>
                                    @empty
                                        <div class="text-muted">
                                            Nincs választható osztály.
                                        </div>
                                    @endforelse

                                </div>
                            </div>

                            {{-- EGYEDI GYERMEKEK --}}
                            <div class="col-lg-6 mb-4">
                                <h5 class="mb-3">Egyedi gyermekek</h5>

                                <input
                                    type="search"
                                    class="form-control mb-3"
                                    id="child-search"
                                    placeholder="Keresés név vagy osztály alapján"
                                >

                                <div
                                    class="border rounded p-3"
                                    style="max-height: 320px; overflow:auto;"
                                    id="child-list"
                                >
                                    @foreach($children as $child)

                                        @php
                                            $classGroupNames = $child->classGroups
                                                ->pluck('name')
                                                ->filter()
                                                ->implode(', ');
                                        @endphp

                                        <div
                                            class="form-check mb-2 child-option"
                                            data-search="{{ mb_strtolower($child->name . ' ' . $classGroupNames) }}"
                                        >

                                            <input
                                                class="form-check-input campaign-child"
                                                type="checkbox"
                                                value="{{ $child->id }}"
                                                id="child-{{ $child->id }}"
                                                name="child_ids[]"
                                                @checked(
                                                    in_array(
                                                        $child->id,
                                                        old('child_ids', $selectedChildIds),
                                                        true
                                                    )
                                                )
                                            >

                                            <label
                                                class="form-check-label"
                                                for="child-{{ $child->id }}"
                                            >
                                                <strong>{{ $child->name }}</strong>

                                                <div class="small text-muted">
                                                    {{ $classGroupNames ?: 'Nincs osztály hozzárendelve' }}
                                                </div>

                                                <div class="small text-muted">
                                                    Gondviselők e-maillel:
                                                    {{ $child->guardians->whereNotNull('email')->count() }}
                                                </div>
                                            </label>
                                        </div>

                                    @endforeach
                                </div>
                            </div>

                        </div>

                        @error('recipients')
                            <div class="alert alert-danger mb-0">
                                {{ $message }}
                            </div>
                        @enderror

                        @error('class_group_ids')
                            <div class="alert alert-danger mb-0">
                                {{ $message }}
                            </div>
                        @enderror

                        @error('child_ids')
                            <div class="alert alert-danger mb-0">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- ÖSSZESÍTÉS --}}
                    <div class="card-header">
                        <h4 class="card-title mb-0">Összesítés</h4>
                    </div>

                    <div class="card-body">
                        <div class="row text-center">

                            <div class="col-md-4 mb-3 mb-md-0">
                                <div
                                    class="fs-3 fw-semibold"
                                    id="selected-class-groups-count"
                                >
                                    0
                                </div>

                                <div class="text-muted">
                                    Kiválasztott osztályok
                                </div>
                            </div>

                            <div class="col-md-4 mb-3 mb-md-0">
                                <div
                                    class="fs-3 fw-semibold"
                                    id="selected-children-count"
                                >
                                    0
                                </div>

                                <div class="text-muted">
                                    Kiválasztott gyermekek
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div
                                    class="fs-3 fw-semibold"
                                    id="expected-email-count"
                                >
                                    -
                                </div>

                                <div class="text-muted">
                                    Várható egyedi e-mail címek
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- ÜZENET --}}
                    <div class="card-header">
                        <h4 class="card-title mb-0">Üzenet</h4>
                    </div>

                    <div class="card-body">

                        <div class="mb-4">
                            <label
                                for="subject"
                                class="form-label"
                            >
                                E-mail tárgya
                            </label>

                            <input
                                type="text"
                                id="subject"
                                name="subject"
                                class="form-control @error('subject') is-invalid @enderror"
                                value="{{ old('subject') }}"
                                maxlength="191"
                                required
                            >

                            @error('subject')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                E-mail szövege
                            </label>

                            <div class="custom-ekeditor mb-3">
                                <div id="ckeditor">{!! old('body') !!}</div>
                            </div>

                            {{-- CKEditor tartalma ide kerül submit előtt --}}
                            <textarea
                                name="body"
                                id="body"
                                class="d-none"
                            >{{ old('body') }}</textarea>

                            @error('body')
                                <div class="text-danger small">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                    </div>

                    <div class="card-footer text-end">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="campaign-submit"
                        >
                            <i class="fa-solid fa-paper-plane me-1"></i>
                            Küldés queue-ba
                        </button>
                    </div>

                </div>
            </div>

            {{-- JOBB OLDAL --}}
            <div class="col-xl-4">
                <div class="card">

                    <div class="card-header">
                        <h4 class="card-title mb-0">
                            Használható helyőrzők
                        </h4>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <code>#SZULO_NEVE#</code>
                            <div class="small text-muted">
                                A gondviselő neve
                            </div>
                        </div>

                        <div class="mb-3">
                            <code>#GYERMEK_NEVE#</code>
                            <div class="small text-muted">
                                A gyermek neve, több gyermeknél vesszővel felsorolva
                            </div>
                        </div>

                        <div class="mb-3">
                            <code>#OSZTALY#</code>
                            <div class="small text-muted">
                                Az osztály neve, több osztálynál vesszővel felsorolva
                            </div>
                        </div>

                        <div class="mb-0">
                            <code>#INTEZMENY_NEVE#</code>
                            <div class="small text-muted">
                                Az intézmény neve
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </form>
@endsection


@push('scripts')
    <script src="{{ asset('dashboard/vendor/ckeditor/ckeditor.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const classGroupInputs = Array.from(
                document.querySelectorAll('.campaign-class-group')
            );

            const childInputs = Array.from(
                document.querySelectorAll('.campaign-child')
            );

            const childSearch = document.getElementById('child-search');

            const selectedClassGroupsCount = document.getElementById(
                'selected-class-groups-count'
            );

            const selectedChildrenCount = document.getElementById(
                'selected-children-count'
            );

            const expectedEmailCount = document.getElementById(
                'expected-email-count'
            );

            const bodyInput = document.getElementById('body');

            const form = document.getElementById('campaign-form');

            const submitButton = document.getElementById('campaign-submit');


            /**
             * CKEditor tartalmának átmásolása
             * a Laravel által elküldött body mezőbe.
             */
            function syncEditor() {
                if (
                    window.editor &&
                    bodyInput &&
                    typeof window.editor.getData === 'function'
                ) {
                    bodyInput.value = window.editor.getData();
                }
            }


            /**
             * Kiválasztott elemek számának frissítése.
             */
            function updateSummary() {

                if (selectedClassGroupsCount) {
                    selectedClassGroupsCount.textContent =
                        classGroupInputs.filter(function (input) {
                            return input.checked;
                        }).length;
                }

                if (selectedChildrenCount) {
                    selectedChildrenCount.textContent =
                        childInputs.filter(function (input) {
                            return input.checked;
                        }).length;
                }

                if (expectedEmailCount) {
                    expectedEmailCount.textContent = '-';
                }
            }


            /**
             * Osztály checkboxok
             */
            classGroupInputs.forEach(function (input) {
                input.addEventListener('change', updateSummary);
            });


            /**
             * Gyermek checkboxok
             */
            childInputs.forEach(function (input) {
                input.addEventListener('change', updateSummary);
            });


            /**
             * Gyermek kereső
             */
            if (childSearch) {

                childSearch.addEventListener('input', function () {

                    const term = childSearch.value
                        .trim()
                        .toLowerCase();

                    document
                        .querySelectorAll('.child-option')
                        .forEach(function (item) {

                            const searchText =
                                (item.dataset.search || '').toLowerCase();

                            item.style.display =
                                searchText.includes(term)
                                    ? ''
                                    : 'none';
                        });

                });
            }


            /**
             * Fontos:
             *
             * A hidden textarea NEM required,
             * ezért a böngésző nem blokkolja a submitot.
             *
             * A CKEditor tartalmát már a kattintáskor
             * bemásoljuk.
             */
            if (submitButton) {
                submitButton.addEventListener('click', function () {
                    syncEditor();
                });
            }


            /**
             * Biztonsági szinkronizálás submit előtt.
             *
             * capture = true, így a közös confirm-form
             * listener előtt lefut.
             */
            if (form) {
                form.addEventListener(
                    'submit',
                    function () {
                        syncEditor();
                    },
                    true
                );
            }


            /**
             * Első állapot kirajzolása.
             */
            updateSummary();

        });
    </script>
@endpush