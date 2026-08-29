@extends('layouts.superadmin')

@section('title', 'Számla részletei')

@section('content')
<div class="container-fluid">
    @php
        $statement = $invoice->monthlyPaymentStatement;
        $periods = $statement ? app(\App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper::class)->fromStatement($statement) : null;
        $statusMeta = $invoice->effective_status_meta;
        $providerMeta = $invoice->provider_meta;
        $paymentTotal = $payments->where('status', \App\Models\InstitutionPayment::STATUS_COMPLETED)->sum('amount');
        $cancellationInvoice = $invoice->isCancellationDocument() ? $invoice : $invoice->cancellationInvoice;
        $originalInvoice = $invoice->isCancellationDocument() ? $invoice->originalInvoice : $invoice;
        // A PDF a privát storage lemezen van (ld. BillingoInvoiceProvider::downloadPdf) -
        // szándékosan nem a publikus /storage/... URL-en, mert a bizonylat személyes
        // adatokat (név, cím, adószám) tartalmaz. Ezért a helyi PDF-hez a jogosultság-
        // ellenőrzött letöltő route-ot adjuk, nem Storage::url()-t (ami 404-et adna).
        $invoiceViewUrl = $invoice->invoice_url
            ?: ($hasLocalInvoicePdf ? route('dashboard.institution.finance.invoices.download', $invoice) : null);
        $canReloadInvoicePdf = $invoice->provider === \App\Models\InstitutionInvoice::PROVIDER_BILLINGO
            && in_array($invoice->status, [\App\Models\InstitutionInvoice::STATUS_ISSUED, \App\Models\InstitutionInvoice::STATUS_VOIDED], true)
            && filled($invoice->provider_invoice_id)
            && ! $hasLocalInvoicePdf;
        $canReloadCancellationPdf = $cancellationInvoice
            && $cancellationInvoice->provider === \App\Models\InstitutionInvoice::PROVIDER_BILLINGO
            && filled($cancellationInvoice->provider_invoice_id)
            && ! $hasLocalCancellationPdf;
    @endphp

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Számla részletei',
        'subtitle' => ($invoice->invoice_number ?: 'Még nincs számlaszám') . ' – ' . ($invoice->child?->name ?? 'Ismeretlen gyermek'),
        'buttons' => [
            [
                'url' => route('dashboard.institution.payment-obligations.show', $statement),
                'text' => 'Fizetési kötelezettség',
                'icon' => 'fa-solid fa-file-lines',
                'class' => 'btn btn-light',
            ],
            [
                'url' => route('dashboard.institution.finance.invoices'),
                'text' => 'Vissza a listához',
                'icon' => 'fa-solid fa-arrow-left',
                'class' => 'btn btn-light',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Bruttó összeg',
            'value' => number_format($invoice->gross_amount, 0, ',', ' ') . ' Ft',
            'subtitle' => 'A kötelezettség alapján számolt számlaösszeg',
            'icon' => 'fa-solid fa-wallet',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Completed befizetések',
            'value' => number_format($paymentTotal, 0, ',', ' ') . ' Ft',
            'subtitle' => 'Csak a completed befizetések számítanak',
            'icon' => 'fa-solid fa-money-bill-transfer',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Szolgáltató',
            'value' => $providerMeta['label'],
            'subtitle' => $invoice->provider_invoice_id ?: 'Még nincs szolgáltatói azonosító',
            'icon' => 'fa-solid fa-file-invoice',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Státusz',
            'value' => $statusMeta['label'],
            'subtitle' => $invoice->issue_date ? 'Kiállítás: ' . $invoice->issue_date->format('Y.m.d.') : 'Még nincs kiállítási dátum',
            'icon' => 'fa-solid fa-circle-info',
            'color' => 'purple',
        ])
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Számla alapadatai</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Számlaszám</span>
                        <strong>{{ $invoice->invoice_number ?: 'Még nincs' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Fizetési hónap</span>
                        <strong>{{ $periods['payment_period_label'] ?? 'Nem elérhető' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Étkezési időszak</span>
                        <strong>{{ $periods['meal_period_label'] ?? 'Nem elérhető' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Jóváírási időszak</span>
                        <strong>{{ $periods['credit_period_label'] ?? 'Nem elérhető' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Nettó összeg</span>
                        <strong>{{ number_format($invoice->net_amount, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Áfa</span>
                        <strong>{{ number_format($invoice->vat_amount, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Bruttó összeg</span>
                        <strong>{{ number_format($invoice->gross_amount, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Kiállítás</span>
                        <strong>{{ $invoice->issue_date?->format('Y.m.d.') ?: 'Még nincs' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Teljesítés</span>
                        <strong>{{ $invoice->fulfillment_date?->format('Y.m.d.') ?: 'Nem elérhető' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Fizetési határidő</span>
                        <strong>{{ $invoice->due_date?->format('Y.m.d.') ?: 'Nem elérhető' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Státusz</span>
                        <span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Vevő és kapcsolatok</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Gyermek</div>
                        <div class="fw-semibold">{{ $invoice->child?->name ?? 'Nem elérhető' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Szülő / gondviselő</div>
                        <div class="fw-semibold">{{ $invoice->guardian?->full_name ?? 'Nem elérhető' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Vevő neve</div>
                        <div class="fw-semibold">{{ $invoice->customer_name }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Számlázási cím</div>
                        <div class="fw-semibold">{{ $invoice->billing_postcode }} {{ $invoice->billing_city }}, {{ $invoice->billing_address }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">E-mail</div>
                        <div class="fw-semibold">{{ $invoice->customer_email ?: 'Nem elérhető' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Szolgáltató</div>
                        <div><span class="badge {{ $providerMeta['class'] }}">{{ $providerMeta['label'] }}</span></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Szolgáltatói azonosító</div>
                        <div class="fw-semibold">{{ $invoice->provider_invoice_id ?: 'Még nincs' }}</div>
                    </div>
                    <div>
                        <div class="text-muted small">PDF / számlalink</div>
                        @if($invoiceViewUrl)
                            <a href="{{ $invoiceViewUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-1">
                                <i class="fa-solid fa-file-arrow-down me-1"></i>Megnyitás
                            </a>
                        @else
                            <div class="fw-semibold">Nem elérhető</div>
                        @endif
                        @if($canReloadInvoicePdf)
                            <form method="POST" action="{{ route('dashboard.institution.finance.invoices.reload-pdf', $invoice) }}" class="mt-2 d-inline-block">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                    <i class="fa-solid fa-rotate me-1"></i>PDF újratöltése a Billingótól
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($invoice->status === \App\Models\InstitutionInvoice::STATUS_FAILED && $invoice->error_message)
        <div class="alert alert-danger mb-4">
            <strong>Szolgáltatói hiba:</strong> {{ $invoice->error_message }}
        </div>
    @endif

    @if($canReloadInvoicePdf)
        <div class="alert alert-warning mb-4">
            <strong>PDF még nem elérhető:</strong> A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.
        </div>
    @endif

    @if($cancellationInvoice)
        <div class="card mb-4 border-dark">
            <div class="card-header bg-warning text-white">
                <h4 class="card-title mb-0"><i class="fa-solid fa-rotate-left me-1"></i>Sztornó bizonylat adatai</h4>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Sztornózva</span>
                    <strong>{{ $invoice->cancelled_at?->format('Y.m.d. H:i') ?: 'Nem elérhető' }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Sztornózta</span>
                    <strong>{{ $invoice->cancelledBy?->name ?: 'Nem elérhető' }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Sztornó bizonylatszám</span>
                    <strong>{{ $cancellationInvoice->invoice_number ?: 'Nem elérhető' }}</strong>
                </div>
                @if($originalInvoice?->cancellation_reason)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Indoklás</span>
                        <strong>{{ $originalInvoice->cancellation_reason }}</strong>
                    </div>
                @endif
                @if($hasLocalCancellationPdf)
                    <div class="pt-2 d-flex gap-2 flex-wrap">
                        <a href="{{ route('dashboard.institution.finance.invoices.download', $cancellationInvoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">
                            <i class="fa-solid fa-file-arrow-down me-1"></i>Sztornó bizonylat megnyitása
                        </a>
                        <a href="{{ route('dashboard.institution.finance.invoices.show', $cancellationInvoice) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Külön dokumentum megnyitása
                        </a>
                    </div>
                @endif
                @if($canReloadCancellationPdf)
                    <div class="alert alert-warning mt-3 mb-0">
                        <strong>PDF még nem elérhető:</strong> A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.
                    </div>
                    <form method="POST" action="{{ route('dashboard.institution.finance.invoices.reload-pdf', $cancellationInvoice) }}" class="pt-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                            <i class="fa-solid fa-rotate me-1"></i>PDF újratöltése a Billingótól
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @elseif($invoice->isCancellable())
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Sztornózás</h4>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancelInvoiceForm">
                    <i class="fa-solid fa-rotate-left me-1"></i>Számla sztornózása
                </button>
            </div>
            <div id="cancelInvoiceForm" class="collapse">
                <div class="card-body">
                    <p class="text-muted">
                        A sztornózás a szolgáltatónál ({{ $providerMeta['label'] }}) is kiállít egy önálló sztornó
                        bizonylatot, amely érvényteleníti ezt a számlát. A művelet nem vonható vissza.
                    </p>
                    <form
                        method="POST"
                        action="{{ route('dashboard.institution.finance.invoices.cancel', $invoice) }}"
                        class="confirm-form"
                        data-title="Biztosan sztornózni szeretnéd?"
                        data-text="A szolgáltatónál ({{ $providerMeta['label'] }}) is kiállít egy önálló sztornó bizonylatot. A művelet nem vonható vissza."
                        data-confirm-button-text="Igen, sztornózom"
                    >
                        @csrf
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">Sztornózás indoklása (opcionális)</label>
                            <textarea name="reason" id="cancellation_reason" class="form-control @error('reason') is-invalid @enderror" rows="2" maxlength="500">{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @error('invoice')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-rotate-left me-1"></i>Sztornózás megerősítése
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @elseif($invoice->isDeletable())
        <div class="card mb-4 border-danger">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Sikertelen próbálkozás törlése</h4>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#deleteInvoiceForm">
                    <i class="fa-solid fa-trash me-1"></i>Törlés
                </button>
            </div>
            <div id="deleteInvoiceForm" class="collapse">
                <div class="card-body">
                    <p class="text-muted">
                        Ez a bejegyzés egy sikertelen számlakiállítási próbálkozás - a szolgáltatónál
                        ({{ $providerMeta['label'] }}) nem jött létre valódi bizonylat, ezért nincs mit
                        sztornózni. A törlés után a fizetési kötelezettséghez újra megpróbálhatod a
                        számla kiállítását. A művelet nem vonható vissza.
                    </p>
                    @error('invoice')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <form
                        method="POST"
                        action="{{ route('dashboard.institution.finance.invoices.destroy', $invoice) }}"
                        class="delete-form"
                        data-title="Biztosan törlöd ezt a sikertelen próbálkozást?"
                        data-text="A szolgáltatónál nem jött létre valódi bizonylat, csak a helyi rekordot törli. A művelet nem vonható vissza."
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-trash me-1"></i>Törlés megerősítése
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Kapcsolódó befizetések</h4>
        </div>
        <div class="card-body">
            @if($payments->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Összeg</th>
                            <th>Fizetési mód</th>
                            <th>Státusz</th>
                            <th>Hivatkozás</th>
                            <th>Rögzítő</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($payments as $payment)
                            @php
                                $paymentMethod = \App\Models\InstitutionPayment::paymentMethodMeta($payment->payment_method);
                                $paymentStatus = \App\Models\InstitutionPayment::statusMeta($payment->status);
                            @endphp
                            <tr>
                                <td>{{ $payment->paid_at?->format('Y.m.d. H:i') }}</td>
                                <td>{{ number_format($payment->amount, 0, ',', ' ') }} Ft</td>
                                <td>
                                    <span class="badge rounded-pill {{ $paymentMethod['class'] }}">
                                        <i class="{{ $paymentMethod['icon'] }} me-1"></i>{{ $paymentMethod['label'] }}
                                    </span>
                                </td>
                                <td><span class="badge {{ $paymentStatus['class'] }}{{ str_contains($paymentStatus['class'], 'text-') ? '' : ' text-white' }}">{{ $paymentStatus['label'] }}</span></td>
                                <td>{{ $payment->reference ?: 'Nem elérhető' }}</td>
                                <td>{{ $payment->recordedBy?->name ?: 'Nem elérhető' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-money-bill-transfer',
                    'title' => 'Még nincs kapcsolódó befizetés',
                    'text' => 'A számla paid állapota csak a kapcsolódó kötelezettséghez rögzített completed befizetések alapján alakulhat ki.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
