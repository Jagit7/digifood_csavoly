@extends('layouts.superadmin')

@section('title', 'Intézmény profil')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Intézmény profil',
        'subtitle' => $institution->name . ' · alapadatok, számlázás és hozzárendelt felhasználók',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Hozzárendelt felhasználók',
            'value' => $institution->users->count(),
            'subtitle' => $activeUsersCount . ' aktív fiók',
            'icon' => 'fa-solid fa-users',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Függő meghívók',
            'value' => $pendingInvitationsCount,
            'subtitle' => 'Még el nem fogadott hozzáférések',
            'icon' => 'fa-solid fa-envelope-open-text',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Számlázási kitöltöttség',
            'value' => $billingCompleteness . '%',
            'subtitle' => 'Név, adószám, cím és határidő alapján',
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'color' => 'green',
            'size' => 'small',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Intézményazonosító',
            'value' => $institution->institution_code ?? '—',
            'subtitle' => $institution->active ? 'Aktív intézmény' : 'Inaktív intézmény',
            'icon' => 'fa-solid fa-building',
            'color' => 'purple',
            'size' => 'small',
        ])
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Alapadatok és számlázás</h4>
                        <div class="text-muted small">Az intézményi profil és a számlázási törzsadatok innen kezelhetők.</div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('dashboard.institution.profile.update') }}">
                        @csrf
                        @method('PUT')

                        <h5 class="mb-3">Alapadatok</h5>
                        <div class="row">
                            <div class="col-lg-8 mb-3">
                                <label class="form-label" for="name">Intézmény neve</label>
                                <input id="name" type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $institution->name) }}" maxlength="255" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="type">Típus</label>
                                <select id="type" name="type" class="form-control @error('type') is-invalid @enderror">
                                    <option value="">Nincs megadva</option>
                                    <option value="ovoda" @selected(old('type', $institution->type) === 'ovoda')>Óvoda</option>
                                    <option value="iskola" @selected(old('type', $institution->type) === 'iskola')>Iskola</option>
                                    <option value="bolcsode" @selected(old('type', $institution->type) === 'bolcsode')>Bölcsőde</option>
                                </select>
                                @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="institution_code">Intézményazonosító</label>
                                <input id="institution_code" type="text" class="form-control" value="{{ $institution->institution_code ?? '—' }}" disabled>
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="om_identifier">OM azonosító</label>
                                <input id="om_identifier" type="text" name="om_identifier" class="form-control @error('om_identifier') is-invalid @enderror"
                                       value="{{ old('om_identifier', $institution->om_identifier) }}" maxlength="6">
                                @error('om_identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="address_zip">Irányítószám</label>
                                <input id="address_zip" type="text" name="address_zip" class="form-control @error('address_zip') is-invalid @enderror"
                                       value="{{ old('address_zip', $institution->address_zip) }}" maxlength="10">
                                @error('address_zip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="address_city">Város</label>
                                <input id="address_city" type="text" name="address_city" class="form-control @error('address_city') is-invalid @enderror"
                                       value="{{ old('address_city', $institution->address_city) }}" maxlength="100">
                                @error('address_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-5 mb-3">
                                <label class="form-label" for="address_line">Cím</label>
                                <input id="address_line" type="text" name="address_line" class="form-control @error('address_line') is-invalid @enderror"
                                       value="{{ old('address_line', $institution->address_line) }}" maxlength="255">
                                @error('address_line') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3">Kapcsolattartó</h5>
                        <div class="row">
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="contact_name">Név</label>
                                <input id="contact_name" type="text" name="contact_name" class="form-control @error('contact_name') is-invalid @enderror"
                                       value="{{ old('contact_name', $institution->contact_name) }}" maxlength="255">
                                @error('contact_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="email">E-mail</label>
                                <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', $institution->email) }}" maxlength="191">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="contact_phone">Telefon</label>
                                <input id="contact_phone" type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $institution->phone) }}" maxlength="50">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <h5 class="mt-4 mb-3">Számlázási adatok</h5>
                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label class="form-label" for="billing_name">Számlázási név</label>
                                <input id="billing_name" type="text" name="billing_name" class="form-control @error('billing_name') is-invalid @enderror"
                                       value="{{ old('billing_name', $institution->billing_name) }}" maxlength="255">
                                @error('billing_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="billing_tax_number">Adószám</label>
                                <input id="billing_tax_number" type="text" name="billing_tax_number" class="form-control @error('billing_tax_number') is-invalid @enderror"
                                       value="{{ old('billing_tax_number', $institution->billing_tax_number) }}" maxlength="50">
                                @error('billing_tax_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="billing_payment_due_days">Fizetési határidő</label>
                                <div class="input-group">
                                    <input id="billing_payment_due_days" type="number" name="billing_payment_due_days"
                                           class="form-control @error('billing_payment_due_days') is-invalid @enderror"
                                           value="{{ old('billing_payment_due_days', $institution->billing_payment_due_days) }}"
                                           min="0" max="365">
                                    <span class="input-group-text">nap</span>
                                </div>
                                @error('billing_payment_due_days') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-3 mb-3">
                                <label class="form-label" for="billing_zip">Irányítószám</label>
                                <input id="billing_zip" type="text" name="billing_zip" class="form-control @error('billing_zip') is-invalid @enderror"
                                       value="{{ old('billing_zip', $institution->billing_zip) }}" maxlength="10">
                                @error('billing_zip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-4 mb-3">
                                <label class="form-label" for="billing_city">Város</label>
                                <input id="billing_city" type="text" name="billing_city" class="form-control @error('billing_city') is-invalid @enderror"
                                       value="{{ old('billing_city', $institution->billing_city) }}" maxlength="100">
                                @error('billing_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-lg-5 mb-3">
                                <label class="form-label" for="billing_address">Cím</label>
                                <input id="billing_address" type="text" name="billing_address" class="form-control @error('billing_address') is-invalid @enderror"
                                       value="{{ old('billing_address', $institution->billing_address) }}" maxlength="255">
                                @error('billing_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Hozzárendelt felhasználók</h4>
                        <div class="text-muted small">Az intézményhez kapcsolt adminisztratív hozzáférések.</div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="padding:10px;">
                        <table class="table table-hover table-responsive-md mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Név</th>
                                    <th>Szerepkör</th>
                                    <th>Állapot</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($institution->users as $user)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $user->name }}</div>
                                            <div class="small text-muted">{{ $user->email }}</div>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary light">
                                                {{ $roleLabels[$user->role] ?? $user->role }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-secondary' }} light">
                                                {{ $user->is_active ? 'Aktív' : 'Inaktív' }}
                                            </span>
                                            @if($user->accepted_invitation_at)
                                                <div class="small text-muted mt-1">{{ $user->accepted_invitation_at->format('Y.m.d. H:i') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            Nincs hozzárendelt felhasználó.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-header">
                    <div>
                        <h4 class="card-title mb-1">Függő meghívások</h4>
                        <div class="text-muted small">Már kiküldött, de még el nem fogadott hozzáférések.</div>
                    </div>
                </div>
                <div class="card-body">
                    @forelse($institution->adminInvitations as $invitation)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $invitation->name }}</div>
                                    <div class="small text-muted">{{ $invitation->email }}</div>
                                </div>
                                <span class="badge badge-warning light">
                                    {{ $roleLabels[$invitation->role] ?? $invitation->role }}
                                </span>
                            </div>
                            <div class="small text-muted mt-2">
                                Meghívva: {{ $invitation->created_at?->format('Y.m.d. H:i') ?? '—' }}
                            </div>
                            <div class="small {{ $invitation->expires_at && $invitation->expires_at->isPast() ? 'text-danger' : 'text-muted' }}">
                                Lejárat: {{ $invitation->expires_at?->format('Y.m.d. H:i') ?? '—' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">Nincs függő meghívás.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
