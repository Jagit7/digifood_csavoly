@extends('layouts.parent')

@section('page_title', 'Havi elszámolások')

@push('styles')
    <style>
        .df-settlement-summary-card {
            border: 0;
            border-radius: 24px;
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 55%, #fff1f2 100%);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }

        .df-settlement-amount {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            line-height: 1;
            color: #111827;
        }

        .df-settlement-card,
        .df-history-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
        }

        .df-settlement-label {
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .df-settlement-value {
            font-size: 1.05rem;
            font-weight: 600;
            color: #111827;
        }

        .df-settlement-breakdown-row {
            border-bottom: 1px dashed rgba(148, 163, 184, 0.45);
        }

        .df-settlement-breakdown-row:last-child {
            border-bottom: 0;
        }

        .df-settlement-status-card {
            border-width: 1px;
            border-style: solid;
            border-radius: 22px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        .df-settlement-status-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background: rgba(255, 255, 255, 0.78);
        }
        .df-settlement-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.48rem 0.8rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            line-height: 1;
            letter-spacing: 0.01em;
            border: 1px solid transparent;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        .df-settlement-badge-closed {
            color: #ffffff;
            background: linear-gradient(135deg, #4f46e5, #6d28d9);
            border-color: rgba(255, 255, 255, 0.18);
        }

        .df-settlement-badge-progress {
            color: #5b21b6;
            background: #f3e8ff;
            border-color: #d8b4fe;
        }

        .df-cib-logo {
            max-width: min(100%, 300px);
            height: auto;
        }
    </style>
@endpush

@php
    $statusCard = $summary['status_card'];
@endphp

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Havi elszámolások',
        'subtitle' => 'A kiválasztott hónap minden kapcsolt gyermekének elszámolása egy helyen.',
    ])

<div class="row">
@include('layouts.partials.components.ui.stats-card', [
        'title' => 'Tényleges fizetendő',
        'value' => number_format($summary['total_payable'], 0, ',', ' ') . ' Ft',
        'subtitle' => $summary['children_count'] . ' gyermek elszámolása',
        'icon' => 'fa-solid fa-file-invoice-dollar',
        'color' => 'blue',
        'colClass' => 'col-xl-3 col-md-6',
    ])

    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Befizetve',
        'value' => number_format($summary['paid_total'], 0, ',', ' ') . ' Ft',
        'subtitle' => $summary['paid_total'] > 0
            ? 'Eddig jóváírt befizetések'
            : 'Még nem érkezett befizetés',
        'icon' => 'fa-solid fa-circle-check',
        'color' => 'green',
        'colClass' => 'col-xl-3 col-md-6',
    ])

    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Fennmaradó összeg',
        'value' => number_format($summary['remaining_total'], 0, ',', ' ') . ' Ft',
        'subtitle' => $summary['remaining_total'] > 0
            ? 'Még rendezendő összeg'
            : 'Nincs fennmaradó tartozás',
        'icon' => 'fa-solid fa-wallet',
        'color' => 'orange',
        'colClass' => 'col-xl-3 col-md-6',
    ])

    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Zsárica rész',
        'value' => number_format($summary['foundation_remaining_total'] ?? 0, 0, ',', ' ') . ' Ft',
        'subtitle' => 'Külön utalással rendezendő',
        'icon' => 'fa-solid fa-building-columns',
        'color' => 'orange',
        'colClass' => 'col-xl-3 col-md-6',
    ])

    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Óvodai rész',
        'value' => number_format($summary['kindergarten_remaining_total'] ?? 0, 0, ',', ' ') . ' Ft',
        'subtitle' => 'Kedvezménnyel csökkentett összeg',
        'icon' => 'fa-solid fa-school',
        'color' => 'green',
        'colClass' => 'col-xl-3 col-md-6',
    ])

    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Fizetési határidő',
        'value' => $summary['due_date_label'] ?? 'Nincs megadva',
        'subtitle' => $summary['due_date_label']
            ? 'A kiválasztott hónap határideje'
            : 'Az intézmény nem adott meg határidőt',
        'icon' => 'fa-solid fa-calendar-days',
        'color' => 'purple',
        'colClass' => 'col-xl-3 col-md-6',
    ])
