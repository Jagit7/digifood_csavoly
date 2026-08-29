@php
    $payment = $payment ?? new \App\Models\InstitutionPayment();
    $paidAtValue = old('paid_at', $payment->paid_at?->format('Y-m-d\TH:i'));
    $selectedChildText = $selectedChild
        ? collect([$selectedChild->name, $selectedChild->group_name, $selectedChild->educational_identifier])->filter()->implode(' – ')
        : null;
    $selectedGuardianId = old('guardian_id', $payment->guardian_id);
    $selectedStatementId = old('monthly_payment_statement_id', $payment->monthly_payment_statement_id);
    $guardianDisabled = !$selectedChild;
    $isCreateMode = ! $payment->exists;

    $statementLabel = static function (?string $childName, string $paymentPeriodLabel, int $remainingAmount, bool $isSettled): string {
        return collect([
            $childName,
            $paymentPeriodLabel,
            $isSettled ? 'Rendezve' : number_format($remainingAmount, 0, ',', ' ').' Ft',
        ])->filter()->implode(' – ');
    };
@endphp

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
        .select2-container--default .select2-results > .select2-results__options {
            max-height: 18rem;
        }
        .df-statement-option-label {
            display: block;
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.35;
        }
        .select2-results__option {
            padding-top: .5rem;
            padding-bottom: .5rem;
        }
        .select2-dropdown {
            z-index: 30;
        }
        @media (min-width: 768px) {
            .df-statement-option-label,
            .select2-container--default .select2-selection--single .select2-selection__rendered {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }
        .df-inline-alert {
            display: none;
        }
    </style>
@endpush

<div class="row">
    <div class="col-xl-6 mb-3">
        <label for="child_id" class="form-label">Gyermek</label>
        <select
            id="child_id"
            name="child_id"
            class="form-control @error('child_id') is-invalid @enderror"
            data-placeholder="Kezdj el gépelni legalább 2 karakterrel"
            required
        >
            <option value=""></option>
            @if($selectedChild)
                <option value="{{ $selectedChild->id }}" selected>{{ $selectedChildText }}</option>
            @endif
        </select>
        <div class="form-text">Keresés név, osztály vagy azonosító alapján. Egyszerre legfeljebb 20 találat jelenik meg.</div>
        @error('child_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-xl-6 mb-3">
        <label for="guardian_id" class="form-label">Szülő / gondviselő</label>
        <select
            id="guardian_id"
            name="guardian_id"
            class="form-control @error('guardian_id') is-invalid @enderror"
            @disabled($guardianDisabled)
        >
            <option value="">{{ $guardianDisabled ? 'Először válassz gyermeket' : 'Nincs megadva' }}</option>
            @foreach($selectedGuardians as $guardian)
                <option value="{{ $guardian->id }}" @selected((string) $selectedGuardianId === (string) $guardian->id)>{{ $guardian->full_name }}</option>
            @endforeach
        </select>
        <div id="guardian-help" class="form-text">Csak a kiválasztott gyermekhez kapcsolt gondviselők választhatók.</div>
        <div id="guardian-warning" class="alert alert-warning py-2 px-3 mt-2 mb-0 df-inline-alert"></div>
        @error('guardian_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-xl-6 mb-3">
        <label for="monthly_payment_statement_id" class="form-label">Kapcsolódó havi kötelezettség</label>
        <select id="monthly_payment_statement_id" name="monthly_payment_statement_id" class="form-control @error('monthly_payment_statement_id') is-invalid @enderror">
            <option value="">Nincs kapcsolva</option>
            @foreach($selectedStatements as $statement)
                @php
                    $periods = app(\App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper::class)->fromStatement($statement);
                    $completedPaymentsTotal = (int) ($statement->completed_payments_total ?? 0);
                    $totalPayable = (int) $statement->total_payable;
                    $remainingAmount = max(0, $totalPayable - $completedPaymentsTotal);
                    $isSettled = $remainingAmount <= 0;
                    $statementText = $statementLabel(
                        $selectedChild?->name,
                        $periods['payment_period_label'],
                        $remainingAmount,
                        $isSettled
                    );
                @endphp
                <option
                    value="{{ $statement->id }}"
                    data-child-name="{{ e($selectedChild?->name) }}"
                    data-payment-period-label="{{ e($periods['payment_period_label']) }}"
                    data-meal-period-label="{{ e($periods['meal_period_label']) }}"
                    data-credit-period-label="{{ e($periods['credit_period_label']) }}"
                    data-total-payable="{{ $totalPayable }}"
                    data-completed-payments-total="{{ $completedPaymentsTotal }}"
                    data-remaining-amount="{{ $remainingAmount }}"
                    data-is-settled="{{ $isSettled ? '1' : '0' }}"
                    @selected((string) $selectedStatementId === (string) $statement->id)
                    @disabled($isSettled && (string) $selectedStatementId !== (string) $statement->id)
                >
                    {{ $statementText }}
                </option>
            @endforeach
        </select>
        <div class="form-text">A kapcsolódó statement mindig a fizetési hónaphoz tartozik, a napi tételei is ugyanennek a hónapnak az étkezési napjait fedik le.</div>
        <div id="statement-warning" class="alert alert-warning py-2 px-3 mt-2 mb-0 df-inline-alert"></div>
        @error('monthly_payment_statement_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    @if($splitManualTransferEnabled)
        <div class="col-xl-6 mb-3">
            <label for="payment_component" class="form-label">Befizetés komponense</label>
            <select id="payment_component" name="payment_component" class="form-control @error('payment_component') is-invalid @enderror" required>
                <option value="">Válassz számlát</option>
                @foreach($paymentComponentOptions as $value => $label)
                    @continue($value === \App\Support\Finance\PaymentComponent::LEGACY)
                    <option value="{{ $value }}" @selected(old('payment_component', $payment->payment_component) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="form-text">A befizetést mindig ahhoz a számlához rögzítsd, amelyre ténylegesen megérkezett.</div>
            @error('payment_component')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="col-xl-6 mb-3">
        <label for="amount" class="form-label">Összeg (Ft)</label>
        <input
            id="amount"
            type="number"
            name="amount"
            min="1"
            step="1"
            value="{{ old('amount', $payment->amount) }}"
            class="form-control @error('amount') is-invalid @enderror"
            required
        >
        <div id="statement-summary" class="alert alert-info mt-2 mb-0 d-none">
            <div class="fw-semibold">Kiválasztott kötelezettség</div>
            <div id="statement-summary-text"></div>
        </div>
        @error('amount')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-xl-6 mb-3">
        <label for="invoice_number" class="form-label">Kapcsolódó számla azonosítója</label>
        <input
            id="invoice_number"
            type="text"
            name="invoice_number"
            value="{{ old('invoice_number', $payment->invoice_number) }}"
            class="form-control @error('invoice_number') is-invalid @enderror"
            maxlength="100"
            placeholder="Például: INV-2026-001"
        >
        <div class="form-text">Ha nincs külön számla rekord, itt rögzíthető a számlaszám vagy azonosító.</div>
        @error('invoice_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-xl-4 mb-3">
        <label for="paid_at" class="form-label">Befizetés dátuma</label>
        <input
            id="paid_at"
            type="datetime-local"
            name="paid_at"
            value="{{ $paidAtValue }}"
            class="form-control @error('paid_at') is-invalid @enderror"
            required
        >
        @error('paid_at')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-xl-4 mb-3">
        <label for="reference" class="form-label">Befizetés azonosítója / referencia</label>
        <input
            id="reference"
            type="text"
            name="reference"
            value="{{ old('reference', $payment->reference) }}"
            class="form-control @error('reference') is-invalid @enderror"
            maxlength="100"
            placeholder="Pl. banki tranzakcióazonosító vagy pénztári bizonylatszám"
        >
        <div class="form-text">Opcionális. Banki tranzakcióazonosító, pénztári bizonylatszám vagy más belső referencia adható meg.</div>
        @error('reference')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-xl-6 mb-3">
        <label for="payment_method" class="form-label">Fizetési mód</label>
        <select id="payment_method" name="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
            <option value="">Válassz fizetési módot</option>
            @foreach($paymentMethodOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('payment_method', $payment->payment_method) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('payment_method')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-xl-6 mb-3">
        <label for="status" class="form-label">Státusz</label>
        <select id="status" name="status" class="form-control @error('status') is-invalid @enderror" required>
            <option value="">Válassz státuszt</option>
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $payment->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mb-0">
        <label for="note" class="form-label">Megjegyzés</label>
        <textarea
            id="note"
            name="note"
            rows="4"
            class="form-control @error('note') is-invalid @enderror"
            placeholder="Belső megjegyzés, egyeztetés vagy kiegészítő információ"
        >{{ old('note', $payment->note) }}</textarea>
        @error('note')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

@push('scripts')
    <script src="{{ asset('dashboard/vendor/select2/js/select2.full.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery || !jQuery.fn.select2) {
                return;
            }

            const $child = jQuery('#child_id');
            const $guardian = jQuery('#guardian_id');
            const $statement = jQuery('#monthly_payment_statement_id');
            const amountField = document.getElementById('amount');
            const guardianWarning = document.getElementById('guardian-warning');
            const statementWarning = document.getElementById('statement-warning');
            const statementSummary = document.getElementById('statement-summary');
            const statementSummaryText = document.getElementById('statement-summary-text');
            const guardiansUrlTemplate = @json(route('dashboard.institution.finance.payments.children.guardians', ['child' => '__CHILD__']));
            const statementsUrlTemplate = @json(route('dashboard.institution.finance.payments.children.statements', ['child' => '__CHILD__']));
            const isCreateMode = @json($isCreateMode);

            // A Select2 alapértelmezett szövegei (pl. "No results found") angolul
            // jelennek meg, ha nincs külön nyelvi csomag betöltve - mivel a
            // digifood felülete végig magyar, ezt a néhány üzenetet inline
            // felülírjuk ahelyett, hogy egy teljes (és a projektben eddig nem
            // használt) Select2 locale fájlt kellene bevezetni.
            const SELECT2_HU_MESSAGES = {
                errorLoading: function () {
                    return 'Hiba történt az adatok betöltése közben.';
                },
                inputTooShort: function (args) {
                    const remaining = args.minimum - args.input.length;
                    return 'Írj be még legalább ' + remaining + ' karaktert.';
                },
                loadingMore: function () {
                    return 'További találatok betöltése…';
                },
                noResults: function () {
                    return 'Nincs találat.';
                },
                searching: function () {
                    return 'Keresés…';
                }
            };

            function replaceChildPlaceholder(url, childId) {
                return url.replace('__CHILD__', childId);
            }

            function resetGuardian(message, disabled = true) {
                $guardian.empty().append(new Option(disabled ? 'Először válassz gyermeket' : 'Nincs megadva', ''));
                $guardian.prop('disabled', disabled).trigger('change.select2');

                if (message) {
                    guardianWarning.textContent = message;
                    guardianWarning.style.display = 'block';
                } else {
                    guardianWarning.textContent = '';
                    guardianWarning.style.display = 'none';
                }
            }

            function resetStatements(message = null) {
                $statement.empty().append(new Option('Nincs kapcsolva', ''));
                $statement.trigger('change.select2');
                updateStatementSummary(null);

                if (isCreateMode && amountField) {
                    amountField.value = '';
                }

                if (message) {
                    statementWarning.textContent = message;
                    statementWarning.style.display = 'block';
                } else {
                    statementWarning.textContent = '';
                    statementWarning.style.display = 'none';
                }
            }

            function fillGuardians(payload, preferredGuardianId = null) {
                resetGuardian(null, false);

                if (!payload.results || !payload.results.length) {
                    resetGuardian(payload.message || 'A kiválasztott gyermekhez nincs kapcsolt gondviselő.', true);
                    return;
                }

                payload.results.forEach(function (item) {
                    const option = new Option(item.text, item.id, false, false);
                    $guardian.append(option);
                });

                let selectedId = preferredGuardianId || payload.auto_select_id || '';

                if (selectedId) {
                    $guardian.val(String(selectedId));
                }

                $guardian.trigger('change.select2');
            }

            function fillStatements(payload, preferredStatementId = null) {
                resetStatements();

                if (!payload.results || !payload.results.length) {
                    // Nem hiba - a gyermekhez egyszerűen még nincs kiszámolt havi
                    // kötelezettség (statement). A befizetés enélkül is rögzíthető,
                    // csak nem lesz egyetlen konkrét hónaphoz sem kapcsolva.
                    resetStatements('Ehhez a gyermekhez jelenleg nincs rögzített havi kötelezettség - a befizetés kötelezettség nélkül is rögzíthető.');
                    return;
                }

                payload.results.forEach(function (item) {
                    const option = new Option(item.text, item.id, false, false);
                    option.disabled = !!item.disabled;
                    option.dataset.childName = item.child_name || '';
                    option.dataset.paymentPeriodLabel = item.payment_period_label || '';
                    option.dataset.mealPeriodLabel = item.meal_period_label || '';
                    option.dataset.creditPeriodLabel = item.credit_period_label || '';
                    option.dataset.totalPayable = item.total_payable ?? '';
                    option.dataset.completedPaymentsTotal = item.completed_payments_total ?? '';
                    option.dataset.remainingAmount = item.remaining_amount ?? '';
                    option.dataset.isSettled = item.is_settled ? '1' : '0';
                    $statement.append(option);
                });

                if (preferredStatementId) {
                    $statement.val(String(preferredStatementId));
                }

                $statement.trigger('change.select2');
            }

            function formatForint(amount) {
                const numeric = Number(amount || 0);

                return new Intl.NumberFormat('hu-HU').format(numeric) + ' Ft';
            }

            function buildStatementLabel(statement) {
                if (!statement) {
                    return '';
                }

                const amountLabel = statement.is_settled ? 'Rendezve' : formatForint(statement.remaining_amount);

                return [statement.child_name, statement.payment_period_label, amountLabel]
                    .filter(Boolean)
                    .join(' – ');
            }

            function statementDataFromOption(option) {
                if (!option) {
                    return null;
                }

                return {
                    id: option.value,
                    child_name: option.dataset.childName || '',
                    payment_period_label: option.dataset.paymentPeriodLabel || '',
                    meal_period_label: option.dataset.mealPeriodLabel || '',
                    credit_period_label: option.dataset.creditPeriodLabel || '',
                    total_payable: Number(option.dataset.totalPayable || 0),
                    completed_payments_total: Number(option.dataset.completedPaymentsTotal || 0),
                    remaining_amount: Number(option.dataset.remainingAmount || 0),
                    is_settled: option.dataset.isSettled === '1',
                };
            }

            function statementDataFromSelect2Item(item) {
                if (!item) {
                    return null;
                }

                const optionData = item.element ? statementDataFromOption(item.element) : null;

                return {
                    id: item.id ?? optionData?.id ?? '',
                    child_name: item.child_name ?? optionData?.child_name ?? '',
                    payment_period_label: item.payment_period_label ?? optionData?.payment_period_label ?? '',
                    meal_period_label: item.meal_period_label ?? optionData?.meal_period_label ?? '',
                    credit_period_label: item.credit_period_label ?? optionData?.credit_period_label ?? '',
                    total_payable: Number(item.total_payable ?? optionData?.total_payable ?? 0),
                    completed_payments_total: Number(item.completed_payments_total ?? optionData?.completed_payments_total ?? 0),
                    remaining_amount: Number(item.remaining_amount ?? optionData?.remaining_amount ?? 0),
                    is_settled: Boolean(item.is_settled ?? optionData?.is_settled ?? false),
                    text: item.text ?? optionData?.text ?? '',
                };
            }

            function updateStatementSummary(statement) {
                if (!statement) {
                    statementSummary.classList.add('d-none');
                    statementSummaryText.textContent = '';
                    return;
                }

                statementSummary.classList.remove('d-none');
                statementSummaryText.innerHTML =
                    '<div class="small text-muted mb-1">'
                    + buildStatementLabel(statement)
                    + '</div>'
                    + '<div class="d-flex flex-wrap gap-3 small mb-1">'
                    + '<span>Eredeti kötelezettség: <strong>' + formatForint(statement.total_payable) + '</strong></span>'
                    + '<span>Eddig befizetve: <strong>' + formatForint(statement.completed_payments_total) + '</strong></span>'
                    + '</div>'
                    + '<div class="fs-5 fw-bold ' + (statement.remaining_amount > 0 ? 'text-danger' : 'text-success') + '">'
                    + 'Még fizetendő: ' + formatForint(statement.remaining_amount)
                    + '</div>';
            }

            function applyStatementSelection() {
                const selectedOption = $statement.find(':selected').get(0);
                const statement = statementDataFromOption(selectedOption);

                if (!statement || !selectedOption || !selectedOption.value) {
                    updateStatementSummary(null);

                    if (isCreateMode && amountField) {
                        amountField.value = '';
                    }

                    return;
                }

                updateStatementSummary(statement);

                if (isCreateMode && amountField) {
                    amountField.value = statement.remaining_amount > 0 ? String(statement.remaining_amount) : '';
                }
            }

            function renderStatementOption(item) {
                if (!item.id) {
                    return item.text;
                }

                const statement = statementDataFromSelect2Item(item);

                return jQuery(
                    '<div class="py-1">'
                    + '<span class="df-statement-option-label">' + buildStatementLabel(statement) + '</span>'
                    + '</div>'
                );
            }

            function loadChildRelatedData(childId, preferredGuardianId = null, preferredStatementId = null) {
                if (!childId) {
                    resetGuardian(null, true);
                    resetStatements();
                    return;
                }

                jQuery.getJSON(replaceChildPlaceholder(guardiansUrlTemplate, childId), function (payload) {
                    fillGuardians(payload, preferredGuardianId);
                }).fail(function () {
                    resetGuardian('Hiba történt a gondviselők betöltése közben. Próbáld újra, vagy frissítsd az oldalt.', true);
                });

                jQuery.getJSON(replaceChildPlaceholder(statementsUrlTemplate, childId), function (payload) {
                    fillStatements(payload, preferredStatementId);
                }).fail(function () {
                    resetStatements('Hiba történt a havi kötelezettségek betöltése közben. Próbáld újra, vagy frissítsd az oldalt.');
                });
            }

            $child.select2({
                theme: 'default',
                width: '100%',
                allowClear: true,
                placeholder: $child.data('placeholder'),
                minimumInputLength: 2,
                language: SELECT2_HU_MESSAGES,
                ajax: {
                    url: @json(route('dashboard.institution.finance.payments.children.search')),
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

            $guardian.select2({
                theme: 'default',
                width: '100%',
                allowClear: true,
                placeholder: 'Nincs megadva',
                language: SELECT2_HU_MESSAGES
            });

            $statement.select2({
                theme: 'default',
                width: '100%',
                allowClear: true,
                placeholder: 'Nincs kapcsolva',
                language: SELECT2_HU_MESSAGES,
                templateResult: renderStatementOption,
                templateSelection: function (item) {
                    if (!item.id) {
                        return item.text;
                    }

                    const statement = statementDataFromSelect2Item(item);

                    if (!statement) {
                        return item.text;
                    }

                    return buildStatementLabel(statement);
                },
                escapeMarkup: function (markup) {
                    return markup;
                }
            });

            $child.on('select2:select', function (event) {
                const childId = event.params.data.id;
                loadChildRelatedData(childId);
            });

            $child.on('select2:clear', function () {
                loadChildRelatedData(null);
            });

            if ($child.val()) {
                loadChildRelatedData($child.val(), @json($selectedGuardianId), @json($selectedStatementId));
            } else {
                resetGuardian(null, true);
                resetStatements();
            }

            $statement.on('change', applyStatementSelection);

            if ($statement.val()) {
                applyStatementSelection();
            }
        });
    </script>
@endpush
