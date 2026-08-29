@extends('layouts.parent')

@section('page_title', 'Feltöltött étlapok')

@push('styles')
    <style>
        .df-parent-menus-page .df-menu-card {
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            height: 100%;
        }

        .df-parent-menus-page .df-menu-preview {
            background:
                linear-gradient(135deg, rgba(20, 184, 166, 0.12), rgba(249, 115, 22, 0.1)),
                #f8fafc;
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .df-parent-menus-page .df-menu-preview img {
            width: 100%;
            height: 100%;
            max-height: 280px;
            object-fit: cover;
            display: block;
        }

        .df-parent-menus-page .df-menu-preview-icon {
            width: 76px;
            height: 76px;
            border-radius: 1.4rem;
            background: rgba(255, 255, 255, 0.84);
            color: #0f766e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .df-parent-menus-page .df-menu-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.85rem;
        }

        .df-parent-menus-page .df-menu-meta-item {
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: #fff;
            padding: 0.95rem 1rem;
        }

        .df-parent-menus-page .df-menu-label {
            display: block;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.35rem;
        }

        .df-parent-menus-page .df-menu-value {
            color: #111827;
            font-weight: 600;
        }

        .df-parent-menus-page .df-menu-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .df-parent-menus-page .df-menu-stat-card {
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
        }

        @media (max-width: 575.98px) {
            .df-parent-menus-page .df-menu-preview {
                min-height: 180px;
            }

            .df-parent-menus-page .df-menu-actions .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="df-parent-menus-page">
        @include('layouts.partials.components.ui.page-header', [
            'title' => 'Feltöltött étlapok',
            'subtitle' => 'Itt tekintheti meg az intézmény által közzétett aktuális és korábbi étlapokat.',
        ])

        <div class="row">
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Elérhető étlapok',
                'value' => $stats['menu_count'],
                'subtitle' => 'Aktív, megtekinthető feltöltések',
                'icon' => 'fa-solid fa-file-lines',
                'color' => 'green',
                'colClass' => 'col-xl-4 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Kapcsolt intézmények',
                'value' => $stats['institution_count'],
                'subtitle' => 'Olyan intézmények, ahol kapcsolt gyermek van',
                'icon' => 'fa-solid fa-school',
                'color' => 'blue',
                'colClass' => 'col-xl-4 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Legutóbbi közzététel',
                'value' => $stats['latest_published_at_label'],
                'subtitle' => 'Utolsó elérhető étlap',
                'icon' => 'fa-solid fa-calendar-days',
                'color' => 'orange',
                'colClass' => 'col-xl-4 col-md-12',
            ])
        </div>

        @if($menus->count() === 0)
            <div class="card df-menu-card">
                <div class="card-body p-4">
                    @include('layouts.partials.components.ui.empty-state', [
                        'icon' => 'fa-solid fa-file-lines',
                        'title' => 'Jelenleg nincs megtekinthető étlap.',
                        'text' => 'Amint az intézmény új étlapot tölt fel és aktívvá teszi, ezen az oldalon fog megjelenni.',
                    ])
                </div>
            </div>
        @else
            <div class="row g-4">
                @foreach($menus as $menu)
                    <div class="col-12 col-xl-6">
                        <div class="card df-menu-card">
                            <div class="df-menu-preview">
                                @if($menu['is_image'])
                                    <a href="{{ $menu['view_url'] }}" target="_blank" rel="noopener" class="d-block w-100 h-100">
                                        <img src="{{ $menu['preview_url'] }}" alt="{{ $menu['title'] }}">
                                    </a>
                                @else
                                    <div class="text-center px-4 py-5">
                                        <div class="df-menu-preview-icon mx-auto mb-3">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>
                                        <div class="fw-semibold text-dark">{{ $menu['file_type_label'] }} fájl</div>
                                        <div class="text-muted small mt-1">{{ $menu['file_name'] }}</div>
                                    </div>
                                @endif
                            </div>

                            <div class="card-body p-4">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                                    <div>
                                        <h4 class="mb-1">{{ $menu['title'] }}</h4>
                                        <div class="text-muted">{{ $menu['institution_name'] }}</div>
                                    </div>

                                    <span class="badge badge-primary light">{{ $menu['type_label'] }}</span>
                                </div>

                                <div class="df-menu-meta mb-4">
                                    <div class="df-menu-meta-item">
                                        <span class="df-menu-label">Érvényesség</span>
                                        <div class="df-menu-value">{{ $menu['period_label'] }}</div>
                                    </div>
                                    <div class="df-menu-meta-item">
                                        <span class="df-menu-label">Közzétéve</span>
                                        <div class="df-menu-value">{{ $menu['published_at_label'] ?? '-' }}</div>
                                    </div>
                                    <div class="df-menu-meta-item">
                                        <span class="df-menu-label">Fájltípus</span>
                                        <div class="df-menu-value">{{ $menu['file_type_label'] }}</div>
                                    </div>
                                    <div class="df-menu-meta-item">
                                        <span class="df-menu-label">Méret</span>
                                        <div class="df-menu-value">{{ $menu['file_size_label'] ?? '-' }}</div>
                                    </div>
                                </div>

                                <div class="df-menu-actions">
                                    <a href="{{ $menu['view_url'] }}" target="_blank" rel="noopener" class="btn btn-primary">
                                        Megtekintés
                                    </a>
                                    <a href="{{ $menu['download_url'] }}" class="btn btn-outline-secondary">
                                        Letöltés
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($menus->hasPages())
                <div class="mt-4">
                    {{ $menus->links('vendor.pagination.digifood') }}
                </div>
            @endif
        @endif
    </div>
@endsection
