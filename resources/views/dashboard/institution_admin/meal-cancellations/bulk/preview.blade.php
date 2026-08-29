@extends('layouts.superadmin')

@section('title', 'Csoportos lemondás előnézet')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Csoportos lemondás előnézet',
        'subtitle' => 'Mentés előtt ellenőrizhető, mi fog ténylegesen létrejönni',
        'buttonText' => 'Vissza a kiválasztáshoz',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.meal-cancellations.bulk.create'),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Kijelölt gyermekek', 'value' => $preview['summary']['selected_children_count'], 'subtitle' => 'A véglegesítésre váró lista', 'icon' => 'fa-solid fa-users', 'color' => 'blue'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Étkezési napok', 'value' => $preview['summary']['service_days_count'], 'subtitle' => 'A tartományban lévő szolgáltatási napok', 'icon' => 'fa-solid fa-calendar-day', 'color' => 'green'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Létrehozandó', 'value' => $preview['summary']['planned_cancellation_count'], 'subtitle' => 'Új lemondási rekord', 'icon' => 'fa-solid fa-save', 'color' => 'orange'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Kihagyások', 'value' => $preview['summary']['duplicate_count'] + $preview['summary']['missing_meal_setting_count'] + $preview['summary']['non_service_day_count'] + $preview['summary']['deadline_blocked_count'] + $preview['summary']['class_cancelled_count'], 'subtitle' => 'Már lemondott / nem rögzíthető alkalmak', 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => 'red'])
    </div>

    <div class="card mb-4">
        <div class="card-header"><h4 class="card-title mb-0">Összesítés</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-4 mb-3">
                    <div class="text-muted small">Esemény</div>
                    <strong>{{ $validated['event_name'] }}</strong>
                </div>
                <div class="col-lg-4 mb-3">
                    <div class="text-muted small">Időszak</div>
                    <strong>{{ $validated['date_from'] }} – {{ $validated['date_to'] }}</strong>
                </div>
                <div class="col-lg-4 mb-3">
                    <div class="text-muted small">Étkezés</div>
                    <strong>{{ $mealScopeOptions[$validated['meal_scope']] ?? $validated['meal_scope'] }}</strong>
                </div>
                <div class="col-lg-12 mb-3">
                    <div class="text-muted small">Érintett osztályok / csoportok</div>
                    <strong>{{ $preview['summary']['selected_group_names'] ? implode(', ', $preview['summary']['selected_group_names']) : 'Nincs csoportadat' }}</strong>
                </div>
                @if(filled($validated['reason'] ?? null))
                    <div class="col-lg-12">
                        <div class="text-muted small">Megjegyzés</div>
                        <div>{{ $validated['reason'] }}</div>
                    </div>
                @endif
            </div>
            <div class="mt-3">
                <span class="badge badge-success light me-2">Új rekord: {{ $preview['summary']['planned_cancellation_count'] }}</span>
                <span class="badge badge-secondary light me-2">Már lemondva: {{ $preview['summary']['duplicate_count'] }}</span>
                <span class="badge badge-warning light me-2">Nincs étkezési beállítás: {{ $preview['summary']['missing_meal_setting_count'] }}</span>
                <span class="badge badge-light me-2">Nem étkezési nap: {{ $preview['summary']['non_service_day_count'] }}</span>
                <span class="badge badge-danger light me-2">Határidőn kívül: {{ $preview['summary']['deadline_blocked_count'] }}</span>
                <span class="badge badge-info light">Csoportszintű lemondás: {{ $preview['summary']['class_cancelled_count'] }}</span>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Feldolgozási előnézet</h4>
            <span class="text-muted">Sorok: {{ $preview['items']->count() }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nap</th>
                            <th>Gyermek</th>
                            <th>Évfolyam</th>
                            <th>Osztály / csoport</th>
                            <th>Eredmény</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($preview['items'] as $item)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($item['service_date'])->format('Y.m.d.') }}</td>
                                <td><strong>{{ $item['child_name'] }}</strong></td>
                                <td>{{ $item['grade_label'] ? $item['grade_label'].'. évf.' : '—' }}</td>
                                <td>{{ $item['group_name'] ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $resultBadgeClasses[$item['result_code']] ?? 'badge-secondary' }} light">
                                        {{ $item['result_label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('dashboard.institution.meal-cancellations.bulk.store') }}" class="confirm-form"
          data-title="Véglegesíted a csoportos lemondást?"
          data-text="A rendszer a most látható, ténylegesen létrehozható lemondásokat fogja elmenteni."
          data-confirm-button-text="Igen, mentem">
        @csrf
        @foreach($validated['selected_child_ids'] as $childId)
            <input type="hidden" name="selected_child_ids[]" value="{{ $childId }}">
        @endforeach
        <input type="hidden" name="date_from" value="{{ $validated['date_from'] }}">
        <input type="hidden" name="date_to" value="{{ $validated['date_to'] }}">
        <input type="hidden" name="meal_scope" value="{{ $validated['meal_scope'] }}">
        <input type="hidden" name="event_name" value="{{ $validated['event_name'] }}">
        <input type="hidden" name="reason" value="{{ $validated['reason'] ?? '' }}">

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('dashboard.institution.meal-cancellations.bulk.create') }}" class="btn btn-light">Mégsem</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check me-1"></i>Végleges mentés
            </button>
        </div>
    </form>
</div>
@endsection
