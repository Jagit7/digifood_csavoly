@extends('layouts.superadmin')

@section('title', 'Intézményi áttekintés')

@push('styles')
<style>
    .df-overview-day .display-5 { font-weight:700; }
    .df-overview-chart { min-height:330px; }
    .df-overview-donut { width:150px; min-height:150px; }
    .df-overview-metrics { min-width:220px; }
    .df-overview-metric { display:flex; justify-content:space-between; gap:1rem; padding:.32rem 0; border-bottom:1px solid #eee; }
    .df-overview-metric:last-child { border-bottom:0; }
    .df-attention-icon { width:38px; height:38px; flex:0 0 38px; display:flex; align-items:center; justify-content:center; border-radius:50%; }
    .df-special-date { width:52px; flex:0 0 52px; text-align:center; }
    .df-special-date strong { display:block; font-size:1.15rem; }
    @media (max-width:575px) {
        .df-overview-day-content { flex-direction:column; }
        .df-overview-metrics { width:100%; min-width:0; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Intézményi áttekintés',
        'subtitle' => $institution->name.' · '.$today->format('Y. m. d.'),
    ])

    <div class="row">
        @foreach([
            ['title' => 'Ma', 'date' => $today, 'data' => $todayData, 'primary' => true],
            ['title' => 'Következő étkezési nap', 'date' => $nextServiceDay, 'data' => $nextDayData, 'primary' => false],
        ] as $dayCard)
            <div class="col-xl-6">
                <div class="card df-overview-day {{ $dayCard['primary'] ? 'border-primary' : '' }}">
                    <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="card-title mb-1">{{ $dayCard['title'] }}</h4>
                            <span class="text-muted">{{ $dayCard['date']?->format('Y. m. d.') ?? 'Nincs következő nap' }}</span>
                        </div>
                        @if($dayCard['data'])
                            <span class="badge {{ $dayCard['data']['summary']['is_service_day'] ? 'badge-success' : 'badge-secondary' }} light">
                                {{ $dayCard['data']['summary']['is_service_day'] ? 'Étkezési nap' : 'Nincs étkeztetés' }}
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($dayCard['data'])
                            <div class="d-flex justify-content-between align-items-center gap-3 df-overview-day-content">
                                <div>
                                    <div class="display-5 mb-1">{{ $dayCard['data']['summary']['total'] }} fő</div>
                                    <span class="text-muted">várható étkező</span>
                                    <div id="{{ $dayCard['primary'] ? 'todayMealDonut' : 'nextMealDonut' }}" class="df-overview-donut"></div>
                                </div>
                                <div class="df-overview-metrics">
                                    <div class="df-overview-metric"><span>Normál</span><strong>{{ $dayCard['data']['summary']['standard'] }}</strong></div>
                                    <div class="df-overview-metric"><span>Allergén</span><strong>{{ $dayCard['data']['summary']['allergen'] }}</strong></div>
                                    <div class="df-overview-metric"><span>Egyéb diéta</span><strong>{{ $dayCard['data']['summary']['other_diet'] }}</strong></div>
                                    <div class="df-overview-metric"><span>Lemondva</span><strong>{{ $dayCard['data']['summary']['cancelled'] }}</strong></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                                <a href="{{ route('dashboard.institution.school-breaks.calendar.day', $dayCard['date']->toDateString()) }}" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-list me-1"></i> Teljes napi lista
                                </a>
                                @if(!$dayCard['primary'] && $window['configured'])
                                    <span class="text-muted small">Lemondási határidő: {{ sprintf('%02d:%02d', $window['setting']->cancellation_hour, $window['setting']->cancellation_minute) }}</span>
                                @endif
                            </div>
                        @else
                            <div class="text-center text-muted py-5">Nem található következő étkezési nap.</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header border-0 pb-0 flex-wrap">
                    <div>
                        <h4 class="card-title mb-1">Étkezési létszám</h4>
                        <span class="text-muted small">Normál, allergén, egyéb diétás és lemondott adagok</span>
                    </div>
                    <ul class="nav nav-tabs vacany-tabs style-1 mt-3 mt-sm-0" role="tablist">
                        <li class="nav-item"><button type="button" class="nav-link active df-period-button" data-period="week">Ezen a héten</button></li>
                        <li class="nav-item"><button type="button" class="nav-link df-period-button" data-period="month">Ebben a hónapban</button></li>
                    </ul>
                </div>
                <div class="card-body"><div id="mealVolumeChart" class="df-overview-chart"></div></div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header border-0"><h4 class="card-title mb-0">Figyelmet igényel!</h4></div>
                <div class="card-body pt-0">
                    @forelse($attentionItems as $item)
                        <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                            <div class="df-attention-icon bg-{{ $item['level'] }}-light text-{{ $item['level'] }}">
                                <i class="{{ $item['icon'] }}"></i>
                            </div>
                            <div class="flex-grow-1"><div>{{ $item['text'] }}</div></div>
                            <a href="{{ route($item['route']) }}" class="btn btn-xs btn-outline-primary">{{ $item['action'] }}</a>
                        </div>
                    @empty
                        <div class="text-center py-5">
                            <i class="fa-solid fa-circle-check text-success fs-1 mb-3"></i>
                            <h5>Nincs nyitott teendő</h5>
                            <p class="text-muted mb-0">A fontos beállítások és adatok rendben vannak.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header border-0"><h4 class="card-title mb-0">Mai létszám csoportonként</h4></div>
                <div class="card-body"><div id="groupMealChart" style="min-height:310px"></div></div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header border-0"><h4 class="card-title mb-0">Mai lemondási arány</h4></div>
                <div class="card-body"><div id="cancellationGauge" style="min-height:250px"></div></div>
                <div class="card-footer text-center border-0">
                    {{ $todayData['summary']['cancelled'] }} lemondás · {{ $todayData['summary']['total'] }} várható étkező
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Közelgő különleges napok</h4>
                    <a href="{{ route('dashboard.institution.school-breaks.calendar') }}" class="small">Teljes naptár</a>
                </div>
                <div class="card-body pt-0">
                    @forelse($specialDays as $day)
                        <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                            <div class="df-special-date"><strong>{{ $day['date']->format('d') }}</strong><span class="text-muted small">{{ $day['date']->format('m.') }}</span></div>
                            <div>
                                <strong>{{ $day['holiday'] ?? $day['break']?->title ?? $day['working_day']?->name }}</strong>
                                <div class="text-muted small">{{ $day['is_service_day'] ? 'Ledolgozós / intézményi munkanap' : 'Nincs étkeztetés' }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">A következő 30 napban nincs különleges nap.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const colors = {
        primary: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#886CC0',
        allergen: '#ff9f43', diet: '#56c7ce', cancelled: '#ea5455', muted: '#c8c8c8'
    };
    const charts = {{ Illuminate\Support\Js::from($chartPayload) }};
    const todayComposition = {{ Illuminate\Support\Js::from($todayComposition) }};
    const nextComposition = {{ Illuminate\Support\Js::from($nextComposition) }};

    function donut(selector, series) {
        const element = document.querySelector(selector);
        if (!element) return;
        new ApexCharts(element, {
            chart: { type: 'donut', height: 150, sparkline: { enabled: true } },
            series: series,
            labels: ['Normál', 'Allergén', 'Egyéb diéta'],
            colors: [colors.primary, colors.allergen, colors.diet],
            legend: { show: false }, dataLabels: { enabled: false },
            stroke: { width: 2 }, tooltip: { y: { formatter: value => value + ' fő' } },
            noData: { text: 'Nincs adat' }
        }).render();
    }
    donut('#todayMealDonut', todayComposition);
    donut('#nextMealDonut', nextComposition);

    function mealSeries(data) {
        return [
            { name: 'Normál', type: 'column', data: data.standard },
            { name: 'Allergén', type: 'column', data: data.allergen },
            { name: 'Egyéb diéta', type: 'column', data: data.other_diet },
            { name: 'Lemondva', type: 'line', data: data.cancelled }
        ];
    }
    const mealChart = new ApexCharts(document.querySelector('#mealVolumeChart'), {
        chart: { type: 'line', height: 330, stacked: true, toolbar: { show: false } },
        series: mealSeries(charts.week), colors: [colors.primary, colors.allergen, colors.diet, colors.cancelled],
        stroke: { width: [0, 0, 0, 3], curve: 'smooth' },
        plotOptions: { bar: { columnWidth: '48%', borderRadius: 3 } },
        dataLabels: { enabled: false }, xaxis: { categories: charts.week.categories },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value) } },
        legend: { position: 'top', horizontalAlign: 'right' },
        tooltip: { shared: true, intersect: false, y: { formatter: value => value + ' fő' } },
        grid: { borderColor: '#eee', strokeDashArray: 4 }
    });
    mealChart.render();
    document.querySelectorAll('.df-period-button').forEach(button => button.addEventListener('click', function () {
        document.querySelectorAll('.df-period-button').forEach(item => item.classList.remove('active'));
        this.classList.add('active');
        const data = charts[this.dataset.period];
        mealChart.updateOptions({ xaxis: { categories: data.categories } });
        mealChart.updateSeries(mealSeries(data));
    }));

    const groupLabels = {{ Illuminate\Support\Js::from($groupLabels) }};
    const groupValues = {{ Illuminate\Support\Js::from($groupValues) }};
    new ApexCharts(document.querySelector('#groupMealChart'), {
        chart: { type: 'bar', height: Math.max(310, groupLabels.length * 38), toolbar: { show: false } },
        series: [{ name: 'Étkezők', data: groupValues }], colors: [colors.primary],
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
        dataLabels: { enabled: true, formatter: value => value + ' fő' },
        xaxis: { categories: groupLabels, min: 0, labels: { formatter: value => Math.round(value) } },
        grid: { borderColor: '#eee', strokeDashArray: 4 },
        tooltip: { y: { formatter: value => value + ' fő' } },
        noData: { text: 'Nincs csoportadat' }
    }).render();

    const expected = {{ $todayData['summary']['total'] }};
    const cancelled = {{ $todayData['summary']['cancelled'] }};
    const cancellationRate = expected + cancelled > 0 ? Math.round(cancelled / (expected + cancelled) * 1000) / 10 : 0;
    new ApexCharts(document.querySelector('#cancellationGauge'), {
        chart: { type: 'radialBar', height: 250 }, series: [cancellationRate], colors: [colors.cancelled],
        labels: ['Lemondva'], plotOptions: { radialBar: { hollow: { size: '62%' }, dataLabels: {
            name: { fontSize: '14px' }, value: { fontSize: '28px', formatter: value => value.toFixed(1) + '%' }
        } } }, stroke: { lineCap: 'round' }
    }).render();
});
</script>
@endpush
