@extends('layouts.parent')

@section('page_title', 'Befizetések')

@push('styles')
    <style>
        .df-parent-payments-page .df-payment-panel,
        .df-parent-payments-page .df-payment-history-card,
        .df-parent-payments-page .df-payment-child-card {
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
        }

        .df-parent-payments-page .df-payment-status-card {
            border-width: 1px;
            border-style: solid;
            border-radius: 1.35rem;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        .df-parent-payments-page .df-payment-status-icon {
            width: 54px;
            height: 54px;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.82);
            font-size: 1.25rem;
        }

        .df-parent-payments-page .df-payment-amount {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            line-height: 1;
            color: #111827;
        }

        .df-parent-payments-page .df-payment-kpi {
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: #fff;
            padding: 1rem;
            height: 100%;
        }

        .df-parent-payments-page .df-payment-label {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .df-parent-payments-page .df-payment-value {
            font-size: 1.05rem;
            font-weight: 600;
            color: #111827;
        }

        .df-parent-payments-page .df-payment-breakdown-row {
            border-bottom: 1px dashed rgba(148, 163, 184, 0.4);
        }

        .df-parent-payments-page .df-payment-breakdown-row:last-child {
            border-bottom: 0;
        }

        .df-parent-payments-page .table > :not(caption) > * > * {
            vertical-align: middle;
        }

        @media (max-width: 767.98px) {
            .df-parent-payments-page .df-payment-history-actions {
                min-width: 11rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="df-parent-payments-page">
        @include('layouts.partials.components.ui.page-header', [
            'title' => 'Befizetések',
            'subtitle' => 'A nyitott fizetési kötelezettségek és a korábbi befizetések áttekintése egy helyen.',
        ])

        <div class="row">
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Fizetendő összeg',
                'value' => number_format($stats['outstanding_total'], 0, ',', ' ') . ' Ft',
                'subtitle' => 'Aktuális tartozás',
                'icon' => 'fa-solid fa-wallet',
                'color' => 'green',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Következő határidő',
                'value' => $stats['next_due_date_label'],
                'subtitle' => $stats['next_due_helper'],
                'icon' => 'fa-solid fa-calendar-days',
                'color' => 'orange',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Ebben a hónapban befizetve',
                'value' => number_format($stats['paid_this_month'], 0, ',', ' ') . ' Ft',
                'subtitle' => 'Sikeres befizetések',
                'icon' => 'fa-solid fa-circle-check',
                'color' => 'blue',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Nyitott tételek',
                'value' => $stats['open_items_label'],
                'subtitle' => 'Befizetésre vár',
                'icon' => 'fa-solid fa-file-invoice-dollar',
                'color' => 'purple',
                'colClass' => 'col-xl-3 col-md-6',
            ])
        </div>

        <div class="card df-payment-panel mb-4">
            <div class="card-body p-4 p-xl-5">
                <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-4">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h3 class="mb-0">Aktuális fizetési kötelezettség</h3>
                            <span class="badge {{ $current_obligation['badge']['class'] }}">{{ $current_obligation['badge']['label'] }}</span>
                        </div>
                        <div class="text-muted">{{ $current_obligation['month_label'] }}</div>
                    </div>

                    <div class="text-xl-end">
                        <div class="df-payment-label mb-2">Fennmaradó összeg</div>
                        <div class="df-payment-amount {{ $focus_summary['remaining_total'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($focus_summary['remaining_total'], 0, ',', ' ') }} Ft
                        </div>
                    </div>
                </div>

                @if(! $has_children)
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-children',
                        'title' => 'Nincs kapcsolt gyermek',
                        'text' => 'Ehhez a szülői fiókhoz jelenleg nincs aktív gyermekkapcsolat rendelve, ezért befizetési adat sem jeleníthető meg.',
                    ])
                @elseif(! $focus_summary['has_statement'] && $stats['outstanding_total'] === 0)
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-circle-check',
                        'title' => 'Jelenleg nincs fizetendő tartozása.',
                        'text' => 'Minden nyilvántartott fizetési kötelezettsége rendezve van.',
                    ])
                @else
                    <div class="row g-4 align-items-start mb-4">
                        <div class="col-12 col-xl-7">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Fizetendő teljes összeg</div>
                                        <div class="df-payment-value">{{ number_format($focus_summary['total_payable'], 0, ',', ' ') }} Ft</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Már befizetett összeg</div>
                                        <div class="df-payment-value text-success">{{ number_format($focus_summary['paid_total'], 0, ',', ' ') }} Ft</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Hátralévő összeg</div>
                                        <div class="df-payment-value {{ $focus_summary['remaining_total'] > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($focus_summary['remaining_total'], 0, ',', ' ') }} Ft
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Fizetési határidő</div>
                                        <div class="df-payment-value">{{ $focus_summary['due_date_label'] ?? 'Nincs határidő' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="df-payment-label mb-0">Befizetési arány</span>
                                    <span class="small text-muted">{{ $current_obligation['progress_percent'] }}%</span>
                                </div>
                                <div class="progress" style="height: .7rem;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $current_obligation['progress_percent'] }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-5">
                            <div class="card df-payment-status-card h-100 {{ $current_obligation['status_card']['color_class'] }}">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="df-payment-status-icon flex-shrink-0">
                                            <i class="{{ $current_obligation['status_card']['icon'] }}"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-2">{{ $current_obligation['status_card']['title'] }}</h5>
                                            <p class="mb-0 text-body-secondary">{{ $current_obligation['status_card']['description'] }}</p>
                                        </div>
                                    </div>

                                    @if($current_obligation['status_card']['show_family_hint'])
                                        <div class="small text-body-secondary mt-3">
                                            A család összes havi díja egyetlen fizetéssel rendezhető.
                                        </div>
                                    @endif

                                    @if(in_array($current_obligation['status_card']['status'], ['ready_to_pay', 'partially_paid'], true))
                                        <div class="row g-3 mt-1">
                                            @if($current_obligation['status_card']['status'] === 'partially_paid')
                                                <div class="col-sm-6">
                                                    <div class="df-payment-kpi">
                                                        <div class="df-payment-label mb-1">Eddig befizetve</div>
                                                        <div class="df-payment-value text-success">{{ number_format($current_obligation['status_card']['paid_amount'], 0, ',', ' ') }} Ft</div>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="col-sm-6">
                                                <div class="df-payment-kpi">
                                                    <div class="df-payment-label mb-1">Fennmaradó összeg</div>
                                                    <div class="df-payment-value text-danger">{{ number_format($current_obligation['status_card']['remaining_amount'], 0, ',', ' ') }} Ft</div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="df-payment-kpi">
                                                    <div class="df-payment-label mb-1">Határidő</div>
                                                    <div class="df-payment-value">{{ $current_obligation['status_card']['due_date_label'] ?? 'Nincs határidő' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="df-payment-kpi">
                                                    <div class="df-payment-label mb-1">Hátralévő idő</div>
                                                    <div class="df-payment-value">{{ $current_obligation['status_card']['days_remaining_label'] ?? 'Nincs határidő' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if($current_obligation['status_card']['can_pay'])
                                        <form method="POST" action="{{ route('parent.monthly-settlements.store') }}" class="mt-4">
                                            @csrf
                                            <input type="hidden" name="month" value="{{ $focus_month_query }}">
                                            <input type="hidden" name="payment_intent_key" value="{{ $focus_summary['payment_intent_key'] }}">
                                            @include('parent.partials.data-processing-consent-checkbox', [
                                                'merchantName' => $focus_merchant_profile['name'],
                                                'dataProcessingRoute' => 'parent.legal.data-processing',
                                                'consentId' => 'payments',
                                            ])
                                            <button type="submit" class="btn btn-danger w-100 mt-3">
                                                <i class="fa-solid fa-credit-card me-2"></i>
                                                Befizetés bankkártyával
                                            </button>
                                        </form>
                                    @elseif($current_obligation['show_payment_guidance'] && $focus_bank_transfer['available'])
                                        <div class="mt-4">
                                            @include('parent.partials.bank-transfer-box', ['bankTransfer' => $focus_bank_transfer])
                                        </div>
                                    @elseif($current_obligation['show_payment_guidance'])
                                        <div class="alert alert-light border mt-4 mb-0">
                                            A befizetés módjáról az intézmény tájékoztatása az irányadó.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="mb-1">Gyermekenkénti bontás</h4>
                            <div class="text-muted small">{{ $focus_month_label }}i részletezés a kapcsolt gyermekekhez.</div>
                        </div>
                        <a href="{{ route('parent.monthly-settlements.index', ['month' => $focus_month_query]) }}" class="btn btn-outline-secondary btn-sm">
                            Havi elszámolás megnyitása
                        </a>
                    </div>

                    <div class="row g-3">
                        @forelse($focus_child_cards as $card)
                            <div class="col-12">
                                <div class="card df-payment-child-card">
                                    <div class="card-body p-4">
                                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                                            <div>
                                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                                    <h5 class="mb-0">{{ $card['child']->name }}</h5>
                                                    <span class="badge {{ $card['status']['class'] }}">{{ $card['status']['label'] }}</span>
                                                </div>
                                                <div class="text-muted small">{{ $card['group_name'] }} • {{ $card['month_label'] }}</div>
                                            </div>

                                            @if($card['has_statement'])
                                                <div class="text-lg-end">
                                                    <div class="df-payment-label">Fizetendő végösszeg</div>
                                                    <div class="h4 mb-0 {{ $card['remaining_amount'] > 0 ? 'text-danger' : 'text-success' }}">
                                                        {{ number_format($card['remaining_amount'], 0, ',', ' ') }} Ft
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if(! $card['has_statement'])
                                            <div class="alert alert-light border mb-0">
                                                Az adott hónaphoz ehhez a gyermekhez még nem készült megjeleníthető elszámolás.
                                            </div>
                                        @else
                                            <div class="row g-3">
                                                <div class="col-md-6 col-xl-3">
                                                    <div class="df-payment-kpi">
                                                        <div class="df-payment-label mb-1">Étkezési alapösszeg</div>
                                                        <div class="df-payment-value">{{ number_format($card['base_meal_amount'], 0, ',', ' ') }} Ft</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 col-xl-3">
                                                    <div class="df-payment-kpi">
                                                        <div class="df-payment-label mb-1">Kedvezmény összege</div>
                                                        <div class="df-payment-value text-success">-{{ number_format($card['discount_amount'], 0, ',', ' ') }} Ft</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 col-xl-3">
                                                    <div class="df-payment-kpi">
                                                        <div class="df-payment-label mb-1">Lemondásokból jóváírva</div>
                                                        <div class="df-payment-value text-success">-{{ number_format($card['statement']->previous_cancellation_credit, 0, ',', ' ') }} Ft</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 col-xl-3">
                                                    <div class="df-payment-kpi">
                                                        <div class="df-payment-label mb-1">Fizetési státusz</div>
                                                        <div class="df-payment-value">{{ $card['status']['label'] }}</div>
                                                    </div>
                                                </div>
                                            </div>

                                            @if($card['issues'] !== [])
                                                <div class="alert alert-warning mt-3 mb-0">
                                                    <ul class="mb-0 ps-3">
                                                        @foreach($card['issues'] as $issue)
                                                            <li>{{ $issue }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            <div class="mt-3">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#payment-child-details-{{ $card['child']->id }}">
                                                    Részletek
                                                </button>
                                            </div>

                                            <div class="collapse mt-3" id="payment-child-details-{{ $card['child']->id }}">
                                                <div class="border rounded-4 p-3">
                                                    <div class="row g-4">
                                                        <div class="col-xl-5">
                                                            <h6 class="mb-3">Elszámolási bontás</h6>
                                                            @foreach($card['details']['breakdown'] as $row)
                                                                <div class="df-payment-breakdown-row d-flex justify-content-between align-items-center py-2">
                                                                    <span class="{{ $row['emphasis'] ? 'fw-semibold text-dark' : 'text-muted' }}">{{ $row['label'] }}</span>
                                                                    <strong class="{{ $row['amount'] < 0 ? 'text-success' : ($row['emphasis'] ? 'text-dark' : '') }}">
                                                                        {{ $row['amount'] < 0 ? '-' : '' }}{{ number_format(abs($row['amount']), 0, ',', ' ') }} Ft
                                                                    </strong>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        <div class="col-xl-7">
                                                            <h6 class="mb-3">Napi részletezés</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm align-middle mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Dátum</th>
                                                                            <th>Státusz</th>
                                                                            <th class="text-end">Fizetendő</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($card['details']['daily_rows'] as $row)
                                                                            <tr>
                                                                                <td>
                                                                                    <div>{{ $row['date_label'] }}</div>
                                                                                    <small class="text-muted text-capitalize">{{ $row['weekday'] }}</small>
                                                                                </td>
                                                                                <td>
                                                                                    <span class="badge {{ $row['status']['badge_class'] }}">{{ $row['status']['label'] }}</span>
                                                                                </td>
                                                                                <td class="text-end">{{ number_format($row['payable_amount'], 0, ',', ' ') }} Ft</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                @include('layouts.partials.components.ui.empty-state', [
                                    'icon' => 'fa-solid fa-circle-check',
                                    'title' => 'Jelenleg nincs fizetendő tartozása.',
                                    'text' => 'Minden nyilvántartott fizetési kötelezettsége rendezve van.',
                                ])
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        <div class="card df-payment-history-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">Korábbi befizetések</h4>
                        <div class="text-muted small">A szülői fiókhoz kapcsolt befizetések előzményei.</div>
                    </div>
                </div>

                @if($history->total() === 0)
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-receipt',
                        'title' => 'Még nincs megjeleníthető befizetési előzmény.',
                        'text' => 'A korábbi sikeres vagy folyamatban lévő befizetések itt fognak megjelenni.',
                    ])
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Befizetés dátuma</th>
                                    <th>Időszak</th>
                                    <th>Gyermek vagy gyermekek</th>
                                    <th class="text-end">Befizetett összeg</th>
                                    <th>Fizetési mód</th>
                                    <th>Tranzakcióazonosító vagy bizonylatszám</th>
                                    <th>Státusz</th>
                                    <th class="text-end">Művelet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($history as $row)
                                    <tr>
                                        <td>{{ $row['paid_at_label'] }}</td>
                                        <td>{{ $row['period_label'] }}</td>
                                        <td>{{ $row['child_names']->join(', ') }}</td>
                                        <td class="text-end">{{ number_format($row['amount'], 0, ',', ' ') }} Ft</td>
                                        <td>
                                            <span class="badge {{ $row['payment_method']['class'] }}">
                                                <i class="{{ $row['payment_method']['icon'] }} me-1"></i>{{ $row['payment_method']['label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $row['reference'] }}</td>
                                        <td><span class="badge {{ $row['status']['class'] }}">{{ $row['status']['label'] }}</span></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap justify-content-end gap-2 df-payment-history-actions">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#payment-history-details-{{ $row['id'] }}">
                                                    Részletek
                                                </button>
                                                @if($row['receipt_url'])
                                                    <a href="{{ $row['receipt_url'] }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                                        Bizonylat letöltése
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="payment-history-details-{{ $row['id'] }}">
                                        <td colspan="8" class="bg-light">
                                            <div class="row g-3 p-2">
                                                <div class="col-md-4">
                                                    <div class="df-payment-label mb-1">Gyermek</div>
                                                    <div class="df-payment-value">{{ $row['details']['child_name'] }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="df-payment-label mb-1">Hivatkozás</div>
                                                    <div class="df-payment-value">{{ $row['details']['reference'] ?: 'Nincs megadva' }}</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="df-payment-label mb-1">Bizonylatszám</div>
                                                    <div class="df-payment-value">{{ $row['details']['invoice_number'] ?: 'Nincs megadva' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($history->hasPages())
                        <div class="mt-4">
                            {{ $history->links('vendor.pagination.digifood') }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
