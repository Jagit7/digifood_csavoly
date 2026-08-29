@extends('layouts.superadmin')

@section('title', 'Bevétel áttekintés')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Bevétel áttekintés',
        'subtitle' => 'A partneri ügyfél számlázás és a közvetlen intézményi (SaaS) számlázás összesített, ténylegesen befolyt bevétele.',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes befolyt bevétel',
            'value' => number_format($summary['total_paid'], 0, ',', ' ').' Ft',
            'subtitle' => 'Mindösszesen, fizetettként rögzítve',
            'icon' => 'fa-solid fa-sack-dollar',
            'color' => 'green',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Ebben a hónapban',
            'value' => number_format($summary['current_month_paid'], 0, ',', ' ').' Ft',
            'subtitle' => 'Ebben a hónapban fizetettként rögzítve',
            'icon' => 'fa-solid fa-calendar-check',
            'color' => 'blue',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Kintlévőség',
            'value' => number_format($summary['total_outstanding'], 0, ',', ' ').' Ft',
            'subtitle' => 'Számlázva, de még nem fizetve',
            'icon' => 'fa-solid fa-hourglass-half',
            'color' => 'orange',
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Partneri / közvetlen megoszlás',
            'value' => number_format($summary['partner_total_paid'], 0, ',', ' ').' / '.number_format($summary['saas_total_paid'], 0, ',', ' ').' Ft',
            'subtitle' => 'Partneri befolyt / közvetlen SaaS befolyt',
            'icon' => 'fa-solid fa-scale-balanced',
            'color' => 'purple',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Havi bevétel (utolsó 12 hónap)</h4>
            <p class="text-muted mb-0">Ténylegesen fizetettként rögzített összegek, hónaponként, forrás szerint bontva.</p>
        </div>
        <div class="card-body">
            <div id="revenueChart" style="min-height: 340px;"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Partneri ügyfél számlázás</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Befolyt:</strong> {{ number_format($summary['partner_total_paid'], 0, ',', ' ') }} Ft</div>
                    <div class="mb-3"><strong>Kintlévőség:</strong> {{ number_format($summary['partner_outstanding'], 0, ',', ' ') }} Ft</div>
                    <a href="{{ route('dashboard.partner-monthly-billings.index') }}" class="btn btn-sm btn-outline-primary">
                        Megnyitás
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Közvetlen intézményi számlázás (SaaS)</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Befolyt:</strong> {{ number_format($summary['saas_total_paid'], 0, ',', ' ') }} Ft</div>
                    <div class="mb-3"><strong>Kintlévőség:</strong> {{ number_format($summary['saas_outstanding'], 0, ',', ' ') }} Ft</div>
                    <a href="{{ route('dashboard.saas-billing-summary.index') }}" class="btn btn-sm btn-outline-primary">
                        Megnyitás
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const element = document.querySelector('#revenueChart');
    if (!element || typeof ApexCharts === 'undefined') {
        return;
    }

    const categories = {{ Illuminate\Support\Js::from($summary['categories']) }};
    const partnerSeries = {{ Illuminate\Support\Js::from($summary['partner_series']) }};
    const saasSeries = {{ Illuminate\Support\Js::from($summary['saas_series']) }};

    new ApexCharts(element, {
        chart: {
            type: 'bar',
            height: 340,
            stacked: true,
            toolbar: { show: false }
        },
        series: [
            { name: 'Partneri bevétel', data: partnerSeries },
            { name: 'Közvetlen SaaS bevétel', data: saasSeries }
        ],
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '45%'
            }
        },
        dataLabels: { enabled: false },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4
        },
        xaxis: {
            categories: categories
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: {
                formatter: function (value) {
                    return Math.round(value).toLocaleString('hu-HU') + ' Ft';
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (value) {
                    return Math.round(value).toLocaleString('hu-HU') + ' Ft';
                }
            }
        },
        legend: {
            position: 'top'
        },
        noData: {
            text: 'Nincs bevétel adat'
        }
    }).render();
});
</script>
@endpush
