@extends('layouts.superadmin')

@section('title', 'CIB tranzakciók')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'CIB tranzakciók',
        'subtitle' => 'Visszakereshető online bankkártyás tranzakciók a CIB Bank felé küldött adatokkal.',
        'buttons' => [
            [
                'url' => route('dashboard.institution.finance.payments'),
                'text' => 'Befizetések',
                'icon' => 'fa-solid fa-wallet',
                'class' => 'btn btn-outline-primary',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Tranzakciók',
            'value' => $summary['count'],
            'subtitle' => 'Szűrt CIB tételek száma',
            'icon' => 'fa-solid fa-receipt',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összeg',
            'value' => number_format($summary['amount_total'], 0, ',', ' ') . ' Ft',
            'subtitle' => 'A listázott tranzakciók AMO összege',
            'icon' => 'fa-solid fa-money-bill-wave',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Sikeres',
            'value' => $summary['successful_count'],
            'subtitle' => 'Lezárt sikeres tranzakciók',
            'icon' => 'fa-solid fa-circle-check',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Függőben',
            'value' => $summary['pending_count'],
            'subtitle' => 'Még nem zárt tranzakciók',
            'icon' => 'fa-solid fa-hourglass-half',
            'color' => 'orange',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Keresés és szűrés</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.finance.cib-transactions') }}">
                <div class="row align-items-end">
                    <div class="col-xl-4 col-lg-6 mb-3">
                        <label class="form-label">TRID, ANUM, referencia, gyermek, gondviselő</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Keresés">
                    </div>
                    <div class="col-xl-2 col-lg-3 mb-3">
                        <label class="form-label">Státusz</label>
                        <select name="status" class="form-control">
                            <option value="">Összes</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
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
                    <div class="col-xl-2 col-lg-3 mb-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">CIB tranzakciólista</h4>
            <span class="text-muted">Találatok: {{ $transactions->total() }}</span>
        </div>
        <div class="card-body">
            @if($transactions->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>TRID</th>
                            <th>ANUM</th>
                            <th>RC</th>
                            <th>RT</th>
                            <th>AMO</th>
                            <th>Szülő / gondviselő</th>
                            <th>Gyermekek</th>
                            <th>Fizetési referencia</th>
                            <th>Státusz</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactions as $transaction)
                            @php
                                $payment = $transaction->parentPayment;
                                $children = $payment?->items?->pluck('child.name')->filter()->unique()->values() ?? collect();
                                $label = $statuses[$transaction->status] ?? $transaction->status;
                                $badgeClass = match($transaction->status) {
                                    \App\Models\CibTransaction::STATUS_SUCCESSFUL => 'bg-success',
                                    \App\Models\CibTransaction::STATUS_PENDING => 'bg-warning text-dark',
                                    \App\Models\CibTransaction::STATUS_UNCERTAIN => 'bg-info text-dark',
                                    \App\Models\CibTransaction::STATUS_FAILED => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <tr>
                                <td>{{ $transaction->created_at?->format('Y.m.d. H:i') }}</td>
                                <td class="fw-semibold">{{ $transaction->trid }}</td>
                                <td>{{ $transaction->anum ?: '—' }}</td>
                                <td>{{ $transaction->final_rc ?: $transaction->init_rc ?: '—' }}</td>
                                <td>{{ $transaction->final_rt ?: $transaction->init_rt ?: '—' }}</td>
                                <td>{{ number_format($transaction->amount, 0, ',', ' ') }} Ft</td>
                                <td>{{ $transaction->guardian?->full_name ?: $transaction->user?->name ?: '—' }}</td>
                                <td>{{ $children->isNotEmpty() ? $children->implode(', ') : '—' }}</td>
                                <td>{{ $transaction->order_ref ?: $payment?->reference ?: '—' }}</td>
                                <td><span class="badge {{ $badgeClass }}">{{ $label }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $transactions->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-credit-card',
                    'title' => 'Még nincs CIB tranzakció',
                    'text' => 'Az első online bankkártyás fizetés után itt jelennek meg a visszakereshető tranzakciós mezők.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
