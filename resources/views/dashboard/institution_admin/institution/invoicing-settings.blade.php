@extends('layouts.superadmin')

@section('title', 'Számlázási és kártyás fizetési beállítások')

@section('content')
    @php
        $selectedInvoiceProvider = old('invoicing_provider', $setting->invoicing_provider ?? '');
        $selectedPaymentProvider = old('card_payment_provider', $setting->card_payment_provider ?? '');
        $invoicingEnabled = (bool) old('invoicing_enabled', $setting->invoicing_enabled);
        $cardPaymentEnabled = (bool) old('card_payment_enabled', $setting->card_payment_enabled);

        $invoiceProviderLabel = $selectedInvoiceProvider !== ''
            ? (config('integrations.invoice_providers.' . $selectedInvoiceProvider) ?? 'Nincs beállítva')
            : 'Nincs beállítva';
        $paymentProviderLabel = $selectedPaymentProvider !== ''
            ? (config('integrations.payment_providers.' . $selectedPaymentProvider) ?? 'Nincs beállítva')
            : 'Nincs beállítva';
        $invoiceStatusLabel = $invoicingEnabled ? 'Bekapcsolva' : 'Kikapcsolva';
        $cardPaymentStatusLabel = $cardPaymentEnabled ? 'Bekapcsolva' : 'Kikapcsolva';
    @endphp

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Számlázási és kártyás fizetési beállítások',
        'subtitle' => $institution->name . ' integrációs beállításai',
    ])

    <form method="POST" action="{{ route('dashboard.institution.settings.invoicing.update') }}">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-12">
                <div class="row g-3 mb-1">
                    @include('layouts.partials.components.ui.stats-card', [
                        'title' => 'Számlázás állapota',
                        'value' => $invoiceStatusLabel,
                        'subtitle' => $invoicingEnabled ? 'A számlázási modul aktív' : 'A számlázási modul ki van kapcsolva',
                        'icon' => $invoicingEnabled ? 'fa-solid fa-circle-check' : 'fa-solid fa-toggle-off',
                        'color' => $invoicingEnabled ? 'green' : 'purple',
                        'size' => 'small',
                    ])
                    @include('layouts.partials.components.ui.stats-card', [
                        'title' => 'Számlázási szolgáltató',
                        'value' => $invoiceProviderLabel,
                        'subtitle' => $invoicingEnabled ? 'Aktuális számlázási provider' : 'Nincs aktív számlázási provider',
                        'icon' => 'fa-solid fa-file-invoice',
                        'color' => 'blue',
                        'size' => 'small',
                    ])
                    @include('layouts.partials.components.ui.stats-card', [
                        'title' => 'Kártyás fizetés állapota',
                        'value' => $cardPaymentStatusLabel,
                        'subtitle' => $cardPaymentEnabled ? 'Az online kártyás fizetés aktív' : 'Az online kártyás fizetés ki van kapcsolva',
                        'icon' => 'fa-solid fa-credit-card',
                        'color' => $cardPaymentEnabled ? 'green' : 'blue',
                        'size' => 'small',
                    ])
                    @include('layouts.partials.components.ui.stats-card', [
                        'title' => 'Fizetési szolgáltató',
                        'value' => $paymentProviderLabel,
                        'subtitle' => $cardPaymentEnabled ? 'Aktuális online fizetési provider' : 'Nincs aktív fizetési provider',
                        'icon' => 'fa-solid fa-wallet',
                        'color' => 'orange',
                        'size' => 'small',
                    ])
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Alapbeállítások</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="invoicing_enabled" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="invoicing_enabled"
                                        name="invoicing_enabled"
                                        value="1"
                                        @checked($invoicingEnabled)
                                    >
                                    <label class="form-check-label" for="invoicing_enabled">Számlázás engedélyezése</label>
                                </div>

                                <div id="invoicing_provider_wrap">
                                    <label class="form-label" for="invoicing_provider">Számlázási szolgáltató</label>
                                    <select
                                        name="invoicing_provider"
                                        id="invoicing_provider"
                                        class="form-control @error('invoicing_provider') is-invalid @enderror"
                                    >
                                        <option value="">Nincs bekapcsolva</option>
                                        @foreach ($invoiceProviders as $value => $label)
                                            <option value="{{ $value }}" @selected($selectedInvoiceProvider === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('invoicing_provider')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="card_payment_enabled" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="card_payment_enabled"
                                        name="card_payment_enabled"
                                        value="1"
                                        @checked($cardPaymentEnabled)
                                    >
                                    <label class="form-check-label" for="card_payment_enabled">Kártyás fizetés engedélyezése</label>
                                </div>

                                <div id="card_payment_provider_wrap" class="mb-3">
                                    <label class="form-label" for="card_payment_provider">Kártyás fizetési szolgáltató</label>
                                    <select
                                        name="card_payment_provider"
                                        id="card_payment_provider"
                                        class="form-control @error('card_payment_provider') is-invalid @enderror"
                                    >
                                        <option value="">Nincs bekapcsolva</option>
                                        @foreach ($paymentProviders as $value => $label)
                                            <option value="{{ $value }}" @selected($selectedPaymentProvider === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('card_payment_provider')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div id="card_payment_test_mode_wrap" class="form-check form-switch">
                                    <input type="hidden" name="card_payment_test_mode" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="card_payment_test_mode"
                                        name="card_payment_test_mode"
                                        value="1"
                                        @checked((bool) old('card_payment_test_mode', $setting->card_payment_test_mode))
                                    >
                                    <label class="form-check-label" for="card_payment_test_mode">Kártyás fizetés teszt mód</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">ÁFA</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            A fizetési kötelezettségeknél (havi elszámolásoknál) megjelenő nettó összeg innen kapja meg
                            az ÁFával növelt (bruttó) értéket. 0%-on hagyva a nettó és a bruttó összeg megegyezik.
                        </p>
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <label class="form-label" for="vat_rate">ÁFA kulcs (%)</label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        class="form-control @error('vat_rate') is-invalid @enderror"
                                        id="vat_rate"
                                        name="vat_rate"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value="{{ old('vat_rate', $setting->vat_rate) }}"
                                    >
                                    <span class="input-group-text">%</span>
                                    @error('vat_rate')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Pl. 27 vagy 5 - a kiválasztott számlázási szolgáltatótól függetlenül alkalmazva.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Banki átutalás (kártyás fizetés nélküli befizetés)</h4>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Ha az intézménynél nincs bekapcsolva a kártyás fizetés, a szülői felület a havi elszámolásoknál
                            ez alapján egy vágólapra másolható átutalási tájékoztatót jelenít meg a fizetendő összeggel.
                            A mező kártyás fizetéssel együtt is kitölthető, ilyenkor csak tájékoztató jelleggel jelenik meg.
                        </p>
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <label class="form-label" for="bank_transfer_account_holder">Kedvezményezett neve</label>
                                <input
                                    type="text"
                                    class="form-control @error('bank_transfer_account_holder') is-invalid @enderror"
                                    id="bank_transfer_account_holder"
                                    name="bank_transfer_account_holder"
                                    maxlength="191"
                                    placeholder="{{ $institution->billing_name ?: $institution->name }}"
                                    value="{{ old('bank_transfer_account_holder', $setting->bank_transfer_account_holder) }}"
                                >
                                @error('bank_transfer_account_holder')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Üresen hagyva a szülői felület az intézmény nevét jeleníti meg.</div>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="bank_transfer_account_number">Bankszámlaszám</label>
                                <input
                                    type="text"
                                    class="form-control @error('bank_transfer_account_number') is-invalid @enderror"
                                    id="bank_transfer_account_number"
                                    name="bank_transfer_account_number"
                                    maxlength="64"
                                    placeholder="12345678-12345678-12345678"
                                    value="{{ old('bank_transfer_account_number', $setting->bank_transfer_account_number) }}"
                                >
                                @error('bank_transfer_account_number')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Üresen hagyva a szülői felületen nem jelenik meg átutalási tájékoztató.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-start py-3 px-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Beállítások mentése
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card" id="billingo_section">
                    <div class="card-header">
                        <h4 class="card-title">Billingo beállítások</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label" for="billingo_api_key">Billingo API-kulcs</label>
                                <input
                                    id="billingo_api_key"
                                    type="password"
                                    name="billingo_api_key"
                                    maxlength="191"
                                    autocomplete="new-password"
                                    class="form-control @error('billingo_api_key') is-invalid @enderror"
                                    placeholder="{{ $setting->hasBillingoApiKey() ? 'Új kulcs megadása a cseréhez' : 'API-kulcs megadása' }}"
                                >
                                @error('billingo_api_key')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="mt-2 d-flex align-items-center gap-3">
                                    @if ($setting->hasBillingoApiKey())
                                        <span class="badge bg-light text-dark border">Mentett kulcs van</span>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="remove_billingo_api_key" name="remove_billingo_api_key" @checked((bool) old('remove_billingo_api_key'))>
                                            <label class="form-check-label" for="remove_billingo_api_key">Kulcs törlése</label>
                                        </div>
                                    @else
                                        <span class="badge bg-secondary">Nincs mentett kulcs</span>
                                    @endif
                                </div>
                                <small class="text-muted d-block mt-2">Az üresen hagyott mező nem törli a már elmentett kulcsot.</small>
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="billingo_document_block_id">Bizonylattömb azonosító</label>
                                <input
                                    id="billingo_document_block_id"
                                    type="text"
                                    name="billingo_document_block_id"
                                    maxlength="100"
                                    value="{{ old('billingo_document_block_id', $setting->billingo_document_block_id) }}"
                                    class="form-control @error('billingo_document_block_id') is-invalid @enderror"
                                >
                                @error('billingo_document_block_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-1">Ez egy szám (pl. 12345), nem a bizonylattömb neve vagy előtagja.</small>
                                @if ($setting->hasBillingoApiKey())
                                    <button
                                        type="button"
                                        id="billingo_document_blocks_lookup"
                                        class="btn btn-sm btn-outline-secondary mt-2"
                                        data-url="{{ route('dashboard.institution.settings.invoicing.billingo-document-blocks') }}"
                                    >
                                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                                        Bizonylattömbök lekérdezése
                                    </button>
                                    <div id="billingo_document_blocks_status" class="small mt-2"></div>
                                    <div id="billingo_document_blocks_list" class="list-group mt-2"></div>
                                @endif
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="billingo_default_payment_method">Alapértelmezett fizetési mód</label>
                                <input
                                    id="billingo_default_payment_method"
                                    type="text"
                                    name="billingo_default_payment_method"
                                    maxlength="100"
                                    value="{{ old('billingo_default_payment_method', $setting->billingo_default_payment_method) }}"
                                    class="form-control @error('billingo_default_payment_method') is-invalid @enderror"
                                >
                                @error('billingo_default_payment_method')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="billingo_due_days">Fizetési határidő napokban</label>
                                <input
                                    id="billingo_due_days"
                                    type="number"
                                    name="billingo_due_days"
                                    min="0"
                                    max="365"
                                    value="{{ old('billingo_due_days', $setting->billingo_due_days) }}"
                                    class="form-control @error('billingo_due_days') is-invalid @enderror"
                                >
                                @error('billingo_due_days')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="billingo_invoice_language">Számla nyelve</label>
                                @php
                                    $billingoLanguageOptions = [
                                        'hu' => 'Magyar',
                                        'en' => 'Angol',
                                        'de' => 'Német',
                                        'fr' => 'Francia',
                                        'hr' => 'Horvát',
                                        'it' => 'Olasz',
                                        'ro' => 'Román',
                                        'sk' => 'Szlovák',
                                        'us' => 'Amerikai angol',
                                    ];
                                @endphp
                                <select
                                    id="billingo_invoice_language"
                                    name="billingo_invoice_language"
                                    class="form-select @error('billingo_invoice_language') is-invalid @enderror"
                                >
                                    @foreach ($billingoLanguageOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billingo_invoice_language', $setting->billingo_invoice_language ?: 'hu') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('billingo_invoice_language')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-1">A Billingo csak ezt a pár nyelvet fogadja el - a korábbi szabad szöveges mező hibás értéket is tartalmazhatott.</small>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-check form-switch mt-lg-4 pt-lg-2">
                                    <input type="hidden" name="billingo_e_invoice_enabled" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="billingo_e_invoice_enabled"
                                        name="billingo_e_invoice_enabled"
                                        value="1"
                                        @checked((bool) old('billingo_e_invoice_enabled', $setting->billingo_e_invoice_enabled))
                                    >
                                    <label class="form-check-label" for="billingo_e_invoice_enabled">E-számla engedélyezése</label>
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-check form-switch mt-lg-4 pt-lg-2">
                                    <input type="hidden" name="billingo_test_mode" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="billingo_test_mode"
                                        name="billingo_test_mode"
                                        value="1"
                                        @checked((bool) old('billingo_test_mode', $setting->billingo_test_mode))
                                    >
                                    <label class="form-check-label" for="billingo_test_mode">Billingo teszt mód</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card" id="szamlazz_section">
                    <div class="card-header">
                        <h4 class="card-title">Számlázz.hu beállítások</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label" for="szamlazz_hu_agent_key">Számlázz.hu agent kulcs</label>
                                <input
                                    id="szamlazz_hu_agent_key"
                                    type="password"
                                    name="szamlazz_hu_agent_key"
                                    maxlength="191"
                                    autocomplete="new-password"
                                    class="form-control @error('szamlazz_hu_agent_key') is-invalid @enderror"
                                    placeholder="{{ $setting->hasSzamlazzHuAgentKey() ? 'Új kulcs megadása a cseréhez' : 'API-kulcs megadása' }}"
                                >
                                @error('szamlazz_hu_agent_key')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="mt-2 d-flex align-items-center gap-3">
                                    @if ($setting->hasSzamlazzHuAgentKey())
                                        <span class="badge bg-light text-dark border">Mentett kulcs van</span>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="remove_szamlazz_hu_agent_key" name="remove_szamlazz_hu_agent_key" @checked((bool) old('remove_szamlazz_hu_agent_key'))>
                                            <label class="form-check-label" for="remove_szamlazz_hu_agent_key">Kulcs törlése</label>
                                        </div>
                                    @else
                                        <span class="badge bg-secondary">Nincs mentett kulcs</span>
                                    @endif
                                </div>
                                <small class="text-muted d-block mt-2">Az üresen hagyott mező nem törli a már elmentett kulcsot.</small>
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="szamlazz_hu_invoice_prefix">Számla előtag</label>
                                <input
                                    id="szamlazz_hu_invoice_prefix"
                                    type="text"
                                    name="szamlazz_hu_invoice_prefix"
                                    maxlength="100"
                                    value="{{ old('szamlazz_hu_invoice_prefix', $setting->szamlazz_hu_invoice_prefix) }}"
                                    class="form-control @error('szamlazz_hu_invoice_prefix') is-invalid @enderror"
                                >
                                @error('szamlazz_hu_invoice_prefix')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="szamlazz_hu_default_payment_method">Alapértelmezett fizetési mód</label>
                                <input
                                    id="szamlazz_hu_default_payment_method"
                                    type="text"
                                    name="szamlazz_hu_default_payment_method"
                                    maxlength="100"
                                    value="{{ old('szamlazz_hu_default_payment_method', $setting->szamlazz_hu_default_payment_method) }}"
                                    class="form-control @error('szamlazz_hu_default_payment_method') is-invalid @enderror"
                                >
                                @error('szamlazz_hu_default_payment_method')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="szamlazz_hu_due_days">Fizetési határidő napokban</label>
                                <input
                                    id="szamlazz_hu_due_days"
                                    type="number"
                                    name="szamlazz_hu_due_days"
                                    min="0"
                                    max="365"
                                    value="{{ old('szamlazz_hu_due_days', $setting->szamlazz_hu_due_days) }}"
                                    class="form-control @error('szamlazz_hu_due_days') is-invalid @enderror"
                                >
                                @error('szamlazz_hu_due_days')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label" for="szamlazz_hu_invoice_language">Számla nyelve</label>
                                <input
                                    id="szamlazz_hu_invoice_language"
                                    type="text"
                                    name="szamlazz_hu_invoice_language"
                                    maxlength="10"
                                    value="{{ old('szamlazz_hu_invoice_language', $setting->szamlazz_hu_invoice_language) }}"
                                    class="form-control @error('szamlazz_hu_invoice_language') is-invalid @enderror"
                                >
                                @error('szamlazz_hu_invoice_language')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <div class="form-check form-switch mt-lg-4 pt-lg-2">
                                    <input type="hidden" name="szamlazz_hu_e_invoice_enabled" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="szamlazz_hu_e_invoice_enabled"
                                        name="szamlazz_hu_e_invoice_enabled"
                                        value="1"
                                        @checked((bool) old('szamlazz_hu_e_invoice_enabled', $setting->szamlazz_hu_e_invoice_enabled))
                                    >
                                    <label class="form-check-label" for="szamlazz_hu_e_invoice_enabled">E-számla engedélyezése</label>
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-check form-switch mt-lg-4 pt-lg-2">
                                    <input type="hidden" name="szamlazz_hu_test_mode" value="0">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"
                                        id="szamlazz_hu_test_mode"
                                        name="szamlazz_hu_test_mode"
                                        value="1"
                                        @checked((bool) old('szamlazz_hu_test_mode', $setting->szamlazz_hu_test_mode))
                                    >
                                    <label class="form-check-label" for="szamlazz_hu_test_mode">Számlázz.hu teszt mód</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card" id="cib_section">
                    <div class="card-header">
                        <h4 class="card-title">CIB Bank kártyás fizetés beállításai</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label class="form-label" for="cib_terminal_id">Terminálazonosító (POS ID)</label>
                                <input
                                    id="cib_terminal_id"
                                    type="text"
                                    name="cib_terminal_id"
                                    maxlength="100"
                                    value="{{ old('cib_terminal_id', $setting->cib_terminal_id) }}"
                                    class="form-control @error('cib_terminal_id') is-invalid @enderror"
                                >
                                @error('cib_terminal_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">A CIB Banktól a kártyaelfogadási szerződéskötéskor kapott azonosító.</small>
                            </div>

                            <div class="col-lg-4">
                                <label class="form-label" for="cib_secret_key">Titkos kulcs</label>
                                <input
                                    id="cib_secret_key"
                                    type="password"
                                    name="cib_secret_key"
                                    maxlength="2000"
                                    autocomplete="new-password"
                                    class="form-control @error('cib_secret_key') is-invalid @enderror"
                                    placeholder="{{ $setting->hasCibCredentials() ? 'Új kulcs megadása a cseréhez' : 'Kulcs megadása' }}"
                                >
                                @error('cib_secret_key')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="mt-2 d-flex align-items-center gap-3">
                                    @php($cibSecretKeySource = $setting->cibSecretKeySource())
                                    @if ($cibSecretKeySource === 'institution')
                                        <span class="badge bg-light text-dark border">Intézményi CIB kulcs beállítva</span>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="remove_cib_secret_key" name="remove_cib_secret_key" @checked((bool) old('remove_cib_secret_key'))>
                                            <label class="form-check-label" for="remove_cib_secret_key">Kulcs törlése</label>
                                        </div>
                                    @elseif ($cibSecretKeySource === 'global_fallback')
                                        <span class="badge bg-info text-dark border">Globális CIB kulcs aktív (.env fallback)</span>
                                    @else
                                        <span class="badge bg-secondary">Nincs mentett kulcs</span>
                                    @endif
                                </div>
                                <small class="text-muted d-block mt-2">Az üresen hagyott mező nem törli a már elmentett kulcsot.</small>
                            </div>

                            <div class="col-lg-4">
                                <div class="alert alert-info mb-0" role="alert">
                                    A CIB Bank technikai integrációjának véglegesítése (a bank hivatalos dokumentációja alapján) még folyamatban van - a fenti adatok megadhatók és biztonságosan tárolódnak, de a tényleges fizetés-indítás csak ezt követően lesz élesben elérhető.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const invoicingEnabled = document.getElementById('invoicing_enabled');
            const invoicingProvider = document.getElementById('invoicing_provider');
            const invoicingProviderWrap = document.getElementById('invoicing_provider_wrap');
            const billingoSection = document.getElementById('billingo_section');
            const szamlazzSection = document.getElementById('szamlazz_section');

            const cardPaymentEnabled = document.getElementById('card_payment_enabled');
            const cardPaymentProvider = document.getElementById('card_payment_provider');
            const cardPaymentProviderWrap = document.getElementById('card_payment_provider_wrap');
            const cardPaymentTestModeWrap = document.getElementById('card_payment_test_mode_wrap');
            const cibSection = document.getElementById('cib_section');

            const toggleVisibility = (element, shouldShow) => {
                if (!element) {
                    return;
                }

                element.classList.toggle('d-none', !shouldShow);
            };

            const syncInvoicingState = () => {
                const enabled = invoicingEnabled.checked;
                const provider = invoicingProvider.value;

                toggleVisibility(invoicingProviderWrap, enabled);
                toggleVisibility(billingoSection, enabled && provider === 'billingo');
                toggleVisibility(szamlazzSection, enabled && provider === 'szamlazz_hu');
            };

            const syncCardPaymentState = () => {
                const enabled = cardPaymentEnabled.checked;
                const provider = cardPaymentProvider.value;

                toggleVisibility(cardPaymentProviderWrap, enabled);
                toggleVisibility(cardPaymentTestModeWrap, enabled);
                toggleVisibility(cibSection, enabled && provider === 'cib');
            };

            invoicingEnabled.addEventListener('change', syncInvoicingState);
            invoicingProvider.addEventListener('change', syncInvoicingState);
            cardPaymentEnabled.addEventListener('change', syncCardPaymentState);
            cardPaymentProvider.addEventListener('change', syncCardPaymentState);

            syncInvoicingState();
            syncCardPaymentState();

            const blockLookupButton = document.getElementById('billingo_document_blocks_lookup');
            const blockLookupStatus = document.getElementById('billingo_document_blocks_status');
            const blockLookupList = document.getElementById('billingo_document_blocks_list');
            const blockIdInput = document.getElementById('billingo_document_block_id');

            if (blockLookupButton) {
                blockLookupButton.addEventListener('click', function () {
                    blockLookupList.innerHTML = '';
                    blockLookupStatus.textContent = 'Lekérdezés folyamatban…';
                    blockLookupStatus.className = 'small mt-2 text-muted';
                    blockLookupButton.disabled = true;

                    fetch(blockLookupButton.dataset.url, {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                        .then(({ ok, data }) => {
                            if (!ok || !data.success) {
                                blockLookupStatus.textContent = data.message || 'Nem sikerült lekérdezni a bizonylattömböket.';
                                blockLookupStatus.className = 'small mt-2 text-danger';
                                return;
                            }

                            blockLookupStatus.textContent = 'Kattints a listából a beillesztéshez:';
                            blockLookupStatus.className = 'small mt-2 text-muted';

                            data.blocks.forEach((block) => {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action py-1 px-2';
                                const label = [block.name, block.prefix ? `(${block.prefix})` : null, block.type]
                                    .filter(Boolean)
                                    .join(' ');
                                item.textContent = `#${block.id}${label ? ' – ' + label : ''}`;
                                item.addEventListener('click', function () {
                                    blockIdInput.value = block.id;
                                });
                                blockLookupList.appendChild(item);
                            });
                        })
                        .catch(() => {
                            blockLookupStatus.textContent = 'Nem sikerült lekérdezni a bizonylattömböket.';
                            blockLookupStatus.className = 'small mt-2 text-danger';
                        })
                        .finally(() => {
                            blockLookupButton.disabled = false;
                        });
                });
            }
        });
    </script>
@endpush
