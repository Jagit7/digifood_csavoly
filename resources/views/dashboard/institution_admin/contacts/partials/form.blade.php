<div class="row">
    <div class="col-lg-6 mb-3">
        <label class="form-label">Név</label>
        <input type="text"
               name="name"
               class="form-control"
               value="{{ old('name', $contact?->name) }}"
               maxlength="191"
               required>
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">Szerep / pozíció</label>
        <input type="text"
               name="role"
               class="form-control"
               value="{{ old('role', $contact?->role) }}"
               maxlength="100">
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">E-mail</label>
        <input type="email"
               name="email"
               class="form-control"
               value="{{ old('email', $contact?->email) }}"
               maxlength="191">
    </div>

    <div class="col-lg-6 mb-3">
        <label class="form-label">Telefon</label>
        <input type="text"
               name="phone"
               class="form-control"
               value="{{ old('phone', $contact?->phone) }}"
               maxlength="50">
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Megjegyzés</label>
        <textarea name="notes"
                  rows="4"
                  class="form-control">{{ old('notes', $contact?->notes) }}</textarea>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_primary" value="0">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="is_primary"
                   name="is_primary"
                   value="1"
                   @checked((bool) old('is_primary', $contact?->is_primary))>
            <label class="form-check-label" for="is_primary">
                Elsődleges kapcsolattartó
            </label>
        </div>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input"
                   type="checkbox"
                   role="switch"
                   id="is_active"
                   name="is_active"
                   value="1"
                   @checked((bool) old('is_active', $contact?->is_active ?? true))>
            <label class="form-check-label" for="is_active">
                Aktív kapcsolattartó
            </label>
        </div>
    </div>
</div>

<div class="text-end">
    <a href="{{ route('dashboard.institution.contacts.index') }}" class="btn btn-light">
        Mégsem
    </a>

    <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk me-1"></i>{{ $submitLabel }}
    </button>
</div>
