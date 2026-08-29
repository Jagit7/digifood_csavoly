@extends('layouts.superadmin')

@section('title', 'Tartozás részletei')

@section('content')
<div class="container-fluid">
    @php
        $statusMeta = $statement->debt_status;
        $invoiceViewUrl = $statement->invoice_url
            ?: ($statement->invoice_pdf_path ? \Illuminate\Support\Facades\Storage::url($statement->invoice_pdf_path) : null);
        $completedPaymentsTotal = $payments->where('status', \App\Models\InstitutionPayment::STATUS_COMPLETED)->sum('amount');
        $primaryBadgeClass = $statusMeta['primary']['class'];
        if (!str_contains($primaryBadgeClass, 'text-dark') && !str_contains($primaryBadgeClass, 'text-muted')) {
            $primaryBadgeClass .= ' text-white';
        }
        $partialBadgeClass = $statusMeta['partial']['class'] ?? null;
        if ($partialBadgeClass && !str_contains($partialBadgeClass, 'text-dark') && !str_contains($partialBadgeClass, 'text-muted')) {
            $partialBadgeClass .= ' text-white';
        }
    @endphp

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Tartozás részletei',
        'subtitle' => ($statement->child?->name ?? 'Ismeretlen gyermek') . ' – ' . $periods['payment_period_label'] . 'i fizetési elszámolás',
        'buttons' => [
            [
                'url' => route('dashboard.institution.finance.payments.create', [
                    'child_id' => $statement->child_id,
                    'guardian_id' => $statement->prefill_guardian_id,
                    'monthly_payment_statement_id' => $statement->id,
                    'amount' => $statement->remaining_amount,
                ]),
                'text' => 'Befizetés rögzítése (teljes űrlap)',
                'icon' => 'fa-solid fa-plus',
                'class' => 'btn btn-outline-success',
            ],
            [
                'url' => route('dashboard.institution.payment-obligations.show', $statement),
                'text' => 'Fizetési kötelezettség',
                'icon' => 'fa-solid fa-file-lines',
                'class' => 'btn btn-light',
            ],
            [
                'url' => route('dashboard.institution.finance.debts'),
                'text' => 'Vissza a listához',
                'icon' => 'fa-solid fa-arrow-left',
                'class' => 'btn btn-light',
            ],
        ],
    ])

    @if($statement->remaining_amount > 0)
        <div class="text-md-end mb-3">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#quickPayModal{{ $statement->id }}">
                <i class="fa-solid fa-check me-1"></i>Gyors fizetés rögzítése
            </button>
        </div>

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
    @endif

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Tényleges fizetendő',
            'value' => number_format($statement->total_payable, 0, ',', ' ') . ' Ft',
            'subtitle' => 'A fizetési hónap teljes kötelezettsége',
            'icon' => 'fa-solid fa-file-invoice-dollar',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Completed befizetések',
            'value' => number_format($statement->completed_payments_total, 0, ',', ' ') . ' Ft',
            'subtitle' => 'Csak a completed státuszú befizetések csökkentik a tartozást',
            'icon' => 'fa-solid fa-money-bill-transfer',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Fennmaradó tartozás',
            'value' => number_format($statement->remaining_amount, 0, ',', ' ') . ' Ft',
            'subtitle' => 'Szerveroldalon újraszámított fennmaradó összeg',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Státusz',
            'value' => $statusMeta['primary']['label'],
            'subtitle' => $statement->partial_paid ? 'Részben fizetve' : 'Nincs részfizetés',
            'icon' => 'fa-solid fa-clock',
            'color' => $statement->late_days > 0 ? 'red' : 'purple',
        ])
    </div>

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Kötelezettség bontása</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border">
                        <div><strong>Fizetési hónap:</strong> {{ $periods['payment_period_label'] }}</div>
                        <div><strong>Étkezési időszak:</strong> {{ $periods['meal_period_label'] }}</div>
                        <div><strong>Jóváírási időszak:</strong> {{ $periods['credit_period_label'] }}</div>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $periods['meal_period_label'] }}i étkezések</span>
                        <strong>{{ number_format($statement->meal_amount, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $periods['credit_period_label'] }}i lemondások jóváírása</span>
                        <strong>-{{ number_format($statement->previous_cancellation_credit, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Egyéb korrekció</span>
                        <strong>{{ number_format($statement->billing_adjustment_amount, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Aktuális havi fizetendő</span>
                        <strong>{{ number_format($statement->invoiceable_amount, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Korábbi elmaradásból származó összeg</span>
                        <strong class="{{ $statement->previous_balance > 0 ? 'text-danger' : '' }}">{{ number_format($statement->previous_balance, 0, ',', ' ') }} Ft</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Fizetési határidő</span>
                        <strong>{{ $statement->due_at->format('Y.m.d. H:i') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Állapot</span>
                        <div class="text-end">
                            <span class="badge {{ $primaryBadgeClass }}">{{ $statusMeta['primary']['label'] }}</span>
                            @if($statusMeta['partial'])
                                <div class="mt-1">
                                    <span class="badge {{ $partialBadgeClass }}">{{ $statusMeta['partial']['label'] }}</span>
                                </div>
                            @endif
                            @if($statement->late_days > 0)
                                <div class="small text-danger mt-1">{{ $statement->late_days }} nap késés</div>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex justify-content-between pt-3">
                        <span class="fw-semibold">Tényleges fizetendő</span>
                        <strong>{{ number_format($statement->total_payable, 0, ',', ' ') }} Ft</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Számla és kapcsolatok</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Gyermek</div>
                        <div class="fw-semibold">{{ $statement->child?->name ?? '—' }}</div>
                        <div class="small text-muted">{{ $statement->child?->group_name ?: 'Nincs osztály / csoport' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Szülő / gondviselő</div>
                        <div class="fw-semibold">{{ $statement->display_guardians }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Kapcsolódó számla</div>
                        <div class="fw-semibold">{{ $statement->invoice_number ?: 'Nincs számlaszám' }}</div>
                        @if($invoiceViewUrl)
                            <a href="{{ $invoiceViewUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-warning mt-2">
                                <i class="fa-solid fa-file-invoice me-1"></i>Kapcsolódó számla megnyitása
                            </a>
                        @elseif($statement->invoice_number)
                            <button type="button" class="btn btn-sm btn-outline-warning mt-2" disabled>
                                <i class="fa-solid fa-file-invoice me-1"></i>Számla megnyitása hamarosan
                            </button>
                        @endif
                    </div>
                    <div>
                        <div class="text-muted small">Számla státusz</div>
                        <div class="fw-semibold">{{ $statement->invoice_status ?: 'Nincs' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Kapcsolódó befizetések</h4>
        </div>
        <div class="card-body">
            @if($payments->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Összeg</th>
                            <th>Fizetési mód</th>
                            <th>Státusz</th>
                            <th>Hivatkozás</th>
                            <th>Rögzítő</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($payments as $payment)
                            @php
                                $paymentStatus = \App\Models\InstitutionPayment::statusMeta($payment->status);
                                $paymentMethod = \App\Models\InstitutionPayment::paymentMethodMeta($payment->payment_method);
                                $paymentStatusClass = $paymentStatus['class'];
                                if (!str_contains($paymentStatusClass, 'text-dark') && !str_contains($paymentStatusClass, 'text-muted')) {
                                    $paymentStatusClass .= ' text-white';
                                }
                            @endphp
                            <tr>
                                <td>{{ $payment->paid_at?->format('Y.m.d. H:i') }}</td>
                                <td>{{ number_format($payment->amount, 0, ',', ' ') }} Ft</td>
                                <td>
                                    <span class="badge rounded-pill {{ $paymentMethod['class'] }}">
                                        <i class="{{ $paymentMethod['icon'] }} me-1"></i>{{ $paymentMethod['label'] }}
                                    </span>
                                </td>
                                <td><span class="badge {{ $paymentStatusClass }}">{{ $paymentStatus['label'] }}</span></td>
                                <td>{{ $payment->reference ?: '—' }}</td>
                                <td>{{ $payment->recordedBy?->name ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr>
                            <th>Completed befizetések</th>
                            <th>{{ number_format($completedPaymentsTotal, 0, ',', ' ') }} Ft</th>
                            <th colspan="4"></th>
                        </tr>
                        <tr>
                            <th>Fennmaradó tartozás</th>
                            <th class="text-danger">{{ number_format($statement->remaining_amount, 0, ',', ' ') }} Ft</th>
                            <th colspan="4"></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-wallet',
                    'title' => 'Ehhez a kötelezettséghez még nincs befizetés',
                    'text' => 'Az első completed befizetés után a rendszer automatikusan csökkenti a fennmaradó tartozást.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
