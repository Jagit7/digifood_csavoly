@extends('layouts.employee')

@section('page_title', 'Befizetéseim')

@push('styles')
    <style>
        .df-employee-payments-page .df-payment-panel,
        .df-employee-payments-page .df-payment-history-card {
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
        }

        .df-employee-payments-page .df-payment-amount {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            line-height: 1;
            color: #111827;
        }

        .df-employee-payments-page .df-payment-kpi {
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: #fff;
            padding: 1rem;
            height: 100%;
        }

        .df-employee-payments-page .df-payment-label {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .df-employee-payments-page .df-payment-value {
            font-size: 1.05rem;
            font-weight: 600;
            color: #111827;
        }

        .df-employee-payments-page .table > :not(caption) > * > * {
            vertical-align: middle;
        }

        .df-employee-payments-page .df-payment-status-card {
            border-width: 1px;
            border-style: solid;
            border-radius: 1.35rem;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        .df-employee-payments-page .df-payment-status-icon {
            width: 54px;
            height: 54px;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.82);
            font-size: 1.25rem;
        }
    </style>
@endpush

@section('content')
    <div class="df-employee-payments-page">
        @include('layouts.partials.components.ui.page-header', [
            'title' => 'Befizetéseim',
            'subtitle' => 'A nyitott fizetési kötelezettség és a korábbi befizetések áttekintése egy helyen.',
        ])

        <div class="row">
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Fizetendő összeg',
                'value' => $stats['outstanding_total_label'],
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
                'value' => $stats['paid_this_month_label'],
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
                @if(! $has_employees)
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-id-badge',
                        'title' => 'Nincs aktív dolgozói jogviszony',
                        'text' => 'Jelenleg nincs aktív dolgozói jogviszonya rögzítve, ezért fizetési adat sem jeleníthető meg.',
                    ])
                @elseif($current_statement === null)
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-file-circle-question',
                        'title' => 'Még nincs elkészült havi elszámolása.',
                        'text' => 'Amint elkészül az első havi elszámolása, itt fog megjelenni.',
                    ])
                @else
                    <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 mb-4">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <h3 class="mb-0">Legutóbbi havi elszámolás</h3>
                                <span class="badge {{ $current_statement['badge']['class'] }}">{{ $current_statement['badge']['label'] }}</span>
                            </div>
                            <div class="text-muted">{{ $current_statement['period_label'] }}</div>
                        </div>

                        <div class="text-xl-end">
                            <div class="df-payment-label mb-2">Fennmaradó összeg</div>
                            <div class="df-payment-amount {{ $current_statement['remaining'] > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $current_statement['remaining_label'] }}
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 align-items-start">
                        <div class="col-12 col-xl-7">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Fizetendő teljes összeg</div>
                                        <div class="df-payment-value">{{ $current_statement['total_payable_label'] }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Már befizetett összeg</div>
                                        <div class="df-payment-value text-success">{{ $current_statement['paid_label'] }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Hátralévő összeg</div>
                                        <div class="df-payment-value {{ $current_statement['remaining'] > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $current_statement['remaining_label'] }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="df-payment-kpi">
                                        <div class="df-payment-label mb-2">Fizetési határidő</div>
                                        <div class="df-payment-value">{{ $current_statement['due_date_label'] }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="df-payment-label mb-0">Befizetési arány</span>
                                    <span class="small text-muted">{{ $current_statement['progress_percent'] }}%</span>
                                </div>
                                <div class="progress" style="height: .7rem;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $current_statement['progress_percent'] }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-5">
                            <div class="card df-payment-status-card h-100 {{ $current_statement['status_card']['color_class'] }}">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="df-payment-status-icon flex-shrink-0">
                                            <i class="{{ $current_statement['status_card']['icon'] }}"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-2">{{ $current_statement['status_card']['title'] }}</h5>
                                            <p class="mb-0 text-body-secondary">{{ $current_statement['status_card']['description'] }}</p>
                                        </div>
                                    </div>

                                    <div class="alert alert-light border mt-4 mb-0">
                                        <i class="fa-solid fa-circle-info me-2"></i>
                                        A befizetés módjáról az intézmény tájékoztatása az irányadó - online bankkártyás fizetés indítására ezen az oldalon jelenleg nincs lehetőség.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card df-payment-history-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">Korábbi befizetések</h4>
                        <div class="text-muted small">A dolgozói jogviszonyhoz kapcsolt befizetések előzményei.</div>
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
                                    <th class="text-end">Befizetett összeg</th>
                                    <th>Fizetési mód</th>
                                    <th>Hivatkozás</th>
                                    <th>Státusz</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($history as $row)
                                    <tr>
                                        <td>{{ $row['paid_at_label'] }}</td>
                                        <td>{{ $row['period_label'] }}</td>
                                        <td class="text-end">{{ $row['amount_label'] }}</td>
                                        <td>{{ $row['payment_method_label'] }}</td>
                                        <td>{{ $row['reference'] }}</td>
                                        <td><span class="badge {{ $row['status']['class'] }}">{{ $row['status']['label'] }}</span></td>
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
