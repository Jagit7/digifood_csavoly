@extends('layouts.superadmin')

@section('title', 'Tartozások')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Tartozások',
        'subtitle' => 'A fizetési hónapok statementjeiből és a completed befizetésekből számított fennmaradó tartozások listája.',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes tartozás',
            'value' => number_format($summary['total_remaining'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'A szűrt tételek teljes fennmaradó összege',
            'icon' => 'fa-solid fa-wallet',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Tartozó gyermekek',
            'value' => $summary['children_count'],
            'subtitle' => 'A szűrt listában érintett gyermekek száma',
            'icon' => 'fa-solid fa-user-graduate',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lejárt tartozások',
            'value' => number_format($summary['overdue_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'Már késedelmes tételek összege',
            'icon' => 'fa-solid fa-clock',
            'color' => 'red',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Részben fizetett',
            'value' => $summary['partial_paid_count'],
            'subtitle' => 'Már fizetett, de még nyitott tételek',
            'icon' => 'fa-solid fa-scale-balanced',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.finance.debts') }}">
                <div class="row align-items-end">
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">Gyermek neve</label>
                        <input type="search" name="child_search" class="form-control" value="{{ request('child_search') }}" placeholder="Keresés gyermek szerint">
                    </div>
                    <div class="col-xl-3 col-lg-6 mb-3">
                        <label class="form-label">Szülő / gondviselő neve</label>
                        <input type="search" name="guardian_search" class="form-control" value="{{ request('guardian_search') }}" placeholder="Keresés gondviselő szerint">
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Hónap</label>
                        <input type="month" name="month" class="form-control" value="{{ request('month') }}">
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Osztály / csoport</label>
                        <select name="group_name" class="form-control">
                            <option value="">Összes</option>
                            @foreach($groupOptions as $group)
                                <option value="{{ $group }}" @selected(request('group_name') === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label">Státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Összes</option>
                            <option value="before_due" @selected(request('status') === 'before_due')>Fizetési határidő előtt</option>
                            <option value="due_today" @selected(request('status') === 'due_today')>Ma esedékes</option>
                            <option value="overdue" @selected(request('status') === 'overdue')>Késedelmes</option>
                            <option value="partial_paid" @selected(request('status') === 'partial_paid')>Részben fizetve</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="overdue_only" value="1" id="overdue_only" @checked(request('overdue_only'))>
                            <label class="form-check-label" for="overdue_only">Csak késedelmes tételek</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->hasAny(['child_search', 'guardian_search', 'month', 'group_name', 'status', 'overdue_only']))
                            <a href="{{ route('dashboard.institution.finance.debts') }}" class="btn btn-light">
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
            <h4 class="card-title mb-0">Fennálló tartozások</h4>
            <span class="text-muted">Találatok: {{ $debts->total() }}</span>
        </div>
        <div class="card-body">
            @if($debts->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Gyermek</th>
                            <th class="text-end">Műveletek</th>
                            <th>Osztály / csoport</th>
                            <th>Szülő / gondviselő</th>
                            <th>Fizetési hónap</th>
                            <th>Tényleges fizetendő</th>
                            <th>Befizetve</th>
                            <th>Tartozás</th>
                            <th>Határidő</th>
                            <th>Késedelmi napok</th>
                            <th>Státusz</th>
                            <th>Kapcsolódó számla</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($debts as $statement)
                            @php
                                $periods = app(\App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper::class)->fromStatement($statement);
                                $statusMeta = $statement->debt_status;
                                $invoiceViewUrl = $statement->invoice_url
                                    ?: ($statement->invoice_pdf_path ? \Illuminate\Support\Facades\Storage::url($statement->invoice_pdf_path) : null);
                                $primaryBadgeClass = $statusMeta['primary']['class'];
                                if (!str_contains($primaryBadgeClass, 'text-dark') && !str_contains($primaryBadgeClass, 'text-muted')) {
                                    $primaryBadgeClass .= ' text-white';
                                }
                                $partialBadgeClass = $statusMeta['partial']['class'] ?? null;
                                if ($partialBadgeClass && !str_contains($partialBadgeClass, 'text-dark') && !str_contains($partialBadgeClass, 'text-muted')) {
                                    $partialBadgeClass .= ' text-white';
                                }
                            @endphp
                            <tr>
                                <td><strong>{{ $statement->child?->name ?? '—' }}</strong></td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.finance.debts.show', $statement) }}" class="btn btn-xs btn-outline-primary" title="Részletek">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="{{ route('dashboard.institution.payment-obligations.show', $statement) }}" class="btn btn-xs btn-outline-secondary" title="Kötelezettség megnyitása">
                                        <i class="fa fa-file-lines"></i>
                                    </a>
                                    <a href="{{ route('dashboard.institution.finance.payments.create', [
                                        'child_id' => $statement->child_id,
                                        'guardian_id' => $statement->prefill_guardian_id,
                                        'monthly_payment_statement_id' => $statement->id,
                                        'amount' => $statement->remaining_amount,
                                    ]) }}" class="btn btn-xs btn-outline-success" title="Befizetés rögzítése (teljes űrlap)">
                                        <i class="fa fa-plus"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs btn-success" title="Gyors fizetés rögzítése"
                                            data-bs-toggle="modal" data-bs-target="#quickPayModal{{ $statement->id }}">
                                        <i class="fa fa-check"></i>
                                    </button>
                                    @if($invoiceViewUrl)
                                        <a href="{{ $invoiceViewUrl }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-warning" title="Kapcsolódó számla">
                                            <i class="fa fa-file-invoice"></i>
                                        </a>
                                    @elseif($statement->invoice_number)
                                        <button type="button" class="btn btn-xs btn-outline-warning" title="A számla megnyitása hamarosan" disabled>
                                            <i class="fa fa-file-invoice"></i>
                                        </button>
                                    @endif
                                </td>
                                <td>{{ $statement->child?->group_name ?: '—' }}</td>
                                <td class="text-wrap">{{ $statement->display_guardians }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $periods['payment_period_label'] }}</div>
                                    <div class="small text-muted">Étkezés: {{ $periods['meal_period_label'] }}</div>
                                    <div class="small text-muted">Jóváírás: {{ $periods['credit_period_label'] }}</div>
                                </td>
                                <td>{{ number_format($statement->total_payable, 0, ',', ' ') }} Ft</td>
                                <td>{{ number_format($statement->completed_payments_total, 0, ',', ' ') }} Ft</td>
                                <td><strong class="text-danger">{{ number_format($statement->remaining_amount, 0, ',', ' ') }} Ft</strong></td>
                                <td>{{ $statement->due_at->format('Y.m.d.') }}</td>
                                <td>
                                    @if($statement->late_days > 0)
                                        {{ $statement->late_days }} nap
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $primaryBadgeClass }}">{{ $statusMeta['primary']['label'] }}</span>
                                    @if($statusMeta['partial'])
                                        <div class="mt-1">
                                            <span class="badge {{ $partialBadgeClass }}">{{ $statusMeta['partial']['label'] }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($statement->invoice_number)
                                        <div class="fw-semibold">{{ $statement->invoice_number }}</div>
                                        @if($invoiceViewUrl)
                                            <a href="{{ $invoiceViewUrl }}" target="_blank" rel="noopener" class="small">Megnyitás</a>
                                        @endif
                                    @else
                                        <span class="text-muted">Nincs</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $debts->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-circle-check',
                    'title' => 'Nincs fennálló tartozás',
                    'text' => 'A kiválasztott szűrés mellett minden kötelezettség teljesítettnek látszik, vagy nincs még fizetendő tétel.',
                ])
            @endif
        </div>
    </div>
