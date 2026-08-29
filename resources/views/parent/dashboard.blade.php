@extends('layouts.parent')

@section('page_title', 'Vezérlőpult')

@push('styles')
<style>
    .df-parent-hero {
        position: relative;
        overflow: hidden;
        border: 0;
        border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(136, 108, 192, 0.96), rgba(62, 145, 255, 0.92));
        color: #fff;
        box-shadow: 0 1.25rem 2.5rem rgba(42, 68, 122, 0.18);
    }
    .df-parent-hero::before,
    .df-parent-hero::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        pointer-events: none;
    }
    .df-parent-hero::before {
        width: 16rem;
        height: 16rem;
        right: -4rem;
        top: -6rem;
    }
    .df-parent-hero::after {
        width: 10rem;
        height: 10rem;
        right: 20%;
        bottom: -4rem;
    }
    .df-parent-hero .card-body {
        position: relative;
        z-index: 1;
        padding: 2rem;
    }
    .df-parent-hero-date {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .45rem .85rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        font-size: .875rem;
    }
    .df-parent-stat-card {
        display: flex;
        width: 100%;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .df-parent-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 1rem 2rem rgba(25, 44, 88, 0.18);
    }
    .df-parent-stat-card .card-body {
        position: relative;
        min-height: 216px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        padding: 1.45rem 1.5rem;
        padding-right: 5.5rem;
    }
    .df-parent-stat-title {
        display: block;
        min-height: 1.5rem;
        color: rgba(255, 255, 255, 0.8);
        font-size: .95rem;
        font-weight: 600;
        line-height: 1.4;
    }
    .df-parent-stat-value-wrap {
        margin-top: 1rem;
        min-height: 4.9rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .df-parent-stat-value {
        margin: 0;
        color: #fff;
        font-size: clamp(1.65rem, 1.15rem + 1vw, 2.15rem);
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -.02em;
    }
    .df-parent-stat-value.df-parent-stat-value-text {
        font-size: clamp(1.35rem, 1rem + .7vw, 1.75rem);
        line-height: 1.25;
    }
    .df-parent-stat-value.df-parent-stat-value-datetime {
        font-size: clamp(1.25rem, .95rem + .6vw, 1.6rem);
        line-height: 1.2;
    }
    .df-parent-stat-value-line + .df-parent-stat-value-line {
        margin-top: .2rem;
    }
    .df-parent-stat-meta {
        margin-top: auto;
        width: 100%;
    }
    .df-parent-stat-subtitle,
    .df-parent-stat-helper {
        display: block;
        color: rgba(255, 255, 255, 0.82);
    }
    .df-parent-stat-subtitle {
        margin-top: .35rem;
        font-size: .96rem;
    }
    .df-parent-stat-helper {
        margin-top: .85rem;
        font-size: .8rem;
        line-height: 1.45;
    }
    .df-parent-stat-card .df-stat-icon {
        position: absolute;
        right: 1.4rem;
        bottom: 1.25rem;
        font-size: 2.7rem;
        opacity: .24;
        pointer-events: none;
    }
    .df-parent-section-card {
        border: 0;
        border-radius: 1.25rem;
        box-shadow: 0 .8rem 1.8rem rgba(33, 49, 89, 0.08);
    }
    .df-parent-section-card .card-header {
        padding: 1.35rem 1.4rem 0;
        background: transparent;
    }
    .df-parent-section-card .card-body {
        padding: 1.4rem;
    }
    .df-parent-section-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: .2rem;
    }
    .df-parent-section-subtitle {
        color: #7e8299;
        font-size: .92rem;
    }
    .df-parent-finance-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
    }
    .df-parent-finance-metric {
        padding: 1rem 1rem .95rem;
        border-radius: 1rem;
        background: #f6f7fb;
    }
    .df-parent-finance-metric small {
        color: #7e8299;
        display: block;
        margin-bottom: .35rem;
    }
    .df-parent-finance-metric strong {
        display: block;
        font-size: 1.12rem;
        color: #1f2b4d;
    }
    .df-parent-chart-wrap {
        min-height: 290px;
    }
    .df-parent-donut-wrap {
        min-height: 280px;
    }
    .df-parent-donut-wrap .apexcharts-datalabels-group {
        transform: translateY(0);
    }
    .df-parent-donut-wrap .apexcharts-datalabel-label {
        fill: #7e8299 !important;
        font-size: 13px !important;
        font-weight: 600 !important;
    }
    .df-parent-donut-wrap .apexcharts-datalabel-value {
        fill: #1f2b4d !important;
        font-size: 28px !important;
        font-weight: 700 !important;
    }
    .df-parent-finance-status-badge,
    .df-parent-finance-status-badge:link,
    .df-parent-finance-status-badge:visited,
    .df-parent-finance-status-badge:hover,
    .df-parent-finance-status-badge:focus {
        color: #fff !important;
    }
    .df-parent-empty {
        border: 1px dashed rgba(136, 108, 192, 0.28);
        border-radius: 1rem;
        background: linear-gradient(180deg, rgba(245, 247, 252, 0.92), rgba(255, 255, 255, 0.98));
    }
    .df-parent-child-card {
        height: 100%;
        border: 1px solid rgba(133, 147, 173, 0.16);
        border-radius: 1.2rem;
        box-shadow: 0 .75rem 1.6rem rgba(42, 54, 92, 0.06);
    }
    .df-parent-child-avatar {
        width: 3rem;
        height: 3rem;
        border-radius: 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(136, 108, 192, 0.18), rgba(62, 145, 255, 0.22));
        color: #5f48a6;
        font-weight: 700;
    }
    .df-parent-badges {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }
    .df-parent-badges .badge {
        font-size: .75rem;
        padding: .5rem .65rem;
    }
    .df-parent-timeline {
        position: relative;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .df-parent-timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: linear-gradient(180deg, rgba(136, 108, 192, 0.28), rgba(62, 145, 255, 0.08));
    }
    .df-parent-timeline-item {
        position: relative;
        padding-left: 3rem;
        padding-bottom: 1.35rem;
    }
    .df-parent-timeline-item:last-child {
        padding-bottom: 0;
    }
    .df-parent-timeline-badge {
        position: absolute;
        left: 0;
        top: .15rem;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        box-shadow: 0 .5rem 1rem rgba(42, 54, 92, 0.14);
    }
    .df-parent-timeline-panel {
        border: 1px solid rgba(133, 147, 173, 0.15);
        border-radius: 1rem;
        padding: 1rem 1rem .95rem;
        background: #fff;
    }
    .df-parent-timeline-meta {
        color: #7e8299;
        font-size: .82rem;
        margin-bottom: .35rem;
    }
    .df-parent-quick-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-height: 100%;
        padding: 1.1rem 1.2rem;
        border: 1px solid rgba(133, 147, 173, 0.18);
        border-radius: 1.15rem;
        background: linear-gradient(180deg, rgba(246, 247, 251, 0.86), rgba(255, 255, 255, 0.98));
        text-decoration: none;
        color: #1f2b4d;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
        min-width: 0;
        box-shadow: 0 .75rem 1.5rem rgba(42, 54, 92, 0.06);
    }
    .df-parent-quick-link:hover {
        transform: translateY(-3px);
        background: #fff;
        border-color: rgba(136, 108, 192, 0.35);
        box-shadow: 0 1rem 2rem rgba(42, 54, 92, 0.12);
    }
    .df-parent-quick-icon {
        width: 3.4rem;
        height: 3.4rem;
        border-radius: 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(136, 108, 192, 0.12), rgba(62, 145, 255, 0.18));
        color: #5f48a6;
        flex: 0 0 3.4rem;
        font-size: 1.1rem;
    }
    .df-parent-quick-content {
        flex: 1 1 auto;
        min-width: 0;
    }
    .df-parent-quick-title {
        display: block;
        margin-bottom: .25rem;
        color: #1f2b4d;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.35;
        word-break: normal;
        overflow-wrap: break-word;
    }
    .df-parent-quick-description {
        display: block;
        color: #7e8299;
        font-size: .88rem;
        line-height: 1.45;
        word-break: normal;
        overflow-wrap: normal;
    }
    .df-parent-quick-arrow {
        flex: 0 0 auto;
        align-self: center;
        color: #9aa3bd;
        font-size: .95rem;
        transition: transform .18s ease, color .18s ease;
    }
    .df-parent-quick-link:hover .df-parent-quick-arrow {
        color: #5f48a6;
        transform: translateX(2px);
    }
    .df-parent-quick-section .df-parent-section-subtitle {
        font-size: 0;
        line-height: 0;
    }
    .df-parent-quick-section .df-parent-section-subtitle::after {
        content: 'A legfontosabb szülői feladatok egy helyen, gyors eléréssel.';
        display: block;
        font-size: .92rem;
        line-height: 1.45;
        color: #7e8299;
    }
    .df-parent-recent-item {
        padding: .95rem 0;
        border-bottom: 1px solid rgba(133, 147, 173, 0.15);
    }
    .df-parent-recent-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .df-parent-progress {
        height: .65rem;
        border-radius: 999px;
        background: rgba(136, 108, 192, 0.12);
        overflow: hidden;
    }
    .df-parent-progress .progress-bar {
        border-radius: 999px;
    }
    @media (max-width: 1199.98px) {
        .df-parent-stat-card .card-body {
            min-height: 208px;
        }
    }
    @media (max-width: 767.98px) {
        .df-parent-hero .card-body,
        .df-parent-section-card .card-body {
            padding: 1.2rem;
        }
        .df-parent-finance-grid {
            grid-template-columns: 1fr;
        }
        .df-parent-chart-wrap,
        .df-parent-donut-wrap {
            min-height: 240px;
        }
        .df-parent-stat-card .card-body {
            min-height: auto;
            padding-right: 4.75rem;
        }
        .df-parent-stat-value-wrap {
            min-height: auto;
        }
    }
