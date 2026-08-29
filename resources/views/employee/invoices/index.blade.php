@extends('layouts.employee')

@section('page_title', 'Számláim')

@push('styles')
    <style>
        .df-employee-invoices-page .df-invoice-card {
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
        }

        .df-employee-invoices-page .df-invoice-info-bar {
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: #f8fafc;
            color: #475569;
        }

        .df-employee-invoices-page .df-invoice-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .df-employee-invoices-page .df-invoice-detail-item {
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: #fff;
            padding: 1rem;
        }

        .df-employee-invoices-page .df-invoice-label {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .df-employee-invoices-page .df-invoice-value {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
        }

        .df-employee-invoices-page .table > :not(caption) > * > * {
            vertical-align: middle;
        }

        @media (max-width: 767.98px) {
            .df-employee-invoices-page .df-invoice-actions {
                min-width: 12rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="df-employee-invoices-page">
        @include('layouts.partials.components.ui.page-header', [
            'title' => 'Számláim',
            'subtitle' => 'Itt tekintheti meg és töltheti le a DigiFood rendszerben kiállított számláit.',
        ])

        <div class="row">
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Összes számla',
                'value' => $stats['total_count_label'],
                'subtitle' => 'Nyilvántartott számla',
                'icon' => 'fa-solid fa-file-invoice',
                'color' => 'blue',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Idei számlák összege',
                'value' => $stats['current_year_gross_label'],
                'subtitle' => $stats['current_year_helper'],
                'icon' => 'fa-solid fa-wallet',
                'color' => 'green',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Legutóbbi számla',
                'value' => $stats['latest_invoice_date_label'],
                'subtitle' => $stats['latest_invoice_helper'],
                'icon' => 'fa-solid fa-calendar-days',
                'color' => 'orange',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Letölthető számlák',
                'value' => $stats['downloadable_count_label'],
                'subtitle' => 'PDF formátumban elérhető',
                'icon' => 'fa-solid fa-file-arrow-down',
                'color' => 'purple',
                'colClass' => 'col-xl-3 col-md-6',
            ])
        </div>

        <div class="alert df-invoice-info-bar mb-4">
            <i class="fa-solid fa-circle-info me-2"></i>{{ $info_text }}
        </div>

        <div class="card df-invoice-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                    <div>
                        <h4 class="mb-1">Kiállított számlák</h4>
                        <div class="text-muted small">A bejelentkezett dolgozói jogviszonyhoz tartozó számlák listája.</div>
                    </div>

                    <form method="GET" action="{{ route('employee.invoices') }}" class="d-flex align-items-center gap-2">
                        <label for="invoice-year-filter" class="small text-muted mb-0">Szűrés</label>
                        <select id="invoice-year-filter" name="year" class="form-select form-control" onchange="this.form.submit()">
                            @foreach($year_filter_options as $option)
                                <option value="{{ $option['value'] }}" @selected($year_filter === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if(! $has_invoices)
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-file-invoice',
                        'title' => 'Még nincs kiállított számlája.',
                        'text' => 'A kiállított számlák ezen az oldalon jelennek majd meg, és innen lesznek letölthetők.',
                    ])
                @elseif($invoices->count() === 0)
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-filter-circle-xmark',
                        'title' => 'Nincs találat a kiválasztott szűrőhöz.',
                        'text' => 'Próbálja meg az Összes év szűrőt, hogy minden elérhető számlát láthasson.',
                    ])
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Számla sorszáma</th>
                                    <th>Kiállítás dátuma</th>
                                    <th>Fizetési határidő</th>
                                    <th>Időszak</th>
                                    <th class="text-end">Végösszeg</th>
                                    <th>Számla státusza</th>
                                    <th>Fizetési státusz</th>
                                    <th class="text-end">Művelet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $row)
                                    <tr>
                                        <td>{{ $row['invoice_number'] }}</td>
                                        <td>{{ $row['invoiced_at_label'] }}</td>
                                        <td>{{ $row['due_date_label'] }}</td>
                                        <td>{{ $row['period_label'] }}</td>
                                        <td class="text-end">{{ $row['total_payable_label'] }}</td>
                                        <td><span class="badge {{ $row['invoice_status']['class'] }}">{{ $row['invoice_status']['label'] }}</span></td>
                                        <td><span class="badge {{ $row['payment_status']['class'] }}">{{ $row['payment_status']['label'] }}</span></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap justify-content-end gap-2 df-invoice-actions">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#invoice-details-{{ $row['id'] }}">
                                                    Részletek
                                                </button>
                                                @if($row['has_download'])
                                                    <a href="{{ $row['download_url'] }}" class="btn btn-outline-primary btn-sm">
                                                        PDF letöltése
                                                    </a>
                                                @endif
                                                @if($row['has_view'])
                                                    <a href="{{ $row['view_url'] }}" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener">
                                                        Megtekintés
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="invoice-details-{{ $row['id'] }}">
                                        <td colspan="8" class="bg-light">
                                            <div class="df-invoice-detail-grid p-2">
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Számla sorszáma</div>
                                                    <div class="df-invoice-value">{{ $row['details']['invoice_number'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Kiállítás dátuma</div>
                                                    <div class="df-invoice-value">{{ $row['details']['invoiced_at_label'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Fizetési határidő</div>
                                                    <div class="df-invoice-value">{{ $row['details']['due_date_label'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Elszámolási időszak</div>
                                                    <div class="df-invoice-value">{{ $row['details']['period_label'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Étkezési alapösszeg</div>
                                                    <div class="df-invoice-value">{{ $row['details']['meal_amount_label'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Előző egyenleg</div>
                                                    <div class="df-invoice-value">{{ $row['details']['previous_balance_label'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Végösszeg</div>
                                                    <div class="df-invoice-value">{{ $row['details']['total_payable_label'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Számlázási szolgáltató</div>
                                                    <div class="df-invoice-value">{{ $row['details']['invoice_provider_label'] }}</div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Számla státusza</div>
                                                    <div class="df-invoice-value">
                                                        <span class="badge {{ $row['details']['invoice_status_class'] }}">{{ $row['details']['invoice_status_label'] }}</span>
                                                    </div>
                                                </div>
                                                <div class="df-invoice-detail-item">
                                                    <div class="df-invoice-label mb-1">Fizetési státusz</div>
                                                    <div class="df-invoice-value">
                                                        <span class="badge {{ $row['details']['payment_status_class'] }}">{{ $row['details']['payment_status_label'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($invoices->hasPages())
                        <div class="mt-4">
                            {{ $invoices->links('vendor.pagination.digifood') }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