</div>

{{-- Gyors fizetés modal minden sorhoz - ld. felhasználói kérés (Feladat #4):
     pontos összegnél elég a kipipálás (a "custom_amount" mező üresen
     maradhat), eltérő összegnél az admin megadhatja a ténylegesen
     beérkezett összeget. --}}
@foreach($debts as $statement)
    <div class="modal fade" id="quickPayModal{{ $statement->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('dashboard.institution.finance.debts.quick-pay', $statement) }}">
                    @csrf
                    <input type="hidden" name="guardian_id" value="{{ $statement->prefill_guardian_id }}">
                    <div class="modal-header">
                        <h5 class="modal-title">Gyors fizetés rögzítése</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border">
                            <div><strong>{{ $statement->child?->name }}</strong></div>
                            <div class="small text-muted">Fennmaradó tartozás: <strong>{{ number_format($statement->remaining_amount, 0, ',', ' ') }} Ft</strong></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fizetési mód</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="">Válassz fizetési módot</option>
                                @foreach($paymentMethodOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Melyik számlára érkezett?</label>
                            <select name="payment_component" class="form-control" required>
                                <option value="">Válassz komponenst</option>
                                <option value="{{ \App\Support\Finance\PaymentComponent::FOUNDATION }}">Zsárica Alapítvány</option>
                                <option value="{{ \App\Support\Finance\PaymentComponent::KINDERGARTEN }}">Óvodai étkezési díj</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Eltérő összeg (Ft)</label>
                            <input type="number" name="custom_amount" class="form-control" min="1" placeholder="Hagyd üresen, ha a pontos összeg érkezett be">
                            <div class="form-text">Ha üresen marad, a rendszer a teljes fennmaradó tartozást ({{ number_format($statement->remaining_amount, 0, ',', ' ') }} Ft) rögzíti kifizetettként.</div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Hivatkozás (opcionális)</label>
                            <input type="text" name="reference" class="form-control" maxlength="100" placeholder="Pl. banki tranzakcióazonosító">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-check me-1"></i>Befizetés rögzítése
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection
