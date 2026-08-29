@extends('layouts.superadmin')

@section('content')
@php
    $typeLabel = match ($institution->type) {
        'ovoda' => 'Óvoda',
        'iskola' => 'Iskola',
        'bolcsode' => 'Bölcsőde',
        default => $institution->type ?: '—',
    };

    $formatAmount = function ($amount) {
        if ($amount === null) {
            return '—';
        }

        return number_format((float) $amount, 0, ',', ' ') . ' Ft';
    };
@endphp

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="card-title mb-1">Intézményi díjszabások</h4>
                    <div class="text-muted">{{ $institution->name }}</div>
                    <div class="small text-muted">Típus: {{ $typeLabel }}</div>
                    <div class="small text-muted">Számlázási partner: {{ $institution->billingPartner?->name ?: 'Nincs partnerhez rendelve' }}</div>
                </div>
                <div class="d-flex gap-2">
                    @if($institution->billingPartner)
                        <a href="{{ route('dashboard.billing-partners.edit', $institution->billingPartner) }}" class="btn btn-outline-secondary btn-sm">
                            Partner szerkesztése
                        </a>
                    @endif
                    <a href="{{ route('dashboard.institutions.edit', $institution) }}" class="btn btn-outline-secondary btn-sm">
                        Vissza az intézményhez
                    </a>
                    <a href="{{ route('dashboard.institutions.billing-rates.create', $institution) }}" class="btn btn-primary btn-sm">
                        Új díjszabás
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($billingRates->isEmpty())
                    <div class="text-center py-5">
                        <h5 class="mb-2">Még nincs rögzített díjszabás.</h5>
                        <p class="text-muted mb-4">Az első díjszabással megadható a gyermekenkénti vagy a fix havi elszámolási alap.</p>
                        <a href="{{ route('dashboard.institutions.billing-rates.create', $institution) }}" class="btn btn-outline-primary">
                            Új díjszabás
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Érvényesség kezdete</th>
                                    <th>Érvényesség vége</th>
                                    <th>Ár gyermekenként</th>
                                    <th>Fix havi díj</th>
                                    <th>Minimum havi díj</th>
                                    <th>Állapot</th>
                                    <th class="text-end">Művelet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($billingRates as $billingRate)
                                    @php
                                        $isCurrent = $billingRate->valid_from->lte($today) && ($billingRate->valid_to === null || $billingRate->valid_to->gte($today));
                                        $isFuture = $billingRate->valid_from->gt($today);
                                        $statusLabel = $isCurrent ? 'Aktuális' : ($isFuture ? 'Jövőbeli' : 'Lejárt');
                                        $statusClass = $isCurrent ? 'bg-success' : ($isFuture ? 'bg-info text-dark' : 'bg-secondary');
                                    @endphp
                                    <tr>
                                        <td>{{ $billingRate->valid_from?->format('Y-m-d') ?: '—' }}</td>
                                        <td>{{ $billingRate->valid_to?->format('Y-m-d') ?: 'Nyitott' }}</td>
                                        <td>{{ $formatAmount($billingRate->price_per_child) }}</td>
                                        <td>{{ $formatAmount($billingRate->fixed_monthly_fee) }}</td>
                                        <td>{{ $formatAmount($billingRate->minimum_monthly_fee) }}</td>
                                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('dashboard.institutions.billing-rates.edit', [$institution, $billingRate]) }}" class="btn btn-sm btn-outline-primary">
                                                Szerkesztés
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
