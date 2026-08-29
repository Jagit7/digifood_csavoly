@extends('layouts.superadmin')

@section('title', 'SuperAdmin áttekintés')

@push('styles')
<style>
    .df-superadmin-hero {
        border: 0;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, .18), transparent 34%),
            linear-gradient(135deg, #1d3557 0%, #355070 48%, #4a6fa5 100%);
        color: #fff;
        box-shadow: 0 16px 36px rgba(29, 53, 87, .18);
    }
    .df-superadmin-hero .card-body {
        padding: 1.75rem;
    }
    .df-superadmin-hero-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .df-superadmin-hero-label {
        display: block;
        font-size: .78rem;
        opacity: .78;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .df-superadmin-hero-value {
        display: block;
        margin-top: .25rem;
        font-size: 1rem;
        font-weight: 600;
    }
    .df-superadmin-chart {
        min-height: 320px;
    }
    .df-superadmin-section-title {
        font-size: 1rem;
        font-weight: 600;
    }
    .df-superadmin-metric {
        display: flex;
        align-items: flex-start;
        gap: .9rem;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(148, 163, 184, .18);
    }
    .df-superadmin-metric:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .df-superadmin-metric:first-child {
        padding-top: 0;
    }
    .df-superadmin-metric-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(74, 111, 165, .1);
        color: #355070;
    }
    .df-superadmin-mini-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .df-superadmin-mini-card {
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 16px;
        padding: 1rem;
        background: #fff;
        min-height: 118px;
    }
    .df-superadmin-mini-card h4 {
        font-size: 1.55rem;
        margin: .5rem 0 .35rem;
    }
    .df-superadmin-quick-action {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 16px;
        color: inherit;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .df-superadmin-quick-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 28px rgba(15, 23, 42, .08);
        border-color: rgba(53, 80, 112, .24);
        color: inherit;
    }
    .df-superadmin-quick-action-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(53, 80, 112, .1);
        color: #355070;
    }
    .df-superadmin-list-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(148, 163, 184, .18);
    }
    .df-superadmin-list-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .df-superadmin-list-item:first-child {
        padding-top: 0;
    }
    .df-superadmin-empty {
        border: 1px dashed rgba(148, 163, 184, .4);
        border-radius: 16px;
        padding: 2rem 1.25rem;
        text-align: center;
        color: #64748b;
        background: rgba(248, 250, 252, .65);
    }
    .df-superadmin-status-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .8rem 0;
        border-bottom: 1px solid rgba(148, 163, 184, .18);
    }
    .df-superadmin-status-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .df-superadmin-timeline-badge {
        font-size: .75rem;
    }
    .df-superadmin-legacy-chart-sink {
        position: absolute;
        width: 0;
        height: 0;
        overflow: hidden;
        opacity: 0;
        pointer-events: none;
    }
    @media (max-width: 991.98px) {
        .df-superadmin-hero-meta,
        .df-superadmin-mini-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $pageTitle,
        'subtitle' => $pageSubtitle,
        'buttons' => collect($quickActions)->take(2)->map(fn ($action) => [
            'text' => $action['label'],
            'url' => $action['url'],
            'icon' => $action['icon'],
            'class' => $action['class'],
        ])->all(),
    ])

    <div class="card df-superadmin-hero mb-4">
        <div class="card-body">
            <div class="row align-items-center g-4">
                <div class="col-xl-7">
                    <span class="badge bg-light text-dark mb-3">Rendszerirányítási dashboard</span>
                    <h2 class="text-white mb-2">SuperAdmin áttekintés</h2>
                    <p class="mb-0 text-white-50">
                        A DigiFood rendszer legfontosabb adatai, az intézményi állapotok és a közelmúlt eseményei egy helyen.
                    </p>
                </div>
                <div class="col-xl-5">
                    <div class="df-superadmin-hero-meta">
                        <div>
                            <span class="df-superadmin-hero-label">Mai dátum</span>
                            <span class="df-superadmin-hero-value">{{ $pageDate->format('Y. m. d.') }}</span>
                        </div>
                        <div>
                            <span class="df-superadmin-hero-label">Adatok frissítve</span>
                            <span class="df-superadmin-hero-value">{{ $pageDate->format('H:i') }}</span>
                        </div>
                        <div>
                            <span class="df-superadmin-hero-label">Aktív intézmények</span>
                            <span class="df-superadmin-hero-value">{{ $institutionStats['active'] }} / {{ $institutionStats['total'] }}</span>
                        </div>
                        <div>
                            <span class="df-superadmin-hero-label">Függő meghívások</span>
                            <span class="df-superadmin-hero-value">{{ $accessStats['pending_invitations'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row df-stats-row">
        @foreach($statCards as $card)
            @include('layouts.partials.components.ui.stats-card', [
                'title' => $card['title'],
                'value' => $card['value'],
                'subtitle' => $card['subtitle'],
                'icon' => $card['icon'],
                'color' => $card['color'],
            ])
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <h4 class="card-title mb-1">Intézményi áttekintés</h4>
                        <div class="text-muted small">Aktivitás, intézménytípusok és az elmúlt hónapok növekedése.</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="df-superadmin-mini-grid mb-4">
                        <div class="df-superadmin-mini-card">
                            <span class="text-muted small">Összes intézmény</span>
                            <h4>{{ $institutionStats['total'] }}</h4>
                            <div class="text-muted small">A DigiFood rendszerben regisztrált intézmények száma.</div>
                        </div>
                        <div class="df-superadmin-mini-card">
                            <span class="text-muted small">Inaktív intézmények</span>
                            <h4>{{ $institutionStats['inactive'] }}</h4>
                            <div class="text-muted small">Jelenleg nem aktív vagy átmenetileg kikapcsolt intézmények.</div>
                        </div>
                        <div class="df-superadmin-mini-card">
                            <span class="text-muted small">Új intézmények 30 nap alatt</span>
                            <h4>{{ $institutionStats['recent_last_30_days'] }}</h4>
                            <div class="text-muted small">Az elmúlt 30 napban létrehozott intézmények száma.</div>
                        </div>
                        <div class="df-superadmin-mini-card">
                            <span class="text-muted small">Aktív arány</span>
                            <h4>
                                @if($institutionStats['total'] > 0)
                                    {{ number_format(($institutionStats['active'] / $institutionStats['total']) * 100, 1, ',', ' ') }}%
                                @else
                                    0%
                                @endif
                            </h4>
                            <div class="text-muted small">Az aktív státuszú intézmények aránya az összeshez viszonyítva.</div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div id="institutionGrowthChart" class="df-superadmin-chart"></div>
                        </div>
                        <div class="col-lg-4">
                            <h5 class="df-superadmin-section-title mb-3">Intézménytípusok</h5>
                            @forelse($institutionStats['type_breakdown'] as $type)
                                <div class="df-superadmin-metric">
                                    <div class="df-superadmin-metric-icon">
                                        <i class="fa-solid fa-layer-group"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">{{ $type['label'] }}</div>
                                        <div class="text-muted small">Intézményszintű megoszlás</div>
                                    </div>
                                    <div class="fw-semibold">{{ $type['total'] }}</div>
                                </div>
                            @empty
                                <div class="df-superadmin-empty">
                                    <div class="mb-2"><i class="fa-solid fa-building-circle-xmark fs-2"></i></div>
                                    <div>Még nincs rögzített intézménytípus adat.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <h4 class="card-title mb-1">Rendszerállapot</h4>
                        <div class="text-muted small">Biztonságosan megjeleníthető technikai állapotinformációk.</div>
                    </div>
                </div>
                <div class="card-body">
                    @foreach($systemStatus as $item)
                        <div class="df-superadmin-status-row">
                            <div>
                                <div class="fw-semibold">{{ $item['label'] }}</div>
                            </div>
                            <span class="badge badge-{{ $item['status'] }} light">{{ $item['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <h4 class="card-title mb-1">Használati mutatók</h4>
                        <div class="text-muted small">Gyermekek, szülői fiókok és az aktív étkezési használat.</div>
                    </div>
                </div>
                <div class="card-body">
                    @foreach($secondaryStats as $metric)
                        <div class="df-superadmin-metric">
                            <div class="df-superadmin-metric-icon">
                                <i class="{{ $metric['icon'] }}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $metric['label'] }}</div>
                                <div class="text-muted small">{{ $metric['help'] }}</div>
                            </div>
                            <div class="fw-semibold fs-4">{{ $metric['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">Legutóbb létrehozott intézmények</h4>
                        <div class="text-muted small">A legfrissebb intézményi rekordok és az alap státuszadatok.</div>
                    </div>
                    @if($institutionsIndexUrl)
                        <a href="{{ $institutionsIndexUrl }}" class="btn btn-sm btn-outline-primary">Összes intézmény</a>
                    @endif
                </div>
                <div class="card-body">
                    @if($recentInstitutions->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Név</th>
                                        <th>Település</th>
                                        <th>Státusz</th>
                                        <th>Létrehozva</th>
                                        <th class="text-end">Művelet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentInstitutions as $institution)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $institution->name }}</div>
                                                <div class="text-muted small">
                                                    {{ $institution->institution_admin_count }} admin
                                                    @if($institution->users->isNotEmpty())
                                                        · {{ $institution->users->pluck('name')->take(2)->join(', ') }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $institution->address_city ?: '—' }}</td>
                                            <td>
                                                <span class="badge badge-{{ $institution->active ? 'success' : 'secondary' }} light">
                                                    {{ $institution->active ? 'Aktív' : 'Inaktív' }}
                                                </span>
                                            </td>
                                            <td>{{ $institution->created_at?->format('Y. m. d.') }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('dashboard.institutions.edit', $institution) }}" class="btn btn-sm btn-outline-primary">
                                                    Szerkesztés
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="df-superadmin-empty">
                            <div class="mb-2"><i class="fa-solid fa-building-circle-exclamation fs-2"></i></div>
                            <p class="mb-3">Még nincs rögzített intézmény.</p>
                            @if(Route::has('dashboard.institutions.create'))
                                <a href="{{ route('dashboard.institutions.create') }}" class="btn btn-primary">
                                    <i class="fa-solid fa-plus me-1"></i> Új intézmény felvétele
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">Adminisztrátori hozzáférések</h4>
                        <div class="text-muted small">Aktív hozzáférések, függő meghívások és gyors elérés.</div>
                    </div>
                    @if($adminAccessIndexUrl)
                        <a href="{{ $adminAccessIndexUrl }}" class="btn btn-sm btn-outline-primary">Kezelés</a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="df-superadmin-mini-grid mb-4">
                        <div class="df-superadmin-mini-card">
                            <span class="text-muted small">Aktív intézményi adminok</span>
                            <h4>{{ $accessStats['active_institution_admins'] }}</h4>
                            <div class="text-muted small">Bejelentkezésre jogosult aktív admin felhasználók.</div>
                        </div>
                        <div class="df-superadmin-mini-card">
                            <span class="text-muted small">Függő meghívások</span>
                            <h4>{{ $accessStats['pending_invitations'] }}</h4>
                            <div class="text-muted small">Elfogadásra váró intézményi admin meghívók.</div>
                        </div>
                    </div>

                    @if($recentInvitations->isNotEmpty())
                        @foreach($recentInvitations as $invitation)
                            <div class="df-superadmin-list-item">
                                <div>
                                    <div class="fw-semibold">{{ $invitation->name }}</div>
                                    <div class="text-muted small">
                                        {{ $invitation->email }}
                                        @if($invitation->institution)
                                            · {{ $invitation->institution->name }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge badge-warning light">{{ $invitation->role }}</span>
                                    <div class="text-muted small mt-1">
                                        Lejárat: {{ $invitation->expires_at?->format('Y. m. d. H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="df-superadmin-empty">
                            <div class="mb-2"><i class="fa-solid fa-envelope-circle-check fs-2"></i></div>
                            <p class="mb-3">Jelenleg nincs függőben lévő admin meghívás.</p>
                            @if($adminInviteUrl)
                                <a href="{{ $adminInviteUrl }}" class="btn btn-outline-primary">
                                    <i class="fa-solid fa-user-plus me-1"></i> Admin meghívása
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-5 mb-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <h4 class="card-title mb-1">Gyors műveletek</h4>
                        <div class="text-muted small">Csak ellenőrzött, működő SuperAdmin útvonalak.</div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    @foreach($quickActions as $action)
                        <a href="{{ $action['url'] }}" class="df-superadmin-quick-action">
                            <div class="d-flex align-items-center gap-3">
                                <span class="df-superadmin-quick-action-icon">
                                    <i class="{{ $action['icon'] }}"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold">{{ $action['label'] }}</div>
                                    <div class="text-muted small">Megnyitás a SuperAdmin felületen</div>
                                </div>
                            </div>
                            <i class="fa-solid fa-arrow-right text-muted"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-7 mb-4">
            <div class="card h-100">
                <div class="card-header border-0 pb-0">
                    <div>
                        <h4 class="card-title mb-1">Közelmúlt eseményei</h4>
                        <div class="text-muted small">Új intézmények és admin-hozzáférési események időrendben.</div>
                    </div>
                </div>
                <div class="card-body">
                    @if($recentEvents->isNotEmpty())
                        @foreach($recentEvents as $event)
                            <div class="df-superadmin-list-item">
                                <div class="d-flex gap-3">
                                    <span class="df-superadmin-metric-icon">
                                        <i class="{{ $event['icon'] }}"></i>
                                    </span>
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <div class="fw-semibold">{{ $event['title'] }}</div>
                                            <span class="badge {{ $event['badge_class'] }} df-superadmin-timeline-badge">{{ $event['badge'] }}</span>
                                        </div>
                                        <div>{{ $event['description'] }}</div>
                                        <div class="text-muted small">{{ $event['meta'] }}</div>
                                    </div>
                                </div>
                                <div class="text-muted small text-end">{{ $event['date']?->format('Y. m. d. H:i') }}</div>
                            </div>
                        @endforeach
                    @else
                        <div class="df-superadmin-empty">
                            <div class="mb-2"><i class="fa-solid fa-clock-rotate-left fs-2"></i></div>
                            <div>Még nincs megjeleníthető közelmúltbeli esemény.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="df-superadmin-legacy-chart-sink" aria-hidden="true">
        <div id="NewCustomers"></div>
        <div id="NewAudience"></div>
        <div id="vacancyChart"></div>
        <div id="UserInsight"></div>
        <div id="UserInsight1"></div>
        <div id="UserInsight2"></div>
        <div id="UserInsight3"></div>
        <div id="UserInsight4"></div>
        <div id="UserInsight5"></div>
        <div id="columnChart"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const element = document.querySelector('#institutionGrowthChart');
    if (!element || typeof ApexCharts === 'undefined') {
        return;
    }

    const chartData = {{ Illuminate\Support\Js::from($institutionChart) }};
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#355070';

    new ApexCharts(element, {
        chart: {
            type: 'bar',
            height: 320,
            toolbar: { show: false }
        },
        series: [{
            name: 'Új intézmények',
            data: chartData.series
        }],
        colors: [primary],
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth: '42%'
            }
        },
        dataLabels: {
            enabled: true
        },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4
        },
        xaxis: {
            categories: chartData.categories
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: {
                formatter: function (value) {
                    return Math.round(value);
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (value) {
                    return value + ' intézmény';
                }
            }
        },
        noData: {
            text: 'Nincs intézményi adat'
        }
    }).render();
});
</script>
@endpush
