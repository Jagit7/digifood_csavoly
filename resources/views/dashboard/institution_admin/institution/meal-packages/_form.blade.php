<div class="row">
    <div class="col-lg-6 mb-3">
        <label class="form-label" for="name">Csomag neve</label>
        <input id="name" type="text" name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $package->name) }}"
               maxlength="191" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-lg-3 mb-3">
        <label class="form-label" for="display_order">Megjelenési sorrend</label>
        <input id="display_order" type="number" name="display_order"
               class="form-control @error('display_order') is-invalid @enderror"
               value="{{ old('display_order', $package->display_order ?? 0) }}"
               min="0" max="65535" required>
        @error('display_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-lg-3 mb-3">
        <label class="form-label" for="pricing_mode">Árképzési mód</label>
        <select id="pricing_mode" name="pricing_mode"
                class="form-control @error('pricing_mode') is-invalid @enderror" required>
            @foreach($pricingModeLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('pricing_mode', $package->pricing_mode) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('pricing_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row" id="custom_price_row" style="display: none;">
    <div class="col-lg-3 mb-3">
        <label class="form-label" for="custom_price">Egyedi csomagár</label>
        <div class="input-group">
            <input id="custom_price" type="number" name="custom_price"
                   class="form-control @error('custom_price') is-invalid @enderror"
                   value="{{ old('custom_price', $package->custom_price) }}"
                   min="1" step="1">
            <span class="input-group-text">Ft</span>
        </div>
        @error('custom_price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="description">Leírás</label>
    <textarea id="description" name="description" rows="4"
              class="form-control @error('description') is-invalid @enderror">{{ old('description', $package->description) }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row mb-3">
    <div class="col-lg-3 mb-3 mb-lg-0">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                   name="is_active" value="1"
                   @checked((bool) old('is_active', $package->is_active))>
            <label class="form-check-label" for="is_active">Aktív</label>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_default" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_default"
                   name="is_default" value="1"
                   @checked((bool) old('is_default', $package->is_default))>
            <label class="form-check-label" for="is_default">Alapértelmezett</label>
        </div>
    </div>
</div>

<div class="card border mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-1">Étkezéstípusok</h5>
        <div class="small text-muted">Legalább egy aktív intézményi étkezéstípust válassz.</div>
    </div>
    <div class="card-body">
        @error('institution_meal_type_ids')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror

        @if($availableMealTypes->count())
            <div class="row">
                @foreach($availableMealTypes as $mealType)
                    <div class="col-xl-4 col-lg-6 mb-3">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="institution_meal_type_ids[]"
                                   value="{{ $mealType->id }}"
                                   id="meal_type_{{ $mealType->id }}"
                                   @checked(in_array($mealType->id, old('institution_meal_type_ids', $selectedMealTypeIds)))>
                            <label class="form-check-label" for="meal_type_{{ $mealType->id }}">
                                {{ $mealType->mealType->name }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-muted">Még nincs választható aktív intézményi étkezéstípus.</div>
        @endif
    </div>
</div>

<div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
    </button>
</div>

<script>
    (function () {
        var pricingModeSelect = document.getElementById('pricing_mode');
        var customPriceRow = document.getElementById('custom_price_row');
        var customPriceInput = document.getElementById('custom_price');
        var CUSTOM_PRICE_MODE = '{{ \App\Models\InstitutionMealPackage::PRICING_MODE_CUSTOM_PRICE }}';

        function toggleCustomPriceField() {
            var isCustom = pricingModeSelect.value === CUSTOM_PRICE_MODE;
            customPriceRow.style.display = isCustom ? '' : 'none';
            customPriceInput.required = isCustom;

            if (!isCustom) {
                customPriceInput.value = '';
            }
        }

        pricingModeSelect.addEventListener('change', toggleCustomPriceField);
        toggleCustomPriceField();
    })();
</script>