</div>

    @if($payment_result)
        <div class="card df-settlement-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Bankkártyás fizetés eredménye</h4>
                        <div class="text-muted">A CIB lezárt tranzakciós visszaigazolása.</div>
                    </div>
                    <span class="badge {{ $payment_result['status']['class'] }}">{{ $payment_result['status']['label'] }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-4 col-xl-2">
                        <div class="rounded-4 bg-light h-100 p-3">
                            <div class="df-settlement-label mb-2">TRID</div>
                            <div class="df-settlement-value">{{ $payment_result['trid'] ?: '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="rounded-4 bg-light h-100 p-3">
                            <div class="df-settlement-label mb-2">ANUM</div>
                            <div class="df-settlement-value">{{ $payment_result['anum'] ?: '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <div class="rounded-4 bg-light h-100 p-3">
                            <div class="df-settlement-label mb-2">RC</div>
                            <div class="df-settlement-value">{{ $payment_result['rc'] ?: '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="rounded-4 bg-light h-100 p-3">
                            <div class="df-settlement-label mb-2">RT</div>
                            <div class="df-settlement-value">{{ $payment_result['rt'] ?: '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="rounded-4 bg-light h-100 p-3">
                            <div class="df-settlement-label mb-2">AMO</div>
                            <div class="df-settlement-value">{{ number_format($payment_result['amo'], 0, ',', ' ') }} HUF</div>
                        </div>
                    </div>
                </div>

                @if($payment_result['invoice_errors'] !== [])
                    <div class="alert alert-warning mt-3 mb-0">
                        <div class="fw-semibold mb-1">A befizetés sikeres, de a számlázás újrapróbálást igényel.</div>
                        <ul class="mb-0 ps-3">
                            @foreach($payment_result['invoice_errors'] as $invoiceError)
                                <li>{{ $invoiceError }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="card df-settlement-summary-card mb-4">
        <div class="card-body p-4 p-xl-5">
            <div class="row g-4 align-items-start">
                <div class="col-12 col-xl-7">
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                        <a href="{{ route('parent.monthly-settlements.index', ['month' => $previous_month_query]) }}" class="btn btn-light border">
                            <i class="fa-solid fa-arrow-left me-1"></i> Előző hónap
                        </a>
                        <h4 class="mb-0">{{ $month_label }}</h4>
                        <a href="{{ route('parent.monthly-settlements.index', ['month' => $next_month_query]) }}" class="btn btn-light border">
                            Következő hónap <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                        @if($show_current_month_link)
                            <a href="{{ route('parent.monthly-settlements.index', ['month' => $current_month_query]) }}" class="btn btn-outline-secondary">
                                Aktuális hónap
                            </a>
                        @endif
                    </div>

                    <div class="text-muted mb-2">{{ $month_label }}i elszámolás</div>
                    <div class="df-settlement-amount mb-3">{{ number_format($summary['remaining_total'], 0, ',', ' ') }} Ft</div>
                    <span class="df-settlement-badge df-settlement-badge-closed">
                        <i class="fa-solid fa-check"></i>
                        {{ $summary['status']['label'] }}
                    </span>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <div class="rounded-4 bg-white border p-3 h-100">
                                <div class="df-settlement-label mb-1">Étkezési időszak</div>
                                <div class="df-settlement-value">{{ $meal_period_label }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="rounded-4 bg-white border p-3 h-100">
                                <div class="df-settlement-label mb-1">Jóváírási időszak</div>
                                <div class="df-settlement-value">{{ $credit_period_label }}</div>
                            </div>
                        </div>
                    </div>

                    @if($summary['payment_enabled'])
                        {{-- A "Kereskedői összesítő" (kereskedő adatai + CIB logó/linkek) kizárólag a
                        kártyás fizetéshez értelmezhető - ha az intézménynél nincs bekapcsolva, nem
                        jelenik meg (ld. felhasználói kérés). --}}
                        <div class="rounded-4 bg-white border p-4 mt-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                                <div>
                                    <div class="df-settlement-label mb-1">Kereskedői összesítő</div>
                                    <div class="fw-semibold">{{ $merchant_profile['name'] }}</div>
                                    @if($merchant_profile['address'])
                                        <div class="text-muted">{{ $merchant_profile['address'] }}</div>
                                    @endif
                                    @if($merchant_profile['tax_number'])
                                        <div class="small text-body-secondary">Adószám: {{ $merchant_profile['tax_number'] }}</div>
                                    @endif
                                    <div class="small text-body-secondary">A kereskedő, a {{ $merchant_profile['name'] }} székhelyének országa és országkódja: {{ $merchant_profile['country'] }}.</div>
                                </div>
                                <div class="text-lg-end">
                                    <img src="{{ asset('images/cib/cib-card-logos-hu.png') }}" alt="CIB Bank és elfogadott kártyák logói" class="df-cib-logo">
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a href="{{ route('parent.legal.card-payment') }}" class="btn btn-outline-primary btn-sm">CIB fizetési tájékoztató</a>
                                <a href="{{ route('parent.legal.card-payment-faq') }}" class="btn btn-outline-primary btn-sm">CIB GYFK</a>
                                <a href="{{ route('parent.legal.payment-flow') }}" class="btn btn-outline-primary btn-sm">Fizetési folyamat</a>
                                <a href="{{ route('parent.legal.complaints') }}" class="btn btn-outline-primary btn-sm">Reklamáció és visszatérítés</a>
                                <a href="{{ route('parent.legal.customer-service') }}" class="btn btn-outline-primary btn-sm">Ügyfélszolgálat</a>
                            </div>
                        </div>
                    @elseif($bank_transfer['available'])
                        <div class="mt-4">
                            @if($bank_transfer['is_split'] ?? false)
                                <div class="row g-3">
                                    @foreach(($bank_transfer['components'] ?? []) as $component)
                                        <div class="col-12">
                                            <div class="rounded-4 bg-white border p-4">
                                                <div class="df-settlement-label mb-1">{{ $component['label'] }}</div>
                                                <div class="h4 mb-3">{{ number_format($component['amount'], 0, ',', ' ') }} Ft</div>
                                                <div class="mb-1">Kedvezményezett: <strong>{{ $component['account_holder'] ?: '—' }}</strong></div>
                                                <div class="mb-1">Bankszámlaszám: <strong>{{ $component['account_number'] ?: '—' }}</strong></div>
                                                <div class="text-muted small">Közlemény: {{ $component['reference'] ?: '—' }}</div>
                                                <div class="small text-body-secondary mt-2">Kérjük, ezt az összeget külön utalással rendezze.</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                @include('parent.partials.bank-transfer-box', ['bankTransfer' => $bank_transfer])
                            @endif
                        </div>
                    @endif
                </div>

                <div class="col-12 col-xl-5">
                    <div class="card df-settlement-status-card h-100 {{ $statusCard['color_class'] }}">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start gap-3">
                                <div class="df-settlement-status-icon flex-shrink-0">
                                    <i class="{{ $statusCard['icon'] }}"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-2">{{ $statusCard['title'] }}</h5>
                                    <p class="mb-0 text-body-secondary">{{ $statusCard['description'] }}</p>
                                </div>
                            </div>

                            @if($statusCard['show_family_hint'])
                                <div class="small mt-3 text-body-secondary">
                                    A család összes havi díja egyetlen fizetéssel rendezhető.
                                </div>
                            @endif

                            @if(in_array($statusCard['status'], ['ready_to_pay', 'partially_paid'], true))
                                <div class="row g-3 mt-1">
                                    @if($statusCard['status'] === 'partially_paid')
                                        <div class="col-sm-6">
                                            <div class="rounded-4 bg-white border h-100 p-3">
                                                <div class="df-settlement-label mb-1">Eddig befizetve</div>
                                                <div class="df-settlement-value text-success">{{ number_format($statusCard['paid_amount'], 0, ',', ' ') }} Ft</div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="col-sm-6">
                                        <div class="rounded-4 bg-white border h-100 p-3">
                                            <div class="df-settlement-label mb-1">Fennmaradó összeg</div>
                                            <div class="df-settlement-value text-danger">{{ number_format($statusCard['remaining_amount'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="rounded-4 bg-white border h-100 p-3">
                                            <div class="df-settlement-label mb-1">Fizetési határidő</div>
                                            <div class="df-settlement-value">{{ $statusCard['due_date_label'] ?? 'Nincs megadva' }}</div>
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="rounded-4 bg-white border h-100 p-3">
                                            <div class="df-settlement-label mb-1">Hátralévő idő</div>
                                            <div class="df-settlement-value">{{ $statusCard['days_remaining_label'] ?? 'Nincs megadva' }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($statusCard['can_pay'])
                                <form method="POST" action="{{ route('parent.monthly-settlements.store') }}" class="mt-4">
                                    @csrf
                                    <input type="hidden" name="month" value="{{ $month_query }}">
                                    <input type="hidden" name="payment_intent_key" value="{{ $summary['payment_intent_key'] }}">
                                    @include('parent.partials.data-processing-consent-checkbox', [
                                        'merchantName' => $merchant_profile['name'],
                                        'dataProcessingRoute' => 'parent.legal.data-processing',
                                        'consentId' => 'settlements',
                                    ])
                                    <button type="submit" class="btn btn-danger w-100 mt-3">
                                        <i class="fa-solid fa-credit-card me-2"></i>
                                        {{ $statusCard['button_text'] }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if($summary['warnings'] !== [])
                <div class="alert alert-warning mt-4 mb-0">
                    <ul class="mb-0 ps-3">
                        @foreach($summary['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-4 bg-white border p-4 mt-4">
                <h5 class="mb-2">{{ $calculation_help['title'] }}</h5>
                <p class="text-muted mb-2">{{ $calculation_help['body'] }}</p>
                <div class="small text-body-secondary">{{ $calculation_help['meal_hint'] }}</div>
                <div class="small text-body-secondary">{{ $calculation_help['credit_hint'] }}</div>
            </div>
        </div>
    </div>

    @if($children_count === 0)
        <div class="card df-settlement-card">
            <div class="card-body">
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-children',
                    'title' => 'Nincs kapcsolt gyermek',
                    'text' => 'Ehhez a szülői fiókhoz jelenleg nincs aktív gyermekkapcsolat rendelve.',
                ])
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($child_cards as $card)
                <div class="col-12">
                    <div class="card df-settlement-card">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <h4 class="mb-0">{{ $card['child']->name }}</h4>
                                        <span class="df-settlement-badge df-settlement-badge-progress">
                                            <i class="fa-regular fa-clock"></i>
                                            {{ $card['status']['label'] }}
                                        </span>
                                    </div>
                                    <div class="text-muted">{{ $card['group_name'] }}</div>
                                    <div class="small text-body-secondary mt-2">
                                        {{ $card['payment_period_label'] }}i elszámolás
                                    </div>
                                    <div class="small text-body-secondary">
                                        Étkezési időszak: {{ $card['meal_period_label'] }}
                                    </div>
                                    <div class="small text-body-secondary">
                                        Jóváírási időszak: {{ $card['credit_period_label'] }}
                                    </div>
                                    <div class="small text-body-secondary">
                                        Következő havi étkezési napok: {{ $card['planned_meal_days'] ?? 0 }} nap
                                    </div>
                                    <div class="small text-body-secondary">
                                        Előző havi jóváírt lemondás: {{ $card['previous_month_cancelled_days'] ?? 0 }} nap
                                    </div>
                                </div>

                                @if($card['has_statement'])
                                    <div class="text-lg-end">
                                        <div class="df-settlement-label">Fennmaradó összeg</div>
                                        <div class="h3 mb-0 {{ $card['remaining_amount'] > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($card['remaining_amount'], 0, ',', ' ') }} Ft
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if(! $card['has_statement'])
                                <div class="alert alert-info mb-0">
                                    Az adott hónaphoz ehhez a gyermekhez még nem készült lezárt vagy megjeleníthető elszámolás.
                                </div>
                            @else
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Étkezési napok</div>
                                            <div class="df-settlement-value">{{ $card['meal_days'] }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Zsárica rész</div>
                                            <div class="df-settlement-value">{{ number_format($card['foundation']['current_total'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Óvodai rész</div>
                                            <div class="df-settlement-value">{{ number_format($card['kindergarten']['current_total'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Alap étkezési díj</div>
                                            <div class="df-settlement-value">{{ number_format($card['base_meal_amount'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Kedvezmények</div>
                                            <div class="df-settlement-value text-success">-{{ number_format($card['discount_amount'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                            <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">{{ $card['credit_period_label'] }}i lemondások jóváírása</div>
                                            <div class="df-settlement-value text-success">-{{ number_format($card['statement']->previous_cancellation_credit, 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Korrekciók</div>
                                            <div class="df-settlement-value">{{ number_format($card['correction_amount'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Korábbi egyenleg</div>
                                            <div class="df-settlement-value">{{ number_format($card['previous_balance'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Befizetve</div>
                                            <div class="df-settlement-value text-success">{{ number_format($card['paid_amount'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Havi előírás</div>
                                            <div class="df-settlement-value">{{ number_format($card['invoiceable_amount'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="rounded-4 bg-light h-100 p-3">
                                            <div class="df-settlement-label mb-2">Tényleges fizetendő</div>
                                            <div class="df-settlement-value">{{ number_format($card['current_total'], 0, ',', ' ') }} Ft</div>
                                        </div>
                                    </div>
                                </div>

                                @if($card['issues'] !== [])
                                    <div class="alert alert-warning">
                                        <ul class="mb-0 ps-3">
                                            @foreach($card['issues'] as $issue)
                                                <li>{{ $issue }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="accordion" id="child-settlement-{{ $card['child']->id }}">
                                    <div class="accordion-item border rounded-4 overflow-hidden">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#child-settlement-body-{{ $card['child']->id }}">
                                                Miért ennyi?
                                            </button>
                                        </h2>
                                        <div id="child-settlement-body-{{ $card['child']->id }}" class="accordion-collapse collapse" data-bs-parent="#child-settlement-{{ $card['child']->id }}">
                                            <div class="accordion-body">
                                                <div class="row g-4">
                                                    <div class="col-xl-5">
                                                        <div class="rounded-4 border p-3 h-100">
                                                            <h6 class="mb-3">Elszámolás felépítése</h6>
                                                            @foreach($card['details']['breakdown'] as $row)
                                                                <div class="df-settlement-breakdown-row d-flex justify-content-between align-items-center py-2">
                                                                    <span class="{{ $row['emphasis'] ? 'fw-semibold text-dark' : 'text-muted' }}">{{ $row['label'] }}</span>
                                                                    <span class="{{ $row['amount'] < 0 ? 'text-success' : 'text-dark' }} {{ $row['emphasis'] ? 'fw-semibold' : '' }}">
                                                                        {{ $row['amount'] < 0 ? '-' : '' }}{{ number_format(abs($row['amount']), 0, ',', ' ') }} Ft
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-7">
                                                        <div class="rounded-4 border p-3 h-100">
                                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                                <h6 class="mb-0">Napi részletező táblázat</h6>
                                                                <span class="small text-muted">{{ $card['meal_period_label'] }}</span>
                                                            </div>
                                                            <div class="table-responsive">
                                                                <table class="table align-middle mb-0">
                                                                    <thead>
                                                                    <tr>
                                                                        <th>Dátum</th>
                                                                        <th>Nap</th>
                                                                        <th>Alapár</th>
                                                                        <th>Kedvezmény</th>
                                                                        <th>Napi fizetendő</th>
                                                                        <th>Státusz</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    @foreach($card['details']['daily_rows'] as $day)
                                                                        <tr>
                                                                            <td>{{ $day['date_label'] }}</td>
                                                                            <td>{{ $day['weekday'] }}</td>
                                                                            <td>{{ number_format($day['base_amount'], 0, ',', ' ') }} Ft</td>
                                                                            <td>{{ $day['discount_amount'] > 0 ? '-' . number_format($day['discount_amount'], 0, ',', ' ') . ' Ft' : '0 Ft' }}</td>
                                                                            <td>{{ number_format($day['payable_amount'], 0, ',', ' ') }} Ft</td>
                                                                            <td>
                                                                                <span class="badge {{ $day['status']['badge_class'] }}">{{ $day['status']['label'] }}</span>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card df-history-card mt-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-1">Fizetési előzmények</h4>
                        <div class="text-muted">Korábbi közös családi fizetések és azok állapota.</div>
                    </div>
                </div>

                @if($history->isEmpty())
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-receipt',
                        'title' => 'Még nincs közös fizetési előzmény',
                        'text' => 'Az első közös fizetési előkészítés után itt jelennek meg a tranzakciók.',
                    ])
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Fizetés dátuma</th>
                                <th>Érintett hónap</th>
                                <th>Gyermekek</th>
                                <th>Teljes összeg</th>
                                <th>Fizetési mód</th>
                                <th>Tranzakcióazonosító</th>
                                <th>Állapot</th>
                                <th>Bizonylat</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($history as $item)
                                <tr>
                                    <td>{{ $item['date'] }}</td>
                                    <td>{{ $item['month_label'] }}</td>
                                    <td>
                                        <div>{{ $item['children']->implode(', ') }}</div>
                                        @foreach($item['items'] as $childItem)
                                            <div class="small text-muted">{{ $childItem['child_name'] }}: {{ number_format($childItem['amount'], 0, ',', ' ') }} Ft</div>
                                        @endforeach
                                    </td>
                                    <td>{{ number_format($item['total_amount'], 0, ',', ' ') }} Ft</td>
                                    <td>
                                        <span class="badge {{ $item['payment_method']['class'] }}">
                                            <i class="{{ $item['payment_method']['icon'] }} me-1"></i>{{ $item['payment_method']['label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>{{ $item['transaction_reference'] }}</div>
                                        @if($item['cib']['trid'])
                                            <div class="small text-muted">TRID: {{ $item['cib']['trid'] }}</div>
                                        @endif
                                        @if($item['cib']['anum'])
                                            <div class="small text-muted">ANUM: {{ $item['cib']['anum'] }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $item['status']['class'] }}">{{ $item['status']['label'] }}</span></td>
                                    <td>
                                        @if($item['receipt_url'])
                                            <a href="{{ $item['receipt_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Megnyitás</a>
                                        @else
                                            <span class="text-muted small">Még nincs</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
