<div class="alert alert-light border">
    <div class="mb-2"><strong>Tudnivalók</strong></div>
    <div class="small text-muted">Gyermekenkénti díjnál a későbbi havi összeg: gyermeklétszám × egységár.</div>
    <div class="small text-muted">Fix díjnál a gyermeklétszámtól független havi összeg kerül alkalmazásra.</div>
    <div class="small text-muted">Minimumdíj esetén a kiszámított havi díj nem lehet a minimum alatti.</div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Havi díj gyermekenként</label>
    <div class="col-sm-9">
        <input type="number"
               step="0.01"
               min="0"
               name="price_per_child"
               class="form-control @error('price_per_child') is-invalid @enderror"
               value="{{ old('price_per_child', isset($billingRate) ? $billingRate->price_per_child : '') }}">
        @error('price_per_child') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Fix havi díj</label>
    <div class="col-sm-9">
        <input type="number"
               step="0.01"
               min="0"
               name="fixed_monthly_fee"
               class="form-control @error('fixed_monthly_fee') is-invalid @enderror"
               value="{{ old('fixed_monthly_fee', isset($billingRate) ? $billingRate->fixed_monthly_fee : '') }}">
        @error('fixed_monthly_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Minimum havi díj</label>
    <div class="col-sm-9">
        <input type="number"
               step="0.01"
               min="0"
               name="minimum_monthly_fee"
               class="form-control @error('minimum_monthly_fee') is-invalid @enderror"
               value="{{ old('minimum_monthly_fee', isset($billingRate) ? $billingRate->minimum_monthly_fee : '') }}">
        @error('minimum_monthly_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Érvényesség kezdete</label>
    <div class="col-sm-9">
        <input type="date"
               name="valid_from"
               class="form-control @error('valid_from') is-invalid @enderror"
               value="{{ old('valid_from', isset($billingRate) && $billingRate->valid_from ? $billingRate->valid_from->format('Y-m-d') : '') }}"
               required>
        @error('valid_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Érvényesség vége</label>
    <div class="col-sm-9">
        <input type="date"
               name="valid_to"
               class="form-control @error('valid_to') is-invalid @enderror"
               value="{{ old('valid_to', isset($billingRate) && $billingRate->valid_to ? $billingRate->valid_to->format('Y-m-d') : '') }}">
        @error('valid_to') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Megjegyzés</label>
    <div class="col-sm-9">
        <textarea name="note"
                  rows="4"
                  class="form-control @error('note') is-invalid @enderror">{{ old('note', $billingRate->note ?? '') }}</textarea>
        @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
