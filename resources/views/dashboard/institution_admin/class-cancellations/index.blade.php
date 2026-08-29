@extends('layouts.superadmin')

@section('title', $institution->type === 'ovoda' ? 'Csoportszintű lemondások' : 'Osztályszintű lemondások')

@section('content')
<div class="container-fluid">

    @php
        $isKindergarten = $institution->type === 'ovoda';
        $groupLabel = $isKindergarten ? 'Csoport' : 'Osztály';
        $groupCancellationLabel = $isKindergarten ? 'Csoportszintű lemondások' : 'Osztályszintű lemondások';
        $newLabel = $isKindergarten ? 'Új csoportszintű lemondás' : 'Új osztályszintű lemondás';
    @endphp

    @include('layouts.partials.components.ui.page-header', [
        'title' => $groupCancellationLabel,
        'subtitle' => $groupLabel . ' szintű étkezéslemondások kezelése',
        'buttonText' => $newLabel,
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.class-cancellations.create')
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes lemondás',
            'value' => $stats['total'],
            'subtitle' => 'Rögzített lemondások',
            'icon' => 'fa-solid fa-ban',
            'color' => 'blue'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív',
            'value' => $stats['active'],
            'subtitle' => 'Jelenleg érvényes',
            'icon' => 'fa-solid fa-check-circle',
            'color' => 'green'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Következő',
            'value' => $stats['upcoming'],
            'subtitle' => 'Jövőbeni lemondások',
            'icon' => 'fa-solid fa-calendar-plus',
            'color' => 'orange'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lejárt',
            'value' => $stats['past'],
            'subtitle' => 'Korábbi lemondások',
            'icon' => 'fa-solid fa-history',
            'color' => 'purple'
        ])
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">
                Rögzített {{ mb_strtolower($groupCancellationLabel) }}
            </h4>
        </div>

        <div class="card-body">
            @if($classCancellations->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>{{ $groupLabel }}</th>
                            <th>Kezdete</th>
                            <th>Vége</th>
                            <th>Napok</th>
                            <th>Érintett gyermekek</th>
                            <th>Indok</th>
                            <th>Állapot</th>
                            <th>Létrehozta</th>
                            <th width="150" class="text-end">Műveletek</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($classCancellations as $classCancellation)
                            @php
                                $today = now()->startOfDay();
                                $start = $classCancellation->date_from->copy()->startOfDay();
                                $end = $classCancellation->date_to->copy()->startOfDay();

                                $days = $start->diffInDays($end) + 1;

                                if ($today->between($start, $end)) {
                                    $status = 'active';
                                } elseif ($start->isFuture()) {
                                    $status = 'upcoming';
                                } else {
                                    $status = 'past';
                                }
                            @endphp

                            <tr>
                                <td>{{ ($classCancellations->firstItem() ?? 0) + $loop->index }}</td>
                                <td>
                                    <strong>{{ $classCancellation->classGroup?->name ?? '—' }}</strong>
                                    @if($classCancellation->classGroup?->schoolYear)
                                        <div class="small text-muted">{{ $classCancellation->classGroup->schoolYear->name }}</div>
                                    @endif
                                </td>

                                <td>
                                    {{ $classCancellation->date_from->format('Y.m.d.') }}
                                </td>

                                <td>
                                    {{ $classCancellation->date_to->format('Y.m.d.') }}
                                </td>

                                <td>
                                    <span class="badge badge-primary light">
                                        {{ $days }} nap
                                    </span>
                                </td>

                                <td>
                                    <span class="badge badge-info light">
                                        {{ $classCancellation->affected_children_count }} fő
                                    </span>
                                </td>

                                <td>
                                    @if($classCancellation->reason)
                                        {{ Str::limit($classCancellation->reason, 80) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    @if($status === 'active')
                                        <span class="badge badge-success light">
                                            Aktív
                                        </span>
                                    @elseif($status === 'upcoming')
                                        <span class="badge badge-warning light">
                                            Következő
                                        </span>
                                    @else
                                        <span class="badge badge-secondary light">
                                            Lejárt
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    {{ $classCancellation->creator?->name ?? '—' }}
                                </td>

                                <td class="text-end">
                                    @if($classCancellation->date_from->isToday() || $classCancellation->date_from->isFuture())
                                        <a href="{{ route('dashboard.institution.class-cancellations.edit', $classCancellation) }}"
                                           class="btn btn-xs btn-outline-warning"
                                           title="Szerkesztés">
                                            <i class="fa fa-pen"></i>
                                        </a>
                                    @endif

                                    <form method="POST"
                                          action="{{ route('dashboard.institution.class-cancellations.destroy', $classCancellation) }}"
                                          class="d-inline delete-form"
                                          data-title="Biztosan törlöd ezt a lemondást?"
                                          data-text="A törölt lemondás nem állítható vissza.">
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
                    {{ $classCancellations->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-ban',
                    'title' => 'Még nincs rögzített ' . mb_strtolower($groupCancellationLabel),
                    'text' => 'Vidd fel az első ' . mb_strtolower($groupLabel) . ' szintű étkezéslemondást.',
                    'buttonText' => $newLabel,
                    'buttonIcon' => 'fa-solid fa-plus',
                    'buttonUrl' => route('dashboard.institution.class-cancellations.create')
                ])
            @endif
        </div>
    </div>
</div>
@endsection
