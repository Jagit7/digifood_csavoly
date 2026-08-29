@extends('layouts.superadmin')

@section('title', 'Gyermek adatainak szerkesztése')

@push('styles')
    <style>
        /*
         * A gondviselő-szerkesztő panel egy keskeny, kártyán belüli oszlopban
         * jelenik meg (nem a teljes oldalszélességben). A Bootstrap col-lg-*
         * osztályok a BÖNGÉSZŐABLAK szélessége alapján aktiválódnak, nem a
         * szülő elem tényleges szélessége alapján - ezért nagy (>=992px)
         * képernyőn is összepréselődtek a mezők ebben a keskeny kártyában
         * (pl. az Irányítószám és Település címke egymásra csúszott, a
         * telefonszám pedig levágva jelent meg). A lenti CSS grid a
         * KONTÉNER tényleges szélességéhez igazodik, ettől független.
         */
        .guardian-fields .gf-row {
            display: grid;
            gap: .5rem;
        }
        .guardian-fields .gf-row + .gf-row,
        .guardian-fields .gf-checks,
        .guardian-fields h6 {
            margin-top: .85rem;
        }
        .guardian-fields label.form-label {
            font-size: 11px;
            margin-bottom: .2rem;
        }
        .guardian-fields .form-control-sm {
            font-size: 13px;
        }
        .guardian-fields .gf-checks .form-check {
            margin-bottom: .35rem;
        }
        .guardian-fields .form-check-label {
            font-size: 12px;
        }
    </style>
@endpush

