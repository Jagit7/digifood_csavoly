@extends('layouts.superadmin')

@section('title', 'A/B menü megtekintése')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'A/B menü megtekintése',
        'subtitle' => ($abMenu->valid_from?->format('Y.m.d.') ?? '-') . ' - ' . ($abMenu->valid_to?->format('Y.m.d.') ?? '-'),
        'buttonText' => 'Vissza',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.menus.ab.index')
    ])

    <div class="row mb-4">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Időszak',
            'value' => ($abMenu->valid_from?->format('Y.m.d.') ?? '-') . ' - ' . ($abMenu->valid_to?->format('Y.m.d.') ?? '-'),
            'subtitle' => 'Menüterv érvényessége',
            'icon' => 'fa-solid fa-calendar-days',
            'color' => 'green'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Menünapok',
            'value' => $abMenu->items->count(),
            'subtitle' => 'Importált napok száma',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'blue'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Feltöltő',
            'value' => $abMenu->creator?->name ?? '-',
            'subtitle' => 'Importáló felhasználó',
            'icon' => 'fa-solid fa-user',
            'color' => 'orange'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Import ideje',
            'value' => $abMenu->created_at?->format('Y.m.d. H:i') ?? '-',
            'subtitle' => $abMenu->active ? 'Aktív menüterv' : 'Inaktív menüterv',
            'icon' => 'fa-solid fa-file-import',
            'color' => 'purple'
        ])
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">
                {{ $abMenu->title ?: 'A/B menüterv' }}
            </h4>
        </div>

        <div class="card-body">
            @if($abMenu->items->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th style="width: 130px;">Dátum</th>
                            <th>A menü</th>
                            <th>B menü</th>
                            <th>Diétás menü</th>
                            <th style="width: 110px;">A létszám</th>
                            <th style="width: 110px;">B létszám</th>
                            <th style="width: 120px;">Diétás létszám</th>
                            <th>Megjegyzés</th>
                            <th class="text-end" style="width: 150px;">Műveletek</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($abMenu->items as $item)
                            @php($dailySummary = $dailySummaries->get($item->menu_date?->toDateString()))
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $item->menu_date?->format('Y.m.d.') }}</strong></td>
                                <td>{{ $item->menu_a ?: '-' }}</td>
                                <td>{{ $item->menu_b ?: '-' }}</td>
                                <td>{{ $item->menu_dietary ?: '-' }}</td>
                                <td>{{ $dailySummary['menu_a_count'] ?? 0 }}</td>
                                <td>{{ $dailySummary['menu_b_count'] ?? 0 }}</td>
                                <td>{{ $dailySummary['dietary_count'] ?? 0 }}</td>
                                <td>{{ $item->note ?: '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.menus.ab.rows.edit', $item) }}"
                                    class="btn btn-xs btn-outline-info">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <form method="POST"
                                        action="{{ route('dashboard.institution.menus.ab.rows.destroy', $item) }}"
                                        class="d-inline delete-form">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-xs btn-outline-danger">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-utensils',
                    'title' => 'Nincs menüsor',
                    'text' => 'Ehhez az A/B menütervhez még nem tartozik importált menüsor.',
                    'buttonText' => 'Vissza a listához',
                    'buttonIcon' => 'fa-solid fa-arrow-left',
                    'buttonUrl' => route('dashboard.institution.menus.ab.index')
                ])
            @endif
        </div>
    </div>

</div>
@endsection
