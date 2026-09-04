@extends('layouts.superadmin')

@section('title', 'Új számla')

@push('styles')
    <link href="{{ asset('dashboard/vendor/select2/css/select2.min.css') }}" rel="stylesheet">
    <style>
        .select2-container .select2-selection--single {
            height: calc(3.5rem + 2px);
            padding: .85rem .75rem;
            border: 1px solid #e6ecf3;
            border-radius: .75rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5rem;
            padding-left: 0;
            color: #212529;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(3.5rem + 2px);
            right: .75rem;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Új számla',
        'subtitle' => $sourcePayment
            ? 'Készpénzes befizetésből előkészített számla létrehozása.'
            : 'Kötelezettség alapú számla vagy számla-előkészítés létrehozása.',
        'buttons' => [
            [
                'url' => route('dashboard.institution.finance.invoices'),
                'text' => 'Vissza a listához',
                'icon' => 'fa-solid fa-arrow-left',
                'class' => 'btn btn-light',
            ],
        ],
    ])

    @if(!$setting->invoicing_enabled)
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <div>A számlázás nincs engedélyezve. Új számla addig nem hozható létre.</div>
            <a href="{{ route('dashboard.institution.settings.invoicing.edit') }}" class="btn btn-sm btn-outline-dark">Beállítások megnyitása</a>
        </div>
    @endif

    @if($sourcePayment && $preview)
        <div class="alert alert-info">
            <div class="fw-semibold mb-1">A számla egy meglévő készpénzes befizetésből lett előkészítve</div>
            <div>
                Befizetés dátuma: {{ $preview['statement']['source_payment_paid_at'] ?: 'Nem elérhető' }},
                hivatkozás: {{ $preview['statement']['source_payment_reference'] ?: 'Nincs megadva' }},
                összeg: {{ isset($preview['statement']['source_payment_amount']) ? number_format($preview['statement']['source_payment_amount'], 0, ',', ' ') . ' Ft' : 'Nem elérhető' }}.
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('dashboard.institution.finance.invoices.store') }}">
        @csrf
        @if($sourcePayment)
            <input type="hidden" name="source_payment_id" value="{{ $sourcePayment->id }}">
        @endif

        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Kapcsolódó fizetési kötelezettség</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-6 mb-3">
                        <label class="form-label">Szolgáltató</label>
                        <input type="text" class="form-control" value="{{ $providerOptions[$selectedProvider] ?? $selectedProvider }}" readonly>
                        <div class="form-text">
                            A számlázási szolgáltatót az intézmény
                            <a href="{{ route('dashboard.institution.settings.invoicing.edit') }}">számlázási beállításai</a>
                            határozzák meg, itt nem módosítható.
                        </div>
                        <input type="hidden" id="provider" name="provider" value="{{ $selectedProvider }}">
                        @error('provider')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-xl-6 mb-3">
                        @if($sourcePayment && $preview)
                            <label class="form-label">Fizetési kötelezettség</label>
                            <input
                                type="text"
                                class="form-control"
                                value="{{ $preview['statement']['child_name'] }} – {{ $preview['statement']['payment_period_label'] }}i fizetési hónap – Étkezés: {{ $preview['statement']['meal_period_label'] }} – Jóváírás: {{ $preview['statement']['credit_period_label'] }} – {{ number_format($preview['statement']['gross_amount'], 0, ',', ' ') }} Ft"
                                readonly
                            >
                            <input type="hidden" name="monthly_payment_statement_id" value="{{ $preview['statement']['id'] }}">
                            <div class="form-text">A készpénzes befizetéshez kapcsolódó kötelezettség rögzítve van, itt nem módosítható.</div>
                        @else
                            <label for="monthly_payment_statement_id" class="form-label">Fizetési kötelezettség</label>
                            <select
                                id="monthly_payment_statement_id"
                                name="monthly_payment_statement_id"
                                class="form-control @error('monthly_payment_statement_id') is-invalid @enderror"
                                data-placeholder="Kezdj el gépelni legalább 2 karakterrel"
                                required
                            >
                                <option value=""></option>
                                @if($preview)
                                    <option value="{{ $preview['statement']['id'] }}" selected>
                                        {{ $preview['statement']['child_name'] }} – {{ $preview['statement']['payment_period_label'] }}i fizetési hónap – Étkezés: {{ $preview['statement']['meal_period_label'] }} – Jóváírás: {{ $preview['statement']['credit_period_label'] }} – {{ number_format($preview['statement']['gross_amount'], 0, ',', ' ') }} Ft
                                    </option>
                                @endif
                            </select>
                            <div class="form-text">Keresés gyermeknév, fizetési hónap vagy belső azonosító alapján. Egyszerre legfeljebb 20 találat jelenik meg.</div>
                            @error('monthly_payment_statement_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>
                </div>

                <div id="invoice-preview-errors" class="alert alert-warning mb-0 {{ $preview && !empty($preview['errors']) ? '' : 'd-none' }}">
                    @if($preview && !empty($preview['errors']))
                        <div>{{ implode(' ', $preview['errors']) }}</div>
                        <a href="{{ $preview['settings_url'] }}" class="btn btn-sm btn-outline-dark mt-2">Számlázási beállítások</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Számla adatai</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Gyermek</label>
                        <input id="preview_child_name" type="text" class="form-control" value="{{ old('preview_child_name', $preview['statement']['child_name'] ?? '') }}" readonly>
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Gondviselő</label>
                        <input id="preview_guardian_name" type="text" class="form-control" value="{{ old('preview_guardian_name', $preview['statement']['guardian_name'] ?? '') }}" readonly>
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Fizetési hónap</label>
                        <input id="preview_month_label" type="text" class="form-control" value="{{ old('preview_month_label', $preview['statement']['month_label'] ?? '') }}" readonly>
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Étkezési időszak</label>
                        <input id="preview_meal_period_label" type="text" class="form-control" value="{{ $preview['statement']['meal_period_label'] ?? '' }}" readonly>
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Jóváírási időszak</label>
                        <input id="preview_credit_period_label" type="text" class="form-control" value="{{ $preview['statement']['credit_period_label'] ?? '' }}" readonly>
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Bruttó összeg</label>
                        <input id="preview_gross_amount" type="text" class="form-control" value="{{ isset($preview['statement']['gross_amount']) ? number_format($preview['statement']['gross_amount'], 0, ',', ' ') . ' Ft' : '' }}" readonly>
                        <div class="form-text">Ez kizárólag az AKTUÁLIS havi, ténylegesen számlázható összeg - korábbi tartozás/túlfizetés nem kerül bele.</div>
                    </div>
                    @php
                        $previewPreviousBalance = (int) ($preview['statement']['previous_balance'] ?? 0);
                    @endphp
                    <div id="preview_previous_balance_wrapper" class="col-xl-4 mb-3 {{ $previewPreviousBalance === 0 ? 'd-none' : '' }}">
                        <label class="form-label" id="preview_previous_balance_label">{{ $preview['statement']['previous_balance_label'] ?? 'Korábbi egyenleg' }}</label>
                        <input id="preview_previous_balance" type="text" class="form-control bg-light" value="{{ $previewPreviousBalance !== 0 ? number_format($preview['statement']['previous_balance_display_amount'] ?? 0, 0, ',', ' ') . ' Ft' : '' }}" readonly>
                        <div class="form-text text-warning">Csak tájékoztató adat - NEM kerül a most kiállított számlára. Kérjük, korábbi tartozását vagy túlfizetését személyesen rendezze az önkormányzatnál.</div>
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Kiállítás dátuma</label>
                        <input id="preview_issue_date" type="text" class="form-control" value="{{ old('preview_issue_date', $preview['statement']['issue_date'] ?? '') }}" readonly>
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Teljesítés dátuma</label>
                        <input id="preview_fulfillment_date" type="text" class="form-control" value="{{ old('preview_fulfillment_date', $preview['statement']['fulfillment_date'] ?? '') }}" readonly>
                        <input id="fulfillment_date" type="hidden" name="fulfillment_date" value="{{ old('fulfillment_date', $preview['statement']['fulfillment_date'] ?? '') }}">
                        @error('fulfillment_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label for="due_date" class="form-label">Fizetési határidő</label>
                        @if($sourcePayment)
                            <input id="due_date_display" type="text" class="form-control" value="{{ old('due_date', $preview['statement']['due_date'] ?? '') }}" readonly>
                            <input id="due_date" type="hidden" name="due_date" value="{{ old('due_date', $preview['statement']['due_date'] ?? '') }}">
                        @else
                            <input id="due_date" type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date', $preview['statement']['due_date'] ?? '') }}" required>
                        @endif
                        @error('due_date')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label for="payment_method" class="form-label">Fizetési mód</label>
                        @if($sourcePayment)
                            <input type="text" class="form-control" value="{{ $paymentMethodOptions[$preview['statement']['payment_method']] ?? 'Készpénz' }}" readonly>
                            <input type="hidden" id="payment_method" name="payment_method" value="{{ old('payment_method', $preview['statement']['payment_method'] ?? '') }}">
                        @else
                            <select id="payment_method" name="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                                <option value="">Válassz fizetési módot</option>
                                @foreach($paymentMethodOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', $preview['statement']['payment_method'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('payment_method')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label class="form-label">Pénznem</label>
                        <input id="preview_currency" type="text" class="form-control" value="{{ old('preview_currency', $preview['statement']['currency'] ?? 'HUF') }}" readonly>
                    </div>
                    <div class="col-xl-8 mb-3">
                        <label class="form-label">Számlatétel megnevezése</label>
                        <input id="preview_item_name" type="text" class="form-control" value="{{ old('preview_item_name', $preview['statement']['item_name'] ?? 'Étkezési térítési díj') }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Vevő számlázási adatai</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-6 mb-3">
                        <label for="customer_name" class="form-label">Vevő neve</label>
                        <input id="customer_name" type="text" name="customer_name" class="form-control @error('customer_name') is-invalid @enderror" value="{{ old('customer_name', $preview['statement']['customer_name'] ?? '') }}" maxlength="191" required>
                        @error('customer_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-6 mb-3">
                        <label for="customer_email" class="form-label">E-mail</label>
                        <input id="customer_email" type="email" name="customer_email" class="form-control @error('customer_email') is-invalid @enderror" value="{{ old('customer_email', $preview['statement']['customer_email'] ?? '') }}" maxlength="191">
                        @error('customer_email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label for="customer_tax_number" class="form-label">Adószám</label>
                        <input id="customer_tax_number" type="text" name="customer_tax_number" class="form-control @error('customer_tax_number') is-invalid @enderror" value="{{ old('customer_tax_number', $preview['statement']['customer_tax_number'] ?? '') }}" maxlength="50">
                        @error('customer_tax_number')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label for="billing_postcode" class="form-label">Irányítószám</label>
                        <input id="billing_postcode" type="text" name="billing_postcode" class="form-control @error('billing_postcode') is-invalid @enderror" value="{{ old('billing_postcode', $preview['statement']['billing_postcode'] ?? '') }}" maxlength="20" required>
                        @error('billing_postcode')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-4 mb-3">
                        <label for="billing_city" class="form-label">Város</label>
                        <input id="billing_city" type="text" name="billing_city" class="form-control @error('billing_city') is-invalid @enderror" value="{{ old('billing_city', $preview['statement']['billing_city'] ?? '') }}" maxlength="100" required>
                        @error('billing_city')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 mb-3">
                        <label for="billing_address" class="form-label">Cím</label>
                        <input id="billing_address" type="text" name="billing_address" class="form-control @error('billing_address') is-invalid @enderror" value="{{ old('billing_address', $preview['statement']['billing_address'] ?? '') }}" maxlength="191" required>
                        @error('billing_address')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 mb-0">
                        <label for="note" class="form-label">Megjegyzés</label>
                        <textarea id="note" name="note" rows="4" class="form-control @error('note') is-invalid @enderror" placeholder="Belső megjegyzés vagy szolgáltatói előkészítés">{{ old('note') }}</textarea>
                        @error('note')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <button id="submit-button" type="submit" class="btn btn-primary" @disabled(!$setting->invoicing_enabled || ($preview && !($preview['available'] ?? false)))>
            Számla létrehozása
        </button>
    </form>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('dashboard/vendor/select2/js/select2.full.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery || !jQuery.fn.select2) {
                return;
            }

            const $statement = jQuery('#monthly_payment_statement_id');
            const submitButton = document.getElementById('submit-button');
            const errorBox = document.getElementById('invoice-preview-errors');
            const previewUrlTemplate = @json(route('dashboard.institution.finance.invoices.statements.preview', ['statement' => '__STATEMENT__']));
            const settingsUrl = @json(route('dashboard.institution.settings.invoicing.edit'));
            const sourcePaymentLocked = @json((bool) $sourcePayment);

            function previewUrl(statementId) {
                return previewUrlTemplate.replace('__STATEMENT__', statementId);
            }

            function setValue(id, value) {
                const field = document.getElementById(id);
                if (field) {
                    field.value = value || '';
                }
            }

            function formatAmount(amount) {
                if (amount === null || amount === undefined || amount === '') {
                    return '';
                }

                return new Intl.NumberFormat('hu-HU').format(amount) + ' Ft';
            }

            function setErrors(errors) {
                if (!errors || !errors.length) {
                    errorBox.classList.add('d-none');
                    errorBox.innerHTML = '';
                    return;
                }

                errorBox.classList.remove('d-none');
                errorBox.innerHTML = '<div>' + errors.join(' ') + '</div><a href="' + settingsUrl + '" class="btn btn-sm btn-outline-dark mt-2">Számlázási beállítások</a>';
            }

            function applyPreview(payload) {
                const statement = payload.statement || {};

                setValue('preview_child_name', statement.child_name);
                setValue('preview_guardian_name', statement.guardian_name);
                setValue('preview_month_label', statement.month_label);
                setValue('preview_meal_period_label', statement.meal_period_label);
                setValue('preview_credit_period_label', statement.credit_period_label);
                setValue('preview_gross_amount', formatAmount(statement.gross_amount));

                const previousBalance = Number(statement.previous_balance || 0);
                const previousBalanceWrapper = document.getElementById('preview_previous_balance_wrapper');
                const previousBalanceLabel = document.getElementById('preview_previous_balance_label');
                if (previousBalanceWrapper) {
                    if (previousBalance !== 0) {
                        previousBalanceWrapper.classList.remove('d-none');
                        if (previousBalanceLabel) {
                            previousBalanceLabel.textContent = statement.previous_balance_label || 'Korábbi egyenleg';
                        }
                        setValue('preview_previous_balance', formatAmount(statement.previous_balance_display_amount ?? Math.abs(previousBalance)));
                    } else {
                        previousBalanceWrapper.classList.add('d-none');
                        setValue('preview_previous_balance', '');
                    }
                }

                setValue('preview_issue_date', statement.issue_date);
                setValue('preview_fulfillment_date', statement.fulfillment_date);
                setValue('fulfillment_date', statement.fulfillment_date);
                setValue('due_date', statement.due_date);
                setValue('due_date_display', statement.due_date);
                setValue('customer_name', statement.customer_name);
                setValue('customer_email', statement.customer_email);
                setValue('customer_tax_number', statement.customer_tax_number);
                setValue('billing_postcode', statement.billing_postcode);
                setValue('billing_city', statement.billing_city);
                setValue('billing_address', statement.billing_address);
                setValue('preview_currency', statement.currency || 'HUF');
                setValue('preview_item_name', statement.item_name || 'Étkezési térítési díj');

                if (statement.payment_method && !sourcePaymentLocked) {
                    jQuery('#payment_method').val(statement.payment_method);
                }

                setErrors(payload.errors || []);
                submitButton.disabled = !payload.available;
            }

            function resetPreview() {
                ['preview_child_name', 'preview_guardian_name', 'preview_month_label', 'preview_meal_period_label', 'preview_credit_period_label', 'preview_gross_amount', 'preview_previous_balance', 'preview_issue_date', 'preview_fulfillment_date', 'fulfillment_date', 'due_date', 'due_date_display', 'customer_name', 'customer_email', 'customer_tax_number', 'billing_postcode', 'billing_city', 'billing_address', 'preview_currency', 'preview_item_name']
                    .forEach(function (id) { setValue(id, ''); });
                const previousBalanceWrapper = document.getElementById('preview_previous_balance_wrapper');
                if (previousBalanceWrapper) {
                    previousBalanceWrapper.classList.add('d-none');
                }
                setErrors([]);
                submitButton.disabled = true;
            }

            function loadPreview() {
                const statementId = $statement.val();

                if (!statementId) {
                    resetPreview();
                    return;
                }

                jQuery.getJSON(previewUrl(statementId))
                    .done(function (payload) {
                        applyPreview(payload);
                    })
                    .fail(function () {
                        setErrors(['Hiba történt az adatok betöltése közben. Próbáld újra, vagy válassz másik kötelezettséget.']);
                        submitButton.disabled = true;
                    });
            }

            if (!sourcePaymentLocked) {
                $statement.select2({
                    theme: 'default',
                    width: '100%',
                    allowClear: true,
                    placeholder: $statement.data('placeholder'),
                    minimumInputLength: 2,
                    ajax: {
                        url: @json(route('dashboard.institution.finance.invoices.statements.search')),
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    }
                });

                $statement.on('select2:select', loadPreview);
                $statement.on('select2:clear', resetPreview);

                if ($statement.val()) {
                    loadPreview();
                }
            }
        });
    </script>
@endpush