@section('content')
@php
    $currentDiscountPeriod = $child->discountPeriods->first();
    $selectedDietaryRestrictionIds = collect(old(
        'dietary_restriction_ids',
        $child->dietaryRestrictions->pluck('id')->all()
    ))->map(fn ($id) => (string) $id);
    $returnList = old('return_list', $returnList ?? \App\Services\Navigation\ChildListReturnService::LIST_CHILDREN);
    $returnQuery = old('return_query', $returnQuery ?? '');
    $returnUrl = $returnUrl ?? route('dashboard.institution.children.index');
    $mealSettingsReturnPayload = http_build_query([
        'return_list' => $returnList,
        'return_query' => $returnQuery,
    ]);
    $returnLabel = match ($returnList) {
        \App\Services\Navigation\ChildListReturnService::LIST_BASICS => 'Vissza az Alapadatokhoz',
        \App\Services\Navigation\ChildListReturnService::LIST_EATERS => 'Vissza az Étkező gyermekekhez',
        default => 'Vissza a gyermeklistához',
    };
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Gyermek adatainak szerkesztése',
        'subtitle' => $child->name,
        'buttonText' => $returnLabel,
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => $returnUrl,
    ])

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Alapadatok</h4>
                </div>
                <div class="card-body">
                    <form id="childEditForm" method="POST" action="{{ route('dashboard.institution.children.update', $child) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="return_list" value="{{ $returnList }}">
                        <input type="hidden" name="return_query" value="{{ old('return_query', $returnQuery ?? '') }}">

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Név <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="name"
                                       class="form-control"
                                       value="{{ old('name', $child->name) }}"
                                       maxlength="191"
                                       required>
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Oktatási azonosító</label>
                                <input type="text"
                                       name="educational_identifier"
                                       class="form-control"
                                       value="{{ old('educational_identifier', $child->educational_identifier) }}"
                                       maxlength="32">
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Osztály / csoport</label>
                                <select name="group_name" class="form-control">
                                    <option value="">Nincs osztályhoz / csoporthoz rendelve</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group }}"
                                            @selected(old('group_name', $child->group_name) === $group)>
                                            {{ $group }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Új osztály az Osztályok / csoportok menüpontban hozható majd létre.
                                </small>
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label class="form-label">Tanév</label>
                                <select name="school_year" class="form-control">
                                    <option value="">Nincs tanévhez rendelve</option>
                                    @foreach($schoolYears as $schoolYear)
                                        <option value="{{ $schoolYear }}"
                                            @selected(old('school_year', $child->school_year) === $schoolYear)>
                                            {{ $schoolYear }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Új tanév a tanévváltási folyamatban lesz létrehozható.
                                </small>
                            </div>

                            <div class="col-lg-6 mb-3 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input type="hidden" name="active" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="active"
                                           name="active"
                                           value="1"
                                           @checked(old('active', $child->active))>
                                    <label class="form-check-label" for="active">Aktív gyermek</label>
                                </div>
                            </div>
                        <div class="mt-3">
                            <a href="{{ route('dashboard.institution.children.meal-settings.index', $child) }}?{{ $mealSettingsReturnPayload }}"
                               class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-utensils me-1"></i>Étkezési beállítások
                                </a>
                                @if($canManageBilling ?? false)
                                    <a href="{{ route('dashboard.institution.billing-addresses.index', ['search' => $child->name]) }}"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fa-solid fa-file-invoice me-1"></i>Számlázási cím szerkesztése
                                    </a>
                                @endif
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
                            <div class="col-lg-4 col-md-6 mb-4">
                                <label class="form-label" for="discount_type_id">Kedvezmény</label>
                                <select id="discount_type_id" name="discount_type_id" class="form-control" required>
                                    @foreach($discounts as $discount)
                                        <option value="{{ $discount->id }}"
                                            @selected((string) old('discount_type_id', $currentDiscountPeriod?->discount_type_id ?? $child->discount_type_id) === (string) $discount->id)>
                                            {{ $discount->name }} – {{ $discount->percentage }}%
                                            @unless($discount->active)
                                                (inaktív)
                                            @endunless
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-4 col-md-6 mb-4">
                                <label class="form-label" for="discount_valid_from">Kedvezmény Tól</label>
                                <input type="date"
                                       id="discount_valid_from"
                                       name="discount_valid_from"
                                       class="form-control"
                                       value="{{ old('discount_valid_from', optional($currentDiscountPeriod?->valid_from)->toDateString() ?? optional($child->created_at)->toDateString() ?? now()->toDateString()) }}"
                                       required>
                            </div>

                            <div class="col-lg-4 col-md-6 mb-4">
                                <label class="form-label" for="discount_valid_to">Kedvezmény Ig</label>
                                <input type="date"
                                       id="discount_valid_to"
                                       name="discount_valid_to"
                                       class="form-control"
                                       value="{{ old('discount_valid_to', optional($currentDiscountPeriod?->valid_to)->toDateString()) }}">
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
                                            @unless($allergen->active)
                                                <span class="text-muted">(inaktív)</span>
                                            @endunless
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
                                            @unless($intolerance->active)
                                                <span class="text-muted">(inaktív)</span>
                                            @endunless
                                        </label>
                                    </div>
                                @empty
                                    <div class="text-muted">Nincs választható ételérzékenység.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="{{ $returnUrl }}" class="btn btn-light">
                                Mégsem
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i>
                                Módosítások mentése
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                @include('dashboard.institution_admin.children.meal-settings.partials.closure-panel', [
                    'child' => $child,
                    'currentSetting' => $child->getRelation('currentMealSetting'),
                    'latestSetting' => $child->getRelation('latestMealSetting'),
                    'closureReasonLabels' => \App\Models\StudentMealSetting::CLOSURE_REASON_LABELS,
                    'standalone' => false,
                ])

                <div class="card-header">
                    <h4 class="card-title mb-0">Vonalkódos kártya</h4>
                </div>
                <div class="card-body">
                    @if($child->hasActiveBarcode())
                        <span class="badge badge-success light">Aktív vonalkód</span>
                    @elseif($child->hasDisabledBarcode())
                        <span class="badge badge-warning light">Letiltott vonalkód</span>
                    @else
                        <span class="badge badge-secondary light">Nincs létrehozva</span>
                    @endif

                    <div class="small text-muted mt-2">
                        @if($child->barcodeGeneratedAtLabel())
                            Generálva: {{ $child->barcodeGeneratedAtLabel() }}
                        @else
                            Ehhez a gyermekhez még nem tartozik mentett vonalkód.
                        @endif
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if(!$child->hasBarcode())
                            <form method="POST" action="{{ route('dashboard.institution.children.barcode.store', $child) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="fa-solid fa-barcode me-1"></i>Vonalkód létrehozása
                                </button>
                            </form>
                        @endif

                        @if($child->hasActiveBarcode())
                            <a href="{{ route('dashboard.institution.children.barcode.print', $child) }}"
                               class="btn btn-outline-success btn-sm">
                                <i class="fa-solid fa-print me-1"></i>Kártya nyomtatása
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
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="fa-solid fa-ban me-1"></i>Vonalkód letiltása
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
                                <button type="submit" class="btn btn-outline-warning btn-sm">
                                    <i class="fa-solid fa-rotate me-1"></i>Vonalkód újragenerálása
                                </button>
                            </form>
                        @endif
                    </div>

                    @if($child->getRelation('currentMealSetting'))
                        <div class="alert alert-info mt-3 mb-0 py-2">
                            A gyermek szerepel az aktív étkezők között, ezért a vonalkódos kártyák oldalon is kezelhető.
                        </div>
                    @endif
                </div>

                <div class="card-header">
                    <h4 class="card-title mb-0">Kapcsolt gondviselők</h4>
                </div>
                <div class="card-body">
                    @forelse($child->guardians as $guardian)
                        @php($guardianOld = old('guardians.' . $guardian->id, []))
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong>{{ $guardian->full_name }}</strong>
                                    @if($guardian->pivot->relationship_type)
                                        <div class="text-muted small">{{ $guardian->pivot->relationship_type }}</div>
                                    @endif
                                    @if($guardian->email)
                                        <div class="small mt-1"><i class="fa-regular fa-envelope me-1"></i>{{ $guardian->email }}</div>
                                    @endif
                                    @if($guardian->phone)
                                        <div class="small"><i class="fa-solid fa-phone me-1"></i>{{ $guardian->phone }}</div>
                                    @endif
                                </div>
                                <button type="button"
                                        class="btn btn-xs btn-outline-secondary"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#guardianEdit{{ $guardian->id }}">
                                    <i class="fa-solid fa-pen me-1"></i>Szerkesztés
                                </button>
                            </div>

                            <div class="collapse mt-3 {{ !empty($guardianOld) ? 'show' : '' }}" id="guardianEdit{{ $guardian->id }}">
                                <div class="guardian-fields">
                                    <div class="gf-row" style="grid-template-columns: 84px 1fr;">
                                        <div>
                                            <label class="form-label">Előtag</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][prefix]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['prefix'] ?? $guardian->prefix }}" maxlength="30">
                                        </div>
                                        <div>
                                            <label class="form-label">Vezetéknév <span class="text-danger">*</span></label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][last_name]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['last_name'] ?? $guardian->last_name }}" maxlength="100" required>
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Keresztnév <span class="text-danger">*</span></label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][first_name]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['first_name'] ?? $guardian->first_name }}" maxlength="100" required>
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">E-mail-cím</label>
                                            <input type="email" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][email]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['email'] ?? $guardian->email }}">
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Telefonszám</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][phone]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['phone'] ?? $guardian->phone }}">
                                        </div>
                                    </div>

                                    @if($canManageBilling ?? false)
                                        <div class="gf-row">
                                            <div>
                                                <label class="form-label">Bankszámlatulajdonos</label>
                                                <input type="text" form="childEditForm"
                                                       name="guardians[{{ $guardian->id }}][bank_account_holder]"
                                                       class="form-control form-control-sm"
                                                       value="{{ $guardianOld['bank_account_holder'] ?? $guardian->bank_account_holder }}" maxlength="200">
                                            </div>
                                        </div>
                                        <div class="gf-row">
                                            <div>
                                                <label class="form-label">Bankszámlaszám</label>
                                                <input type="text" form="childEditForm"
                                                       name="guardians[{{ $guardian->id }}][bank_account_number]"
                                                       class="form-control form-control-sm"
                                                       value="{{ $guardianOld['bank_account_number'] ?? $guardian->bank_account_number }}" maxlength="64">
                                            </div>
                                        </div>
                                    @endif

                                    <hr class="my-1">
                                    <h6 class="mb-2">Lakcím</h6>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Cím típusa</label>
                                            <select form="childEditForm" name="guardians[{{ $guardian->id }}][address_type]" class="form-control form-control-sm">
                                                <option value="">Nincs megadva</option>
                                                @foreach(['Állandó lakcím', 'Ideiglenes lakcím', 'Tartózkodási hely', 'Intézménycím'] as $addressType)
                                                    <option value="{{ $addressType }}"
                                                        @selected(($guardianOld['address_type'] ?? $guardian->address_type) === $addressType)>
                                                        {{ $addressType }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Ország</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][country]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['country'] ?? $guardian->country }}">
                                        </div>
                                    </div>

                                    <div class="gf-row" style="grid-template-columns: 88px 1fr;">
                                        <div>
                                            <label class="form-label">Irsz.</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][postal_code]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['postal_code'] ?? $guardian->postal_code }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Település</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][city]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['city'] ?? $guardian->city }}">
                                        </div>
                                    </div>

                                    <div class="gf-row" style="grid-template-columns: 1fr 110px;">
                                        <div>
                                            <label class="form-label">Közterület neve</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][street_name]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['street_name'] ?? $guardian->street_name }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Közterület jellege</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][street_type]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['street_type'] ?? $guardian->street_type }}">
                                        </div>
                                    </div>

                                    <div class="gf-row" style="grid-template-columns: repeat(3, 1fr); max-width: 260px;">
                                        <div>
                                            <label class="form-label">Házszám</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][house_number]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['house_number'] ?? $guardian->house_number }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Emelet</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][floor]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['floor'] ?? $guardian->floor }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Ajtó</label>
                                            <input type="text" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][door]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $guardianOld['door'] ?? $guardian->door }}">
                                        </div>
                                    </div>

                                    <hr class="my-1">
                                    <h6 class="mb-2">Kapcsolat a gyermekhez</h6>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Rokonsági fok</label>
                                            <select form="childEditForm" name="guardians[{{ $guardian->id }}][relationship_type]" class="form-control form-control-sm">
                                                <option value="">Nincs megadva</option>
                                                @foreach($relationshipTypes ?? [] as $relationshipType)
                                                    <option value="{{ $relationshipType }}"
                                                        @selected(($guardianOld['relationship_type'] ?? $guardian->pivot->relationship_type) === $relationshipType)>
                                                        {{ $relationshipType }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="gf-checks">
                                        @foreach([
                                            'is_legal_representative' => 'Törvényes képviselő',
                                            'has_no_custody' => 'Nem gyakorol szülői felügyeletet',
                                            'is_emergency_contact' => 'Értesítendő hozzátartozó',
                                            'receives_family_allowance' => 'Családi pótlékra jogosult',
                                        ] as $field => $label)
                                            <div class="form-check">
                                                <input type="checkbox" form="childEditForm" class="form-check-input"
                                                       id="guardian_{{ $guardian->id }}_{{ $field }}"
                                                       name="guardians[{{ $guardian->id }}][{{ $field }}]" value="1"
                                                       @checked($guardianOld[$field] ?? $guardian->pivot->$field)>
                                                <label class="form-check-label" for="guardian_{{ $guardian->id }}_{{ $field }}">
                                                    {{ $label }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-2">
                                        <div class="form-check form-switch">
                                            <input type="hidden" form="childEditForm"
                                                   name="guardians[{{ $guardian->id }}][active]" value="0">
                                            <input type="checkbox" form="childEditForm"
                                                   class="form-check-input"
                                                   id="guardian_{{ $guardian->id }}_active"
                                                   name="guardians[{{ $guardian->id }}][active]" value="1"
                                                   @checked($guardianOld['active'] ?? $guardian->active)>
                                            <label class="form-check-label" for="guardian_{{ $guardian->id }}_active">
                                                Aktív gondviselő
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">Nincs gondviselő kapcsolva a gyermekhez.</div>
                    @endforelse

                    @if($child->guardians->count() < 2)
                        @php($newGuardianOld = old('new_guardian', []))
                        @php($showNewGuardianForm = $errors->has('new_guardian.*') || filled($newGuardianOld['last_name'] ?? null) || filled($newGuardianOld['first_name'] ?? null))
                        <div class="border-top pt-3 mt-1">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#newGuardianFields">
                                <i class="fa-solid fa-plus me-1"></i>Új gondviselő hozzáadása
                            </button>

                            <div class="collapse mt-3 {{ $showNewGuardianForm ? 'show' : '' }}" id="newGuardianFields">
                                <div class="guardian-fields">
                                    <div class="gf-row" style="grid-template-columns: 84px 1fr;">
                                        <div>
                                            <label class="form-label">Előtag</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[prefix]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['prefix'] ?? '' }}" maxlength="30">
                                        </div>
                                        <div>
                                            <label class="form-label">Vezetéknév <span class="text-danger">*</span></label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[last_name]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['last_name'] ?? '' }}" maxlength="100">
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Keresztnév <span class="text-danger">*</span></label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[first_name]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['first_name'] ?? '' }}" maxlength="100">
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">E-mail-cím</label>
                                            <input type="email" form="childEditForm"
                                                   name="new_guardian[email]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['email'] ?? '' }}">
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Telefonszám</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[phone]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['phone'] ?? '' }}">
                                        </div>
                                    </div>

                                    @if($canManageBilling ?? false)
                                        <div class="gf-row">
                                            <div>
                                                <label class="form-label">Bankszámlatulajdonos</label>
                                                <input type="text" form="childEditForm"
                                                       name="new_guardian[bank_account_holder]"
                                                       class="form-control form-control-sm"
                                                       value="{{ $newGuardianOld['bank_account_holder'] ?? '' }}" maxlength="200">
                                            </div>
                                        </div>
                                        <div class="gf-row">
                                            <div>
                                                <label class="form-label">Bankszámlaszám</label>
                                                <input type="text" form="childEditForm"
                                                       name="new_guardian[bank_account_number]"
                                                       class="form-control form-control-sm"
                                                       value="{{ $newGuardianOld['bank_account_number'] ?? '' }}" maxlength="64">
                                            </div>
                                        </div>
                                    @endif

                                    <hr class="my-1">
                                    <h6 class="mb-2">Lakcím</h6>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Cím típusa</label>
                                            <select form="childEditForm" name="new_guardian[address_type]" class="form-control form-control-sm">
                                                <option value="">Nincs megadva</option>
                                                @foreach(['Állandó lakcím', 'Ideiglenes lakcím', 'Tartózkodási hely', 'Intézménycím'] as $addressType)
                                                    <option value="{{ $addressType }}"
                                                        @selected(($newGuardianOld['address_type'] ?? '') === $addressType)>
                                                        {{ $addressType }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Ország</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[country]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['country'] ?? '' }}">
                                        </div>
                                    </div>

                                    <div class="gf-row" style="grid-template-columns: 88px 1fr;">
                                        <div>
                                            <label class="form-label">Irsz.</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[postal_code]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['postal_code'] ?? '' }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Település</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[city]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['city'] ?? '' }}">
                                        </div>
                                    </div>

                                    <div class="gf-row" style="grid-template-columns: 1fr 110px;">
                                        <div>
                                            <label class="form-label">Közterület neve</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[street_name]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['street_name'] ?? '' }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Közterület jellege</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[street_type]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['street_type'] ?? '' }}">
                                        </div>
                                    </div>

                                    <div class="gf-row" style="grid-template-columns: repeat(3, 1fr); max-width: 260px;">
                                        <div>
                                            <label class="form-label">Házszám</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[house_number]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['house_number'] ?? '' }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Emelet</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[floor]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['floor'] ?? '' }}">
                                        </div>
                                        <div>
                                            <label class="form-label">Ajtó</label>
                                            <input type="text" form="childEditForm"
                                                   name="new_guardian[door]"
                                                   class="form-control form-control-sm"
                                                   value="{{ $newGuardianOld['door'] ?? '' }}">
                                        </div>
                                    </div>

                                    <hr class="my-1">
                                    <h6 class="mb-2">Kapcsolat a gyermekhez</h6>

                                    <div class="gf-row">
                                        <div>
                                            <label class="form-label">Rokonsági fok</label>
                                            <select form="childEditForm" name="new_guardian[relationship_type]" class="form-control form-control-sm">
                                                <option value="">Nincs megadva</option>
                                                @foreach($relationshipTypes ?? [] as $relationshipType)
                                                    <option value="{{ $relationshipType }}"
                                                        @selected(($newGuardianOld['relationship_type'] ?? '') === $relationshipType)>
                                                        {{ $relationshipType }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="gf-checks">
                                        @foreach([
                                            'is_legal_representative' => 'Törvényes képviselő',
                                            'has_no_custody' => 'Nem gyakorol szülői felügyeletet',
                                            'is_emergency_contact' => 'Értesítendő hozzátartozó',
                                            'receives_family_allowance' => 'Családi pótlékra jogosult',
                                        ] as $field => $label)
                                            <div class="form-check">
                                                <input type="checkbox" form="childEditForm" class="form-check-input"
                                                       id="new_guardian_{{ $field }}"
                                                       name="new_guardian[{{ $field }}]" value="1"
                                                       @checked($newGuardianOld[$field] ?? false)>
                                                <label class="form-check-label" for="new_guardian_{{ $field }}">
                                                    {{ $label }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-2">
                                        <div class="form-check form-switch">
                                            <input type="hidden" form="childEditForm"
                                                   name="new_guardian[active]" value="0">
                                            <input type="checkbox" form="childEditForm"
                                                   class="form-check-input"
                                                   id="new_guardian_active"
                                                   name="new_guardian[active]" value="1"
                                                   @checked($newGuardianOld['active'] ?? true)>
                                            <label class="form-check-label" for="new_guardian_active">
                                                Aktív gondviselő
                                            </label>
                                        </div>
                                    </div>

                                    <div class="small text-muted mt-2">
                                        A vezetéknév és keresztnév kitöltése esetén a mentéskor létrejön az új
                                        gondviselő, és automatikusan a gyermekhez kapcsolódik.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="small text-muted mt-2">
                        A módosítások a "Módosítások mentése" gombbal, a fenti alapadatokkal együtt kerülnek elmentésre.
                        A számlázási cím a fenti "Számlázási cím szerkesztése" gombbal (Számlázási címek menüpont) módosítható.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
