@extends('layouts.superadmin')

@section('title', 'Új gyermek')

@section('content')
@php
    $selectedDietaryRestrictionIds = collect(old('dietary_restriction_ids', []))->map(fn ($id) => (string) $id);
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új gyermek',
        'subtitle' => 'Új gyermek vagy tanuló rögzítése',
        'buttonText' => 'Vissza a gyermeklistához',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.children.index'),
    ])

    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Alapadatok</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('dashboard.institution.children.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Név <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="name"
                                       class="form-control"
                                       value="{{ old('name') }}"
                                       maxlength="191"
                                       required>
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Oktatási azonosító</label>
                                <input type="text"
                                       name="educational_identifier"
                                       class="form-control"
                                       value="{{ old('educational_identifier') }}"
                                       maxlength="32">
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Osztály / csoport</label>
                                <select name="group_name" class="form-control">
                                    <option value="">Nincs osztályhoz / csoporthoz rendelve</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group }}" @selected(old('group_name') === $group)>
                                            {{ $group }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Tanév</label>
                                <select name="school_year" class="form-control">
                                    <option value="">Nincs tanévhez rendelve</option>
                                    @foreach($schoolYears as $schoolYear)
                                        <option value="{{ $schoolYear }}" @selected(old('school_year') === $schoolYear)>
                                            {{ $schoolYear }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-6 mb-3 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="active"
                                           name="active"
                                           value="1"
                                           @checked(old('active', true))>
                                    <label class="form-check-label" for="active">Aktív gyermek</label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-4">
                            <h4 class="mb-1">Étkezési beállítások</h4>
                            <div class="text-muted small">
                                A kedvezmény, allergének és ételérzékenységek kizárólag ehhez a gyermekhez tartoznak.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-4">
                                <label class="form-label d-block">Étkező? <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input @error('is_eater') is-invalid @enderror"
                                               type="radio"
                                               name="is_eater"
                                               id="is_eater_yes"
                                               value="1"
                                               @checked(old('is_eater', '0') === '1')>
                                        <label class="form-check-label" for="is_eater_yes">Igen</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input @error('is_eater') is-invalid @enderror"
                                               type="radio"
                                               name="is_eater"
                                               id="is_eater_no"
                                               value="0"
                                               @checked(old('is_eater', '0') !== '1')>
                                        <label class="form-check-label" for="is_eater_no">Nem</label>
                                    </div>
                                </div>
                                @error('is_eater') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-lg-6 mb-4" id="meal_valid_from_section" style="display: none;">
                                <label class="form-label" for="meal_valid_from">Étkezés kezdete <span class="text-danger">*</span></label>
                                <input type="date"
                                       id="meal_valid_from"
                                       name="meal_valid_from"
                                       class="form-control @error('meal_valid_from') is-invalid @enderror"
                                       value="{{ old('meal_valid_from', $today) }}">
                                @error('meal_valid_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 mb-4">
                                <label class="form-label" for="discount_type_id">Kedvezmény</label>
                                <select id="discount_type_id" name="discount_type_id" class="form-control" required>
                                    @foreach($discounts as $discount)
                                        <option value="{{ $discount->id }}" @selected((string) old('discount_type_id') === (string) $discount->id)>
                                            {{ $discount->name }} - {{ $discount->percentage }}%
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-6 mb-4">
                                <label class="form-label" for="discount_valid_from">Kedvezmény Tól</label>
                                <input type="date"
                                       id="discount_valid_from"
                                       name="discount_valid_from"
                                       class="form-control"
                                       value="{{ old('discount_valid_from', now()->toDateString()) }}"
                                       required>
                            </div>

                            <div class="col-lg-6 mb-4">
                                <label class="form-label" for="discount_valid_to">Kedvezmény Ig</label>
                                <input type="date"
                                       id="discount_valid_to"
                                       name="discount_valid_to"
                                       class="form-control"
                                       value="{{ old('discount_valid_to') }}">
                            </div>

                            <div class="col-lg-6 mb-4">
                                <h5 class="mb-3">Allergének</h5>
                                @forelse($allergens as $allergen)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox"
                                               name="dietary_restriction_ids[]" value="{{ $allergen->id }}"
                                               id="dietaryRestriction{{ $allergen->id }}"
                                               @checked($selectedDietaryRestrictionIds->contains((string) $allergen->id))>
                                        <label class="form-check-label" for="dietaryRestriction{{ $allergen->id }}">
                                            {{ $allergen->name }}
                                        </label>
                                    </div>
                                @empty
                                    <div class="text-muted">Nincs választható allergén.</div>
                                @endforelse
                            </div>

                            <div class="col-lg-6 mb-4">
                                <h5 class="mb-3">Ételérzékenységek</h5>
                                @forelse($intolerances as $intolerance)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox"
                                               name="dietary_restriction_ids[]" value="{{ $intolerance->id }}"
                                               id="dietaryRestriction{{ $intolerance->id }}"
                                               @checked($selectedDietaryRestrictionIds->contains((string) $intolerance->id))>
                                        <label class="form-check-label" for="dietaryRestriction{{ $intolerance->id }}">
                                            {{ $intolerance->name }}
                                        </label>
                                    </div>
                                @empty
                                    <div class="text-muted">Nincs választható ételérzékenység.</div>
                                @endforelse
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-4">
                            <h4 class="mb-1">Gondviselő hozzáadása</h4>
                            <div class="text-muted small">
                                Opcionális: már itt hozzákapcsolhatsz egy gondviselőt, hogy ne kelljen külön a
                                Szülők / gondviselők menüpontot is megnyitnod.
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12 d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="guardian_mode"
                                           id="guardian_mode_none" value="none"
                                           @checked(old('guardian_mode', 'none') === 'none')>
                                    <label class="form-check-label" for="guardian_mode_none">
                                        Nincs most megadva
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="guardian_mode"
                                           id="guardian_mode_existing" value="existing"
                                           @checked(old('guardian_mode') === 'existing')>
                                    <label class="form-check-label" for="guardian_mode_existing">
                                        Meglévő gondviselő hozzákapcsolása
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="guardian_mode"
                                           id="guardian_mode_new" value="new"
                                           @checked(old('guardian_mode') === 'new')>
                                    <label class="form-check-label" for="guardian_mode_new">
                                        Új gondviselő felvitele
                                    </label>
                                </div>
                            </div>
                            @error('guardian_mode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="row" id="guardian_existing_section" style="display: none;">
                            <div class="col-lg-8 mb-3">
                                <label class="form-label" for="existing_guardian_id">Gondviselő keresése</label>
                                <select id="existing_guardian_id" name="existing_guardian_id"
                                        class="form-control selectpicker @error('existing_guardian_id') is-invalid @enderror"
                                        data-live-search="true" data-none-selected-text="Válassz gondviselőt…">
                                    <option value=""></option>
                                    @foreach($existingGuardians as $guardian)
                                        <option value="{{ $guardian->id }}"
                                            @selected((string) old('existing_guardian_id') === (string) $guardian->id)>
                                            {{ trim($guardian->prefix . ' ' . $guardian->last_name . ' ' . $guardian->first_name) }}
                                            @if($guardian->email) ({{ $guardian->email }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('existing_guardian_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                <small class="text-muted">
                                    A gondviselő személyes és számlázási adatai a Szülők / gondviselők
                                    menüpontban szerkeszthetők.
                                </small>
                            </div>
                        </div>

                        <div class="row" id="guardian_relation_section" style="display: none;">
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="relationship_type">Rokonsági fok</label>
                                <select id="relationship_type" name="relationship_type" class="form-control">
                                    <option value="">Nincs megadva</option>
                                    @foreach($relationshipTypes as $relationshipType)
                                        <option value="{{ $relationshipType }}"
                                            @selected(old('relationship_type') === $relationshipType)>
                                            {{ $relationshipType }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-8 mb-3 d-flex flex-wrap align-items-end gap-4">
                                @foreach([
                                    'is_legal_representative' => 'Törvényes képviselő',
                                    'has_no_custody' => 'Nem gyakorol szülői felügyeletet',
                                    'is_emergency_contact' => 'Értesítendő hozzátartozó',
                                    'receives_family_allowance' => 'Családi pótlékra jogosult',
                                ] as $field => $label)
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input"
                                               id="{{ $field }}" name="{{ $field }}" value="1"
                                               @checked(old($field))>
                                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="row" id="guardian_new_section" style="display: none;">
                            <div class="col-lg-2 mb-3">
                                <label class="form-label">Előtag</label>
                                <input type="text" name="guardian_prefix"
                                       class="form-control @error('guardian_prefix') is-invalid @enderror"
                                       value="{{ old('guardian_prefix') }}" maxlength="30">
                                @error('guardian_prefix') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-5 mb-3">
                                <label class="form-label">Vezetéknév</label>
                                <input type="text" name="guardian_last_name"
                                       class="form-control @error('guardian_last_name') is-invalid @enderror"
                                       value="{{ old('guardian_last_name') }}" maxlength="100">
                                @error('guardian_last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-5 mb-3">
                                <label class="form-label">Keresztnév</label>
                                <input type="text" name="guardian_first_name"
                                       class="form-control @error('guardian_first_name') is-invalid @enderror"
                                       value="{{ old('guardian_first_name') }}" maxlength="100">
                                @error('guardian_first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-5 mb-3">
                                <label class="form-label">E-mail-cím</label>
                                <input type="email" name="guardian_email" class="form-control"
                                       value="{{ old('guardian_email') }}">
                            </div>
                            <div class="col-lg-7 mb-3">
                                <label class="form-label">Telefonszám</label>
                                <input type="text" name="guardian_phone" class="form-control"
                                       value="{{ old('guardian_phone') }}">
                            </div>
                            @if($canManageBilling)
                                <div class="col-lg-5 mb-3">
                                    <label class="form-label">Bankszámlatulajdonos</label>
                                    <input type="text" name="guardian_bank_account_holder" class="form-control"
                                           value="{{ old('guardian_bank_account_holder') }}" maxlength="200">
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Bankszámlaszám</label>
                                    <input type="text" name="guardian_bank_account_number" class="form-control"
                                           value="{{ old('guardian_bank_account_number') }}" maxlength="64">
                                </div>
                            @endif
                        </div>

                        <div class="row" id="guardian_new_address_section" style="display: none;">
                            <div class="col-12"><h5 class="mb-3">Gondviselő lakcíme</h5></div>
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Cím típusa</label>
                                <select name="guardian_address_type" class="form-control">
                                    <option value="">Nincs megadva</option>
                                    @foreach(['Állandó lakcím', 'Ideiglenes lakcím', 'Tartózkodási hely', 'Intézménycím'] as $addressType)
                                        <option value="{{ $addressType }}"
                                            @selected(old('guardian_address_type') === $addressType)>
                                            {{ $addressType }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3">
                                <label class="form-label">Ország</label>
                                <input type="text" name="guardian_country" class="form-control"
                                       value="{{ old('guardian_country') }}">
                            </div>
                            <div class="col-lg-2 mb-3">
                                <label class="form-label">Irányítószám</label>
                                <input type="text" name="guardian_postal_code" class="form-control"
                                       value="{{ old('guardian_postal_code') }}">
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label">Település</label>
                                <input type="text" name="guardian_city" class="form-control"
                                       value="{{ old('guardian_city') }}">
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label">Közterület neve</label>
                                <input type="text" name="guardian_street_name" class="form-control"
                                       value="{{ old('guardian_street_name') }}">
                            </div>
                            <div class="col-lg-2 mb-3">
                                <label class="form-label">Közterület jellege</label>
                                <input type="text" name="guardian_street_type" class="form-control"
                                       value="{{ old('guardian_street_type') }}">
                            </div>
                            <div class="col-lg-2 mb-3">
                                <label class="form-label">Házszám</label>
                                <input type="text" name="guardian_house_number" class="form-control"
                                       value="{{ old('guardian_house_number') }}">
                            </div>
                            <div class="col-lg-2 mb-3">
                                <label class="form-label">Emelet</label>
                                <input type="text" name="guardian_floor" class="form-control"
                                       value="{{ old('guardian_floor') }}">
                            </div>
                            <div class="col-lg-2 mb-3">
                                <label class="form-label">Ajtó</label>
                                <input type="text" name="guardian_door" class="form-control"
                                       value="{{ old('guardian_door') }}">
                            </div>
                        </div>

                        @if($canManageBilling)
                            <div class="row" id="guardian_billing_section" style="display: none;">
                                <div class="col-12 d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Számlázási adatok</h5>
                                    <div class="form-check form-switch mb-0">
                                        <input type="hidden" name="billing_enabled" value="0">
                                        <input type="checkbox" class="form-check-input" id="billing_enabled"
                                               name="billing_enabled" value="1" @checked(old('billing_enabled'))>
                                        <label class="form-check-label" for="billing_enabled">
                                            Számlázási profil aktív
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Fizető típusa</label>
                                    <select name="payer_type" class="form-control">
                                        <option value="">Válassz típust</option>
                                        @foreach($payerTypes as $value => $label)
                                            <option value="{{ $value }}" @selected(old('payer_type') === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-8 mb-3">
                                    <label class="form-label">Számlázási név</label>
                                    <input type="text" name="billing_name" class="form-control"
                                           value="{{ old('billing_name') }}">
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Adószám</label>
                                    <input type="text" name="tax_number" class="form-control"
                                           value="{{ old('tax_number') }}">
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Számlázási e-mail</label>
                                    <input type="email" name="billing_email" class="form-control"
                                           value="{{ old('billing_email') }}">
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Fizetési mód</label>
                                    <select name="payment_method" class="form-control">
                                        <option value="">Nincs megadva</option>
                                        @foreach($paymentMethods as $value => $label)
                                            <option value="{{ $value }}" @selected(old('payment_method') === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-2 mb-3">
                                    <label class="form-label">Irányítószám</label>
                                    <input type="text" name="billing_postal_code" class="form-control"
                                           value="{{ old('billing_postal_code') }}">
                                </div>
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Település</label>
                                    <input type="text" name="billing_city" class="form-control"
                                           value="{{ old('billing_city') }}">
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Számlázási cím</label>
                                    <input type="text" name="billing_address" class="form-control"
                                           value="{{ old('billing_address') }}"
                                           placeholder="Közterület, házszám, emelet, ajtó">
                                </div>
                                <div class="col-12 mb-4">
                                    <label class="form-label">Külső fizetői kód / megjegyzés</label>
                                    <input type="text" name="employer_reference" class="form-control"
                                           value="{{ old('employer_reference') }}" maxlength="255"
                                           placeholder="Például munkáltatói azonosító vagy rövid közlemény">
                                </div>
                            </div>
                        @endif

                        <div class="text-end">
                            <a href="{{ route('dashboard.institution.children.index') }}" class="btn btn-light">
                                Mégsem
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                Gyermek létrehozása
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modeRadios = document.querySelectorAll('input[name="guardian_mode"]');
    var existingSection = document.getElementById('guardian_existing_section');
    var relationSection = document.getElementById('guardian_relation_section');
    var newSection = document.getElementById('guardian_new_section');
    var newAddressSection = document.getElementById('guardian_new_address_section');
    var billingSection = document.getElementById('guardian_billing_section');
    var eaterRadios = document.querySelectorAll('input[name="is_eater"]');
    var mealValidFromSection = document.getElementById('meal_valid_from_section');
    var mealValidFromInput = document.getElementById('meal_valid_from');

    function isEaterSelected() {
        var checked = document.querySelector('input[name="is_eater"]:checked');

        return checked ? checked.value === '1' : false;
    }

    function toggleMealValidFrom() {
        var visible = isEaterSelected();

        if (mealValidFromSection) {
            mealValidFromSection.style.display = visible ? '' : 'none';
        }

        if (mealValidFromInput) {
            mealValidFromInput.required = visible;
        }
    }

    function currentGuardianMode() {
        var checked = document.querySelector('input[name="guardian_mode"]:checked');

        return checked ? checked.value : 'none';
    }

    function toggleGuardianSections() {
        var mode = currentGuardianMode();

        if (existingSection) {
            existingSection.style.display = mode === 'existing' ? '' : 'none';
        }
        if (relationSection) {
            relationSection.style.display = mode === 'none' ? 'none' : '';
        }
        if (newSection) {
            newSection.style.display = mode === 'new' ? '' : 'none';
        }
        if (newAddressSection) {
            newAddressSection.style.display = mode === 'new' ? '' : 'none';
        }
        if (billingSection) {
            billingSection.style.display = mode === 'new' ? '' : 'none';
        }
    }

    eaterRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleMealValidFrom);
    });
    toggleMealValidFrom();

    modeRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleGuardianSections);
    });
    toggleGuardianSections();

    if (window.jQuery && jQuery.fn.selectpicker) {
        jQuery('.selectpicker').selectpicker();
    }

    var billingEnabled = document.getElementById('billing_enabled');

    if (billingEnabled) {
        var billingFields = document.querySelectorAll(
            '[name="payer_type"], [name="billing_name"], [name="tax_number"], ' +
            '[name="billing_postal_code"], [name="billing_city"], [name="billing_address"], ' +
            '[name="billing_email"], [name="payment_method"], [name="employer_reference"]'
        );

        billingFields.forEach(function (field) {
            field.addEventListener(field.type === 'checkbox' || field.tagName === 'SELECT' ? 'change' : 'input', function () {
                if ((field.type === 'checkbox' && field.checked) || (field.type !== 'checkbox' && field.value.trim() !== '')) {
                    billingEnabled.checked = true;
                }
            });
        });
    }
});
</script>
@endpush
