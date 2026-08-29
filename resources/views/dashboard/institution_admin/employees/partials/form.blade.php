@php
    $selectedRestrictionIds = collect(old(
        'dietary_restriction_ids',
        $employee?->dietaryRestrictions?->pluck('id')->all() ?? []
    ))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="row">
    <div class="col-12">
        <h5 class="mb-3">Személyes adatok</h5>
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">Név</label>
        <input type="text"
               name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $employee?->name) }}"
               maxlength="191"
               required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-3 mb-3">
        <label class="form-label">E-mail</label>
        <input type="email"
               name="email"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $employee?->email) }}"
               maxlength="191">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-3 mb-3">
        <label class="form-label">Telefonszám</label>
        <input type="text"
               name="phone"
               class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $employee?->phone) }}"
               maxlength="50">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mt-2">
        <h5 class="mb-3">Cím / számlázási adatok</h5>
    </div>

    <div class="col-lg-3 mb-3">
        <label class="form-label">Cím típusa</label>
        <input type="text"
               name="address_type"
               class="form-control @error('address_type') is-invalid @enderror"
               value="{{ old('address_type', $employee?->address_type) }}"
               maxlength="50">
        @error('address_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-3 mb-3">
        <label class="form-label">Ország</label>
        <input type="text"
               name="country"
               class="form-control @error('country') is-invalid @enderror"
               value="{{ old('country', $employee?->country) }}"
               maxlength="100">
        @error('country')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-2 mb-3">
        <label class="form-label">Irányítószám</label>
        <input type="text"
               name="postal_code"
               class="form-control @error('postal_code') is-invalid @enderror"
               value="{{ old('postal_code', $employee?->postal_code) }}"
               maxlength="10">
        @error('postal_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-4 mb-3">
        <label class="form-label">Település</label>
        <input type="text"
               name="city"
               class="form-control @error('city') is-invalid @enderror"
               value="{{ old('city', $employee?->city) }}"
               maxlength="100">
        @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-4 mb-3">
        <label class="form-label">Közterület neve</label>
        <input type="text"
               name="street_name"
               class="form-control @error('street_name') is-invalid @enderror"
               value="{{ old('street_name', $employee?->street_name) }}"
               maxlength="191">
        @error('street_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-3 mb-3">
        <label class="form-label">Közterület jellege</label>
        <input type="text"
               name="street_type"
               class="form-control @error('street_type') is-invalid @enderror"
               value="{{ old('street_type', $employee?->street_type) }}"
               maxlength="50">
        @error('street_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-2 mb-3">
        <label class="form-label">Házszám</label>
        <input type="text"
               name="house_number"
               class="form-control @error('house_number') is-invalid @enderror"
               value="{{ old('house_number', $employee?->house_number) }}"
               maxlength="30">
        @error('house_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-1 mb-3">
        <label class="form-label">Emelet</label>
        <input type="text"
               name="floor"
               class="form-control @error('floor') is-invalid @enderror"
               value="{{ old('floor', $employee?->floor) }}"
               maxlength="20">
        @error('floor')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-2 mb-3">
        <label class="form-label">Ajtó</label>
        <input type="text"
               name="door"
               class="form-control @error('door') is-invalid @enderror"
               value="{{ old('door', $employee?->door) }}"
               maxlength="20">
        @error('door')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mt-2">
        <h5 class="mb-3">Bankszámlaadatok</h5>
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">Számlatulajdonos neve</label>
        <input type="text"
               name="bank_account_holder"
               class="form-control @error('bank_account_holder') is-invalid @enderror"
               value="{{ old('bank_account_holder', $employee?->bank_account_holder) }}"
               maxlength="191">
        @error('bank_account_holder')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">Bankszámlaszám</label>
        <input type="text"
               name="bank_account_number"
               class="form-control @error('bank_account_number') is-invalid @enderror"
               value="{{ old('bank_account_number', $employee?->bank_account_number) }}"
               maxlength="64">
        @error('bank_account_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mt-2">
        <h5 class="mb-3">Étkezési / kedvezmény adatok</h5>
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">Kedvezménytípus</label>
        <select name="discount_type_id"
                class="form-control @error('discount_type_id') is-invalid @enderror">
            <option value="">Nincs kiválasztva</option>

            @foreach($discountTypes as $discountType)
                <option value="{{ $discountType->id }}"
                    @selected((string) old('discount_type_id', $employee?->discount_type_id) === (string) $discountType->id)>
                    {{ $discountType->name }} ({{ $discountType->percentage }}%)
                </option>
            @endforeach
        </select>

        @error('discount_type_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">Diéták / ételérzékenységek</label>

        <select name="dietary_restriction_ids[]"
                class="form-control selectpicker @error('dietary_restriction_ids') is-invalid @enderror"
                multiple
                title="Nincs kiválasztva"
                data-live-search="true"
                data-actions-box="true"
                data-selected-text-format="count > 3">

            @foreach($dietaryRestrictions as $restriction)
                <option value="{{ $restriction->id }}"
                    @selected(in_array($restriction->id, $selectedRestrictionIds, true))>
                    {{ $restriction->name }}
                    @if($restriction->type === \App\Models\DietaryRestriction::TYPE_ALLERGEN)
                        - allergén
                    @else
                        - érzékenység
                    @endif
                </option>
            @endforeach
        </select>

        @error('dietary_restriction_ids')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror

        @error('dietary_restriction_ids.*')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mt-2">
        <h5 class="mb-3">Állapot</h5>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="active" value="0">

            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="active"
                   name="active"
                   value="1"
                   @checked((bool) old('active', $employee?->active ?? true))>

            <label class="form-check-label" for="active">
                Aktív dolgozó
            </label>
        </div>
    </div>
</div>

<div class="text-end">
    <a href="{{ $employeesIndexUrl ?? route('dashboard.institution.employees.index') }}"
       class="btn btn-light">
        Mégsem
    </a>

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk me-1"></i>{{ $submitLabel }}
    </button>
</div>
