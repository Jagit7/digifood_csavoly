@php
    /** @var \App\Models\Child $child */
    $child = $card['child'];
    $mealData = $card['meal_data'];
    $discount = $card['discount'];
@endphp

<div class="card border-0 shadow-sm h-100 child-card">
    <div class="card-body d-flex flex-column p-0">
        <div class="child-card-header p-4">
            <div class="d-flex align-items-start gap-3">
                <div class="child-card-avatar">
                {{ \Illuminate\Support\Str::of($child->name)->trim()->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') }}
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h4 class="mb-0">{{ $child->name }}</h4>
                        <span class="badge {{ $child->active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-muted border' }}">
                            <i class="fa-solid {{ $child->active ? 'fa-circle-check' : 'fa-circle' }} me-1"></i>
                            {{ $child->active ? 'Aktív' : 'Inaktív' }}
                        </span>
                    </div>
                    <div class="text-muted small d-flex flex-wrap gap-3">
                        @if($child->institution?->name)
                            <span><i class="fa-solid fa-school me-1"></i>{{ $child->institution->name }}</span>
                        @endif
                        @if($child->group_name)
                            <span><i class="fa-solid fa-people-group me-1"></i>{{ $child->group_name }}</span>
                        @endif
                        @if($card['institution_type_label'])
                            <span><i class="fa-solid fa-layer-group me-1"></i>{{ $card['institution_type_label'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="child-card-body px-4 pb-4">
            <div class="row g-4">
                <div class="col-12 col-xxl-6">
                    <section class="child-info-section h-100">
                        <div class="child-info-section-title">
                            <i class="fa-solid fa-circle-info text-primary me-2"></i>Alapadatok
                        </div>

                        <div class="df-parent-info-list">
                            <div class="df-parent-info-row">
                                <span>Intézmény</span>
                                <strong>{{ $child->institution?->name ?? 'Nincs megadva' }}</strong>
                            </div>
                            <div class="df-parent-info-row">
                                <span>Csoport / osztály</span>
                                <strong>{{ $child->group_name ?: 'Nincs megadva' }}</strong>
                            </div>
                            <div class="df-parent-info-row">
                                <span>Nevelési / tanév</span>
                                <strong>{{ $child->school_year ?: 'Nincs megadva' }}</strong>
                            </div>
                            <div class="df-parent-info-row">
                                <span>Kapcsolt gondviselő</span>
                                <strong>{{ $card['guardian_names']->isNotEmpty() ? $card['guardian_names']->implode(', ') : 'Nincs megadva' }}</strong>
                            </div>
                            @if($card['linked_at_label'])
                                <div class="df-parent-info-row">
                                    <span>Kapcsolás dátuma</span>
                                    <strong>{{ $card['linked_at_label'] }}</strong>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>

                <div class="col-12 col-xxl-6">
                    <section class="child-info-section h-100">
                        @include('parent.children.partials.meal-data-card', [
                            'mealData' => $mealData,
                            'nextMealDayLabel' => $card['next_meal_day_label'],
                            'nextCancellationDeadlineLabel' => $card['next_cancellation_deadline_label'],
                        ])
                    </section>
                </div>

                <div class="col-12 col-xxl-6">
                    <section class="child-info-section h-100">
                        <div class="child-info-section-title">
                            <i class="fa-solid fa-percent text-primary me-2"></i>Kedvezmények
                        </div>

                            <div class="df-parent-info-row">
                                <span>Kedvezmény</span>
                                <strong>{{ $discount['has_active_discount'] ? $discount['name'] : 'Nincs aktív kedvezmény' }}</strong>
                            </div>
                            <div class="df-parent-info-row">
                                <span>Mérték</span>
                                <strong>{{ $discount['percentage_label'] ?? 'Nincs megadva' }}</strong>
                            </div>
                    </section>
                </div>

                <div class="col-12 col-xxl-6">
                    <section class="child-info-section h-100">
                        <div class="child-info-section-title">
                            <i class="fa-solid fa-shield-heart text-primary me-2"></i>Állapot
                        </div>

                        <div class="df-parent-info-list">
                            <div class="df-parent-info-row">
                                <span>Diétás étkezés</span>
                                <strong>{{ $mealData['is_dietary'] ? 'Beállítva' : 'Nincs' }}</strong>
                            </div>
                            @if($card['next_cancellation_deadline_label'])
                                <div class="df-parent-info-row">
                                    <span>Lemondási határidő</span>
                                    <strong>{{ $card['next_cancellation_deadline_label'] }}</strong>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 d-flex flex-wrap gap-2">
                            @foreach($card['status_items'] as $statusItem)
                                <span class="badge bg-{{ $statusItem['tone'] }}{{ in_array($statusItem['tone'], ['light'], true) ? ' text-muted border' : '-subtle text-'.$statusItem['tone'].' border border-'.$statusItem['tone'].'-subtle' }}">
                                    <i class="{{ $statusItem['icon'] }} me-1"></i>{{ $statusItem['label'] }}
                                </span>
                            @endforeach
                            @foreach($mealData['dietary_names'] as $dietaryName)
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">{{ $dietaryName }}</span>
                            @endforeach
                        </div>

                        @if($mealData['note'])
                            <div class="df-parent-inline-panel mt-3">
                                <div class="text-muted small mb-1">Megjegyzés</div>
                                <div class="fw-semibold">{{ $mealData['note'] }}</div>
                            </div>
                        @endif
                    </section>
                </div>
            </div>
        </div>

        <div class="child-card-footer px-4 py-3 mt-auto">
            <div class="d-flex flex-wrap gap-2">
                @foreach($card['meal_actions'] as $action)
                    <a href="{{ $action['url'] }}" class="{{ $action['class'] }}">
                        <i class="{{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
