@extends('layouts.superadmin')

@section('title', 'Étkezési beállítás szerkesztése')

@section('content')
@php
    $selectedMealTypeIds = collect(old('institution_meal_type_ids', $selectedMealTypeIds))->map(fn ($id) => (string) $id);
    $returnList = old('return_list', $returnList ?? null);
    $returnQuery = old('return_query', $returnQuery ?? '');
    $returnParams = array_filter([
        'return_list' => $returnList,
        'return_query' => $returnQuery,
    ], fn ($value) => filled($value));
    $mealSettingsReturnQuery = $returnParams !== [] ? '?'.http_build_query($returnParams) : '';
    $isInstitutionSecretary = auth()->user()?->isInstitutionSecretary() ?? false;
    $isAlreadyStarted = $mealSetting->valid_from->toDateString() <= ($today ?? now()->toDateString());
@endphp
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Étkezési beállítás szerkesztése',
        'subtitle' => $child->name . ' · meglévő étkezési szabály módosítása',
        'buttonText' => 'Vissza az előzményekhez',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.children.meal-settings.index', $child) . $mealSettingsReturnQuery,
    ])

    @if($isAlreadyStarted)
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            Ez a beállítás már <strong>{{ $mealSetting->valid_from->format('Y.m.d.') }}-től</strong> érvényben van (akár már el is múlt).
            A kezdődátum vagy a mód módosítása visszamenőleg is megváltoztathatja az étkezési napokat.
            @if($isInstitutionSecretary)
                Ha a módosítás már lezárt havi elszámolást is érint, a rendszer figyelmeztetni fog, de a lezárt hónapokat nem írja át automatikusan.
            @else
                Ha a módosítás már lezárt havi elszámolást is érint, azt a rendszer nem írja át automatikusan – erről mentés után külön jelzést kapsz, és külön pénzügyi korrekció lehet szükséges.
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Beállítás adatai</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.children.meal-settings.update', [$child, $mealSetting]) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="return_list" value="{{ $returnList }}">
                <input type="hidden" name="return_query" value="{{ $returnQuery }}">

                <div class="mb-4">
                    <label class="form-label d-block">Mód</label>
                    @foreach($modeLabels as $value => $label)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="mode" value="{{ $value }}"
                                   id="mode_{{ $value }}"
                                   @checked(old('mode', $mealSetting->mode) === $value)>
                            <label class="form-check-label" for="mode_{{ $value }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    @error('mode') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="row">
                    <div class="col-lg-4 mb-3">
                        <label class="form-label" for="valid_from">Érvényes ettől</label>
                        <input id="valid_from" type="date" name="valid_from"
                               class="form-control @error('valid_from') is-invalid @enderror"
                               value="{{ old('valid_from', $mealSetting->valid_from->toDateString()) }}" required>
                        @error('valid_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="small text-muted mt-1">
                            Jelenlegi érvényesség: {{ $mealSetting->valid_from->format('Y.m.d.') }} – {{ $mealSetting->valid_to?->format('Y.m.d.') ?? 'nyitott' }}
                        </div>
                    </div>
                    <div class="col-lg-8 mb-3">
                        <label class="form-label" for="note">Megjegyzés</label>
                        <input id="note" type="text" name="note"
                               class="form-control @error('note') is-invalid @enderror"
                               value="{{ old('note', $mealSetting->note) }}">
                        @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="card border mb-4" data-mode-block="institution_default">
                    <div class="card-header bg-light">
                        <h5 class="mb-1">Intézményi alapértelmezett</h5>
                        <div class="small text-muted">
                            Jelenlegi aktív alapcsomag:
                            <strong>{{ $defaultPackage?->name ?? 'Nincs beállítva' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="card border mb-4" data-mode-block="package">
                    <div class="card-header bg-light">
                        <h5 class="mb-1">Másik menücsomag kiválasztása</h5>
                    </div>
                    <div class="card-body">
                        <label class="form-label" for="institution_meal_package_id">Menücsomag</label>
                        <select id="institution_meal_package_id" name="institution_meal_package_id"
                                class="form-control @error('institution_meal_package_id') is-invalid @enderror">
                            <option value="">Nincs kiválasztva</option>
                            @foreach($availablePackages as $package)
                                <option value="{{ $package->id }}"
                                    @selected((string) old('institution_meal_package_id', $mealSetting->institution_meal_package_id) === (string) $package->id)>
                                    {{ $package->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('institution_meal_package_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="card border mb-4" data-mode-block="custom">
                    <div class="card-header bg-light">
                        <h5 class="mb-1">Egyedi étkezések</h5>
                    </div>
                    <div class="card-body">
                        @error('institution_meal_type_ids')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror

                        <div class="row">
                            @forelse($availableMealTypes as $mealType)
                                <div class="col-xl-4 col-lg-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="institution_meal_type_ids[]"
                                               value="{{ $mealType->id }}"
                                               id="meal_type_{{ $mealType->id }}"
                                               @checked($selectedMealTypeIds->contains((string) $mealType->id))>
                                        <label class="form-check-label" for="meal_type_{{ $mealType->id }}">
                                            {{ $mealType->mealType->name }}
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-muted">Nincs választható aktív étkezéstípus.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="{{ route('dashboard.institution.children.meal-settings.index', $child) }}{{ $mealSettingsReturnQuery }}" class="btn btn-light">Mégsem</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // A "Mód" (institution_default / package / custom) rádiógomb alapján
        // csak a hozzá tartozó kártyát mutatjuk, a többit elrejtjük - és a
        // rejtett kártyákban lévő mezőket letiltjuk (disabled), hogy azok
        // mentéskor NE kerüljenek be a beküldött adatok közé. Enélkül egy
        // korábban kitöltött/kijelölt, de már nem releváns mező (pl. egy
        // véletlenül bejelölve maradt egyedi étkezéstípus) a szerveroldali
        // validációt dobta el egy, a felhasználó számára érthetetlen
        // hibaüzenettel. Ez tisztán megjelenítési javítás, a validációs
        // logikát nem érinti.
        var modeRadios = document.querySelectorAll('input[name="mode"]');
        var blocks = document.querySelectorAll('[data-mode-block]');

        function applyMealSettingModeVisibility() {
            var checkedRadio = document.querySelector('input[name="mode"]:checked');
            var activeMode = checkedRadio ? checkedRadio.value : null;

            blocks.forEach(function (block) {
                // Egy sikertelen mentés után a szerver visszaküldheti a
                // korábban beküldött (hibás) adatokat egy, az aktuálisan
                // kiválasztott módhoz nem tartozó blokkban - ilyenkor a
                // hibaüzenetet tartalmazó blokkot NEM rejtjük el, különben a
                // felhasználó nem látná, mit kell javítania.
                var hasValidationError = block.querySelector('.invalid-feedback, .alert-danger') !== null;
                var isActive = block.getAttribute('data-mode-block') === activeMode || hasValidationError;
                block.style.display = isActive ? '' : 'none';

                block.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.disabled = ! isActive;
                });
            });
        }

        modeRadios.forEach(function (radio) {
            radio.addEventListener('change', applyMealSettingModeVisibility);
        });

        applyMealSettingModeVisibility();
    })();
</script>
@endpush
@endsection
