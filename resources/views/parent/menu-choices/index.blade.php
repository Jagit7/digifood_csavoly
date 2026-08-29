@extends('layouts.parent')

@section('title', 'Menüválasztás')

@push('styles')
    <style>
        .df-menu-choice-item {
            background: #f8fafc;
        }
    </style>
@endpush

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Menüválasztás',
        'subtitle' => 'A/B menük kiválasztása gyermekenként',
    ])

    @if($sections->isEmpty())
        <div class="card">
            <div class="card-body">
                <h4 class="mb-2">Jelenleg nincs aktív menüválasztási időszak.</h4>
                <p class="text-muted mb-0">
                    Ha az intézmény elindítja a következő A/B menüválasztást, itt fog megjelenni.
                </p>
            </div>
        </div>
    @endif

    @foreach($sections as $section)
        <div class="card mb-4">
            <div class="card-header border-0 pb-0">
                <div class="w-100">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                        <div>
                            <h4 class="mb-1">{{ $section['institution']->name }}</h4>
                            <div class="text-muted">
                                {{ $section['plan']->title ?: 'A/B menüterv' }}
                            </div>
                        </div>
                        <span class="badge {{ $section['state'] === 'active' ? 'badge-success' : 'badge-secondary' }} light">
                            {{ $section['state_label'] }}
                        </span>
                    </div>

                    @if($section['state'] === 'active')
                        <div class="alert alert-info mt-3 mb-0">
                            <strong>A/B menüválasztás</strong><br>
                            Választási határidő: {{ $section['deadline']?->format('Y.m.d. H:i') ?? '—' }}<br>
                            A választás a határidő napján 23:59-ig módosítható.
                        </div>
                    @else
                        <div class="alert alert-secondary mt-3 mb-0">
                            <strong>A menüválasztási időszak lezárult.</strong><br>
                            A korábban leadott választások továbbra is láthatók, de nem módosíthatók.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card-body">
                @foreach($section['children'] as $child)
                    @php($isDietary = app(\App\Services\Meals\AbMenuSelectionService::class)->isDietaryChild($child))
                    <div class="card border mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">{{ $child->name }}</h5>
                        </div>
                        <div class="card-body">
                            @if($isDietary)
                                <div class="alert alert-warning mb-3">
                                    Diétás étkezés – A/B menüválasztás nem szükséges.
                                </div>
                            @endif

                            <form method="POST"
                                  action="{{ route('parent.menu-choices.update', $child->id) }}">
                                @csrf
                                @method('PUT')

                                <div class="df-menu-choice-list">
                                    @foreach($section['items'] as $item)
                                        @php($choiceRecord = app(\App\Services\Meals\AbMenuSelectionService::class)->explicitChoiceRecord($section['choice_map'], $child->id, $item))
                                        @php($effectiveChoice = app(\App\Services\Meals\AbMenuSelectionService::class)->effectiveChoiceForItem($child, $item, $choiceRecord))
                                        <div class="df-menu-choice-item border rounded-3 p-3 mb-3">
                                            <div class="fw-bold mb-2">{{ $item->menu_date?->format('Y.m.d.') }}</div>

                                            <div class="row g-3 mb-3">
                                                <div class="col-sm-6">
                                                    <div class="small text-muted mb-1">A menü</div>
                                                    <div>{{ $item->menu_a ?: '—' }}</div>
                                                </div>
                                                <div class="col-sm-6">
                                                    <div class="small text-muted mb-1">B menü</div>
                                                    <div>
                                                        {{ $item->menu_b ?: '—' }}
                                                        @if($isDietary && filled($item->menu_dietary))
                                                            <div class="small text-muted mt-1">
                                                                Diétás menü: {{ $item->menu_dietary }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="small text-muted mb-1">Választás</div>
                                            @if(!$isDietary && app(\App\Services\Meals\AbMenuSelectionService::class)->isSelectableItem($item))
                                                <div class="d-flex flex-wrap gap-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input"
                                                               type="radio"
                                                               name="choices[{{ $item->id }}]"
                                                               id="choice-a-{{ $child->id }}-{{ $item->id }}"
                                                               value="A"
                                                               @checked($effectiveChoice === 'A')
                                                               @disabled($section['state'] !== 'active')>
                                                        <label class="form-check-label" for="choice-a-{{ $child->id }}-{{ $item->id }}">
                                                            A menü
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input"
                                                               type="radio"
                                                               name="choices[{{ $item->id }}]"
                                                               id="choice-b-{{ $child->id }}-{{ $item->id }}"
                                                               value="B"
                                                               @checked($effectiveChoice === 'B')
                                                               @disabled($section['state'] !== 'active')>
                                                        <label class="form-check-label" for="choice-b-{{ $child->id }}-{{ $item->id }}">
                                                            B menü
                                                        </label>
                                                    </div>
                                                </div>
                                            @elseif($isDietary)
                                                <span class="text-muted">Diétás étkezés</span>
                                            @else
                                                <span class="text-muted">Ehhez a naphoz nincs választható B menü.</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if($section['state'] === 'active' && !$isDietary)
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">Mentés</button>
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
