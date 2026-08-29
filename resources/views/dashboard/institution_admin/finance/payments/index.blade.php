@extends('layouts.superadmin')

@section('title', 'Befizetések')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Befizetések',
        'subtitle' => 'Az intézményhez rögzített befizetések kezelése és nyomon követése.',
        'buttons' => [
            [
                'url' => route('dashboard.institution.finance.cib-transactions'),
                'text' => 'CIB tranzakciók',
                'icon' => 'fa-solid fa-credit-card',
                'class' => 'btn btn-outline-primary',
            ],
            [
                'url' => route('dashboard.institution.finance.payments.create'),
                'text' => 'Új befizetés',
                'icon' => 'fa-solid fa-plus',
                'class' => 'btn btn-primary',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Szűrt tételek',
            'value' => $summary['count'],
            'subtitle' => 'Találatok száma a jelenlegi szűrés szerint',
            'icon' => 'fa-solid fa-list-check',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Szűrt összeg',
            'value' => number_format($summary['amount_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'Az aktuálisan listázott befizetések összege',
            'icon' => 'fa-solid fa-wallet',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Teljesült befizetések',
            'value' => number_format($summary['completed_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'A teljesült státuszú tételek összege',
            'icon' => 'fa-solid fa-circle-check',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Függő tételek',
            'value' => $summary['pending_count'],
            'subtitle' => 'Még nem lezárt befizetések',
            'icon' => 'fa-solid fa-hourglass-half',
            'color' => 'orange',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.finance.payments') }}">
                <div class="row align-items-end">
                    <div class="col-xl-4 col-lg-6 mb-3">
                        <label class="form-label">Gyermek vagy gondviselő neve</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Keresés név alapján">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Összes</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Fizetési mód</label>
                        <select name="payment_method" class="form-control">
                            <option value="">Összes</option>
                            @foreach($paymentMethodOptions as $value => $label)
                                <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Dátumtól</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Dátumig</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->hasAny(['search', 'status', 'payment_method', 'date_from', 'date_to']))
                            <a href="{{ route('dashboard.institution.finance.payments') }}" class="btn btn-light">
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
            <h4 class="card-title mb-0">Befizetések listája</h4>
            <span class="text-muted">Találatok: {{ $payments->total() }}</span>
        </div>
        <div class="card-body">
            @if($payments->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Befizetés dátuma</th>
                            <th>Gyermek</th>
                            <th class="text-end">Műveletek</th>
                            <th>Szülő / gondviselő</th>
                            <th>Összeg</th>
                            <th>Fizetési mód</th>
                            <th>Kapcsolódó kötelezettség</th>
                            <th>Kapcsolódó számla</th>
                            <th>Státusz</th>
                            <th>Rögzítő</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($payments as $payment)
                            @php
                                $statusMeta = \App\Models\InstitutionPayment::statusMeta($payment->status);
                                $methodMeta = \App\Models\InstitutionPayment::paymentMethodMeta($payment->payment_method);
                                $statement = $payment->monthlyPaymentStatement;
                                $relatedInvoice = $payment->invoice_number ?: $statement?->invoice_number;
                            @endphp
                            <tr>
                                <td>{{ $payment->paid_at?->format('Y.m.d. H:i') }}</td>
                                <td>
                                    <strong>{{ $payment->child?->name ?? '—' }}</strong>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.finance.payments.show', $payment) }}" class="btn btn-xs btn-outline-primary" title="Részletek">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="{{ route('dashboard.institution.finance.payments.edit', $payment) }}" class="btn btn-xs btn-outline-warning" title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                    <form method="POST"
                                          action="{{ route('dashboard.institution.finance.payments.destroy', $payment) }}"
                                          class="d-inline confirm-form"
                                          data-title="Biztosan törlöd ezt a befizetést?"
                                          data-text="A törlés nem vonható vissza."
                                          data-confirm-button-text="Igen, törlöm"
                                          data-confirm-button-color="#dc3545">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Törlés">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>{{ $payment->guardian?->full_name ?: '—' }}</td>
                                <td><strong>{{ number_format($payment->amount, 0, ',', ' ') }} Ft</strong></td>
                                <td>
                                    <span class="badge rounded-pill {{ $methodMeta['class'] }}">
                                        <i class="{{ $methodMeta['icon'] }} me-1"></i>{{ $methodMeta['label'] }}
                                    </span>
                                </td>
                                <td>
                                    @if($statement)
                                        <div class="fw-semibold">{{ sprintf('%04d.%02d', $statement->year, $statement->month) }}</div>
                                        <div class="small text-muted">{{ $statement->child?->name }}</div>
                                    @else
                                        <span class="text-muted">Nincs kapcsolva</span>
                                    @endif
                                </td>
                                <td>{{ $relatedInvoice ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                                </td>
                                <td>{{ $payment->recordedBy?->name ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $payments->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-wallet',
                    'title' => 'Még nincs befizetés rögzítve',
                    'text' => 'Az első befizetés létrehozásával elindítható az intézményi pénzügyi nyilvántartás.',
                    'buttonText' => 'Új befizetés',
                    'buttonUrl' => route('dashboard.institution.finance.payments.create'),
                    'buttonIcon' => 'fa-solid fa-plus',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
