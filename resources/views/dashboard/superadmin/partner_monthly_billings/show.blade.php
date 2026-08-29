@extends('layouts.superadmin')

@section('title', 'Ügyfél számlázás')

@section('content')
@php
    $formatMoney = function ($amount) {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $numeric = (float) $amount;
        $decimals = floor($numeric) == $numeric ? 0 : 2;

        return number_format($numeric, $decimals, ',', ' ') . ' Ft';
    };

    $statusMeta = function (?string $status) {
        return match ($status) {
            'draft' => ['label' => 'Tervezet', 'class' => 'badge badge-warning light'],
            'invoiced' => ['label' => 'Számlázva', 'class' => 'badge badge-info light'],
            'paid' => ['label' => 'Fizetve', 'class' => 'badge badge-success light'],
            default => ['label' => 'Nincs létrehozva', 'class' => 'badge badge-secondary light'],
        };
    };

    $billingName = $billingPartner->billing_name ?: $billingPartner->name;
    $billingAddress = collect([
        $billingPartner->billing_zip,
        $billingPartner->billing_city,
        $billingPartner->billing_address,
    ])->filter()->implode(' ');

    $status = $statusMeta($monthlyBilling?->status);

    $copyLines = [];

    if ($monthlyBilling) {
        $copyLines[] = 'DigiFood rendszerhasználati díj – ' . $selectedMonthLabel;
        $copyLines[] = '';
        $copyLines[] = 'Partner: ' . $billingPartner->name;
        $copyLines[] = 'Számlázási név: ' . $billingName;

        if (filled($billingPartner->tax_number)) {
            $copyLines[] = 'Adószám: ' . $billingPartner->tax_number;
        }

        if (filled($billingAddress)) {
            $copyLines[] = 'Számlázási cím: ' . $billingAddress;
        }

        if (filled($billingPartner->billing_email)) {
            $copyLines[] = 'Számlázási e-mail: ' . $billingPartner->billing_email;
        }

        foreach ($monthlyBilling->items as $item) {
            $copyLines[] = '';
            $copyLines[] = $item->institution_name_snapshot;
            $copyLines[] = $item->fixed_monthly_fee !== null
                ? 'Fix havi rendszerhasználati díj: ' . $formatMoney($item->net_amount)
                : ($item->calculation_description ?: $formatMoney($item->net_amount));
        }

        $copyLines[] = '';
        $copyLines[] = 'Nettó összesen: ' . $formatMoney($monthlyBilling->net_amount);
        $copyLines[] = 'ÁFA (' . rtrim(rtrim((string) $billingPartner->vat_rate, '0'), '.') . '%): ' . $formatMoney($monthlyBilling->vat_amount);
        $copyLines[] = 'Bruttó fizetendő: ' . $formatMoney($monthlyBilling->gross_amount);

        if ($billingPartner->payment_due_days !== null) {
            $copyLines[] = 'Fizetési határidő: ' . $billingPartner->payment_due_days . ' nap';
        }
    }

    $copyText = implode(PHP_EOL, $copyLines);
@endphp

@include('layouts.partials.components.ui.page-header', [
    'title' => 'Ügyfél számlázás',
    'subtitle' => 'Partnerenkénti havi számlázási pillanatkép és intézményi tételsorok.',
    'buttons' => [
        [
            'url' => route('dashboard.partner-monthly-billings.index', ['month' => $selectedMonthQuery]),
            'text' => 'Vissza a havi partnerlistára',
            'class' => 'btn btn-outline-secondary',
            'icon' => 'fa-solid fa-arrow-left',
        ],
    ],
])

<div class="d-flex flex-wrap gap-2 align-items-center mb-4">
    <a href="{{ route('dashboard.partner-monthly-billings.show', ['billingPartner' => $billingPartner, 'month' => $previousMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-chevron-left me-1"></i>Előző hónap
    </a>
    <div class="btn btn-light btn-sm disabled">{{ $selectedMonthLabel }}</div>
    <a href="{{ route('dashboard.partner-monthly-billings.show', ['billingPartner' => $billingPartner, 'month' => $nextMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
        Következő hónap<i class="fa-solid fa-chevron-right ms-1"></i>
    </a>
    <a href="{{ route('dashboard.partner-monthly-billings.show', ['billingPartner' => $billingPartner, 'month' => $currentMonthQuery]) }}" class="btn btn-outline-primary btn-sm">
        Aktuális hónap
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Partner neve</div>
                <div class="fw-semibold">{{ $billingPartner->name }}</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Számlázási név</div>
                <div>{{ $billingPartner->billing_name ?: '—' }}</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Adószám</div>
                <div>{{ $billingPartner->tax_number ?: '—' }}</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Számlázási cím</div>
                <div>{{ $billingAddress ?: '—' }}</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Számlázási e-mail</div>
                <div>{{ $billingPartner->billing_email ?: '—' }}</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Fizetési határidő</div>
                <div>{{ $billingPartner->payment_due_days !== null ? $billingPartner->payment_due_days . ' nap' : '—' }}</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">ÁFA-kulcs</div>
                <div>{{ rtrim(rtrim((string) $billingPartner->vat_rate, '0'), '.') }}%</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Kiválasztott hónap</div>
                <div>{{ $selectedMonthLabel }}</div>
            </div>
            <div class="col-xl-4 col-lg-6">
                <div class="small text-muted">Kapcsolt intézmények</div>
                <div>{{ $billingPartner->institutions_count }}</div>
            </div>
        </div>
    </div>
