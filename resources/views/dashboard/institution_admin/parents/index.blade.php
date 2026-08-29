@extends('layouts.superadmin')

@section('title', 'Szülők / gondviselők')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Szülők / gondviselők',
        'subtitle' => $canManageBilling
            ? 'Kapcsolattartási, gyermekkapcsolati és számlázási adatok kezelése'
            : 'Kapcsolattartási és gyermekkapcsolati adatok kezelése',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív gondviselők',
            'value' => $stats['total'],
            'subtitle' => 'Aktív szülői és gondviselői rekordok',
            'icon' => 'fa-solid fa-users',
            'color' => 'green',
        ])
        @if($canManageBilling)
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Számlafogadók',
                'value' => $stats['invoice_recipients'],
                'subtitle' => 'Gyermekhez kijelölt számlázási profilok',
                'icon' => 'fa-solid fa-file-invoice',
                'color' => 'blue',
            ])
        @endif
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Hiányzó e-mail',
            'value' => $stats['missing_email'],
            'subtitle' => 'Ellenőrzést igénylő kapcsolattartás',
            'icon' => 'fa-solid fa-envelope',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Hiányzó telefon',
            'value' => $stats['missing_phone'],
            'subtitle' => 'Ellenőrzést igénylő kapcsolattartás',
            'icon' => 'fa-solid fa-phone-slash',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.parents.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-5 col-lg-5 mb-3">
                        <label class="form-label">Név, e-mail vagy telefonszám</label>
                        <input type="search" name="search" class="form-control"
                               value="{{ request('search') }}" placeholder="Keresés...">
                    </div>
                    <div class="col-xl-2 col-lg-2 mb-3">
                        <label class="form-label">Állapot</label>
                        <select name="status" class="form-control">
                            <option value="">Minden állapot</option>
                            <option value="active" @selected(request('status') === 'active')>Aktív</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inaktív</option>
                        </select>
                    </div>
                    @if($canManageBilling)
                        <div class="col-xl-3 col-lg-3 mb-3">
                            <label class="form-label">Számlafogadó</label>
                            <select name="billing" class="form-control">
                                <option value="">Minden gondviselő</option>
                                <option value="yes" @selected(request('billing') === 'yes')>Kijelölt számlafogadó</option>
                                <option value="no" @selected(request('billing') === 'no')>Nincs kijelölve</option>
                            </select>
                        </div>
                    @endif
                    <div class="col-xl-2 col-lg-2 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->hasAny(['search', 'status', 'billing']))
                            <a href="{{ route('dashboard.institution.parents.index') }}"
                               class="btn btn-light" title="Szűrők törlése">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Gondviselők</h4>
            <span class="text-muted">Találatok: {{ $guardians->total() }}</span>
        </div>
        <div class="card-body">
            @if($guardians->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>Szülő / gondviselő</th>
                            <th width="110" class="text-end">Művelet</th>
                            <th>Kapcsolt gyermekek</th>
                            <th>Elérhetőség</th>
                            <th>Jogviszony</th>
                            @if($canManageBilling)
                                <th>Számlázás</th>
                            @endif
                            <th>Állapot</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($guardians as $guardian)
                            @php
                                $billingProfile = $guardian->billingProfiles->first();
                                $invoiceChildren = $guardian->billingProfiles
                                    ->flatMap(fn ($profile) => $profile->children)
                                    ->unique('id');
                                $isLegalRepresentative = $guardian->children
                                    ->contains(fn ($child) => (bool) $child->pivot->is_legal_representative);
                                $isEmergencyContact = $guardian->children
                                    ->contains(fn ($child) => (bool) $child->pivot->is_emergency_contact);
                            @endphp
                            <tr>
                                <td>{{ ($guardians->firstItem() ?? 0) + $loop->index }}</td>
                                <td><strong>{{ $guardian->full_name }}</strong></td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.parents.edit', $guardian) }}"
                                       class="btn btn-xs btn-outline-warning" title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                </td>
                                <td>
                                    @forelse($guardian->children as $child)
                                        <div class="mb-1">
                                            <span class="badge badge-primary light">
                                                {{ $child->name }}
                                                @if($child->group_name)
                                                    – {{ $child->group_name }}
                                                @endif
                                            </span>
                                        </div>
                                    @empty
                                        <span class="badge badge-warning light">Nincs gyermekkapcsolat</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($guardian->email)
                                        <div><i class="fa-regular fa-envelope me-1 text-muted"></i>{{ $guardian->email }}</div>
                                    @else
                                        <div class="text-warning"><i class="fa-regular fa-envelope me-1"></i>Nincs e-mail</div>
                                    @endif
                                    @if($guardian->phone)
                                        <div><i class="fa-solid fa-phone me-1 text-muted"></i>{{ $guardian->phone }}</div>
                                    @else
                                        <div class="text-warning"><i class="fa-solid fa-phone me-1"></i>Nincs telefon</div>
                                    @endif
                                </td>
                                <td>
                                    @if($isLegalRepresentative)
                                        <span class="badge badge-success light mb-1">Törvényes képviselő</span>
                                    @endif
                                    @if($isEmergencyContact)
                                        <span class="badge badge-info light mb-1">Értesítendő</span>
                                    @endif
                                    @if(!$isLegalRepresentative && !$isEmergencyContact)
                                        <span class="text-muted">Kapcsolt gondviselő</span>
                                    @endif
                                </td>
                                @if($canManageBilling)
                                    <td>
                                        @if($billingProfile)
                                            <span class="badge badge-success light mb-1">Számlázási adat mentve</span>
                                            <div class="d-flex align-items-center justify-content-between gap-2 flex-nowrap">
                                                <div class="min-w-0">
                                                    <div class="small fw-semibold text-truncate">{{ $billingProfile->billing_name }}</div>
                                                    <div class="small text-muted text-truncate">
                                                        @if($invoiceChildren->count())
                                                            {{ $invoiceChildren->count() }} gyermek számlafogadója
                                                        @else
                                                            Nincs gyermekhez kijelölve
                                                        @endif
                                                    </div>
                                                </div>
                                                <button type="button"
                                                        class="btn btn-xs btn-outline-primary flex-shrink-0"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#billingModal{{ $guardian->id }}">
                                                    <i class="fa-solid fa-file-invoice me-1"></i>Részletek
                                                </button>
                                            </div>
                                        @else
                                            <span class="badge badge-secondary light">Nincs számlázási adat</span>
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    @if($guardian->active)
                                        <span class="badge badge-success light">Aktív</span>
                                    @else
                                        <span class="badge badge-secondary light">Inaktív</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $guardians->links('vendor.pagination.digifood') }}
                </div>

                @if($canManageBilling)
                @foreach($guardians as $guardian)
                    @php
                        $billingProfile = $guardian->billingProfiles->first();
                        $billingChildren = $billingProfile?->children ?? collect();
                        $payerTypeLabel = match($billingProfile?->payer_type) {
                            'guardian' => 'Szülő / gondviselő',
                            'employer' => 'Munkáltató',
                            'organization' => 'Más szervezet',
                            'municipality' => 'Önkormányzat / támogató',
                            default => '-',
                        };
                        $paymentMethodLabel = match($billingProfile?->payment_method) {
                            'transfer' => 'Átutalás',
                            'cash' => 'Készpénz',
                            'card' => 'Bankkártya',
                            'direct_debit' => 'Csoportos beszedés',
                            default => '-',
                        };
                    @endphp

                    @if($billingProfile)
                        <div class="modal fade" id="billingModal{{ $guardian->id }}" tabindex="-1"
                             aria-labelledby="billingModalLabel{{ $guardian->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div>
                                            <h5 class="modal-title" id="billingModalLabel{{ $guardian->id }}">
                                                Számlázási adatok
                                            </h5>
                                            <div class="text-muted small">{{ $guardian->full_name }}</div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3"><strong>Fizető típusa</strong><br>{{ $payerTypeLabel }}</div>
                                            <div class="col-md-6 mb-3"><strong>Számlázási név</strong><br>{{ $billingProfile->billing_name }}</div>
                                            <div class="col-md-6 mb-3"><strong>Adószám</strong><br>{{ $billingProfile->tax_number ?: '-' }}</div>
                                            <div class="col-md-6 mb-3"><strong>Számlázási e-mail</strong><br>{{ $billingProfile->email ?: '-' }}</div>
                                            <div class="col-md-6 mb-3"><strong>Fizetési mód</strong><br>{{ $paymentMethodLabel }}</div>
                                            <div class="col-md-6 mb-3">
                                                <strong>Számlázási cím</strong><br>
                                                {{ collect([$billingProfile->postal_code, $billingProfile->city, $billingProfile->address])->filter()->implode(' ') ?: '-' }}
                                            </div>
                                            <div class="col-md-6 mb-3"><strong>Bankszámlatulajdonos</strong><br>{{ $guardian->bank_account_holder ?: '-' }}</div>
                                            <div class="col-md-6 mb-3"><strong>Bankszámlaszám</strong><br>{{ $guardian->bank_account_number ?: '-' }}</div>
                                            <div class="col-12 mb-3">
                                                <strong>Külső fizetői kód / megjegyzés</strong><br>
                                                {{ $billingProfile->employer_reference ?: '-' }}
                                            </div>
                                            <div class="col-12">
                                                <strong>Számlázáshoz kijelölt gyermekek</strong>
                                                <div class="mt-2">
                                                    @forelse($billingChildren as $child)
                                                        <span class="badge badge-primary light me-1 mb-1">
                                                            {{ $child->name }}@if($child->group_name) – {{ $child->group_name }}@endif
                                                        </span>
                                                    @empty
                                                        <span class="text-muted">Nincs gyermekhez kijelölve.</span>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="{{ route('dashboard.institution.parents.edit', $guardian) }}" class="btn btn-primary">
                                            <i class="fa-solid fa-pen me-1"></i>Szerkesztés
                                        </a>
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Bezárás</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
                @endif
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-users',
                    'title' => 'Nincs a szűrésnek megfelelő gondviselő',
                    'text' => 'Módosítsd a keresési feltételeket, vagy tölts fel intézményi Excel-fájlt.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
