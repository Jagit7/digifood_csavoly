<div class="card-header border-0 px-0 pt-0 pb-3">
    <h4 class="card-title mb-0">Étkezési adatok</h4>
</div>

<div class="card-body p-0">
    @if($mealData['is_closed_meal_relationship'])
        <div class="alert alert-warning border mb-4" role="alert">
            <div class="fw-semibold mb-1">
                Étkezési jogviszony lezárva
            </div>
            <div>
                Utolsó étkezési nap: {{ $mealData['valid_to_label'] ?? '—' }}
            </div>
            @if($mealData['closure_reason_label'])
                <div class="small mt-2">
                    Lezárás oka: {{ $mealData['closure_reason_label'] }}
                </div>
            @endif
        </div>
    @elseif($mealData['is_upcoming_setting'])
        {{--
            Ugyanaz a jelzés, mint amit az admin felület mutat, ha a
            gyermeknek van jövőben induló, még nem érvényes étkezési
            beállítása (ld. Dashboard\InstitutionAdmin\ChildMealSettingController
            $isUpcomingSetting). A két felületnek egységesnek kell lennie,
            hogy a szülő ne lássa "nincs aktív étkezés"-nek azt, amit az
            intézmény már beütemezett.
        --}}
        <div class="alert alert-info border mb-4" role="alert">
            <div class="fw-semibold mb-1">
                Ütemezett étkezés
            </div>
            <div>
                Az étkeztetés <strong>{{ $mealData['upcoming_valid_from_label'] }}-től</strong> van beütemezve, jelenleg (ma) még nem étkező.
            </div>
            @if($mealData['upcoming_mode_label'])
                <div class="small mt-2">
                    Étkeztetés módja: {{ $mealData['upcoming_mode_label'] }}
                </div>
            @endif
        </div>
    @elseif($mealData['show_missing_package_notice'])
        <div class="alert alert-light border mb-4" role="alert">
            <div class="fw-semibold">
                Ehhez a gyermekhez jelenleg nincs aktív étkezési csomag beállítva.
            </div>
        </div>
    @endif

    <div class="row g-3">

        {{-- Étkezési jogviszony --}}
        <div class="col-12">
            <div class="text-muted small mb-1">
                Étkezési jogviszony
            </div>
            <div class="fw-semibold">
                {{ $mealData['status_label'] }}
            </div>
        </div>

        {{-- Étkeztetés módja --}}
        @if($mealData['mode_label'])
            <div class="col-12">
                <div class="text-muted small mb-1">
                    Étkeztetés módja
                </div>
                <div class="fw-semibold">
                    {{ $mealData['mode_label'] }}
                </div>
            </div>
        @endif

        {{-- Aktuális étkezési csomag --}}
        @if($mealData['package_name'])
            <div class="col-12">
                <div class="text-muted small mb-1">
                    Aktuális étkezési csomag
                </div>
                <div class="fw-semibold">
                    {{ $mealData['package_name'] }}
                </div>
            </div>
        @endif

        {{-- Érvényesség --}}
        @if($mealData['valid_from_label'])
            <div class="col-12">
                <div class="text-muted small mb-1">
                    Érvényesség kezdete
                </div>
                <div class="fw-semibold">
                    {{ $mealData['valid_from_label'] }}
                </div>
            </div>
        @endif

        @if($mealData['valid_to_label'])
            <div class="col-12">
                <div class="text-muted small mb-1">
                    {{ $mealData['is_closed_meal_relationship'] ? 'Utolsó étkezési nap' : 'Érvényesség vége' }}
                </div>
                <div class="fw-semibold">
                    {{ $mealData['valid_to_label'] }}
                </div>
            </div>
        @endif

        @if($mealData['closure_reason_label'])
            <div class="col-12">
                <div class="text-muted small mb-1">
                    Lezárás oka
                </div>
                <div class="fw-semibold">
                    {{ $mealData['closure_reason_label'] }}
                </div>
            </div>
        @endif

        @if($mealData['closure_note'])
            <div class="col-12">
                <div class="text-muted small mb-1">
                    Megjegyzés a lezáráshoz
                </div>
                <div class="fw-semibold">
                    {{ $mealData['closure_note'] }}
                </div>
            </div>
        @endif

        {{-- Diétás étkezés --}}
        <div class="col-12">
            <div class="text-muted small mb-2">
                Diétás étkezés
            </div>

            <span class="badge {{ $mealData['is_dietary'] ? 'bg-warning text-dark' : 'bg-light text-muted border' }}">
                <i class="fa-solid {{ $mealData['is_dietary'] ? 'fa-shield-heart' : 'fa-ban' }} me-1"></i>
                {{ $mealData['is_dietary'] ? 'Igen' : 'Nem' }}
            </span>
        </div>

        {{-- Diéta megnevezése --}}
        @if($mealData['dietary_names']->isNotEmpty())
            <div class="col-12">
                <div class="text-muted small mb-2">
                    Diéta megnevezése
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @foreach($mealData['dietary_names'] as $dietaryName)
                        <span class="badge bg-warning-subtle text-warning border text-nowrap">
                            {{ $dietaryName }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Aktív étkezéstípusok --}}
        @if($mealData['meal_types']->isNotEmpty())
            <div class="col-12">
                <div class="text-muted small mb-2">
                    Aktív étkezéstípusok
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @foreach($mealData['meal_types'] as $mealTypeName)
                        <span class="badge bg-primary-subtle text-primary border text-nowrap">
                            <i class="fa-solid fa-utensils me-1"></i>
                            {{ $mealTypeName }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Megjegyzés --}}
        @if($mealData['note'])
            <div class="col-12">
                <div class="text-muted small mb-1">
                    Megjegyzés
                </div>
                <div class="fw-semibold">
                    {{ $mealData['note'] }}
                </div>
            </div>
        @endif

        {{-- A/B menü --}}
        @if($mealData['ab_menu'])
            <div class="col-12">
                <div class="border rounded p-3 mt-1">

                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <div class="text-muted small">
                                A/B menü
                            </div>
                            <div class="fw-semibold">
                                {{ $mealData['ab_menu']['date_label'] }}
                            </div>
                        </div>

                        <span class="badge bg-info text-dark text-nowrap">
                            <i class="fa-solid fa-check me-1"></i>
                            {{ $mealData['ab_menu']['choice_label'] }}
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="text-muted small mb-1">
                                A menü
                            </div>
                            <div class="fw-semibold">
                                {{ $mealData['ab_menu']['menu_a'] }}
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="text-muted small mb-1">
                                B menü
                            </div>
                            <div class="fw-semibold">
                                {{ $mealData['ab_menu']['menu_b'] }}
                            </div>
                        </div>

                        @if($mealData['ab_menu']['menu_dietary'])
                            <div class="col-12">
                                <div class="text-muted small mb-1">
                                    Diétás menü
                                </div>
                                <div class="fw-semibold">
                                    {{ $mealData['ab_menu']['menu_dietary'] }}
                                </div>
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        @endif

    </div>
</div>
