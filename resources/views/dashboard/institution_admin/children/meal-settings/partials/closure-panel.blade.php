@php
    $standalone = $standalone ?? true;
    $closureTarget = $currentSetting ?? (($latestSetting?->wasClosedManually()) ? $latestSetting : null);
    $isClosedMealRelationship = $currentSetting === null && $closureTarget?->wasClosedManually() && $closureTarget->valid_to !== null;
    $isInstitutionSecretary = auth()->user()?->isInstitutionSecretary() ?? false;
    $defaultLastMealDay = old(
        'last_meal_day',
        $closureTarget?->valid_to?->toDateString()
            ?? max(now()->toDateString(), $currentSetting?->valid_from?->toDateString() ?? now()->toDateString())
    );
    $defaultClosureReason = old('closure_reason', $closureTarget?->closure_reason);
    $defaultClosureNote = old('closure_note', $closureTarget?->closure_note);
    $returnList = old('return_list', $returnList ?? null);
    $returnQuery = old('return_query', $returnQuery ?? '');
@endphp

@if($standalone)
<div class="card mb-4">
@endif
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">Étkezési jogviszony</h4>
        @if($isClosedMealRelationship)
            <span class="badge badge-warning light">Lezárt</span>
        @elseif($currentSetting)
            <span class="badge badge-success light">Aktív</span>
        @else
            <span class="badge badge-secondary light">Nincs aktív időszak</span>
        @endif
    </div>
    <div class="card-body">
        @if($isClosedMealRelationship)
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Utolsó étkezési nap</div>
                    <div class="fw-semibold">{{ $closureTarget->valid_to?->format('Y.m.d.') ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Lezárás oka</div>
                    <div class="fw-semibold">{{ $closureTarget->closureReasonLabel() ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Lezárta</div>
                    <div class="fw-semibold">{{ $closureTarget->closedBy?->name ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Lezárás időpontja</div>
                    <div class="fw-semibold">{{ $closureTarget->closed_at?->timezone(config('app.timezone'))->format('Y.m.d. H:i') ?? '—' }}</div>
                </div>
                @if($closureTarget->closure_note)
                    <div class="col-12">
                        <div class="text-muted small">Megjegyzés</div>
                        <div class="fw-semibold">{{ $closureTarget->closure_note }}</div>
                    </div>
                @endif
            </div>
        @elseif($currentSetting)
            <div class="alert alert-light border mb-0">
                Az utolsó étkezési nap megadásával a gyermek étkezési jogviszonya lezárható.
                @if($isInstitutionSecretary)
                    A megadott dátum után a gyermekhez nem tartozik új étkezési időszak.
                @else
                    A megadott dátum után a rendszer már nem számol új étkezési díjat, de a korábbi pénzügyi és étkezési adatok megmaradnak.
                @endif
            </div>
        @else
            <div class="text-muted">
                Jelenleg nincs nyitott étkezési időszak, ezért nincs mit lezárni.
            </div>
        @endif

        @if($closureTarget)
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button"
                        class="btn btn-outline-warning btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#mealRelationshipClosureModal">
                    <i class="fa-solid fa-calendar-xmark me-1"></i>
                    {{ $isClosedMealRelationship ? 'Lezárás módosítása' : 'Étkezési jogviszony lezárása' }}
                </button>

                @if($isClosedMealRelationship)
                    <form method="POST"
                          action="{{ route('dashboard.institution.children.meal-settings.reopen', [$child, $closureTarget]) }}"
                          class="confirm-form"
                          data-title="Újranyitja az étkezési jogviszonyt?"
                        data-text="{{ $isInstitutionSecretary
                              ? 'A lezárás törlődik, és a gyermek újra aktív étkezési időszakba kerül.'
                              : 'A lezárás törlődik, és a gyermek újra aktív étkezési időszakba kerül. Lezárt havi elszámolásokat a rendszer most sem ír át automatikusan.' }}"
                          data-confirm-button-text="Igen, újranyitom">
                        @csrf
                        <input type="hidden" name="return_list" value="{{ $returnList }}">
                        <input type="hidden" name="return_query" value="{{ $returnQuery }}">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="fa-solid fa-rotate-left me-1"></i>Étkezési jogviszony újranyitása
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
@if($standalone)
</div>
@endif

@if($closureTarget)
    <div class="modal fade" id="mealRelationshipClosureModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('dashboard.institution.children.meal-settings.close', [$child, $closureTarget]) }}">
                    @csrf
                    <input type="hidden" name="return_list" value="{{ $returnList }}">
                    <input type="hidden" name="return_query" value="{{ $returnQuery }}">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $isClosedMealRelationship ? 'Lezárás módosítása' : 'Étkezési jogviszony lezárása' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="last_meal_day">Utolsó étkezési nap</label>
                            <input type="date"
                                   id="last_meal_day"
                                   name="last_meal_day"
                                   class="form-control @error('last_meal_day') is-invalid @enderror"
                                   value="{{ $defaultLastMealDay }}"
                                   min="{{ now()->toDateString() }}"
                                   required>
                            <div class="form-text">Visszamenőlegesen (a mai napnál korábbi dátumra) nem szüntethető meg az étkezési jogviszony.</div>
                            @error('last_meal_day')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="closure_reason">Lezárás oka</label>
                            <select id="closure_reason"
                                    name="closure_reason"
                                    class="form-control @error('closure_reason') is-invalid @enderror"
                                    required>
                                <option value="">Válassz okot</option>
                                @foreach($closureReasonLabels as $reasonValue => $reasonLabel)
                                    <option value="{{ $reasonValue }}" @selected($defaultClosureReason === $reasonValue)>
                                        {{ $reasonLabel }}
                                    </option>
                                @endforeach
                            </select>
                            @error('closure_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="closure_note">Egyéb megjegyzés</label>
                            <input type="text"
                                   id="closure_note"
                                   name="closure_note"
                                   maxlength="191"
                                   class="form-control @error('closure_note') is-invalid @enderror"
                                   value="{{ $defaultClosureNote }}">
                            @error('closure_note')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-light border mb-0">
                            <div class="fw-semibold mb-1">Biztosan lezárja a gyermek étkezési jogviszonyát?</div>
                            <div>Utolsó étkezési nap: {{ $defaultLastMealDay ?: '—' }}</div>
                            <div class="small mt-2">
                                @if($isInstitutionSecretary)
                                    A megadott dátum után a gyermek részére nem indul új étkezési időszak.
                                @else
                                    A megadott dátum után a gyermek részére nem keletkezik új étkezési díj.
                                    A korábbi étkezési és pénzügyi adatok megmaradnak.
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
