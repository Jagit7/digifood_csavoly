<div class="row">
    <div class="col-lg-4 mb-3">
        <label class="form-label" for="price">Ár (Ft)</label>
        <div class="input-group">
            <input id="price" type="number" name="price"
                   class="form-control @error('price') is-invalid @enderror"
                   value="{{ old('price', $priceModel->price ?? '') }}"
                   min="1" step="1" required>
            <span class="input-group-text">Ft</span>
        </div>
        @error('price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-lg-4 mb-3">
        <label class="form-label" for="valid_from">Érvényes ettől</label>
        <input id="valid_from" type="date" name="valid_from"
               class="form-control @error('valid_from') is-invalid @enderror"
               value="{{ old('valid_from', isset($priceModel) && $priceModel->valid_from ? $priceModel->valid_from->format('Y-m-d') : '') }}"
               required>
        @error('valid_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @if(isset($priceModel) && $priceModel->exists)
        <div class="col-lg-4 mb-3">
            <label class="form-label">Érvényes eddig</label>
            <input type="text" class="form-control" value="{{ $priceModel->valid_to?->format('Y.m.d.') ?? 'Nyitott' }}" disabled>
        </div>
    @endif
</div>

<div class="mb-4">
    <label class="form-label" for="price_note">Megjegyzés</label>
    <textarea id="price_note" name="price_note" rows="4"
              class="form-control @error('price_note') is-invalid @enderror">{{ old('price_note', $priceModel->note ?? '') }}</textarea>
    @error('price_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
    </button>
</div>
