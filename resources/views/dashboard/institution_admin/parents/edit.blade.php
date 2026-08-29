@extends('layouts.superadmin')

@section('title', 'Gondviselő szerkesztése')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Gondviselő szerkesztése',
        'subtitle' => $parent->full_name,
        'buttonText' => 'Vissza a gondviselőkhöz',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.parents.index'),
    ])

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>A módosítás nem menthető.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('dashboard.institution.parents.update', $parent) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Személyes és kapcsolattartási adatok</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Előtag</label>
                        <input type="text" name="prefix" class="form-control"
                               value="{{ old('prefix', $parent->prefix) }}" maxlength="30">
                    </div>
                    <div class="col-lg-5 mb-3">
                        <label class="form-label">Vezetéknév <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control"
                               value="{{ old('last_name', $parent->last_name) }}" required>
                    </div>
                    <div class="col-lg-5 mb-3">
                        <label class="form-label">Keresztnév <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control"
                               value="{{ old('first_name', $parent->first_name) }}" required>
                    </div>
                    <div class="col-lg-5 mb-3">
                        <label class="form-label">E-mail-cím</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email', $parent->email) }}">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label class="form-label">Telefonszám</label>
                        <input type="text" name="phone" class="form-control"
                               value="{{ old('phone', $parent->phone) }}">
                    </div>
                    @if($canManageBilling)
                        <div class="col-lg-5 mb-3">
                            <label class="form-label">Bankszámlatulajdonos</label>
                            <input type="text" name="bank_account_holder" class="form-control"
                                   value="{{ old('bank_account_holder', $parent->bank_account_holder) }}" maxlength="200">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label class="form-label">Bankszámlaszám</label>
                            <input type="text" name="bank_account_number" class="form-control"
                                   value="{{ old('bank_account_number', $parent->bank_account_number) }}" maxlength="64">
                        </div>
                    @endif
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" class="form-check-input" id="active" name="active" value="1"
                                   @checked(old('active', $parent->active))>
                            <label class="form-check-label" for="active">Aktív gondviselő</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Lakcím</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <label class="form-label">Cím típusa</label>
                        <select name="address_type" class="form-control">
                            <option value="">Nincs megadva</option>
                            @foreach(['Állandó lakcím', 'Ideiglenes lakcím', 'Tartózkodási hely', 'Intézménycím'] as $addressType)
                                <option value="{{ $addressType }}" @selected(old('address_type', $parent->address_type) === $addressType)>
                                    {{ $addressType }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label">Ország</label>
                        <input type="text" name="country" class="form-control" value="{{ old('country', $parent->country) }}">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Irányítószám</label>
                        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $parent->postal_code) }}">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label class="form-label">Település</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city', $parent->city) }}">
                    </div>
                    <div class="col-lg-4 mb-3">
                        <label class="form-label">Közterület neve</label>
                        <input type="text" name="street_name" class="form-control" value="{{ old('street_name', $parent->street_name) }}">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Közterület jellege</label>
                        <input type="text" name="street_type" class="form-control" value="{{ old('street_type', $parent->street_type) }}">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Házszám</label>
                        <input type="text" name="house_number" class="form-control" value="{{ old('house_number', $parent->house_number) }}">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Emelet</label>
                        <input type="text" name="floor" class="form-control" value="{{ old('floor', $parent->floor) }}">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Ajtó</label>
                        <input type="text" name="door" class="form-control" value="{{ old('door', $parent->door) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4 class="card-title mb-0">Kapcsolt gyermekek és jogosultságok</h4></div>
            <div class="card-body">
                @forelse($parent->children as $child)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong>{{ $child->name }}</strong>
                                <span class="text-muted ms-2">{{ $child->group_name ?: 'Nincs csoport' }}</span>
                            </div>
                            <span class="badge badge-primary light">{{ $child->school_year ?: 'Nincs tanév' }}</span>
                        </div>
                        <div class="row">
                            <div class="col-lg-4 mb-3">
                                <label class="form-label">Rokonsági fok</label>
                                <select name="relations[{{ $child->id }}][relationship_type]" class="form-control">
                                    <option value="">Nincs megadva</option>
                                    @foreach($relationshipTypes as $relationshipType)
                                        <option value="{{ $relationshipType }}"
                                            @selected(old("relations.{$child->id}.relationship_type", $child->pivot->relationship_type) === $relationshipType)>
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
                                               id="relation_{{ $child->id }}_{{ $field }}"
                                               name="relations[{{ $child->id }}][{{ $field }}]" value="1"
                                               @checked(old("relations.{$child->id}.{$field}", $child->pivot->{$field}))>
                                        <label class="form-check-label" for="relation_{{ $child->id }}_{{ $field }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-warning mb-0">Nincs gyermek kapcsolva ehhez a gondviselőhöz.</div>
                @endforelse
            </div>
        </div>

        @if($canManageBilling)
            <div class="card" id="billing-profile">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Számlázási adatok</h4>
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" name="billing_enabled" value="0">
                        <input type="checkbox" class="form-check-input" id="billing_enabled"
                               name="billing_enabled" value="1"
                               @checked(old('billing_enabled', $billingProfile?->active ?? false))>
                        <label class="form-check-label" for="billing_enabled">Számlázási profil aktív</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <label class="form-label">Fizető típusa</label>
                            <select name="payer_type" class="form-control">
                                <option value="">Válassz típust</option>
                                @foreach($payerTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payer_type', $billingProfile?->payer_type) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-8 mb-3">
                            <label class="form-label">Számlázási név</label>
                            <input type="text" name="billing_name" class="form-control"
                                   value="{{ old('billing_name', $billingProfile?->billing_name) }}">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label class="form-label">Adószám</label>
                            <input type="text" name="tax_number" class="form-control"
                                   value="{{ old('tax_number', $billingProfile?->tax_number) }}">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label class="form-label">Számlázási e-mail</label>
                            <input type="email" name="billing_email" class="form-control"
                                   value="{{ old('billing_email', $billingProfile?->email) }}">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label class="form-label">Fizetési mód</label>
                            <select name="payment_method" class="form-control">
                                <option value="">Nincs megadva</option>
                                @foreach($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', $billingProfile?->payment_method) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-3">
                            <label class="form-label">Irányítószám</label>
                            <input type="text" name="billing_postal_code" class="form-control"
                                   value="{{ old('billing_postal_code', $billingProfile?->postal_code) }}">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label class="form-label">Település</label>
                            <input type="text" name="billing_city" class="form-control"
                                   value="{{ old('billing_city', $billingProfile?->city) }}">
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Számlázási cím</label>
                            <input type="text" name="billing_address" class="form-control"
                                   value="{{ old('billing_address', $billingProfile?->address) }}"
                                   placeholder="Közterület, házszám, emelet, ajtó">
                        </div>
                        <div class="col-12 mb-4">
                            <label class="form-label">Külső fizetői kód / megjegyzés</label>
                            <input type="text" name="employer_reference" class="form-control"
                                   value="{{ old('employer_reference', $billingProfile?->employer_reference) }}"
                                   maxlength="255"
                                   placeholder="Például munkáltatói azonosító vagy rövid közlemény">
                            <small class="text-muted">
                                A munkáltató vagy más külső fizető által kért kód, hivatkozás vagy néhány szavas megjegyzés.
                            </small>
                        </div>
                    </div>

                    <h5 class="mb-3">Kihez tartozó étkezési számlát kapja?</h5>
                    @forelse($parent->children as $child)
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input"
                                   id="billing_child_{{ $child->id }}"
                                   name="billing_children[]" value="{{ $child->id }}"
                                   @checked(in_array($child->id, array_map('intval', old('billing_children', $billingChildIds))))>
                            <label class="form-check-label" for="billing_child_{{ $child->id }}">
                                {{ $child->name }}
                                @if($child->group_name)
                                    <span class="text-muted">({{ $child->group_name }})</span>
                                @endif
                            </label>
                        </div>
                    @empty
                        <div class="text-muted">Nincs kijelölhető gyermekkapcsolat.</div>
                    @endforelse
                    <div class="alert alert-info mt-3 mb-0 py-2">
                        Gyermekenként egy elsődleges számlázási profil lehet. Új kijelölés esetén a korábbi számlafogadó automatikusan lecserélődik.
                    </div>
                </div>
            </div>
        @endif

        <div class="text-end mb-4">
            <a href="{{ route('dashboard.institution.parents.index') }}" class="btn btn-light">Mégsem</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-1"></i>Módosítások mentése
            </button>
        </div>
    </form>
</div>
@endsection

@if($canManageBilling)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const enabled = document.getElementById('billing_enabled');
        const fields = document.querySelectorAll(
            '[name="payer_type"], [name="billing_name"], [name="tax_number"], ' +
            '[name="billing_postal_code"], [name="billing_city"], [name="billing_address"], ' +
            '[name="billing_email"], [name="payment_method"], [name="employer_reference"], ' +
            '[name="billing_children[]"]'
        );

        fields.forEach(function (field) {
            field.addEventListener(field.type === 'checkbox' || field.tagName === 'SELECT' ? 'change' : 'input', function () {
                if ((field.type === 'checkbox' && field.checked) || (field.type !== 'checkbox' && field.value.trim() !== '')) {
                    enabled.checked = true;
                }
            });
        });
    });
    </script>
    @endpush
@endif
