@extends('layouts.superadmin')

@section('title', 'Partneri ügyfél számlázás')

@section('content')
@php
    $formatMoney = function ($amount) {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $numeric = (float) $amount;
        $decimals = floor($numeric) == $numeric ? 0 : 2;

        return number_format($numeric, $decimals, ',', ' ') . ' Ft';
    };

    $statusMeta = function (?string $status) {
        return match ($status) {
            'draft' => ['label' => 'Tervezet', 'class' => 'badge badge-warning light'],
            'invoiced' => ['label' => 'Számlázva', 'class' => 'badge badge-info light'],
            'paid' => ['label' => 'Fizetve', 'class' => 'badge badge-success light'],
            default => ['label' => 'Nincs létrehozva', 'class' => 'badge badge-secondary light'],
        };
    };
@endphp

@include('layouts.partials.components.ui.page-header', [
    'title' => 'Partneri ügyfél számlázás',
    'subtitle' => 'Csak azoknak az intézményeknek, amelyek számlázási partnerhez (viszonteladóhoz) vannak rendelve. Havi, gyermeklétszám alapú számlázási pillanatképek partnerenként.',
])

<div class="d-flex flex-wrap gap-2 align-items-center mb-4">
    <a href="{{ route('dashboard.partner-monthly-billings.index', ['month' => $previousMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-chevron-left me-1"></i>Előző hónap
    </a>
    <div class="btn btn-warning btn-sm disabled">{{ $selectedMonthLabel }}</div>
    <a href="{{ route('dashboard.partner-monthly-billings.index', ['month' => $nextMonthQuery]) }}" class="btn btn-outline-secondary btn-sm">
        Következő hónap<i class="fa-solid fa-chevron-right ms-1"></i>
    </a>
    <a href="{{ route('dashboard.partner-monthly-billings.index', ['month' => $currentMonthQuery]) }}" class="btn btn-success btn-sm">
        Aktuális hónap
    </a>
</div>

<div class="row">
    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Aktív partnerek',
        'value' => $stats['active_partners'],
        'subtitle' => 'Aktív számlázási partnerek száma',
        'icon' => 'fa-solid fa-building-circle-check',
        'color' => 'blue',
    ])
    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Kapcsolt intézmények',
        'value' => $stats['linked_institutions'],
        'subtitle' => 'Aktív partnerekhez rendelt intézmények',
        'icon' => 'fa-solid fa-school',
        'color' => 'green',
    ])
    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Snapshotban rögzített gyermekek',
        'value' => $stats['snapshot_children'],
        'subtitle' => 'Csak a már létrehozott havi snapshotokból',
        'icon' => 'fa-solid fa-children',
        'color' => 'orange',
    ])
    @include('layouts.partials.components.ui.stats-card', [
        'title' => 'Havi bruttó számlázandó',
        'value' => $formatMoney($stats['monthly_gross_cents'] / 100),
        'subtitle' => 'Csak a már rögzített havi snapshotokból',
        'icon' => 'fa-solid fa-file-invoice-dollar',
        'color' => 'purple',
    ])
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="card-title mb-1">Partnerek havi állapota</h4>
            <p class="text-muted mb-0">Kiválasztott hónap: {{ $selectedMonthLabel }}</p>
        </div>
        <span class="text-muted">Aktív partnerek: {{ $billingPartners->count() }}</span>
    </div>
    <div class="card-body">
        @if($billingPartners->isEmpty())
            @include('layouts.partials.components.ui.empty-state', [
                'icon' => 'fa-solid fa-file-invoice-dollar',
                'title' => 'Nincs aktív számlázási partner',
                'text' => 'A havi ügyfél számlázás itt fog megjelenni, amint van legalább egy aktív partner.',
            ])
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Partner</th>
                        <th>Intézmények</th>
                        <th>Gyermeklétszám</th>
                        <th>Nettó</th>
                        <th>ÁFA</th>
                        <th>Bruttó</th>
                        <th>Állapot</th>
                        <th class="text-end">Művelet</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($billingPartners as $billingPartner)
                        @php
                            $monthlyBilling = $billingPartner->monthlyBillings->first();
                            $status = $statusMeta($monthlyBilling?->status);
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $billingPartner->name }}</div>
                                @if($billingPartner->billing_name)
                                    <div class="small text-muted">{{ $billingPartner->billing_name }}</div>
                                @endif
                            </td>
                            <td>{{ $billingPartner->institutions_count }}</td>
                            <td>{{ $monthlyBilling ? $monthlyBilling->total_children : '—' }}</td>
                            <td>{{ $monthlyBilling ? $formatMoney($monthlyBilling->net_amount) : '—' }}</td>
                            <td>{{ $monthlyBilling ? $formatMoney($monthlyBilling->vat_amount) : '—' }}</td>
                            <td>{{ $monthlyBilling ? $formatMoney($monthlyBilling->gross_amount) : '—' }}</td>
                            <td>
                                <span class="{{ $status['class'] }}">{{ $status['label'] }}</span>
                                @if($monthlyBilling?->invoice_number)
                                    <div class="small text-muted mt-1">{{ $monthlyBilling->invoice_number }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('dashboard.partner-monthly-billings.show', ['billingPartner' => $billingPartner, 'month' => $selectedMonthQuery]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    Megnyitás
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
@endsection
