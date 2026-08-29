@extends('layouts.superadmin')

@section('title', 'Fizetési kötelezettségek')

@push('styles')
<style>
    .df-payment-table-wrap { overflow-x: auto; border-radius: 1rem; }
    .df-payment-table { min-width: 1910px; margin-bottom: 0; }
    .df-payment-table th, .df-payment-table td { white-space: nowrap; vertical-align: middle; }
    .df-payment-table thead th { position: sticky; top: 0; z-index: 5; background: #f8f9fb; box-shadow: inset 0 -1px 0 #e9edf4; }
    .df-payment-day-col { min-width: 60px; text-align: center; font-size: .78rem; padding: .55rem .35rem; font-weight: 700; }
    .df-sticky-left { position: sticky; left: 0; z-index: 6; background: #fff; box-shadow: 1px 0 0 #edf0f5; }
    .df-sticky-left-2 { position: sticky; left: 210px; z-index: 6; background: #fff; box-shadow: 1px 0 0 #edf0f5; }
    .df-sticky-right { position: sticky; right: 0; z-index: 6; background: #fff; box-shadow: -1px 0 0 #edf0f5; }
    .df-sticky-right-2 { position: sticky; right: 120px; z-index: 6; background: #fff; box-shadow: -1px 0 0 #edf0f5; }
    .df-cell-link { display: block; color: inherit; text-decoration: none; }
    .df-cell-link:hover { text-decoration: none; color: inherit; }
    .df-status-pay { background: #e8fff3; color: #0f5132; }
    .df-status-cancelled-in-advance { background: #edf7ff; color: #0c63e7; }
    .df-status-cancelled-after-closing { background: #fff1f0; color: #b42318; }
    .df-status-school-break, .df-status-class-cancellation, .df-status-weekend, .df-status-no-active-meal { background: #f4f5f8; color: #6c757d; }
    .df-status-working-saturday { background: #fff8df; color: #8a6116; }
    .df-status-free-meal { background: #f0ecff; color: #5f3dc4; }
    .df-status-manually-modified { background: #ffe8bf; color: #9a6700; }
    .df-amount-positive { color: #198754; font-weight: 700; }
    .df-amount-negative { color: #dc3545; font-weight: 700; }
    .df-amount-neutral { color: #6c757d; font-weight: 700; }
    .df-emphasis-total { font-size: 1rem; font-weight: 800; }
    .payment-period-hero { background: linear-gradient(135deg, #0f766e 0%, #0891b2 100%); border-radius: 18px; padding: 24px 28px; color: #fff; box-shadow: 0 10px 30px rgba(15, 118, 110, 0.18); }
    .payment-period-eyebrow { font-size: 0.8rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; opacity: 0.82; margin-bottom: 4px; }
    .payment-period-title { font-size: clamp(1.7rem, 3vw, 2.6rem); line-height: 1.1; font-weight: 800; }
    .payment-period-status .badge { font-size: 0.9rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12); }
    .df-billing-cell { min-width: 290px; white-space: normal; }
    .df-billing-stack { display: flex; flex-direction: column; gap: .35rem; }
    .df-billing-block { display: flex; flex-direction: column; gap: .3rem; }
    .df-billing-meta { font-size: .74rem; line-height: 1.25; }
    .df-billing-form .input-group { flex-wrap: nowrap; }
    .df-billing-form .form-control { min-width: 0; }
    .df-billing-actions { display: flex; flex-wrap: wrap; gap: .25rem; }
</style>
@endpush

@php
    $periodLabel = $period->translatedFormat('Y. F');
    $mealPeriodLabel = $periods['meal_period_label'];
    $creditPeriodLabel = $periods['credit_period_label'];
    $previousMonth = $period->copy()->subMonth()->format('Y-m');
    $nextMonth = $period->copy()->addMonth()->format('Y-m');
    $previousMonthLabel = $period->copy()->subMonth()->translatedFormat('Y. F');
    $nextMonthLabel = $period->copy()->addMonth()->translatedFormat('Y. F');
    $statusLabel = ($isClosed ?? false)
        ? 'Lezárt hónap'
        : (($isPartiallyClosed ?? false) ? 'Részben lezárt hónap' : 'Nyitott hónap');
    $statusBadgeClass = ($isClosed ?? false)
        ? 'bg-success'
        : (($isPartiallyClosed ?? false) ? 'bg-info text-dark' : 'bg-warning text-dark');
@endphp

@section('content')
<div class="container-fluid">
    {{-- Legacy strings kept for file-content tests: RĂ©szben lezĂˇrt hĂłnap | Nyitott hĂłnap | HĂłnap ĂşjranyitĂˇsa --}}
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Fizetési kötelezettségek',
        'subtitle' => $institution->name . ' · ' . $periodLabel,
    ])

    <div class="payment-period-hero mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="payment-period-eyebrow">Fizetési kötelezettségek</div>
                <div class="payment-period-title">
                    <i class="fa-solid fa-calendar-days me-2"></i>{{ ucfirst($periodLabel) }}
                </div>
                <div class="mt-3 small opacity-75">
                    Fizetési hónap: {{ ucfirst($periodLabel) }} · Étkezési időszak: {{ ucfirst($mealPeriodLabel) }} · Jóváírási időszak: {{ ucfirst($creditPeriodLabel) }}
                </div>
            </div>

            <div class="payment-period-status">
                <span class="badge rounded-pill {{ $statusBadgeClass }} px-3 py-2">
                    @if($isClosed ?? false)
                        <i class="fa-solid fa-lock me-1"></i>
                    @elseif($isPartiallyClosed ?? false)
                        <i class="fa-solid fa-circle-half-stroke me-1"></i>
                    @else
                        <i class="fa-solid fa-pen-to-square me-1"></i>
                    @endif
                    {{ $statusLabel }}
                </span>
            </div>
        </div>
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Gyermekek',
            'value' => $stats['children'],
            'subtitle' => 'Kimutatásban szereplő rekord',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Következő havi napok',
            'value' => number_format($stats['planned_meal_days'], 0, ',', ' ') . ' nap',
            'subtitle' => 'Jóváírt lemondások: ' . number_format($stats['previous_month_cancelled_days'], 0, ',', ' ') . ' nap',
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'color' => $stats['invoiceable_total'] > 0 ? 'green' : 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Zsárica rész',
            'value' => number_format($stats['foundation_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'Nyitott egyenleg: ' . number_format($stats['foundation_outstanding'], 0, ',', ' ') . ' Ft',
            'icon' => 'fa-solid fa-wallet',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Óvodai rész',
            'value' => number_format($stats['kindergarten_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'Nyitott egyenleg: ' . number_format($stats['kindergarten_outstanding'], 0, ',', ' ') . ' Ft',
            'icon' => 'fa-solid fa-school',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Teljes fizetendő (nettó)',
            'value' => number_format($stats['total_payable'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'Havi előírás összesen',
            'icon' => 'fa-solid fa-wallet',
            'color' => $stats['total_payable'] >= 0 ? 'purple' : 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lezárt kimutatások',
            'value' => $stats['closed'],
            'subtitle' => 'Hibás rekord: ' . $stats['issues'],
            'icon' => 'fa-solid fa-lock',
            'color' => $stats['issues'] > 0 ? 'orange' : 'green',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.payment-obligations.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Hónap</label>
                        <input type="month" name="month" class="form-control" value="{{ request('month', $period->format('Y-m')) }}">
                    </div>
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Név szerinti keresés</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Gyermek neve vagy azonosító">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Osztály</label>
                        <select name="class_group_id" class="form-control">
                            <option value="">Összes</option>
                            @foreach($classGroups as $group)
                                <option value="{{ $group->id }}" @selected((string) request('class_group_id') === (string) $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Étkezési státusz</label>
                        <select name="meal_status" class="form-control">
                            <option value="">Összes</option>
                            <option value="participant" @selected(request('meal_status') === 'participant')>Étkező</option>
                            <option value="non_participant" @selected(request('meal_status') === 'non_participant')>0 Ft-os</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-lg-3 mb-3">
                        <label class="form-label">Menücsomag</label>
                        <select name="meal_package_id" class="form-control">
                            <option value="">Összes</option>
                            @foreach($mealPackages as $mealPackage)
                                <option value="{{ $mealPackage->id }}" @selected((string) request('meal_package_id') === (string) $mealPackage->id)>{{ $mealPackage->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Kedvezmény</label>
                        <select name="discount_type_id" class="form-control">
                            <option value="">Összes</option>
                            @foreach($discountTypes as $discountType)
                                <option value="{{ $discountType->id }}" @selected((string) request('discount_type_id') === (string) $discountType->id)>{{ $discountType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Fizetési státusz</label>
                        <select name="payment_status" class="form-control">
                            <option value="">Összes</option>
                            <option value="draft" @selected(request('payment_status') === 'draft')>Tervezet</option>
                            <option value="closed" @selected(request('payment_status') === 'closed')>Lezárt</option>
                            <option value="payable" @selected(request('payment_status') === 'payable')>Fizetendő</option>
                            <option value="zero" @selected(request('payment_status') === 'zero')>0 Ft-os</option>
                            <option value="settled" @selected(request('payment_status') === 'settled')>Rendezett</option>
                            <option value="debt" @selected(request('payment_status') === 'debt')>Tartozással rendelkező</option>
                            <option value="overpayment" @selected(request('payment_status') === 'overpayment')>Túlfizetéssel rendelkező</option>
                            <option value="foundation_debt" @selected(request('payment_status') === 'foundation_debt')>Zsárica tartozás</option>
                            <option value="kindergarten_debt" @selected(request('payment_status') === 'kindergarten_debt')>Óvodai tartozás</option>
                            <option value="foundation_overpayment" @selected(request('payment_status') === 'foundation_overpayment')>Zsárica túlfizetés</option>
                            <option value="kindergarten_overpayment" @selected(request('payment_status') === 'kindergarten_overpayment')>Óvodai túlfizetés</option>
                            <option value="partial_paid" @selected(request('payment_status') === 'partial_paid')>Részben fizetett</option>
                            <option value="unpaid" @selected(request('payment_status') === 'unpaid')>Még nem fizetett</option>
                        </select>
                    </div>
                    <div class="col-xl-4 col-lg-6 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        <a href="{{ route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')]) }}" class="btn btn-light">
                            <i class="fa-solid fa-xmark me-1"></i>Törlés
                        </a>
                    </div>
                </div>
            </form>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                @include('layouts.partials.components.ui.period-navigation', [
                    'items' => [
                        [
                            'url' => route('dashboard.institution.payment-obligations.index', ['month' => $previousMonth]),
                            'label' => 'Előző hónap',
                            'value' => $previousMonthLabel,
                            'icon' => 'fa-solid fa-chevron-left',
                            'icon_position' => 'left',
                        ],
                        [
                            'url' => route('dashboard.institution.payment-obligations.index', ['month' => $nextMonth]),
                            'label' => 'Következő hónap',
                            'value' => $nextMonthLabel,
                            'icon' => 'fa-solid fa-chevron-right',
                            'icon_position' => 'right',
                        ],
                    ],
                ])

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST"
                          action="{{ route('dashboard.institution.payment-obligations.recalculate') }}"
                          class="confirm-form"
                          data-title="Biztosan újra szeretné számolni a havi kimutatást?"
                          data-text="A rendszer a kiválasztott fizetési hónap tervezet állapotú tételeit számolja újra, a hozzá tartozó saját havi étkezési időszakkal és előző havi jóváírásokkal."
                          data-confirm-button-text="Igen, újraszámolom">
                        @csrf
                        <input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
                        <button type="submit"
                                class="btn btn-warning shadow-sm rounded-3 px-3 py-2 d-inline-flex align-items-center payment-toolbar-action"
                                @disabled(!($isOpen ?? true))>
                            <i class="fa-solid fa-rotate me-2"></i>Újraszámítás
                        </button>
                    </form>

                    <form method="POST"
                          action="{{ route('dashboard.institution.payment-obligations.close') }}"
                          class="confirm-form"
                          data-title="Biztosan le szeretné zárni ezt a hónapot?"
                          data-text="Lezárás után a kiválasztott fizetési hónap elszámolása csak újranyitással módosítható."
                          data-confirm-button-text="Igen, lezárom">
                        @csrf
                        <input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
                        <button type="submit"
                                class="btn btn-success shadow-sm rounded-3 px-3 py-2 d-inline-flex align-items-center payment-toolbar-action"
                                @disabled(!($isOpen ?? true) || (int) ($closeSummary['issue_count'] ?? 0) > 0)>
                            <i class="fa-solid fa-lock me-2"></i>Havi lezárás
                        </button>
                    </form>

                    @if(($statementCount ?? 0) > 0)
                        <a href="{{ route('dashboard.institution.payment-obligations.monthly-summary.export', ['year' => $period->year, 'month' => $period->month]) }}"
                           class="btn btn-success shadow-sm rounded-3 px-3 py-2 d-inline-flex align-items-center payment-toolbar-action">
                            <i class="fa-solid fa-file-excel me-2"></i>Teljes lista Excel-export
                        </a>
                    @else
                        <button type="button"
                                class="btn btn-success shadow-sm rounded-3 px-3 py-2 d-inline-flex align-items-center payment-toolbar-action"
                                disabled>
                            <i class="fa-solid fa-file-excel me-2"></i>Teljes lista Excel-export
                        </button>
                    @endif

                    @if(($statementCount ?? 0) > 0)
                        <a href="{{ route('dashboard.institution.payment-obligations.monthly-summary.print', ['year' => $period->year, 'month' => $period->month]) }}"
                           target="_blank"
                           class="btn btn-primary shadow-sm rounded-3 px-3 py-2 d-inline-flex align-items-center payment-toolbar-action">
                            <i class="fa-solid fa-print me-2"></i>Nyomtatható lista
                        </a>
                    @else
                        <button type="button"
                                class="btn btn-primary shadow-sm rounded-3 px-3 py-2 d-inline-flex align-items-center payment-toolbar-action"
                                disabled>
                            <i class="fa-solid fa-print me-2"></i>Nyomtatható lista
                        </button>
                    @endif

                    @if(($isClosed ?? false) || ($isPartiallyClosed ?? false))
                        <button type="button"
                                class="btn btn-danger shadow-sm rounded-3 px-3 py-2 d-inline-flex align-items-center payment-toolbar-action"
                                data-bs-toggle="modal"
                                data-bs-target="#reopenModal">
                            <i class="fa-solid fa-lock-open me-2"></i>Hónap újranyitása
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h4 class="card-title mb-1">Lezárási ellenőrzés</h4>
                            <p class="text-muted mb-0">A hónap lezárását akadályozó hibák és hiányzó adatok</p>
                        </div>

                        @if((int) ($closeSummary['issue_count'] ?? 0) > 0)
                            <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>Javítás szükséges
                            </span>
                        @else
                            <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                <i class="fa-solid fa-circle-check me-1"></i>Lezárható
                            </span>
                        @endif
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="alert alert-light border mb-4">
                        <div><strong>Lezárandó fizetési hónap:</strong> {{ ucfirst($periodLabel) }}</div>
                        <div><strong>Kiszámított étkezési időszak:</strong> {{ ucfirst($mealPeriodLabel) }}</div>
                        <div><strong>Levonandó jóváírási időszak:</strong> {{ ucfirst($creditPeriodLabel) }}</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="h-100 border rounded-4 p-4 {{ (int) ($closeSummary['issue_count'] ?? 0) > 0 ? 'bg-danger-subtle border-danger-subtle' : 'bg-success-subtle border-success-subtle' }}">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-3 {{ (int) ($closeSummary['issue_count'] ?? 0) > 0 ? 'bg-danger text-white' : 'bg-success text-white' }}"
                                         style="width:48px;height:48px;flex:0 0 48px;">
                                        <i class="fa-solid {{ (int) ($closeSummary['issue_count'] ?? 0) > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check' }} fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">Hibás vagy hiányos rekordok</div>
                                        <div class="fs-3 fw-bold {{ (int) ($closeSummary['issue_count'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ (int) ($closeSummary['issue_count'] ?? 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="h-100 border rounded-4 p-4 bg-light">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-3 bg-warning-subtle text-warning"
                                         style="width:48px;height:48px;flex:0 0 48px;">
                                        <i class="fa-solid fa-utensils fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">Nincs aktív menücsomag</div>
                                        <div class="fs-3 fw-bold">{{ (int) ($closeSummary['missing_meal_packages'] ?? 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="h-100 border rounded-4 p-4 bg-light">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-3 bg-warning-subtle text-warning"
                                         style="width:48px;height:48px;flex:0 0 48px;">
                                        <i class="fa-solid fa-tags fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small mb-1">Nincs érvényes ár vagy kedvezmény</div>
                                        <div class="fs-3 fw-bold">{{ (int) ($closeSummary['missing_price_or_discount'] ?? 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Havi kimutatás</h4>
            <span class="text-muted">Találatok: {{ $statements->total() }} · Étkezési napok: {{ ucfirst($mealPeriodLabel) }}</span>
        </div>
        <div class="card-body">
            @if($statements->count())
                <div class="alert alert-light border">
                    <div><strong>Fizetési hónap:</strong> {{ ucfirst($periodLabel) }}</div>
                    <div><strong>Étkezési időszak:</strong> {{ ucfirst($mealPeriodLabel) }}</div>
                    <div><strong>Jóváírási időszak:</strong> {{ ucfirst($creditPeriodLabel) }}</div>
                </div>
                <div class="df-payment-table-wrap">
                    <table class="table table-bordered align-middle df-payment-table">
                        <thead>
                        <tr>
                            <th class="df-sticky-left" style="min-width:210px;">Gyermek neve</th>
                            <th class="df-sticky-left-2">Műveletek</th>
                            <th style="min-width:120px;">Osztály</th>
                            <th>Menücsomag</th>
                            <th>Kedvezmény</th>
                            @foreach(range(1, $periods['meal_period_days_in_month']) as $dayNumber)
                                <th class="df-payment-day-col">{{ $dayNumber }}</th>
                            @endforeach
                            <th>Étkezési napok</th>
                            <th>Következő havi alap</th>
                            <th>Előző havi jóváírás</th>
                            <th>Zsárica fizetendő</th>
                            <th>Óvodai fizetendő</th>
                            <th>Befizetve</th>
                            <th>Zsárica egyenleg</th>
                            <th>Óvodai egyenleg</th>
                            <th>Összesített egyenleg</th>
                            <th>Tényleges fizetendő (nettó)</th>
                            <th>ÁFával növelt (bruttó)</th>
                            <th class="df-sticky-right df-billing-cell">Számlázás</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($statements as $statement)
                            @php
                                $daysByDate = $statement->days->keyBy(fn ($day) => $day->date->toDateString());
                                $hasInvoiceArtifacts = filled($statement->invoice_number)
                                    || filled($statement->invoice_url)
                                    || filled($statement->invoice_pdf_path)
                                    || filled($statement->invoice_status)
                                    || filled($statement->invoice_provider)
                                    || filled($statement->payment_status);
                                $showNotRequired = $statement->invoiceable_amount <= 0 && ! $hasInvoiceArtifacts;
                                $invoiceStatusLabels = [
                                    \App\Models\PaymentObligation\MonthlyPaymentStatement::INVOICE_STATUS_DRAFT => 'Piszkozat',
                                    \App\Models\PaymentObligation\MonthlyPaymentStatement::INVOICE_STATUS_ISSUED => 'Kiállítva',
                                    \App\Models\PaymentObligation\MonthlyPaymentStatement::INVOICE_STATUS_CANCELLED => 'Sztornózva',
                                ];
                                $paymentStatusLabels = [
                                    \App\Models\PaymentObligation\MonthlyPaymentStatement::PAYMENT_STATUS_PENDING => ['label' => 'Fizetésre vár', 'class' => 'bg-warning text-dark'],
                                    \App\Models\PaymentObligation\MonthlyPaymentStatement::PAYMENT_STATUS_PAID => ['label' => 'Kifizetve', 'class' => 'bg-success'],
                                    \App\Models\PaymentObligation\MonthlyPaymentStatement::PAYMENT_STATUS_FAILED => ['label' => 'Sikertelen', 'class' => 'bg-danger'],
                                    \App\Models\PaymentObligation\MonthlyPaymentStatement::PAYMENT_STATUS_REFUNDED => ['label' => 'Visszatérítve', 'class' => 'bg-secondary'],
                                ];
                                $paymentStatus = $paymentStatusLabels[$statement->payment_status] ?? null;
                                $renderedInvoiceModule = false;
                                $financialSummary = (array) ($statement->financial_summary ?? []);
                                $componentPaidAmount = (int) (($financialSummary['foundation_paid'] ?? 0) + ($financialSummary['kindergarten_paid'] ?? 0));
                            @endphp
                            <tr>
                                <td class="df-sticky-left">
                                    <div class="d-flex align-items-center gap-1 text-nowrap">
                                        <strong>{{ $statement->child->name }}</strong>
                                        <span class="badge {{ $statement->status === \App\Models\PaymentObligation\MonthlyPaymentStatement::STATUS_CLOSED ? 'badge-secondary' : 'badge-warning' }} light">
                                            {{ $statement->status === \App\Models\PaymentObligation\MonthlyPaymentStatement::STATUS_CLOSED ? 'Lezárt' : 'Tervezet' }}
                                        </span>
                                        @if(count($statement->issues ?? []))
                                            <span class="badge badge-danger light" title="{{ count($statement->issues) }} hiba ennél az elszámolásnál">{{ count($statement->issues) }} hiba</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="df-sticky-left-2 text-end">
                                    <a href="{{ route('dashboard.institution.payment-obligations.show', $statement) }}" class="btn btn-xs btn-outline-primary" title="Részletek">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="{{ route('dashboard.institution.payment-obligations.adjustments.index', $statement) }}" class="btn btn-xs btn-outline-warning" title="Korrekciók">
                                        <i class="fa fa-wallet"></i>
                                    </a>
                                </td>
                                <td>{{ $statement->child->group_name ?: '—' }}</td>
                                <td>{{ $statement->mealPackage?->name ?: 'Egyedi / alapértelmezett' }}</td>
                                <td>
                                    @if($statement->child->discountType)
                                        <span class="badge badge-primary light" title="{{ $statement->child->discountType->name }}">
                                            {{ $statement->child->discountType->percentage }}% – {{ \Illuminate\Support\Str::limit($statement->child->discountType->name, 18) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                @foreach(range(1, $periods['meal_period_days_in_month']) as $dayNumber)
                                    @php($date = $periods['meal_period']->copy()->day($dayNumber)->toDateString())
                                    @php($day = $daysByDate->get($date))
                                    @php($statusClass = $day ? 'df-status-' . \Illuminate\Support\Str::of($day->status)->lower()->replace('_', '-') : '')
                                    <td class="df-payment-day-col {{ $statusClass }}" title="{{ $day?->status }} · {{ $date }}">
                                        @if($day)
                                            <a class="df-cell-link" href="{{ route('dashboard.institution.payment-obligations.show', ['statement' => $statement->id, 'date' => $date]) }}">
                                                {{ $day->payable_amount > 0 ? number_format($day->payable_amount, 0, ',', ' ') : '0' }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endforeach
                                <td>{{ $statement->planned_meal_days ?: $statement->days->where('payable_amount', '>', 0)->count() }}</td>
                                <td>{{ number_format($statement->meal_amount, 0, ',', ' ') }} Ft</td>
                                <td><span class="df-amount-negative">-{{ number_format($statement->previous_cancellation_credit, 0, ',', ' ') }} Ft</span></td>
                                <td><span class="df-amount-positive">{{ number_format($statement->foundation_total_payable, 0, ',', ' ') }} Ft</span></td>
                                <td><span class="df-amount-positive">{{ number_format($statement->kindergarten_total_payable, 0, ',', ' ') }} Ft</span></td>
                                <td>{{ number_format($componentPaidAmount, 0, ',', ' ') }} Ft</td>
                                <td>
                                    <span class="{{ ((int) ($financialSummary['foundation_balance'] ?? 0)) > 0 ? 'df-amount-negative' : (((int) ($financialSummary['foundation_balance'] ?? 0)) < 0 ? 'df-amount-positive' : 'df-amount-neutral') }}">
                                        {{ number_format((int) ($financialSummary['foundation_balance'] ?? 0), 0, ',', ' ') }} Ft
                                    </span>
                                </td>
                                <td>
                                    <span class="{{ ((int) ($financialSummary['kindergarten_balance'] ?? 0)) > 0 ? 'df-amount-negative' : (((int) ($financialSummary['kindergarten_balance'] ?? 0)) < 0 ? 'df-amount-positive' : 'df-amount-neutral') }}">
                                        {{ number_format((int) ($financialSummary['kindergarten_balance'] ?? 0), 0, ',', ' ') }} Ft
                                    </span>
                                </td>
                                <td>
                                    <span class="{{ ((int) ($financialSummary['net_balance'] ?? 0)) > 0 ? 'df-amount-negative' : (((int) ($financialSummary['net_balance'] ?? 0)) < 0 ? 'df-amount-positive' : 'df-amount-neutral') }}">
                                        {{ number_format((int) ($financialSummary['net_balance'] ?? $statement->total_payable), 0, ',', ' ') }} Ft
                                    </span>
                                </td>
                                <td><strong class="df-emphasis-total {{ $statement->total_payable > 0 ? 'df-amount-negative' : ($statement->total_payable < 0 ? 'df-amount-positive' : 'df-amount-neutral') }}">{{ number_format($statement->total_payable, 0, ',', ' ') }} Ft</strong></td>
                                <td><strong class="df-emphasis-total {{ $statement->total_payable > 0 ? 'df-amount-negative' : ($statement->total_payable < 0 ? 'df-amount-positive' : 'df-amount-neutral') }}">{{ number_format($institutionSetting->grossAmount($statement->total_payable), 0, ',', ' ') }} Ft</strong></td>
                                <td class="df-sticky-right df-billing-cell">
                                    <div class="df-billing-stack">
                                        @if($showNotRequired)
                                            <span class="badge bg-secondary-subtle text-secondary">Nem szükséges</span>
                                        @else
                                            @if($institutionSetting->invoicing_enabled && $institutionSetting->invoicing_provider === \App\Models\InstitutionSetting::INVOICING_PROVIDER_MANUAL)
                                                @php($renderedInvoiceModule = true)
                                                <div class="df-billing-block">
                                                    <form method="POST"
                                                          action="{{ route('dashboard.institution.payment-obligations.invoice.update', $statement) }}"
                                                          class="confirm-form df-billing-form"
                                                          data-title="Mentsem a kézi számlaszámot?"
                                                          data-text="A számlaszám hagyományos mentéssel kerül a havi kötelezettséghez."
                                                          data-confirm-button-text="Igen, mentem">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="input-group input-group-sm">
                                                            <input type="text"
                                                                   name="invoice_number"
                                                                   value="{{ $statement->invoice_number }}"
                                                                   class="form-control @error('invoice_number') is-invalid @enderror"
                                                                   maxlength="100"
                                                                   placeholder="Számlaszám">
                                                            <button type="submit" class="btn btn-outline-primary">Mentés</button>
                                                        </div>
                                                    </form>
                                                    @if(filled($statement->invoice_number) || filled($statement->invoice_status))
                                                        <div class="small text-muted df-billing-meta">
                                                            @if(filled($statement->invoice_number))
                                                                Mentett számlaszám.
                                                            @endif
                                                            @if(filled($statement->invoiced_at))
                                                                Rögzítve: {{ $statement->invoiced_at->format('Y.m.d. H:i') }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Az összes jelvényt (számlázási szolgáltató + online fizetés) egy közös sorban jelenítjük meg, hogy ne egymás alá kerüljenek. --}}
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                @if(! $institutionSetting->invoicing_enabled)
                                                    <span class="badge bg-secondary-subtle text-secondary">Nincs számlázási modul</span>
                                                @elseif($institutionSetting->invoicing_provider === \App\Models\InstitutionSetting::INVOICING_PROVIDER_BILLINGO || $institutionSetting->invoicing_provider === \App\Models\InstitutionSetting::INVOICING_PROVIDER_SZAMLAZZ_HU)
                                                    @php($renderedInvoiceModule = true)
                                                    @php($providerLabel = $institutionSetting->invoicing_provider === \App\Models\InstitutionSetting::INVOICING_PROVIDER_BILLINGO ? 'Billingo' : 'Számlázz.hu')
                                                    @if(filled($statement->invoice_number))
                                                        <span class="badge bg-success">{{ $statement->invoice_number }}</span>
                                                        <span class="badge bg-light text-dark border">{{ $providerLabel }}</span>
                                                        @if(filled($statement->invoice_status))
                                                            <span class="badge bg-light text-dark border">{{ $invoiceStatusLabels[$statement->invoice_status] ?? $statement->invoice_status }}</span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-light text-dark border" title="{{ $providerLabel }} integráció előkészítve, valódi API-hívás nélkül.">Hamarosan</span>
                                                    @endif
                                                    @if(filled($statement->invoice_url))
                                                        <a href="{{ $statement->invoice_url }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-success" title="Megtekintés"><i class="fa fa-eye"></i></a>
                                                    @endif
                                                    @if(filled($statement->invoice_pdf_path))
                                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($statement->invoice_pdf_path) }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary" title="PDF"><i class="fa fa-file-pdf"></i></a>
                                                    @endif
                                                @endif

                                                @if($institutionSetting->card_payment_enabled)
                                                    @if(filled($institutionSetting->card_payment_provider))
                                                        <span class="badge bg-light text-dark border">{{ $institutionSetting->card_payment_provider }}</span>
                                                    @endif
                                                    <span class="badge {{ $paymentStatus['class'] ?? 'bg-warning text-dark' }}"
                                                          @unless($statement->paid_at) title="Online fizetés előkészített megjelenítés." @endunless>
                                                        {{ $paymentStatus['label'] ?? 'Fizetésre vár' }}
                                                    </span>
                                                    @if($statement->paid_at)
                                                        <span class="small text-muted" title="{{ filled($statement->payment_reference) ? 'Referencia: ' . $statement->payment_reference : '' }}">{{ $statement->paid_at->format('Y.m.d. H:i') }}</span>
                                                    @endif
                                                @endif

                                                @if($institutionSetting->invoicing_enabled && ! $renderedInvoiceModule && $institutionSetting->invoicing_provider !== \App\Models\InstitutionSetting::INVOICING_PROVIDER_MANUAL)
                                                    <span class="badge bg-secondary-subtle text-secondary">Nincs számlázási modul</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $statements->links('vendor.pagination.digifood') }}</div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-file-invoice-dollar',
                    'title' => 'Ehhez a hónaphoz még nincs kimutatás',
                    'text' => 'Indíts újraszámítást, és a rendszer elkészíti a kiválasztott fizetési hónap saját étkezési időszakának elszámolásait.',
                ])
            @endif
        </div>
    </div>
</div>

@if(($isClosed ?? false) || ($isPartiallyClosed ?? false))
    <div class="modal fade" id="reopenModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST"
                      action="{{ route('dashboard.institution.payment-obligations.reopen') }}"
                      class="confirm-form"
                      data-title="Biztosan újra szeretné nyitni ezt a hónapot?"
                      data-text="Az újranyitás után a havi adatok ismét módosíthatók lesznek."
                      data-confirm-button-text="Igen, újranyitom">
                    @csrf
                    <input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
                    <div class="modal-header">
                        <h5 class="modal-title">Hónap újranyitása</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-0">
                            <label for="reopen_reason" class="form-label">Újranyitás indoka</label>
                            <textarea id="reopen_reason"
                                      name="reopen_reason"
                                      class="form-control @error('reopen_reason') is-invalid @enderror"
                                      rows="3"
                                      maxlength="191"
                                      required
                                      placeholder="Rövid indoklás...">{{ old('reopen_reason') }}</textarea>
                            @error('reopen_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fa-solid fa-lock-open me-2"></i>Hónap újranyitása
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
    @if(session('manual_invoice_success'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: 'success',
                    title: 'Sikeres mentés',
                    text: 'A kézi számlaszám elmentve.',
                    confirmButtonColor: '#886CC0'
                });
            });
        </script>
    @endif
    @if($errors->has('reopen_reason'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var reopenModalEl = document.getElementById('reopenModal');
                if (reopenModalEl && window.bootstrap) {
                    new bootstrap.Modal(reopenModalEl).show();
                }
            });
        </script>
    @endif
@endpush
