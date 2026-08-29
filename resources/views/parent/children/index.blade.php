@extends('layouts.parent')

@section('page_title', 'Gyermekeim')

@push('styles')
    <style>
        .df-parent-children-page .child-card {
            border-radius: 1.25rem;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
        }

        .df-parent-children-page .child-card-header,
        .df-parent-children-page .child-card-body,
        .df-parent-children-page .child-card-footer {
            position: relative;
        }

        .df-parent-children-page .child-card-avatar {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            background: linear-gradient(135deg, #d94a16 0%, #f27a22 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .df-parent-children-page .child-info-section {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            background: #fcfdfd;
            padding: 1.1rem;
        }

        .df-parent-children-page .child-info-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
        }

        .df-parent-children-page .df-parent-info-list {
            display: grid;
            gap: 0.75rem;
        }

        .df-parent-children-page .df-parent-info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
        }

        .df-parent-children-page .df-parent-info-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .df-parent-children-page .df-parent-info-row span {
            color: #64748b;
            font-size: 0.86rem;
        }

        .df-parent-children-page .df-parent-info-row strong {
            color: #0f172a;
            text-align: right;
        }

        .df-parent-children-page .df-parent-inline-panel {
            border-radius: 0.9rem;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 0.95rem;
        }

        .df-parent-children-page .child-card-footer {
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        @media (max-width: 767.98px) {
            .df-parent-children-page .df-page-header {
                margin-bottom: 1.5rem;
            }

            .df-parent-children-page .df-parent-info-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .df-parent-children-page .df-parent-info-row strong {
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    <div class="df-parent-children-page" style="margin-bottom:30px;">
        @php
            $childrenCount = $stats['children_count'] ?? 0;
            $institutionsCount = $stats['institutions_count'] ?? 0;
            $subtitle = $childrenCount === 1
                ? 'Itt láthatod gyermeked intézményi, étkezési és kedvezményadatait.'
                : 'Itt láthatod gyermekeid intézményi, étkezési és kedvezményadatait.';
        @endphp

        @include('layouts.partials.components.ui.page-header', [
            'title' => 'Gyermekeim',
            'subtitle' => $subtitle,
        ])

        <div class="row">
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Kapcsolt gyermekek',
                'value' => $stats['children_count'],
                'subtitle' => $stats['children_count'] === 1 ? '1 kapcsolt gyermek' : $stats['children_count'].' kapcsolt gyermek',
                'icon' => 'fa-solid fa-users',
                'color' => 'blue',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Érintett intézmények',
                'value' => $stats['institutions_count'],
                'subtitle' => 'Kapcsolt intézményi háttér',
                'icon' => 'fa-solid fa-school',
                'color' => 'green',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Aktív étkezők',
                'value' => $stats['active_meal_count'],
                'subtitle' => 'Aktív étkezési beállítással',
                'icon' => 'fa-solid fa-utensils',
                'color' => 'orange',
                'colClass' => 'col-xl-3 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Aktív kedvezmények',
                'value' => $stats['active_discount_count'],
                'subtitle' => 'Érvényes kedvezményes gyermekek',
                'icon' => 'fa-solid fa-percent',
                'color' => 'purple',
                'colClass' => 'col-xl-3 col-md-6',
            ])
        </div>

        @if($childrenCount > 1 && $institutionsCount > 1)
            <div class="alert alert-light border mb-4">
                Több intézményhez kapcsolódó gyermekek adatai jelennek meg. Az intézmény neve minden kártya fejlécében külön látható.
            </div>
        @endif

        <div class="row g-4">
            @forelse($childCards as $card)
                <div class="{{ $childrenCount === 1 ? 'col-12' : 'col-12 col-xl-6 d-flex' }}">
                    @include('parent.children.partials.child-overview-card', ['card' => $card])
                </div>
            @empty
                <div class="col-12">
                    @include('layouts.partials.components.ui.empty-state', [
                        'title' => 'Nincs kapcsolt gyermek',
                        'text' => 'Ehhez a szülői fiókhoz jelenleg egyetlen gyermek sincs hozzárendelve. A kapcsolást az intézményi adminisztrátor tudja elvégezni.',
                        'icon' => 'fa-solid fa-children',
                    ])
                </div>
            @endforelse
        </div>

        @if($children->hasPages())
            <div class="mt-4">
                {{ $children->links('vendor.pagination.digifood') }}
            </div>
        @endif
    </div>
@endsection