</div>

@if(!$monthlyBilling)
    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <strong>Ehhez a partnerhez erre a hónapra még nem készült számlázási pillanatkép.</strong>
            </div>

            <div class="alert alert-warning">
                A gyermeklétszám az aktuálisan aktív gyermekek alapján kerül rögzítésre. Korábbi hónapok történeti létszáma nem állítható vissza pontosan.
            </div>

            <form method="POST"
                  action="{{ route('dashboard.partner-monthly-billings.store', ['billingPartner' => $billingPartner]) }}"
                  class="mt-4">
                @csrf
                <input type="hidden" name="month" value="{{ $selectedMonthQuery }}">

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-1"></i>Havi adatok létrehozása
                </button>
            </form>
        </div>
    </div>
@else
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h4 class="card-title mb-1">Intézményi tételsorok</h4>
                <p class="text-muted mb-0">A mentett snapshot adatai kerülnek megjelenítésre, újraszámolás nélkül.</p>
            </div>
            <div class="d-flex align-items-start gap-2 flex-wrap justify-content-end">
                <span class="{{ $status['class'] }}">{{ $status['label'] }}</span>

                @if($monthlyBilling->status === 'draft')
                    <form method="POST"
                          action="{{ route('dashboard.partner-monthly-billings.recalculate', ['monthlyBilling' => $monthlyBilling]) }}"
                          class="confirm-form"
                          data-title="Biztosan újraszámolod?"
                          data-text="Az újraszámítás az aktuális gyermeklétszám és díjszabások alapján felülírja a tervezet tételsorait. Folytatod?"
                          data-confirm-button-text="Igen, újraszámolom">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-sm">
                            <i class="fa-solid fa-rotate me-1"></i>Újraszámítás
                        </button>
                    </form>

                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#markAsInvoicedModal">
                        <i class="fa-solid fa-file-invoice me-1"></i>Számlázottnak jelölés
                    </button>
                @elseif($monthlyBilling->status === 'invoiced')
                    <form method="POST"
                          action="{{ route('dashboard.partner-monthly-billings.mark-paid', ['monthlyBilling' => $monthlyBilling]) }}"
                          class="confirm-form"
                          data-title="Biztosan fizetettnek jelölöd?"
                          data-text="Biztosan fizetettnek jelölöd ezt a havi számlát?"
                          data-confirm-button-text="Igen, fizetett">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fa-solid fa-circle-check me-1"></i>Fizetettnek jelölés
                        </button>
                    </form>
                @endif

                <button type="button"
                        class="btn btn-outline-secondary btn-sm"
                        id="copyBillingSnapshotButton">
                    <i class="fa-solid fa-copy me-1"></i>Számlázási adatok másolása
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="small text-muted">Állapot</div>
                    <div><span class="{{ $status['class'] }}">{{ $status['label'] }}</span></div>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="small text-muted">Számlaszám</div>
                    <div>{{ $monthlyBilling->invoice_number ?: '—' }}</div>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="small text-muted">Számlázás időpontja</div>
                    <div>{{ $monthlyBilling->invoiced_at?->format('Y.m.d. H:i') ?: '—' }}</div>
                </div>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="small text-muted">Fizetés időpontja</div>
                    <div>{{ $monthlyBilling->paid_at?->format('Y.m.d. H:i') ?: '—' }}</div>
                </div>
                <div class="col-12">
                    <div class="small text-muted">Megjegyzés</div>
                    <div>{{ $monthlyBilling->note ?: '—' }}</div>
                </div>
            </div>

            <div class="accordion mb-4" id="billingSnapshotTextAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="billingSnapshotTextHeading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#billingSnapshotTextCollapse" aria-expanded="false" aria-controls="billingSnapshotTextCollapse">
                            Szöveg megtekintése
                        </button>
                    </h2>
                    <div id="billingSnapshotTextCollapse" class="accordion-collapse collapse" aria-labelledby="billingSnapshotTextHeading" data-bs-parent="#billingSnapshotTextAccordion">
                        <div class="accordion-body">
                            <textarea id="billingSnapshotText" class="form-control" rows="12" readonly>{{ $copyText }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Intézmény</th>
                        <th>Gyermeklétszám</th>
                        <th>Díjszabás</th>
                        <th>Minimumdíj</th>
                        <th>Számítás</th>
                        <th class="text-end">Nettó összeg</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($monthlyBilling->items as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->institution_name_snapshot }}</td>
                            <td>{{ $item->child_count }}</td>
                            <td>
                                @if($item->fixed_monthly_fee !== null)
                                    Fix havi díj: {{ $formatMoney($item->fixed_monthly_fee) }}
                                @elseif($item->price_per_child !== null)
                                    {{ $formatMoney($item->price_per_child) }} / gyermek
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $item->minimum_monthly_fee !== null ? $formatMoney($item->minimum_monthly_fee) : '—' }}</td>
                            <td>{{ $item->calculation_description ?: '—' }}</td>
                            <td class="text-end fw-semibold">{{ $formatMoney($item->net_amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-primary">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <div class="small text-muted">Összes gyermek</div>
                    <div class="h4 mb-0">{{ $monthlyBilling->total_children }}</div>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <div class="small text-muted">Nettó összeg</div>
                    <div class="h4 mb-0">{{ $formatMoney($monthlyBilling->net_amount) }}</div>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <div class="small text-muted">ÁFA</div>
                    <div class="h4 mb-0">{{ $formatMoney($monthlyBilling->vat_amount) }}</div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="small text-muted">Bruttó fizetendő</div>
                    <div class="h4 mb-0">{{ $formatMoney($monthlyBilling->gross_amount) }}</div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="small text-muted">Állapot</div>
                    <div><span class="{{ $status['class'] }}">{{ $status['label'] }}</span></div>
                </div>
            </div>
        </div>
    </div>

    @if($monthlyBilling->status === 'draft')
        <div class="modal fade" id="markAsInvoicedModal" tabindex="-1" aria-labelledby="markAsInvoicedModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('dashboard.partner-monthly-billings.mark-invoiced', ['monthlyBilling' => $monthlyBilling]) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="markAsInvoicedModalLabel">Számlázottnak jelölés</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                A számlázottnak jelölt havi adat ezután már nem számolható újra.
                            </div>

                            <div class="mb-3">
                                <label for="invoice_number" class="form-label">Számlaszám</label>
                                <input type="text"
                                       class="form-control @error('invoice_number') is-invalid @enderror"
                                       id="invoice_number"
                                       name="invoice_number"
                                       maxlength="100"
                                       value="{{ old('invoice_number', $monthlyBilling->invoice_number) }}"
                                       required>
                                @error('invoice_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="note" class="form-label">Megjegyzés</label>
                                <textarea class="form-control" id="note" name="note" rows="3">{{ old('note', $monthlyBilling->note) }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                            <button type="submit" class="btn btn-primary">Számlázottnak jelölés</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endif
@endsection

@push('scripts')
@if($monthlyBilling)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const copyButton = document.getElementById('copyBillingSnapshotButton');
    const copyTextarea = document.getElementById('billingSnapshotText');

    if (!copyButton || !copyTextarea) {
        return;
    }

    copyButton.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(copyTextarea.value);

            Swal.fire({
                icon: 'success',
                title: 'Sikeres másolás',
                text: 'A számlázási adatok a vágólapra kerültek.',
                confirmButtonColor: '#886CC0',
            });
        } catch (error) {
            Swal.fire({
                icon: 'warning',
                title: 'A másolás nem sikerült',
                text: 'A másolás nem sikerült. Jelöld ki és másold ki manuálisan az adatokat.',
                confirmButtonColor: '#886CC0',
            });

            const collapseElement = document.getElementById('billingSnapshotTextCollapse');

            if (collapseElement && typeof bootstrap !== 'undefined') {
                bootstrap.Collapse.getOrCreateInstance(collapseElement).show();
            }

            copyTextarea.focus();
            copyTextarea.select();
        }
    });

    @if($monthlyBilling->status === 'draft' && $errors->has('invoice_number'))
    const invoicedModalElement = document.getElementById('markAsInvoicedModal');

    if (invoicedModalElement && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(invoicedModalElement).show();
    }
    @endif
});
</script>
@endif
@endpush