</style>
@endpush

@section('content')
    @php
        // Ugyanaz a szabály, mint az oldalsáv "Befizetések" menüpontjánál
        // (ld. parent.partials.sidebar) - ha egyetlen kapcsolt
        // intézménynél sincs bekapcsolva sem a kártyás fizetés, sem a
        // számlázás, a lenti "Havi étkezési költségek" kártya "Befizetések"
        // gyorsgombja se jelenjen meg, különben egy sehova nem mutató
        // oldalsáv-menüpontra hivatkozna.
        $dashboardConnectedInstitutions = auth()->user()?->guardians()
            ->where('active', true)
            ->with('institution.setting')
            ->get()
            ->pluck('institution')
            ->filter() ?? collect();
        $dashboardShowPaymentsMenu = $dashboardConnectedInstitutions->contains(
            fn ($institution) => (bool) ($institution->setting?->invoicing_enabled ?? false)
                || (bool) ($institution->setting?->card_payment_enabled ?? false)
        );
    @endphp

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Vezérlőpult',
        'subtitle' => 'A szülői felület kezdőoldala',
    ])

    <div class="card df-parent-hero mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-lg-center">
                <div>
                    <span class="badge bg-white text-primary mb-3">DigiFood szülői felület</span>
                    <h2 class="mb-2 text-white">{{ $greeting }}</h2>
                    <p class="mb-0 text-white-50 fs-6">Itt egy helyen követheti gyermekei étkezéseit és pénzügyeit.</p>
                </div>
                <div class="df-parent-hero-date">
                    <i class="fa-regular fa-calendar-days"></i>
                    <span>{{ $todayLabel }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-stretch" style="margin-bottom:10px;">
        @foreach($statsCards as $card)
            <div class="col-xl-3 col-md-6 d-flex">
                <a href="{{ $card['url'] }}" class="df-parent-stat-card h-100">
                    <div class="card df-stat-card df-stat-{{ $card['color'] }} h-100">
                        <div class="card-body">
                            <span class="df-parent-stat-title">{{ $card['title'] }}</span>
                            <div class="df-parent-stat-value-wrap">
                                <div class="df-parent-stat-value {{ $card['value_class'] ?? '' }}">
                                    @foreach($card['value_lines'] as $line)
                                        <span class="df-parent-stat-value-line d-block">{{ $line }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="df-parent-stat-meta">
                                <span class="df-parent-stat-subtitle">{{ $card['subtitle'] }}</span>
                                <span class="df-parent-stat-helper">{{ $card['helper'] }}</span>
                            </div>
                            <i class="{{ $card['icon'] }} df-stat-icon"></i>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-7 mb-4">
            <div class="card df-parent-section-card h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="df-parent-section-title">Aktuális havi pénzügyi állapot</div>
                        <div class="df-parent-section-subtitle">A kapcsolt gyermekek aktuális havi fizetendője és annak teljesítése.</div>
                    </div>
                    <a href="{{ route('parent.monthly-settlements.index') }}" class="btn btn-sm btn-outline-primary">Részletek</a>
                </div>
                <div class="card-body">
                    @if($financialOverview['has_statement'])
                        <div class="row align-items-center g-4">
                            <div class="col-lg-5">
                                <div id="parentFinanceDonut" class="df-parent-donut-wrap"></div>
                            </div>
                            <div class="col-lg-7">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <div>
                                        <div class="text-muted small">{{ $financialOverview['month_label'] }}</div>
                                        <h3 class="mb-0">{{ number_format($financialOverview['completion_percent'], 0, ',', ' ') }}%</h3>
                                    </div>
                                    <span class="badge df-parent-finance-status-badge {{ $financialOverview['status']['class'] }}">{{ $financialOverview['status']['label'] }}</span>
                                </div>
                                <div class="df-parent-progress mb-4">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $financialOverview['completion_percent'] }}%"></div>
                                </div>
                                <div class="df-parent-finance-grid">
                                    <div class="df-parent-finance-metric">
                                        <small>Teljes fizetendő</small>
                                        <strong>{{ number_format($financialOverview['total_payable'], 0, ',', ' ') }} Ft</strong>
                                    </div>
                                    <div class="df-parent-finance-metric">
                                        <small>Már befizetve</small>
                                        <strong>{{ number_format($financialOverview['paid_total'], 0, ',', ' ') }} Ft</strong>
                                    </div>
                                    <div class="df-parent-finance-metric">
                                        <small>Hátralék</small>
                                        <strong>{{ number_format($financialOverview['remaining'], 0, ',', ' ') }} Ft</strong>
                                    </div>
                                    <div class="df-parent-finance-metric">
                                        <small>Elszámolás státusza</small>
                                        <strong>{{ $financialOverview['status']['label'] }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="df-parent-empty">
                            @include('layouts.partials.components.ui.empty-state', [
                                'title' => 'Az aktuális hónaphoz még nem készült elszámolás.',
                                'text' => 'Amint elkészül a havi elszámolás, itt megjelenik a teljes fizetendő összeg, a befizetések és a hátralék állapota.',
                                'icon' => 'fa-solid fa-wallet',
                                'buttonText' => 'Havi elszámolások',
                                'buttonUrl' => route('parent.monthly-settlements.index'),
                                'buttonIcon' => 'fa-solid fa-file-lines',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5 mb-4">
            <div class="card df-parent-section-card h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="df-parent-section-title">Lemondások eredménye</div>
                        <div class="df-parent-section-subtitle">Aktuális havi lemondások és a következő még módosítható étkezési nap.</div>
                    </div>
                    <a href="{{ route('parent.meal-cancellations') }}" class="btn btn-sm btn-outline-primary">Lemondások kezelése</a>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <div class="df-parent-finance-metric h-100">
                                <small>Lemondott étkezések</small>
                                <strong>{{ $cancellationSummary['count'] }}</strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="df-parent-finance-metric h-100">
                                <small>Megtakarított összeg</small>
                                <strong>
                                    @if($cancellationSummary['saved_amount_reliable'])
                                        {{ number_format($cancellationSummary['saved_amount'], 0, ',', ' ') }} Ft
                                    @else
                                        Nincs megbízható adat
                                    @endif
                                </strong>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 rounded-4" style="background:#f6f7fb;">
                        <div class="text-muted small mb-2">Következő még lemondható étkezés</div>
                        <div class="fw-semibold">{{ $cancellationSummary['next_deadline'] }}</div>
                    </div>
                    <div class="mt-4">
                        <div class="df-parent-section-title h6 mb-2">Legutóbbi számla és befizetés</div>
                        @if($recentFinance['latest_payment'] || $recentFinance['latest_invoice'])
                            @if($recentFinance['latest_payment'])
                                <div class="df-parent-recent-item">
                                    <div class="d-flex justify-content-between gap-3 align-items-start">
                                        <div>
                                            <div class="fw-semibold">Legutóbbi befizetés</div>
                                            <div class="text-muted small">
                                                {{ $recentFinance['latest_payment']['child_name'] ?? 'Kapcsolt gyermek' }}
                                                · {{ $recentFinance['latest_payment']['date'] }}
                                            </div>
                                        </div>
                                        <strong>{{ $recentFinance['latest_payment']['amount'] }}</strong>
                                    </div>
                                </div>
                            @endif
                            @if($recentFinance['latest_invoice'])
                                <div class="df-parent-recent-item">
                                    <div class="d-flex justify-content-between gap-3 align-items-start">
                                        <div>
                                            <div class="fw-semibold">Legutóbbi számla</div>
                                            <div class="text-muted small">
                                                {{ $recentFinance['latest_invoice']['child_name'] ?? 'Kapcsolt gyermek' }}
                                                · {{ $recentFinance['latest_invoice']['number'] }}
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <strong class="d-block">{{ $recentFinance['latest_invoice']['amount'] }}</strong>
                                            <span class="badge {{ $recentFinance['latest_invoice']['status']['class'] }} mt-2">{{ $recentFinance['latest_invoice']['status']['label'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="df-parent-empty">
                                @include('layouts.partials.components.ui.empty-state', [
                                    'title' => 'Nincs még számla vagy befizetés.',
                                    'text' => 'Amint megjelenik számla vagy rögzített befizetés, itt röviden látni fogja.',
                                    'icon' => 'fa-solid fa-receipt',
                                ])
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12 mb-4">
            <div class="card df-parent-section-card h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="df-parent-section-title">Havi étkezési költségek</div>
                        <div class="df-parent-section-subtitle">Az elmúlt legfeljebb 6 lezárt hónap összesített fizetendő összege.</div>
                    </div>
                    @if($dashboardShowPaymentsMenu)
                        <a href="{{ route('parent.payments') }}" class="btn btn-sm btn-outline-primary">Befizetések</a>
                    @endif
                </div>
                <div class="card-body">
                    @if($historyChart['has_data'])
                        <div id="parentHistoryChart" class="df-parent-chart-wrap"></div>
                    @else
                        <div class="df-parent-empty">
                            @include('layouts.partials.components.ui.empty-state', [
                                'title' => 'Nincs elegendő lezárt pénzügyi előzmény.',
                                'text' => 'Az oszlopdiagram akkor jelenik meg, ha már legalább egy lezárt havi elszámolás rendelkezésre áll.',
                                'icon' => 'fa-solid fa-chart-column',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 mb-4">
            <div class="card df-parent-section-card h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="df-parent-section-title">Közelgő étkezések és lemondások</div>
                        <div class="df-parent-section-subtitle">A következő legfeljebb 5 releváns étkezési nap.</div>
                    </div>
                    <a href="{{ route('parent.meal-cancellations') }}" class="btn btn-sm btn-outline-primary">Összes étkezés megtekintése</a>
                </div>
                <div class="card-body">
                    @if($timeline->isNotEmpty())
                        <ul class="df-parent-timeline">
                            @foreach($timeline as $item)
                                <li class="df-parent-timeline-item">
                                    <span class="df-parent-timeline-badge {{ $item['status']['class'] }}">
                                        <i class="fa-solid fa-utensils"></i>
                                    </span>
                                    <div class="df-parent-timeline-panel">
                                        <div class="df-parent-timeline-meta">{{ $item['date_label'] }}</div>
                                        <div class="fw-semibold mb-1">
                                            @if($item['child_name'])
                                                {{ $item['child_name'] }} ·
                                            @endif
                                            {{ $item['meal_label'] }}
                                        </div>
                                        <div class="d-flex justify-content-between gap-3 align-items-start flex-wrap">
                                            <div>
                                                <span class="badge {{ $item['status']['class'] }}">{{ $item['status']['label'] }}</span>
                                                @if(!$item['is_modifiable'] && $item['reason'])
                                                    <div class="text-muted small mt-2">{{ $item['reason'] }}</div>
                                                @endif
                                            </div>
                                            @if($item['is_modifiable'])
                                                <a href="{{ $item['action_url'] }}" class="btn btn-xs btn-primary">{{ $item['action_label'] }}</a>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="df-parent-empty">
                            @include('layouts.partials.components.ui.empty-state', [
                                'title' => 'Nincs közelgő étkezés.',
                                'text' => 'A következő időszakban még nem található megjeleníthető étkezési nap a kapcsolt gyermekekhez.',
                                'icon' => 'fa-solid fa-calendar-days',
                            ])
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12 mb-4">
            <div class="card df-parent-section-card h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="df-parent-section-title">Gyermekeim</div>
                        <div class="df-parent-section-subtitle">Minden kártya kizárólag a bejelentkezett szülőhöz kapcsolt gyermek adatait mutatja.</div>
                    </div>
                    <a href="{{ route('parent.children.index') }}" class="btn btn-sm btn-outline-primary">Összes gyermek</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($childCards as $child)
                            <div class="col-lg-6 mb-4">
                                <div class="card df-parent-child-card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3 mb-3">
                                            <div class="df-parent-child-avatar">{{ $child['initials'] }}</div>
                                            <div class="flex-grow-1">
                                                <h4 class="card-title mb-1">{{ $child['name'] }}</h4>
                                                <div class="text-muted small">{{ $child['institution'] }}</div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-sm-6">
                                                <div class="text-muted small">Osztály / csoport</div>
                                                <div class="fw-semibold">{{ $child['group_name'] }}</div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="text-muted small">Étkeztetési státusz</div>
                                                <div class="fw-semibold">{{ $child['meal_status'] }}</div>
                                            </div>
                                            <div class="col-12">
                                                <div class="text-muted small">Aktív menücsomag</div>
                                                <div class="fw-semibold">{{ $child['meal_package'] }}</div>
                                            </div>
                                        </div>

                                        <div class="df-parent-badges mb-4">
                                            @if($child['discount'])
                                                <span class="badge bg-info-subtle text-info border">{{ $child['discount'] }}</span>
                                            @endif
                                            @foreach($child['dietary'] as $dietary)
                                                <span class="badge bg-warning-subtle text-warning border">{{ $dietary }}</span>
                                            @endforeach
                                            @if(blank($child['discount']) && collect($child['dietary'])->isEmpty())
                                                <span class="badge bg-light text-muted border">Nincs külön jelzés</span>
                                            @endif
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ $child['details_url'] }}" class="btn btn-primary btn-sm">Részletek</a>
                                            <a href="{{ $child['meal_url'] }}" class="btn btn-light btn-sm">Étkezések kezelése</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="df-parent-empty">
                                    @include('layouts.partials.components.ui.empty-state', [
                                        'title' => 'Nincs kapcsolt gyermek',
                                        'text' => 'Ehhez a szülői fiókhoz jelenleg egyetlen gyermek sincs hozzárendelve.',
                                        'icon' => 'fa-solid fa-children',
                                    ])
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 mb-4">
            <div class="card df-parent-section-card df-parent-quick-section h-100">
                <div class="card-header border-0">
                    <div class="df-parent-section-title">Gyors műveletek</div>
                    <div class="df-parent-section-subtitle">Minden gomb működő, névvel ellátott route-ra vezet.</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($quickActions as $action)
                            <div class="col-xl-4 col-md-6">
                                <a href="{{ $action['url'] }}" class="df-parent-quick-link">
                                    <span class="df-parent-quick-icon"><i class="{{ $action['icon'] }}"></i></span>
                                    <span class="df-parent-quick-content">
                                        <span class="df-parent-quick-title">{{ $action['title'] }}</span>
                                        <span class="df-parent-quick-description">{{ $action['description'] }}</span>
                                    </span>
                                    <span class="df-parent-quick-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#886CC0';
    const accent = '#3e91ff';
    const success = '#2bc155';
    const warning = '#ff9f43';
    const muted = '#d7dceb';
    const textMuted = '#7e8299';

    const donutPayload = @json($financialDonut);
    if (donutPayload && document.querySelector('#parentFinanceDonut')) {
        new ApexCharts(document.querySelector('#parentFinanceDonut'), {
            chart: { type: 'donut', height: 280, toolbar: { show: false } },
            series: donutPayload.series,
            labels: ['Befizetve', 'Még fizetendő'],
            colors: [success, primary],
            stroke: { width: 0 },
            legend: { position: 'bottom' },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '74%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                offsetY: -8,
                                color: textMuted,
                                formatter: function () {
                                    return 'Teljesítés';
                                }
                            },
                            value: {
                                show: true,
                                offsetY: 8,
                                fontSize: '28px',
                                fontWeight: 700,
                                color: '#1f2b4d',
                                formatter: function () {
                                    return donutPayload.percent + '%';
                                }
                            },
                            total: {
                                show: false,
                                showAlways: true,
                                label: 'Teljesítés',
                                color: textMuted,
                                fontSize: '28px',
                                fontWeight: 700,
                                offsetY: 16,
                                formatter: function () {
                                    return donutPayload.percent + '%';
                                }
                            }
                        }
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('hu-HU').format(value) + ' Ft';
                    }
                }
            }
        }).render();
    }

    const historyChart = @json($historyChart);
    if (historyChart.has_data && document.querySelector('#parentHistoryChart')) {
        new ApexCharts(document.querySelector('#parentHistoryChart'), {
            chart: { type: 'bar', height: 290, toolbar: { show: false } },
            series: [{ name: 'Fizetendő', data: historyChart.totals }],
            colors: [accent],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '48%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: historyChart.categories },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('hu-HU').format(Math.round(value)) + ' Ft';
                    }
                }
            },
            grid: { borderColor: '#edf0f7', strokeDashArray: 4 },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('hu-HU').format(value) + ' Ft';
                    }
                }
            }
        }).render();
    }
});
</script>
@endpush
