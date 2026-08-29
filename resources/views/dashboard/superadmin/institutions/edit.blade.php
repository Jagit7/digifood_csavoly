@extends('layouts.superadmin')
@section('content')
<div class="row">
    @include('layouts.partials.flash')

    {{-- BAL OLDAL --}}
    <div class="col-xl-9 col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Intézmény szerkesztése</h4>
            </div>

            <div class="card-body">
                <div class="basic-form">
                    <div class="d-flex justify-content-end mb-3">
                        <a href="{{ route('dashboard.institutions.billing-rates.index', $institution) }}" class="btn btn-outline-primary">
                            Díjszabások kezelése
                        </a>
                    </div>

                    <form method="POST" action="{{ route('dashboard.institutions.update', $institution) }}" id="institutionForm">
                        @csrf
                        @method('PUT')

                        <h5 class="mb-3">Alapadatok</h5>

                        {{-- Intézményi azonosító (institution_code) - csak olvasható --}}
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Intézményi azonosító</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       class="form-control"
                                       value="{{ $institution->institution_code ?? '—' }}"
                                       readonly
                                       disabled>
                                <small class="text-muted">Ezt az azonosítót a rendszer generálta, nem módosítható.</small>
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Intézmény neve</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $institution->name) }}"
                                       required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Típus</label>
                            <div class="col-sm-9">
                                <select name="type" class="form-control @error('type') is-invalid @enderror">
                                    <option value="">-- válassz --</option>
                                    <option value="ovoda" {{ old('type', $institution->type) === 'ovoda' ? 'selected' : '' }}>Óvoda</option>
                                    <option value="iskola" {{ old('type', $institution->type) === 'iskola' ? 'selected' : '' }}>Iskola</option>
                                    <option value="bolcsode" {{ old('type', $institution->type) === 'bolcsode' ? 'selected' : '' }}>Bölcsőde</option>
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">OM azonosító</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="om_identifier"
                                       class="form-control @error('om_identifier') is-invalid @enderror"
                                       value="{{ old('om_identifier', $institution->om_identifier) }}"
                                       maxlength="6">
                                <small class="text-muted">6 számjegy (opcionális)</small>
                                @error('om_identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Cégjegyzékszám</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="company_registration_number"
                                       class="form-control @error('company_registration_number') is-invalid @enderror"
                                       value="{{ old('company_registration_number', $institution->company_registration_number) }}"
                                       placeholder="Pl. 00 18 062764">
                                <small class="text-muted">Az impresszumon jelenik meg (banki/jogi követelmény)</small>
                                @error('company_registration_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Intézmény cím (strukturált) --}}
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Irányítószám</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="address_zip"
                                       class="form-control @error('address_zip') is-invalid @enderror"
                                       value="{{ old('address_zip', $institution->address_zip) }}"
                                       maxlength="10"
                                       placeholder="Pl. 2096">
                                @error('address_zip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Város</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="address_city"
                                       class="form-control @error('address_city') is-invalid @enderror"
                                       value="{{ old('address_city', $institution->address_city) }}"
                                       placeholder="Pl. Zsámbék">
                                @error('address_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Cím (utca, házszám)</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="address_line"
                                       class="form-control @error('address_line') is-invalid @enderror"
                                       value="{{ old('address_line', $institution->address_line) }}"
                                       placeholder="Pl. Fő utca 1.">
                                @error('address_line') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3">Kapcsolattartó</h5>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Kapcsolattartó neve</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="contact_name"
                                       class="form-control @error('contact_name') is-invalid @enderror"
                                       value="{{ old('contact_name', $institution->contact_name) }}">
                                @error('contact_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Kapcsolattartó e-mail</label>
                            <div class="col-sm-9">
                                <input type="email"
                                       name="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', $institution->email) }}">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Kapcsolattartó telefon</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="phone"
                                       class="form-control @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $institution->phone) }}">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3">Számlázási adatok</h5>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számlázási név</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="billing_name"
                                       class="form-control @error('billing_name') is-invalid @enderror"
                                       value="{{ old('billing_name', $institution->billing_name) }}">
                                @error('billing_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Adószám</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="billing_tax_number"
                                       class="form-control @error('billing_tax_number') is-invalid @enderror"
                                       value="{{ old('billing_tax_number', $institution->billing_tax_number) }}">
                                @error('billing_tax_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Számlázási cím (strukturált) --}}
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számlázási irányítószám</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="billing_zip"
                                       class="form-control @error('billing_zip') is-invalid @enderror"
                                       value="{{ old('billing_zip', $institution->billing_zip) }}"
                                       maxlength="10">
                                @error('billing_zip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számlázási város</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="billing_city"
                                       class="form-control @error('billing_city') is-invalid @enderror"
                                       value="{{ old('billing_city', $institution->billing_city) }}">
                                @error('billing_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számlázási cím (utca, házszám)</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="billing_address"
                                       class="form-control @error('billing_address') is-invalid @enderror"
                                       value="{{ old('billing_address', $institution->billing_address) }}">
                                @error('billing_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Fizetési határidő (nap)</label>
                            <div class="col-sm-9">
                                <input type="number"
                                       min="0"
                                       name="billing_payment_due_days"
                                       class="form-control @error('billing_payment_due_days') is-invalid @enderror"
                                       value="{{ old('billing_payment_due_days', $institution->billing_payment_due_days) }}">
                                @error('billing_payment_due_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Digifood havidíj / aktív étkező (Ft)</label>
                            <div class="col-sm-9">
                                <input type="number"
                                       min="0"
                                       step="0.01"
                                       name="saas_fee_per_active_eater"
                                       class="form-control @error('saas_fee_per_active_eater') is-invalid @enderror"
                                       value="{{ old('saas_fee_per_active_eater', $institution->saas_fee_per_active_eater) }}">
                                <small class="text-muted">Ezzel az összeggel szorozzuk az intézmény aktív étkezőinek (diák + dolgozó) számát a havi Digifood számlázási összesítőben.</small>
                                @error('saas_fee_per_active_eater') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3">Integrációk</h5>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">KRÉTA azonosító</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="kreta_code"
                                       class="form-control @error('kreta_code') is-invalid @enderror"
                                       value="{{ old('kreta_code', $institution->kreta_code) }}">
                                @error('kreta_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számlázz.hu partner azonosító</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="szamlazz_partner_id"
                                       class="form-control @error('szamlazz_partner_id') is-invalid @enderror"
                                       value="{{ old('szamlazz_partner_id', $institution->szamlazz_partner_id) }}">
                                @error('szamlazz_partner_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számlaszám prefix</label>
                            <div class="col-sm-9">
                                <input type="text"
                                       name="invoice_prefix"
                                       class="form-control @error('invoice_prefix') is-invalid @enderror"
                                       value="{{ old('invoice_prefix', $institution->invoice_prefix) }}">
                                @error('invoice_prefix') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Állapot</label>
                            <div class="col-sm-9">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="active" value="0">
                                    <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $institution->active) ? 'checked' : '' }}>
                                    <label class="form-check-label">Aktív intézmény</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Beléptető modul</label>
                            <div class="col-sm-9">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="barcode_entry_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" name="barcode_entry_enabled" value="1" {{ old('barcode_entry_enabled', $setting->barcode_entry_enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label">Vonalkódos beléptető engedélyezve</label>
                                </div>
                                <small class="text-muted">A vonalkód-generálás ettől függetlenül minden intézmény számára elérhető marad.</small>
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Adminisztrátori böngészőkorlátozás</label>
                            <div class="col-sm-9">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="admin_browser_restriction_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" name="admin_browser_restriction_enabled" value="1" {{ old('admin_browser_restriction_enabled', $setting->admin_browser_restriction_enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label">Böngészőkorlátozás engedélyezve</label>
                                </div>
                                <small class="text-muted">Bekapcsolva az intézmény adminisztrátorai legfeljebb 2 engedélyezett böngészőből használhatják a rendszert. Új böngésző engedélyezéséhez superadmin jóváhagyás szükséges. Kikapcsolva nincs böngészőkorlátozás.</small>
                            </div>
                        </div>

                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számlázási partner</label>
                            <div class="col-sm-9">
                                <select name="billing_partner_id" class="form-control @error('billing_partner_id') is-invalid @enderror">
                                    <option value="">Nincs partnerhez rendelve</option>
                                    @foreach($billingPartners as $billingPartner)
                                        <option value="{{ $billingPartner->id }}" {{ (string) old('billing_partner_id', $institution->billing_partner_id) === (string) $billingPartner->id ? 'selected' : '' }}>
                                            {{ $billingPartner->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('billing_partner_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('dashboard.institutions.index') }}" class="btn btn-outline-secondary">
                                Vissza
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Mentés
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    {{-- JOBB OLDAL: ÖSSZEFOGLALÓ --}}
    <div class="col-xl-3 col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Összefoglaló</h4>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Intézményi azonosító:</strong><br><span>{{ $institution->institution_code ?? '—' }}</span></li>

                    <li class="list-group-item"><strong>Név:</strong><br><span data-preview="name">–</span></li>
                    <li class="list-group-item"><strong>Típus:</strong><br><span data-preview="type">–</span></li>
                    <li class="list-group-item"><strong>OM azonosító:</strong><br><span data-preview="om_identifier">–</span></li>
                    <li class="list-group-item"><strong>Cégjegyzékszám:</strong><br><span data-preview="company_registration_number">–</span></li>

                    <li class="list-group-item"><strong>Irányítószám:</strong><br><span data-preview="address_zip">–</span></li>
                    <li class="list-group-item"><strong>Város:</strong><br><span data-preview="address_city">–</span></li>
                    <li class="list-group-item"><strong>Cím:</strong><br><span data-preview="address_line">–</span></li>

                    <li class="list-group-item"><strong>Kapcsolattartó:</strong><br><span data-preview="contact_name">–</span></li>
                    <li class="list-group-item"><strong>Email:</strong><br><span data-preview="email">–</span></li>
                    <li class="list-group-item"><strong>Telefon:</strong><br><span data-preview="phone">–</span></li>

                    <li class="list-group-item"><strong>Számlázási név:</strong><br><span data-preview="billing_name">–</span></li>
                    <li class="list-group-item"><strong>Adószám:</strong><br><span data-preview="billing_tax_number">–</span></li>
                    <li class="list-group-item"><strong>Számlázási irányítószám:</strong><br><span data-preview="billing_zip">–</span></li>
                    <li class="list-group-item"><strong>Számlázási város:</strong><br><span data-preview="billing_city">–</span></li>
                    <li class="list-group-item"><strong>Számlázási cím:</strong><br><span data-preview="billing_address">–</span></li>
                    <li class="list-group-item"><strong>Fizetési határidő:</strong><br><span data-preview="billing_payment_due_days">–</span></li>
                    <li class="list-group-item"><strong>Digifood díj / aktív étkező:</strong><br><span data-preview="saas_fee_per_active_eater">–</span></li>

                    <li class="list-group-item"><strong>KRÉTA kód:</strong><br><span data-preview="kreta_code">–</span></li>
                    <li class="list-group-item"><strong>Számlázz.hu partner:</strong><br><span data-preview="szamlazz_partner_id">–</span></li>
                    <li class="list-group-item"><strong>Prefix:</strong><br><span data-preview="invoice_prefix">–</span></li>
                </ul>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('institutionForm');
    if (!form) return;

    function setPreview(name, value) {
        const el = document.querySelector('[data-preview="' + name + '"]');
        if (!el) return;
        el.innerText = (value && String(value).trim() !== '') ? value : '–';
    }

    function readValue(field) {
        if (!field) return '';
        if (field.type === 'checkbox') return field.checked ? 'Igen' : 'Nem';
        if (field.tagName === 'SELECT') {
            const opt = field.options[field.selectedIndex];
            return opt ? (opt.text || field.value) : field.value;
        }
        return field.value;
    }

    const fields = form.querySelectorAll('input[name], select[name], textarea[name]');
    fields.forEach(f => {
        setPreview(f.name, readValue(f));
        f.addEventListener('input', () => setPreview(f.name, readValue(f)));
        f.addEventListener('change', () => setPreview(f.name, readValue(f)));
    });
})();
</script>
@endpush
