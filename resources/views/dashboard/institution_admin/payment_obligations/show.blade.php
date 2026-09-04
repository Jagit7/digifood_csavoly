@extends('layouts.superadmin')

@section('title', 'Havi részletező')

@php
    $formatForint = fn (int $amount): string => number_format($amount, 0, ',', ' ') . ' Ft';
    $paymentPeriod = \Illuminate\Support\Carbon::create($statement->year, $statement->month, 1);
    $mealPeriod = $paymentPeriod->copy()->addMonth();
    $creditPeriod = $paymentPeriod->copy()->subMonth();
    $isSplit = $statement->usesSplitPaymentModel();
    $financialSummary = $financialSummary ?? [];
    $displayTotalPayable = \App\Support\Finance\SettlementAmountPresenter::payableDisplayAmount((int) $statement->total_payable);
    $overpaymentAmount = \App\Support\Finance\SettlementAmountPresenter::overpaymentAmount((int) $statement->total_payable);
    $previousBalanceLabel = \App\Support\Finance\SettlementAmountPresenter::previousBalanceLabel((int) $statement->previous_balance);
    $previousBalanceDisplay = \App\Support\Finance\SettlementAmountPresenter::previousBalanceDisplayAmount((int) $statement->previous_balance);
@endphp

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Havi részletező',
        'subtitle' => $statement->child->name . ' · ' . $paymentPeriod->translatedFormat('Y. F') . 'i fizetési elszámolás',
        'buttons' => [
            [
                'text' => 'Excel export',
                'url' => route('dashboard.institution.payment-obligations.export', [
                    'child' => $child->id,
                    'year' => $year,
                    'month' => $month,
                ]),
                'icon' => 'fa-solid fa-file-excel',
                'class' => 'btn btn-success',
            ],
            [
                'text' => 'Pénzügyi korrekciók',
                'url' => route('dashboard.institution.payment-obligations.adjustments.index', $statement),
                'icon' => 'fa-solid fa-wallet',
                'class' => 'btn btn-outline-warning',
            ],
            [
                'text' => 'Vissza a listához',
                'url' => route('dashboard.institution.payment-obligations.index', ['month' => sprintf('%04d-%02d', $statement->year, $statement->month)]),
                'class' => 'btn btn-light',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', ['title' => $mealPeriod->translatedFormat('Y. F') . 'i tervezett étkezések', 'value' => $formatForint($statement->meal_amount), 'subtitle' => $statement->planned_meal_days . ' napból számolva', 'icon' => 'fa-solid fa-utensils', 'color' => 'blue'])
        @include('layouts.partials.components.ui.stats-card', ['title' => $creditPeriod->translatedFormat('Y. F') . 'i lemondási jóváírás', 'value' => $formatForint($statement->previous_cancellation_credit), 'subtitle' => $statement->previous_month_cancelled_days . ' nap jóváírása', 'icon' => 'fa-solid fa-reply', 'color' => 'green'])
        @if($isSplit)
            @include('layouts.partials.components.ui.stats-card', ['title' => 'Zsárica rész', 'value' => $formatForint($statement->foundation_total_payable), 'subtitle' => 'Befizetve: ' . $formatForint((int) ($financialSummary['foundation_paid'] ?? 0)), 'icon' => 'fa-solid fa-hand-holding-heart', 'color' => 'orange'])
            @include('layouts.partials.components.ui.stats-card', ['title' => 'Óvodai rész', 'value' => $formatForint($statement->kindergarten_total_payable), 'subtitle' => 'Befizetve: ' . $formatForint((int) ($financialSummary['kindergarten_paid'] ?? 0)), 'icon' => 'fa-solid fa-school', 'color' => 'green'])
        @else
            @include('layouts.partials.components.ui.stats-card', ['title' => 'Aktuális havi fizetendő', 'value' => $formatForint($statement->invoiceable_amount), 'subtitle' => 'A korábbi egyenleg és a korrekciók nélkül', 'icon' => 'fa-solid fa-hand-holding-heart', 'color' => 'orange'])
        @endif
        @include('layouts.partials.components.ui.stats-card', ['title' => $previousBalanceLabel, 'value' => $formatForint($previousBalanceDisplay), 'subtitle' => $isSplit ? 'Komponensenként hozott tartozás vagy túlfizetés' : 'A korábbi hónapok(ból) hozott egyenleg', 'icon' => 'fa-solid fa-clock-rotate-left', 'color' => $previousBalanceLabel === 'Korábbi túlfizetés' ? 'green' : 'blue'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Fizetendő összesen', 'value' => $formatForint($displayTotalPayable), 'subtitle' => $overpaymentAmount > 0 ? ('Fennmaradó túlfizetés: ' . $formatForint($overpaymentAmount)) : ($isSplit ? 'A két számla külön egyenlegéből áll össze' : 'A havi díj és a korábbi egyenleg összesen'), 'icon' => 'fa-solid fa-wallet', 'color' => 'purple'])
    </div>

    <div class="alert {{ $statement->status === \App\Models\PaymentObligation\MonthlyPaymentStatement::STATUS_CLOSED ? 'alert-secondary' : 'alert-info' }}">
        <strong>Állapot:</strong> {{ $statement->status === \App\Models\PaymentObligation\MonthlyPaymentStatement::STATUS_CLOSED ? 'Lezárt hónap' : 'Tervezet' }}
    </div>

    <div class="alert alert-light border">
        <div><strong>Fizetési hónap:</strong> {{ $paymentPeriod->translatedFormat('Y. F') }}</div>
        <div><strong>Étkezési időszak:</strong> {{ $mealPeriod->translatedFormat('Y. F') }}</div>
        <div><strong>Jóváírási időszak:</strong> {{ $creditPeriod->translatedFormat('Y. F') }}</div>
        <div class="mt-2 text-muted">A havi elszámolás a következő havi étkezési napokat számolja, és ebből vonja le az előző havi, jogos lemondások jóváírását.</div>
    </div>

    @if(count($statement->issues ?? []))
        <div class="alert alert-warning">
            <h6 class="alert-heading mb-2">Hiányos adatok</h6>
            <div class="fw-semibold mb-2">{{ $statement->child->name }}</div>
            <ul class="mb-0">
                @foreach($statement->issues as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Havi pénzügyi bontás</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Következő havi étkezési napok</span>
                        <strong>{{ number_format($statement->planned_meal_days, 0, ',', ' ') }} nap</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Előző havi jóváírt lemondások</span>
                        <strong>{{ number_format($statement->previous_month_cancelled_days, 0, ',', ' ') }} nap</strong>
                    </div>
                    @if($isSplit)
                        <div class="pt-3 pb-2 border-bottom">
                            <div class="fw-semibold mb-2">Zsárica Alapítvány rész</div>
                            <div class="d-flex justify-content-between"><span>Bruttó összeg</span><strong>{{ $formatForint($statement->foundation_gross_amount) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Lemondási jóváírás</span><strong>-{{ $formatForint($statement->foundation_cancellation_credit) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Kézi korrekció</span><strong>{{ $formatForint($statement->foundation_billing_adjustment_amount) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Korábbi egyenleg</span><strong>{{ $formatForint($statement->foundation_previous_balance) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Befizetve</span><strong>{{ $formatForint((int) ($financialSummary['foundation_paid'] ?? 0)) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Egyenleg</span><strong class="{{ ((int) ($financialSummary['foundation_balance'] ?? 0)) > 0 ? 'text-danger' : 'text-success' }}">{{ $formatForint((int) ($financialSummary['foundation_balance'] ?? 0)) }}</strong></div>
                        </div>
                        <div class="pt-3 pb-2 border-bottom">
                            <div class="fw-semibold mb-2">Óvodai étkezési díj</div>
                            <div class="d-flex justify-content-between"><span>Bruttó összeg</span><strong>{{ $formatForint($statement->kindergarten_gross_amount) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Kedvezmény</span><strong>-{{ $formatForint($statement->kindergarten_discount_amount) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Lemondási jóváírás</span><strong>-{{ $formatForint($statement->kindergarten_cancellation_credit) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Kézi korrekció</span><strong>{{ $formatForint($statement->kindergarten_billing_adjustment_amount) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Korábbi egyenleg</span><strong>{{ $formatForint($statement->kindergarten_previous_balance) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Befizetve</span><strong>{{ $formatForint((int) ($financialSummary['kindergarten_paid'] ?? 0)) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>Egyenleg</span><strong class="{{ ((int) ($financialSummary['kindergarten_balance'] ?? 0)) > 0 ? 'text-danger' : 'text-success' }}">{{ $formatForint((int) ($financialSummary['kindergarten_balance'] ?? 0)) }}</strong></div>
                        </div>
                    @else
                        <div class="pt-3 pb-2 border-bottom">
                            <div class="d-flex justify-content-between"><span>{{ $periods['meal_period_label'] }}i étkezési díj (következő havi)</span><strong>{{ $formatForint($statement->meal_amount) }}</strong></div>
                            <div class="d-flex justify-content-between"><span>{{ $periods['credit_period_label'] }}i lemondások jóváírása</span><strong>-{{ $formatForint($statement->previous_cancellation_credit) }}</strong></div>
                            @if((int) $statement->billing_adjustment_amount !== 0)
                                <div class="d-flex justify-content-between"><span>Egyéb korrekció (a havi díjat módosítja)</span><strong>{{ $formatForint($statement->billing_adjustment_amount) }}</strong></div>
                            @endif
                            <div class="d-flex justify-content-between border-top pt-2 mt-1">
                                <span class="fw-semibold">Aktuális havi fizetendő</span>
                                <strong>{{ $formatForint($statement->invoiceable_amount) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>{{ $previousBalanceLabel }}</span>
                                <strong>{{ $formatForint($previousBalanceDisplay) }}</strong>
                            </div>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Fizetési határidő</span>
                        <strong>{{ $statement->due_at?->format('Y.m.d. H:i') ?? '—' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between pt-3">
                        <span class="fw-semibold">Fizetendő összesen</span>
                        <strong>{{ $formatForint($displayTotalPayable) }}</strong>
                    </div>
                    @if($overpaymentAmount > 0)
                        <div class="d-flex justify-content-between text-success">
                            <span class="fw-semibold">Fennmaradó túlfizetés</span>
                            <strong>{{ $formatForint($overpaymentAmount) }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Kapcsolatok és számla</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Gyermek</div>
                        <div class="fw-semibold">{{ $statement->child?->name ?? '—' }}</div>
                        <div class="small text-muted">{{ $statement->child?->group_name ?: 'Nincs osztály / csoport' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Szülő / gondviselő</div>
                        <div class="fw-semibold">{{ $statement->display_guardians ?? ($statement->child?->guardians?->pluck('full_name')->implode(', ') ?: '—') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Kapcsolódó számla</div>
                        <div class="fw-semibold">{{ $statement->invoice_number ?: 'Nincs számlaszám' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Számla státusz</div>
                        <div class="fw-semibold">{{ $statement->invoice_status ?: 'Nincs' }}</div>
                    </div>
                    <div>
                        <div class="text-muted small">Nettó egyenleg</div>
                        <div class="fw-semibold {{ ((int) ($financialSummary['net_balance'] ?? 0)) > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $formatForint((int) ($financialSummary['net_balance'] ?? $statement->total_payable)) }}
                        </div>
                        @if($isSplit)
                            <div class="small text-muted mt-2">A két számla túlfizetése és tartozása külön követett egyenleg, nem keveredik automatikusan.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Napi részletezés</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-responsive-md align-middle">
                    <thead>
                    <tr>
                        <th>Dátum</th>
                        <th>Nap</th>
                        <th>Eredeti státusz</th>
                        <th>Végleges státusz</th>
                        <th>Listaár</th>
                        <th>Kedvezmény</th>
                        @if($isSplit)
                            <th>Zsárica díj</th>
                            <th>Óvodai díj</th>
                        @endif
                        <th>Fizetendő összeg</th>
                        <th>Megjegyzés</th>
                        <th class="text-end">Műveletek</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($detailRows as $row)
                        @php($day = $row['day'])
                        @php($isHighlighted = $highlightDate === $row['date'])
                        @php($isEditable = $statement->status === \App\Models\PaymentObligation\MonthlyPaymentStatement::STATUS_DRAFT)
                        <tr id="day-{{ $row['date'] }}" @class(['table-warning' => $isHighlighted])>
                            <td>{{ $row['date_display'] }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $row['day_name'] }}</span></td>
                            <td><span class="badge {{ $row['original_status_badge_class'] }}">{{ $row['original_status_label'] }}</span></td>
                            <td>
                                <span class="badge {{ $row['final_status_badge_class'] }}">{{ $row['final_status_label'] }}</span>
                                @if($day->manually_modified)
                                    <span class="badge bg-dark ms-1">Kézi</span>
                                @endif
                            </td>
                            <td>
                                @if($row['has_missing_price'])
                                    <span class="badge bg-danger">Nincs ár</span>
                                @else
                                    {{ $formatForint($row['list_price_amount']) }}
                                @endif
                            </td>
                            <td>
                                @if($row['discount_percent'] === 0)
                                    <span class="badge bg-secondary">Nincs</span>
                                @else
                                    <span class="badge bg-success">{{ $row['discount_display'] }}</span>
                                @endif
                            </td>
                            @if($isSplit)
                                <td>{{ $formatForint($row['foundation_payable_amount']) }}</td>
                                <td>
                                    <div>{{ $formatForint($row['kindergarten_payable_amount']) }}</div>
                                    @if($row['kindergarten_discount_percent'] > 0)
                                        <div class="small text-muted">Kedvezmény: {{ $row['kindergarten_discount_percent'] }}%</div>
                                    @endif
                                </td>
                            @endif
                            <td>
                                @if($row['has_missing_price'])
                                    <span class="badge bg-danger">Nincs ár</span>
                                @elseif($row['payable_amount'] === 0)
                                    <strong class="text-muted">{{ $formatForint(0) }}</strong>
                                @else
                                    <strong class="text-success">{{ $formatForint($row['payable_amount']) }}</strong>
                                @endif
                            </td>
                            <td class="text-wrap">
                                @foreach($row['note_lines'] as $noteLine)
                                    <div>{{ $noteLine }}</div>
                                @endforeach
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-xs {{ $isEditable ? 'btn-primary' : 'btn-light text-muted border' }}"
                                            @if($isEditable) data-bs-toggle="modal" data-bs-target="#dayModal{{ $day->id }}" @endif
                                            title="{{ $isEditable ? 'Szerkesztés' : 'A hónap le van zárva, ezért ez a nap nem szerkeszthető. Módosításhoz nyisd újra a hónapot a listaoldalon (Újranyitás).' }}"
                                            @disabled(! $isEditable)>
                                        <i class="fa {{ $isEditable ? 'fa-pen' : 'fa-lock' }}"></i>
                                    </button>
                                    @if($day->manually_modified)
                                        <form method="POST"
                                              action="{{ route('dashboard.institution.payment-obligations.days.reset', ['statement' => $statement->id, 'day' => $day->id]) }}"
                                              class="d-inline confirm-form"
                                              data-title="Biztosan vissza szeretné állítani a kézi módosítást?"
                                              data-text="A nap eredeti státusza és összege áll vissza."
                                              data-confirm-button-text="Igen, visszaállítom">
                                            @csrf
                                            <button type="submit" class="btn btn-xs {{ $isEditable ? 'btn-outline-warning' : 'btn-light text-muted border' }}"
                                                    title="{{ $isEditable ? 'Kézi módosítás visszaállítása' : 'A hónap le van zárva, ezért ez nem állítható vissza. Nyisd újra a hónapot a listaoldalon (Újranyitás).' }}"
                                                    @disabled(! $isEditable)>
                                                <i class="fa {{ $isEditable ? 'fa-rotate-left' : 'fa-lock' }}"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@foreach($detailRows as $row)
    @php($day = $row['day'])
    <div class="modal fade" id="dayModal{{ $day->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST"
                      action="{{ route('dashboard.institution.payment-obligations.days.update', ['statement' => $statement->id, 'day' => $day->id]) }}"
                      class="confirm-form"
                      data-title="Biztosan menteni szeretné a napi módosítást?"
                      data-text="A napi összeg és státusz frissülni fog a havi összesítésekben is."
                      data-confirm-button-text="Igen, mentem">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $row['date_display'] }} módosítása</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Státusz</label>
                            <select name="status" class="form-control" required>
                                @foreach($statusOptions as $statusOption)
                                    <option value="{{ $statusOption['value'] }}" @selected($day->status === $statusOption['value'])>{{ $statusOption['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fizetendő összeg</label>
                            <input type="number" name="payable_amount" class="form-control" min="0" value="{{ $day->payable_amount }}" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Módosítás indoka</label>
                            <textarea name="modification_reason" class="form-control" rows="3" maxlength="191" required>{{ $day->modification_reason }}</textarea>
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
@endforeach
@endsection
