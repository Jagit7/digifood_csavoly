@extends('layouts.superadmin')

@section('title', 'Közvetlen intézményi számlázás (SaaS)')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Közvetlen intézményi számlázás (SaaS díj)',
        'subtitle' => 'Csak azoknak az aktív intézményeknek, amelyek NINCSENEK számlázási partnerhez rendelve. Aktív étkezőszám (diák + dolgozó) alapú havidíj, kiküldve a(z) '.$recipientEmail.' címre minden hónap '.$dayOfMonth.'. napján.',
        'buttons' => [
            [
                'url' => route('dashboard.revenue-overview.index'),
                'text' => 'Bevétel áttekintés',
                'class' => 'btn btn-outline-primary',
                'icon' => 'fa-solid fa-chart-line',
            ],
        ],
    ])

    @error('saas_billing_summary')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Számlázható intézmények',
            'value' => $rows->count(),
            'subtitle' => 'Van beállítva Ft/fő díj, nincs partnerhez rendelve',
            'icon' => 'fa-solid fa-school',
            'color' => 'blue',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív étkezők összesen',
            'value' => $totalEaters,
            'subtitle' => 'Diák + dolgozó, a fenti intézményekben',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'green',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Számlázható összeg',
            'value' => number_format($totalAmount, 0, ',', ' ').' Ft',
            'subtitle' => $monthLabel,
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'color' => 'orange',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Hiányzó díjszabás',
            'value' => $missingRateInstitutions->count(),
            'subtitle' => 'Aktív, partnerhez nem rendelt intézmény, nincs díja',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="card-title mb-1">{{ $monthLabel }} - előnézet</h4>
                <p class="text-muted mb-0">
                    Ez azt mutatja, mi menne ki, ha most küldenénk el az összesítőt - a már ebben a hónapban
                    számlázott vagy fizetett intézmények itt nem szerepelnek újra.
                    @if($alreadySent)
                        Ebben a hónapban már ki lett küldve az automatikus összesítő.
                    @endif
                </p>
            </div>

            <form method="POST"
                  action="{{ route('dashboard.saas-billing-summary.send') }}"
                  class="confirm-form"
                  data-title="Biztosan elküldi az összesítőt most?"
                  data-text="Az e-mail a(z) {{ $recipientEmail }} címre megy ki, {{ $rows->count() }} intézmény adatával."
                  data-confirm-button-text="Küldés"
                  data-cancel-button-text="Mégse">
                @csrf
                <button type="submit" class="btn btn-primary" @disabled($rows->isEmpty())>
                    <i class="fa-solid fa-paper-plane me-1"></i>
                    Küldés most{{ $alreadySent ? ' (újraküldés)' : '' }}
                </button>
            </form>
        </div>

        <div class="card-body">
            @if($rows->isEmpty())
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-file-invoice-dollar',
                    'title' => 'Nincs számlázható intézmény',
                    'text' => 'Állíts be Ft/fő díjat legalább egy aktív, partnerhez nem rendelt intézménynél az intézmény szerkesztő oldalán.',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Intézmény</th>
                            <th class="text-end">Aktív gyerek</th>
                            <th class="text-end">Aktív dolgozó</th>
                            <th class="text-end">Összes étkező</th>
                            <th class="text-end">Ft / fő</th>
                            <th class="text-end">Számlázható összeg</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row['institution_name'] }}</div>
                                    <div class="small text-muted">
                                        {{ $row['billing_tax_number'] ?: 'Nincs adószám megadva' }}
                                    </div>
                                </td>
                                <td class="text-end">{{ $row['children_count'] }}</td>
                                <td class="text-end">{{ $row['employees_count'] }}</td>
                                <td class="text-end fw-semibold">{{ $row['eaters_count'] }}</td>
                                <td class="text-end">{{ number_format($row['rate'], 0, ',', ' ') }} Ft</td>
                                <td class="text-end fw-semibold">{{ number_format($row['total_amount'], 0, ',', ' ') }} Ft</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if($missingRateInstitutions->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Hiányzó díjszabás</h4>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Ezek az aktív, partnerhez nem rendelt intézmények nincsenek benne a fenti összesítőben, mert
                    nincs beállítva náluk Digifood havidíj / aktív étkező érték:
                </p>
                <ul class="mb-0">
                    @foreach($missingRateInstitutions as $institution)
                        <li>
                            <a href="{{ route('dashboard.institutions.edit', $institution) }}">{{ $institution->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if($institutionsHandledByPartner->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Partneri számlázáson keresztül kezelt intézmények</h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">
                    Ezeknél az aktív intézményeknél van számlázási partner beállítva, ezért a "Partneri ügyfél
                    számlázás" oldalon számlázandók, itt a duplikáció elkerülése miatt nem jelennek meg:
                </p>
                <ul class="mb-0">
                    @foreach($institutionsHandledByPartner as $institution)
                        <li>{{ $institution->name }} <span class="text-muted">— {{ $institution->billingPartner?->name ?? 'ismeretlen partner' }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Számlázási tételek</h4>
            <p class="text-muted mb-0">Itt lehet intézményenként, hónaponként végigvezetni a számlázás állapotát.</p>
        </div>
        <div class="card-body">
            @if($items->isEmpty())
                <div class="text-muted">Még nincs egyetlen számlázási tétel sem - küldj ki egy összesítőt előbb.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Hónap</th>
                            <th>Intézmény</th>
                            <th class="text-end">Étkezők</th>
                            <th class="text-end">Összeg</th>
                            <th>Állapot</th>
                            <th>Számlaszám</th>
                            <th class="text-end">Műveletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item->run?->month_label }}</td>
                                <td>{{ $item->institution_name_snapshot }}</td>
                                <td class="text-end">{{ $item->eaters_count }}</td>
                                <td class="text-end fw-semibold">{{ number_format($item->amount, 0, ',', ' ') }} Ft</td>
                                <td>
                                    <span class="badge {{ $item->status_badge_class }} light">{{ $item->status_label }}</span>
                                </td>
                                <td>{{ $item->invoice_number ?: '—' }}</td>
                                <td class="text-end">
                                    @if($item->status === \App\Models\SaasBillingSummaryItem::STATUS_PENDING)
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary js-mark-invoiced-trigger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#markInvoicedModal"
                                                data-action="{{ route('dashboard.saas-billing-summary.items.mark-invoiced', $item) }}"
                                                data-institution="{{ $item->institution_name_snapshot }}">
                                            Számlázás
                                        </button>
                                    @elseif($item->status === \App\Models\SaasBillingSummaryItem::STATUS_INVOICED)
                                        <form method="POST"
                                              action="{{ route('dashboard.saas-billing-summary.items.mark-paid', $item) }}"
                                              class="confirm-form d-inline"
                                              data-title="Biztosan fizetettnek jelöli?"
                                              data-text="{{ $item->institution_name_snapshot }} - {{ number_format($item->amount, 0, ',', ' ') }} Ft"
                                              data-confirm-button-text="Fizetve"
                                              data-cancel-button-text="Mégse">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                Fizetettnek jelöl
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">
                                            {{ $item->paid_at?->format('Y.m.d.') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $items->links('vendor.pagination.digifood') }}
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">E-mail küldési napló</h4>
            <p class="text-muted mb-0">Ez csak az összesítő e-mail kézbesítését mutatja, nem a számlázás állapotát.</p>
        </div>
        <div class="card-body">
            @if($lastRuns->isEmpty())
                <div class="text-muted">Még nem ment ki egyetlen összesítő sem.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Hónap</th>
                            <th class="text-end">Intézmények</th>
                            <th class="text-end">Összeg</th>
                            <th>Állapot</th>
                            <th>Indította</th>
                            <th>Elküldve</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($lastRuns as $run)
                            <tr>
                                <td>{{ $run->month_label }}</td>
                                <td class="text-end">{{ $run->institution_count }}</td>
                                <td class="text-end">{{ number_format($run->total_amount, 0, ',', ' ') }} Ft</td>
                                <td>
                                    <span class="badge {{ $run->status_badge_class }} light">{{ $run->status_label }}</span>
                                    @if($run->status === \App\Models\SaasBillingSummaryRun::STATUS_FAILED && $run->error_message)
                                        <div class="small text-danger mt-1">{{ $run->error_message }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $run->triggered_by === 'manual' ? 'Kézi' : 'Automatikus' }}
                                    @if($run->triggeredByUser)
                                        <div class="small text-muted">{{ $run->triggeredByUser->name }}</div>
                                    @endif
                                </td>
                                <td>{{ $run->sent_at?->format('Y.m.d. H:i') ?: '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Megosztott modal a "Számlázás" művelethez --}}
    <div class="modal fade" id="markInvoicedModal" tabindex="-1" aria-labelledby="markInvoicedModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="markInvoicedForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="markInvoicedModalLabel">Számlázottnak jelölés</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Intézmény: <strong id="markInvoicedInstitutionName"></strong></p>

                        <div class="mb-3">
                            <label for="invoice_number" class="form-label">Számlaszám</label>
                            <input type="text"
                                   class="form-control"
                                   id="invoice_number"
                                   name="invoice_number"
                                   required
                                   maxlength="100">
                        </div>

                        <div class="mb-0">
                            <label for="note" class="form-label">Megjegyzés (opcionális)</label>
                            <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                        <button type="submit" class="btn btn-primary">Mentés</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-mark-invoiced-trigger').forEach(function (button) {
                button.addEventListener('click', function () {
                    const form = document.getElementById('markInvoicedForm');
                    const nameLabel = document.getElementById('markInvoicedInstitutionName');

                    if (form) {
                        form.action = button.dataset.action;
                    }

                    if (nameLabel) {
                        nameLabel.textContent = button.dataset.institution || '';
                    }
                });
            });
        });
    </script>
@endpush
