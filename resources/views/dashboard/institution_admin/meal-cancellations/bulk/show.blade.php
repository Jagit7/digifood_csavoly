@extends('layouts.superadmin')

@section('title', 'Csoportos lemondás részletei')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $batch->event_name,
        'subtitle' => 'Csoportos lemondási művelet részletes naplója',
        'buttonText' => 'Vissza az előzményekhez',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.meal-cancellations.bulk.index'),
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Kijelölt gyermekek', 'value' => $batch->selected_children_count, 'subtitle' => 'A batch induló listája', 'icon' => 'fa-solid fa-users', 'color' => 'blue'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Létrehozott lemondások', 'value' => $batch->created_cancellation_count, 'subtitle' => 'Ténylegesen mentett rekord', 'icon' => 'fa-solid fa-save', 'color' => 'green'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Már lemondva', 'value' => $batch->duplicate_count, 'subtitle' => 'Duplikáció nélkül kihagyva', 'icon' => 'fa-solid fa-clone', 'color' => 'purple'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Nem rögzíthető', 'value' => $batch->missing_meal_setting_count + $batch->non_service_day_count + $batch->deadline_blocked_count + $batch->class_cancelled_count, 'subtitle' => 'Szabály miatt kihagyott alkalmak', 'icon' => 'fa-solid fa-ban', 'color' => 'red'])
    </div>

    <div class="card mb-4">
        <div class="card-header"><h4 class="card-title mb-0">Művelet adatai</h4></div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-4 mb-3"><div class="text-muted small">Időszak</div><strong>{{ $batch->date_from->format('Y.m.d.') }} – {{ $batch->date_to->format('Y.m.d.') }}</strong></div>
                <div class="col-lg-4 mb-3"><div class="text-muted small">Rögzítette</div><strong>{{ $batch->creator?->name ?? '—' }}</strong></div>
                <div class="col-lg-4 mb-3"><div class="text-muted small">Létrehozva</div><strong>{{ $batch->created_at?->format('Y.m.d. H:i') }}</strong></div>
                <div class="col-lg-12 mb-3"><div class="text-muted small">Étkezési kör</div><strong>Minden aznapra beállított étkezés</strong></div>
                @if($batch->reason)
                    <div class="col-lg-12"><div class="text-muted small">Megjegyzés</div><div>{{ $batch->reason }}</div></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Napi és gyermekenkénti eredmények</h4>
            <span class="text-muted">Sorok: {{ $batch->items->count() }}</span>
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
                        @foreach($batch->items as $item)
                            <tr>
                                <td>{{ $item->service_date->format('Y.m.d.') }}</td>
                                <td><strong>{{ $item->child_name }}</strong></td>
                                <td>{{ $item->grade_label ? $item->grade_label.'. évf.' : '—' }}</td>
                                <td>{{ $item->group_name ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $resultBadgeClasses[$item->result_code] ?? 'badge-secondary' }} light">
                                        {{ $item->result_label }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
