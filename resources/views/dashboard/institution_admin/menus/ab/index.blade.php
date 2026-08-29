@extends('layouts.superadmin')
@section('title', 'A/B + diétás menük')
@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'A/B + diétás menük',
        'subtitle' => 'Excelből importált választható menük',
        'buttonText' => 'Excel import',
        'buttonIcon' => 'fa-solid fa-file-excel',
        'buttonUrl' => route('dashboard.institution.menus.ab.create')
    ])

    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        <strong>Egy Excel-fájl importálása önmagában még nem indítja el a menüválasztást a szülők
        számára</strong> - az importált terv csak akkor válik láthatóvá és választhatóvá a szülőknek,
        ha azt a <a href="{{ route('dashboard.institution.menus.choices.index') }}"><strong>Menüválasztások</strong></a>
        oldalon külön elindítja. A lenti táblázat "Menüválasztás" oszlopa mutatja, hogy egy adott
        importált terv el van-e már indítva. <strong>Az indításkor a rendszer minden érintett szülőnek
        automatikusan e-mail értesítést küld</strong> a választás megkezdéséről és a határidőről.
    </div>

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív menütervek',
            'value' => $activePlans,
            'subtitle' => 'Aktív A/B menütervek',
            'icon' => 'fa-solid fa-calendar-days',
            'color' => 'green'
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Menünapok',
            'value' => $totalItems,
            'subtitle' => 'Importált napok száma',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'blue'
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Következő időszak',
            'value' => $nextMonthLabel,
            'subtitle' => 'Következő import',
            'icon' => 'fa-solid fa-calendar-plus',
            'color' => 'orange'
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Utolsó import',
            'value' => $latestImportLabel,
            'subtitle' => 'Excel feltöltés',
            'icon' => 'fa-solid fa-file-import',
            'color' => 'purple'
        ])
    </div>
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">
                Importált A/B menütervek
            </h4>
        </div>
        <div class="card-body">
            @if($plans->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                    <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>Időszak</th>
                            <th>Megnevezés</th>
                            <th>Napok</th>
                            <th>Feltöltő</th>
                            <th>Import ideje</th>
                            <th>Terv állapota</th>
                            <th>Menüválasztás</th>
                            <th width="170" class="text-end">Műveletek</th>
                        </tr>
                        </thead>
                        <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td>{{ ($plans->firstItem() ?? 0) + $loop->index }}</td>
                            <td>
                                    <strong>
                                        {{ $plan->valid_from->format('Y.m.d.') }}
                                        -
                                        {{ $plan->valid_to->format('Y.m.d.') }}
                                    </strong>
                                </td>
                                <td>
                                    <strong>
                                        {{ $plan->title ?: 'A/B menüterv' }}
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge-primary light">
                                        {{ $plan->items_count }} nap
                                    </span>
                                </td>
                                <td>
                                    {{ $plan->creator?->name }}
                                </td>
                                <td>
                                    {{ $plan->created_at->format('Y.m.d. H:i') }}
                                </td>
                                <td>
                                    @if($plan->active)
                                        <span class="badge badge-success light">
                                            Aktív
                                        </span>
                                    @else
                                        <span class="badge badge-danger light">
                                            Inaktív
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $plan->selection_state_badge }} light">
                                        {{ $plan->selection_state_label }}
                                    </span>
                                    @if($plan->published_at)
                                        <div class="small text-muted mt-1">
                                            Elindítva: {{ $plan->published_at->format('Y.m.d. H:i') }}
                                        </div>
                                    @else
                                        <div class="small text-muted mt-1">
                                            A szülők még nem látják, nem kaptak értesítést.
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.menus.ab.show', $plan) }}"
                                    class="btn btn-xs btn-outline-primary"
                                    title="Megtekintés">
                                        <i class="fa fa-eye"></i>
                                    </a>

                                    <a href="{{ route('dashboard.institution.menus.ab.edit', $plan) }}"
                                    class="btn btn-xs btn-outline-warning"
                                    title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>

                                    <form method="POST"
                                        action="{{ route('dashboard.institution.menus.ab.destroy', $plan) }}"
                                        class="d-inline delete-form">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit"
                                                class="btn btn-xs btn-outline-danger"
                                                title="Törlés">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $plans->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-file-excel',
                    'title' => 'Még nincs importált A/B menü',
                    'text' => 'Importáld az első Excel állományt.',
                    'buttonText' => 'Excel import',
                    'buttonIcon' => 'fa-solid fa-file-excel',
                    'buttonUrl' => route('dashboard.institution.menus.ab.create')
                ])
            @endif
        </div>
    </div>
</div>
@endsection
