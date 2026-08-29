<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Partner neve</label>
    <div class="col-sm-9">
        <input type="text"
               name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $billingPartner->name ?? '') }}"
               required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Számlázási név</label>
    <div class="col-sm-9">
        <input type="text"
               name="billing_name"
               class="form-control @error('billing_name') is-invalid @enderror"
               value="{{ old('billing_name', $billingPartner->billing_name ?? '') }}">
        @error('billing_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Adószám</label>
    <div class="col-sm-9">
        <input type="text"
               name="tax_number"
               class="form-control @error('tax_number') is-invalid @enderror"
               value="{{ old('tax_number', $billingPartner->tax_number ?? '') }}">
        @error('tax_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Irányítószám</label>
    <div class="col-sm-9">
        <input type="text"
               name="billing_zip"
               class="form-control @error('billing_zip') is-invalid @enderror"
               value="{{ old('billing_zip', $billingPartner->billing_zip ?? '') }}">
        @error('billing_zip') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Város</label>
    <div class="col-sm-9">
        <input type="text"
               name="billing_city"
               class="form-control @error('billing_city') is-invalid @enderror"
               value="{{ old('billing_city', $billingPartner->billing_city ?? '') }}">
        @error('billing_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Cím</label>
    <div class="col-sm-9">
        <input type="text"
               name="billing_address"
               class="form-control @error('billing_address') is-invalid @enderror"
               value="{{ old('billing_address', $billingPartner->billing_address ?? '') }}">
        @error('billing_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Számlázási e-mail</label>
    <div class="col-sm-9">
        <input type="email"
               name="billing_email"
               class="form-control @error('billing_email') is-invalid @enderror"
               value="{{ old('billing_email', $billingPartner->billing_email ?? '') }}">
        @error('billing_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Fizetési határidő (nap)</label>
    <div class="col-sm-9">
        <input type="number"
               min="0"
               max="365"
               name="payment_due_days"
               class="form-control @error('payment_due_days') is-invalid @enderror"
               value="{{ old('payment_due_days', $billingPartner->payment_due_days ?? 8) }}">
        @error('payment_due_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">ÁFA (%)</label>
    <div class="col-sm-9">
        <input type="number"
               step="0.01"
               min="0"
               max="100"
               name="vat_rate"
               class="form-control @error('vat_rate') is-invalid @enderror"
               value="{{ old('vat_rate', isset($billingPartner) ? $billingPartner->vat_rate : '27.00') }}"
               required>
        @error('vat_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Számla megjegyzés</label>
    <div class="col-sm-9">
        <textarea name="invoice_note"
                  rows="4"
                  class="form-control @error('invoice_note') is-invalid @enderror">{{ old('invoice_note', $billingPartner->invoice_note ?? '') }}</textarea>
        @error('invoice_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">Állapot</label>
    <div class="col-sm-9">
        <div class="form-check form-switch">
            <input type="hidden" name="active" value="0">
            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $billingPartner->active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label">Aktív számlázási partner</label>
        </div>
    </div>
</div>
