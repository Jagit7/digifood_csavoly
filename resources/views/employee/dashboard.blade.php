@extends('layouts.employee')

@section('page_title', 'Vezérlőpult')

@push('styles')
    <style>
        .df-employee-section-card {
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 .8rem 1.8rem rgba(33, 49, 89, 0.08);
        }

        .df-employee-section-card .card-header {
            padding: 1.35rem 1.4rem 0;
            background: transparent;
        }

        .df-employee-section-card .card-body {
            padding: 1.4rem;
        }

        .df-employee-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: .2rem;
        }

        .df-employee-section-subtitle {
            color: #7e8299;
            font-size: .92rem;
        }

        .df-employee-child-card {
            height: 100%;
            border: 1px solid rgba(133, 147, 173, 0.16);
            border-radius: 1.2rem;
            box-shadow: 0 .75rem 1.6rem rgba(42, 54, 92, 0.06);
        }

        .df-employee-child-avatar {
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

        .df-employee-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .df-employee-badges .badge {
            font-size: .75rem;
            padding: .5rem .65rem;
        }

        .df-employee-empty {
            border: 1px dashed rgba(136, 108, 192, 0.28);
            border-radius: 1rem;
            background: linear-gradient(180deg, rgba(245, 247, 252, 0.92), rgba(255, 255, 255, 0.98));
        }

        .df-employee-stat-card {
            display: flex;
            width: 100%;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .df-employee-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 1rem 2rem rgba(25, 44, 88, 0.18);
        }

        .df-employee-stat-card .card-body {
            position: relative;
            min-height: 190px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 1.45rem 1.5rem;
            padding-right: 5.5rem;
        }

        .df-employee-stat-title {
            display: block;
            min-height: 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: .95rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .df-employee-stat-value-wrap {
            margin-top: 1rem;
            min-height: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .df-employee-stat-value {
            margin: 0;
            color: #fff;
            font-size: clamp(1.65rem, 1.15rem + 1vw, 2.15rem);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -.02em;
        }

        .df-employee-stat-value.df-employee-stat-value-text {
            font-size: clamp(1.25rem, 1rem + .7vw, 1.6rem);
            line-height: 1.25;
        }

        .df-employee-stat-meta {
            margin-top: auto;
            width: 100%;
        }

        .df-employee-stat-subtitle,
        .df-employee-stat-helper {
            display: block;
            color: rgba(255, 255, 255, 0.82);
        }

        .df-employee-stat-subtitle {
            margin-top: .35rem;
            font-size: .96rem;
        }

        .df-employee-stat-helper {
            margin-top: .85rem;
            font-size: .8rem;
            line-height: 1.45;
        }

        @media (max-width: 767.98px) {
            .df-employee-stat-card .card-body {
                min-height: auto;
                padding-right: 4.75rem;
            }

            .df-employee-stat-value-wrap {
                min-height: auto;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-1">Üdvözöljük, {{ auth()->user()?->name }}!</h4>
                    <div class="text-muted">
                        @if($employees->isNotEmpty())
                            {{ $employees->pluck('institution.name')->filter()->unique()->implode(', ') }}
                        @else
                            Jelenleg nincs aktív dolgozói jogviszonya rögzítve.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-stretch" style="margin-bottom:10px;">
        <div class="col-xl-4 col-md-6 d-flex">
            <a href="{{ route('employee.meal-cancellations') }}" class="df-employee-stat-card h-100">
                <div class="card df-stat-card df-stat-orange h-100">
                    <div class="card-body">
                        <span class="df-employee-stat-title">Aktív lemondásaim</span>
                        <div class="df-employee-stat-value-wrap">
                            <div class="df-employee-stat-value">{{ $upcomingCancellableCount }}</div>
                        </div>
                        <div class="df-employee-stat-meta">
                            <span class="df-employee-stat-subtitle">Jövőbeli, még lemondható nap</span>
                            <span class="df-employee-stat-helper">Kezelés a lemondások oldalon</span>
                        </div>
                        <i class="fa-solid fa-calendar-xmark df-stat-icon"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-4 col-md-6 d-flex">
            <a href="{{ Route::has('employee.invoices') ? route('employee.invoices') : '#' }}" class="df-employee-stat-card h-100">
                <div class="card df-stat-card df-stat-blue h-100">
                    <div class="card-body">
                        <span class="df-employee-stat-title">Legutóbbi havi elszámolás</span>
                        <div class="df-employee-stat-value-wrap">
                            <div class="df-employee-stat-value {{ $latestStatement ? '' : 'df-employee-stat-value-text' }}">
                                {{ $latestStatement ? number_format((int) $latestStatement->total_payable, 0, ',', ' ').' Ft' : 'Még nincs elszámolás' }}
                            </div>
                        </div>
                        <div class="df-employee-stat-meta">
                            <span class="df-employee-stat-subtitle">
                                {{ $latestStatement ? $latestStatement->year.'. '.$latestStatement->month.'. hónap' : 'Amint elkészül, itt jelenik meg' }}
                            </span>
                            <span class="df-employee-stat-helper">Számláim megtekintése</span>
                        </div>
                        <i class="fa-solid fa-file-invoice-dollar df-stat-icon"></i>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-4 col-md-6 d-flex">
            <a href="{{ Route::has('employee.payments') ? route('employee.payments') : '#' }}" class="df-employee-stat-card h-100">
                <div class="card df-stat-card df-stat-green h-100">
                    <div class="card-body">
                        <span class="df-employee-stat-title">Legutóbbi befizetésem</span>
                        <div class="df-employee-stat-value-wrap">
                            <div class="df-employee-stat-value {{ $recentPayments->isNotEmpty() ? '' : 'df-employee-stat-value-text' }}">
                                {{ $recentPayments->isNotEmpty() ? number_format((int) $recentPayments->first()->amount, 0, ',', ' ').' Ft' : 'Még nincs befizetés' }}
                            </div>
                        </div>
                        <div class="df-employee-stat-meta">
                            <span class="df-employee-stat-subtitle">
                                {{ $recentPayments->isNotEmpty() ? $recentPayments->first()->paid_at?->format('Y.m.d.') : 'Amint érkezik, itt jelenik meg' }}
                            </span>
                            <span class="df-employee-stat-helper">Összes befizetés megtekintése</span>
                        </div>
                        <i class="fa-solid fa-wallet df-stat-icon"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card df-employee-section-card">
                <div class="card-header border-0 d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="df-employee-section-title">Saját étkezésem</div>
                        <div class="df-employee-section-subtitle">A kártya kizárólag a bejelentkezett dolgozói jogviszony(ok) étkezési adatait mutatja.</div>
                    </div>
                    @if(Route::has('employee.account'))
                        <a href="{{ route('employee.account') }}" class="btn btn-sm btn-outline-primary">Fiókom</a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($employeeCards as $card)
                            <div class="col-lg-6 mb-4">
                                <div class="card df-employee-child-card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3 mb-3">
                                            <div class="df-employee-child-avatar">{{ $card['initials'] }}</div>
                                            <div class="flex-grow-1">
                                                <h4 class="card-title mb-1">{{ $card['name'] }}</h4>
                                                <div class="text-muted small">{{ $card['institution'] }}</div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-sm-6">
                                                <div class="text-muted small">Étkeztetési státusz</div>
                                                <div class="fw-semibold">{{ $card['meal_status'] }}</div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="text-muted small">Aktív menücsomag</div>
                                                <div class="fw-semibold">{{ $card['meal_package'] }}</div>
                                            </div>
                                        </div>

                                        <div class="df-employee-badges mb-4">
                                            @if($card['discount'])
                                                <span class="badge bg-info-subtle text-info border">{{ $card['discount'] }}</span>
                                            @endif
                                            @foreach($card['dietary'] as $dietary)
                                                <span class="badge bg-warning-subtle text-warning border">{{ $dietary }}</span>
                                            @endforeach
                                            @if(blank($card['discount']) && collect($card['dietary'])->isEmpty())
                                                <span class="badge bg-light text-muted border">Nincs külön jelzés</span>
                                            @endif
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ $card['details_url'] }}" class="btn btn-primary btn-sm">Fiókom</a>
                                            <a href="{{ $card['meal_url'] }}" class="btn btn-light btn-sm">Étkezések kezelése</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="df-employee-empty">
                                    @include('layouts.partials.components.ui.empty-state', [
                                        'title' => 'Nincs aktív dolgozói jogviszony',
                                        'text' => 'Ehhez a fiókhoz jelenleg nincs aktív dolgozói jogviszony rögzítve.',
                                        'icon' => 'fa-solid fa-id-badge',
                                    ])
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
