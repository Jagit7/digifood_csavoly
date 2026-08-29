@extends('layouts.superadmin')

@section('title', 'Dolgozói havi elszámolások')

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
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Dolgozói havi elszámolások',
        'subtitle' => $institution->name . ' · ' . $periodLabel,
    ])

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-muted mb-1">Dolgozói elszámolás</div>
                    <h2 class="mb-2">{{ ucfirst($periodLabel) }}</h2>
                    <div class="text-muted small">
                        Fizetési hónap: {{ ucfirst($periodLabel) }} · Étkezési időszak: {{ ucfirst($mealPeriodLabel) }} · Jóváírási időszak: {{ ucfirst($creditPeriodLabel) }}
                    </div>
                </div>
                <span class="badge rounded-pill {{ $statusBadgeClass }} px-3 py-2">{{ $statusLabel }}</span>
            </div>
        </div>
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Dolgozók',
            'value' => $stats['employees'],
            'subtitle' => 'Kimutatásban szereplő rekord',
            'icon' => 'fa-solid fa-id-badge',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Havi előírás',
            'value' => number_format($stats['invoiceable_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'Étkezési díj és korrekciók után',
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'color' => $stats['invoiceable_total'] > 0 ? 'green' : 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Teljes fizetendő',
            'value' => number_format($stats['total_payable'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'Korábbi egyenlegekkel együtt',
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
            <form method="GET" action="{{ route('dashboard.institution.employee-payment-obligations.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Hónap</label>
                        <input type="month" name="month" class="form-control" value="{{ request('month', $period->format('Y-m')) }}">
                    </div>
                    <div class="col-xl-3 col-lg-4 mb-3">
                        <label class="form-label">Név vagy e-mail</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Dolgozó neve vagy e-mail címe">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Étkezési státusz</label>
                        <select name="meal_status" class="form-control">
                            <option value="">Összes</option>
                            <option value="participant" @selected(request('meal_status') === 'participant')>Étkező</option>
                            <option value="non_participant" @selected(request('meal_status') === 'non_participant')>0 Ft-os</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
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
                        </select>
                    </div>
                    <div class="col-xl-4 col-lg-6 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        <a href="{{ route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')]) }}" class="btn btn-light">
                            <i class="fa-solid fa-xmark me-1"></i>Törlés
                        </a>
                    </div>
                </div>
            </form>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                @include('layouts.partials.components.ui.period-navigation', [
                    'items' => [
                        [
                            'url' => route('dashboard.institution.employee-payment-obligations.index', ['month' => $previousMonth]),
                            'label' => 'Előző hónap',
                            'value' => $previousMonthLabel,
                            'icon' => 'fa-solid fa-chevron-left',
                            'icon_position' => 'left',
                        ],
                        [
                            'url' => route('dashboard.institution.employee-payment-obligations.index', ['month' => $nextMonth]),
                            'label' => 'Következő hónap',
                            'value' => $nextMonthLabel,
                            'icon' => 'fa-solid fa-chevron-right',
                            'icon_position' => 'right',
                        ],
                    ],
                ])

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST"
                          action="{{ route('dashboard.institution.employee-payment-obligations.recalculate') }}"
                          class="confirm-form"
                          data-title="Biztosan újra szeretné számolni a dolgozói kimutatást?"
                          data-text="A rendszer a kiválasztott fizetési hónaphoz tartozó dolgozói tételeket számolja újra."
                          data-confirm-button-text="Igen, újraszámolom">
                        @csrf
                        <input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
                        <button type="submit" class="btn btn-warning" @disabled(!($isOpen ?? true))>
                            <i class="fa-solid fa-rotate me-1"></i>Újraszámítás
                        </button>
                    </form>

                    <form method="POST"
                          action="{{ route('dashboard.institution.employee-payment-obligations.close') }}"
                          class="confirm-form"
                          data-title="Biztosan le szeretné zárni ezt a dolgozói hónapot?"
                          data-text="Lezárás után az elszámolás csak újranyitással módosítható."
                          data-confirm-button-text="Igen, lezárom">
                        @csrf
                        <input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
                        <button type="submit" class="btn btn-success" @disabled(!($isOpen ?? true) || (int) ($closeSummary['issue_count'] ?? 0) > 0)>
                            <i class="fa-solid fa-lock me-1"></i>Havi lezárás
                        </button>
                    </form>

                    <form method="POST"
                          action="{{ route('dashboard.institution.employee-payment-obligations.reopen') }}"
                          class="confirm-form"
                          data-title="Biztosan újranyitja a dolgozói hónapot?"
                          data-text="Az újranyitás után a dolgozói kimutatások ismét szerkeszthető tervezetként kezelhetők."
                          data-confirm-button-text="Igen, újranyitom">
                        @csrf
                        <input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
                        <input type="hidden" name="reopen_reason" value="Dolgozói havi elszámolás újranyitása admin felületről.">
                        <button type="submit" class="btn btn-outline-secondary" @disabled(!(($isClosed ?? false) || ($isPartiallyClosed ?? false)))>
                            <i class="fa-solid fa-lock-open me-1"></i>Újranyitás
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Lezárási ellenőrzés</h4>
                    <p class="text-muted mb-0">A dolgozói hónap lezárását akadályozó hiányzó vagy hibás adatok</p>
                </div>
                @if((int) ($closeSummary['issue_count'] ?? 0) > 0)
                    <span class="badge rounded-pill bg-danger-subtle text-danger px-3 py-2">Javítás szükséges</span>
                @else
                    <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">Lezárható</span>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Hibás vagy hiányos rekordok</div>
                        <div class="fs-4 fw-bold">{{ (int) ($closeSummary['issue_count'] ?? 0) }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Dolgozói kimutatások</div>
                        <div class="fs-4 fw-bold">{{ (int) ($closeSummary['employees'] ?? 0) }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Hiányzó menücsomag / beállítás</div>
                        <div class="fs-4 fw-bold">{{ (int) ($closeSummary['missing_meal_packages'] ?? 0) }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Hiányzó ár</div>
                        <div class="fs-4 fw-bold">{{ (int) ($closeSummary['missing_price_or_discount'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <h4 class="card-title mb-0">Dolgozói havi kimutatások</h4>
        </div>
        <div class="card-body">
            @if($statements->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Dolgozó</th>
                            <th>E-mail</th>
                            <th>Étkezési napok</th>
                            <th>Menücsomag</th>
                            <th>Alapösszeg</th>
                            <th>Korrekció</th>
                            <th>Korábbi egyenleg</th>
                            <th>Fizetendő összeg</th>
                            <th>Fizetési határidő</th>
                            <th>Státusz</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($statements as $statement)
                            @php
                                $discount = $statement->discount?->percentage ?? $statement->employee?->discountType?->percentage ?? 0;
                                $statusBadge = $statement->status === \App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement::STATUS_CLOSED
                                    ? 'bg-success'
                                    : 'bg-warning text-dark';
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $statement->employee->name }}</div>
                                    @if(count($statement->issues ?? []))
                                        <div class="small text-danger mt-1">{{ count($statement->issues) }} hiba</div>
                                    @endif
                                </td>
                                <td>{{ $statement->employee->email ?: '—' }}</td>
                                <td>{{ $statement->days->where('payable_amount', '>', 0)->count() }}</td>
                                <td>
                                    {{ $statement->mealPackage?->name ?: 'Egyedi / alapértelmezett' }}
                                    @if($discount > 0)
                                        <div class="small text-muted mt-1">Kedvezmény: {{ $discount }}%</div>
                                    @endif
                                </td>
                                <td>{{ number_format($statement->meal_amount, 0, ',', ' ') }} Ft</td>
                                <td class="{{ $statement->billing_adjustment_amount < 0 ? 'text-success' : ($statement->billing_adjustment_amount > 0 ? 'text-danger' : 'text-muted') }}">
                                    {{ number_format($statement->billing_adjustment_amount, 0, ',', ' ') }} Ft
                                </td>
                                <td class="{{ $statement->previous_balance < 0 ? 'text-success' : ($statement->previous_balance > 0 ? 'text-danger' : 'text-muted') }}">
                                    {{ number_format($statement->previous_balance, 0, ',', ' ') }} Ft
                                </td>
                                <td class="fw-bold {{ $statement->total_payable > 0 ? 'text-danger' : ($statement->total_payable < 0 ? 'text-success' : 'text-muted') }}">
                                    {{ number_format($statement->total_payable, 0, ',', ' ') }} Ft
                                </td>
                                <td>{{ $statement->due_date?->format('Y.m.d.') ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $statusBadge }}">
                                        {{ $statement->status === \App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement::STATUS_CLOSED ? 'Lezárt' : 'Tervezet' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $statements->links('vendor.pagination.digifood') }}</div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-id-badge',
                    'title' => 'Ehhez a hónaphoz még nincs dolgozói kimutatás',
                    'text' => 'Indíts újraszámítást, és a rendszer elkészíti a kiválasztott hónaphoz tartozó dolgozói havi elszámolásokat.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
