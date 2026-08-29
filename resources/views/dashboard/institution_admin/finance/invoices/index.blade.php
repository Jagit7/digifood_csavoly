@extends('layouts.superadmin')

@section('title', 'Számlák')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Számlák',
        'subtitle' => 'Az intézményhez kapcsolódó számlák és számla-előkészítések listája.',
        'buttons' => [
            [
                'url' => '#',
                'text' => 'Szinkronizálás a Billingóval',
                'icon' => 'fa-solid fa-rotate',
                'class' => 'btn btn-outline-success js-billingo-sync-trigger',
            ],
            [
                'url' => route('dashboard.institution.finance.invoices.create'),
                'text' => $setting->invoicing_enabled ? 'Új számla' : 'Számlázási beállítások',
                'icon' => $setting->invoicing_enabled ? 'fa-solid fa-plus' : 'fa-solid fa-gear',
                'class' => $setting->invoicing_enabled ? 'btn btn-primary' : 'btn btn-light',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Számlák száma',
            'value' => $summary['issued_count'],
            'subtitle' => 'Az aktív szűrő szerinti tételek darabszáma',
            'icon' => 'fa-solid fa-file-invoice',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Bruttó összeg',
            'value' => number_format($summary['gross_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'A szűrt számlák bruttó végösszege',
            'icon' => 'fa-solid fa-wallet',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Kifizetetlen összeg',
            'value' => number_format($summary['unpaid_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'A még nem rendezett számlák összege',
            'icon' => 'fa-solid fa-hourglass-half',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lejárt számlák',
            'value' => $summary['overdue_count'],
            'subtitle' => 'A fizetési határidőn túli tételek száma',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'color' => 'red',
        ])
    </div>

    @if(!$setting->invoicing_enabled)
        <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
            <div>A számlázás jelenleg nincs engedélyezve ennél az intézménynél.</div>
            <a href="{{ route('dashboard.institution.settings.invoicing.edit') }}" class="btn btn-sm btn-outline-dark">Beállítások megnyitása</a>
        </div>
    @endif

    <form method="POST" action="{{ route('dashboard.institution.finance.invoices.sync') }}" class="d-none" id="billingo-sync-form">
        @csrf
    </form>

    <div class="alert alert-light border mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
            <div>
                <strong>Utolsó sikeres Billingo-szinkron:</strong>
                {{ $syncMeta['last_successful_sync_at']?->format('Y.m.d. H:i') ?: 'Még nem futott sikeresen' }}
            </div>
            <div>
                <strong>Utolsó hiba:</strong>
                {{ $syncMeta['last_error'] ?: 'Nincs rögzített hiba' }}
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.finance.invoices') }}">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">Keresés</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Számlaszám, gyermek vagy gondviselő">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Hónap</label>
                        <input type="month" name="month" class="form-control" value="{{ request('month') }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Szolgáltató</label>
                        <select name="provider" class="form-control">
                            <option value="">Összes</option>
                            @foreach($providerOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('provider') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Összes</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <label class="form-label">Fizetési mód</label>
                        <select name="payment_method" class="form-control">
                            <option value="">Összes</option>
                            @foreach($paymentMethodOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Dokumentumtípus</label>
                        <select name="document_type" class="form-control">
                            <option value="">Összes</option>
                            @foreach($documentTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('document_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">PDF állapota</label>
                        <select name="pdf_state" class="form-control">
                            <option value="">Összes</option>
                            <option value="missing" @selected(request('pdf_state') === 'missing')>PDF hiányzik</option>
                            <option value="error" @selected(request('pdf_state') === 'error')>Szinkronizálási hiba</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Kiállítás kezdete</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Kiállítás vége</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->hasAny(['search', 'month', 'provider', 'document_type', 'status', 'payment_method', 'pdf_state', 'date_from', 'date_to']))
                            <a href="{{ route('dashboard.institution.finance.invoices') }}" class="btn btn-light">
                                <i class="fa-solid fa-xmark me-1"></i>Szűrők törlése
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Számlalista</h4>
            <span class="text-muted">Találatok: {{ $invoices->total() }}</span>
        </div>
        <div class="card-body">
            @if($invoices->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Számlaszám</th>
                            <th class="text-end">Műveletek</th>
                            <th>Típus</th>
                            <th>Kapcsolódó dokumentum</th>
                            <th>Gyermek / fizető</th>
                            <th>Kiállítás</th>
                            <th>Teljesítés</th>
                            <th>Összeg</th>
                            <th>Státusz</th>
                            <th>PDF állapot</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($invoices as $invoice)
                            @php
                                $statement = $invoice->monthlyPaymentStatement;
                                $paymentMethod = \App\Models\InstitutionPayment::paymentMethodMeta($invoice->payment_method);
                                $statusMeta = $invoice->effective_status_meta;
                                $providerMeta = $invoice->provider_meta;
                                $relatedDocument = $invoice->isCancellationDocument()
                                    ? $invoice->originalInvoice?->invoice_number
                                    : $invoice->cancellationInvoice?->invoice_number;
                                $hasPdf = app(\App\Services\Finance\InstitutionInvoiceService::class)->hasUsableInvoicePdf($invoice);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $invoice->invoice_number ?: 'Még nincs' }}</div>
                                    @if($invoice->provider_invoice_id)
                                        <div class="small text-muted">{{ $invoice->provider_invoice_id }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.finance.invoices.show', $invoice) }}" class="btn btn-xs btn-outline-primary" title="Részletek">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @if(!$hasPdf && $invoice->provider === \App\Models\InstitutionInvoice::PROVIDER_BILLINGO)
                                        <form method="POST" action="{{ route('dashboard.institution.finance.invoices.reload-pdf', $invoice) }}" class="d-inline-block">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-warning" title="PDF újratöltése a Billingótól">
                                                <i class="fa-solid fa-rotate"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $invoice->isCancellationDocument() ? 'bg-dark text-white' : 'bg-primary text-white' }}">
                                        {{ $invoice->document_type_label }}
                                    </span>
                                    @if($invoice->isOriginalDocument() && $invoice->status === \App\Models\InstitutionInvoice::STATUS_VOIDED)
                                        <div class="small text-muted mt-1">Sztornózva</div>
                                    @endif
                                </td>
                                <td>{{ $relatedDocument ?: 'Nincs kapcsolt dokumentum' }}</td>
                                <td>
                                    <div>{{ $invoice->child?->name ?? 'Nem elérhető' }}</div>
                                    <div class="small text-muted">{{ $invoice->guardian?->full_name ?? $invoice->customer_name }}</div>
                                    @if($statement)
                                        <div class="small text-muted">{{ sprintf('%04d.%02d', $statement->year, $statement->month) }}</div>
                                    @endif
                                </td>
                                <td>{{ $invoice->issue_date?->format('Y.m.d.') ?: 'Még nincs' }}</td>
                                <td>{{ $invoice->fulfillment_date?->format('Y.m.d.') ?: 'Nem elérhető' }}</td>
                                <td>
                                    {{ number_format($invoice->gross_amount, 0, ',', ' ') }} Ft
                                    <div class="small text-muted">{{ $providerMeta['label'] }} · {{ $paymentMethod['label'] }}</div>
                                </td>
                                <td><span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span></td>
                                <td>
                                    @if($hasPdf)
                                        <span class="badge bg-success">PDF letöltve</span>
                                    @elseif($invoice->sync_error_message)
                                        <span class="badge bg-danger">Szinkronizálási hiba</span>
                                    @elseif($invoice->provider === \App\Models\InstitutionInvoice::PROVIDER_BILLINGO)
                                        <span class="badge bg-warning text-dark">Szinkronizálásra vár</span>
                                    @else
                                        <span class="badge bg-secondary">PDF hiányzik</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $invoices->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-file-invoice',
                    'title' => 'Még nincs számla',
                    'text' => 'A szűrésnek megfelelő számla vagy számla-előkészítés még nem található.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-billingo-sync-trigger').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            document.getElementById('billingo-sync-form')?.submit();
        });
    });
});
</script>
@endpush
