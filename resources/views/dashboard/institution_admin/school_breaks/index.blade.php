@extends('layouts.superadmin')

@section('title', 'Iskolai szünetek')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Iskolai szünetek',
        'subtitle' => 'Intézményi tanítási szünetek kezelése',
        'buttonText' => 'Új szünet',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.school-breaks.create')
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes szünet',
            'value' => $stats['total'],
            'subtitle' => 'Rögzített szünetek',
            'icon' => 'fa-solid fa-calendar-days',
            'color' => 'blue'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív',
            'value' => $stats['active'],
            'subtitle' => 'Jelenleg érvényes',
            'icon' => 'fa-solid fa-circle-check',
            'color' => 'green'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Következő',
            'value' => $stats['upcoming'],
            'subtitle' => 'Jövőbeni szünetek',
            'icon' => 'fa-solid fa-calendar-plus',
            'color' => 'orange'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lejárt',
            'value' => $stats['past'],
            'subtitle' => 'Korábbi szünetek',
            'icon' => 'fa-solid fa-clock-rotate-left',
            'color' => 'purple'
        ])
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">
                Rögzített iskolai szünetek
            </h4>

            <a href="{{ route('dashboard.institution.school-breaks.calendar') }}"
               class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-calendar me-1"></i>
                Intézményi naptár
            </a>
        </div>

        <div class="card-body">
            @if($schoolBreaks->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                    <thead>
                    <tr>
                        <th width="70">#</th>
                        <th>Megnevezés</th>
                            <th>Kezdete</th>
                            <th>Vége</th>
                            <th>Napok</th>
                            <th>Típus</th>
                            <th>Állapot</th>
                            <th width="150" class="text-end">Műveletek</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($schoolBreaks as $schoolBreak)
                            @php
                                $today = now()->startOfDay();
                                $start = $schoolBreak->start_date->startOfDay();
                                $end = $schoolBreak->end_date->startOfDay();

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
                            <td>{{ ($schoolBreaks->firstItem() ?? 0) + $loop->index }}</td>
                            <td>
                                    <strong>{{ $schoolBreak->title }}</strong>

                                    @if($schoolBreak->description)
                                        <div class="text-muted small mt-1">
                                            {{ Str::limit($schoolBreak->description, 80) }}
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    {{ $schoolBreak->start_date->format('Y.m.d.') }}
                                </td>

                                <td>
                                    {{ $schoolBreak->end_date->format('Y.m.d.') }}
                                </td>

                                <td>
                                    <span class="badge badge-primary light">
                                        {{ $days }} nap
                                    </span>
                                </td>

                                <td>
                                    @switch($schoolBreak->type)
                                        @case('holiday')
                                            <span class="badge badge-info light">Ünnepnap</span>
                                            @break

                                        @case('maintenance')
                                            <span class="badge badge-warning light">Karbantartás</span>
                                            @break

                                        @case('other')
                                            <span class="badge badge-secondary light">Egyéb</span>
                                            @break

                                        @default
                                            <span class="badge badge-success light">Iskolai szünet</span>
                                    @endswitch
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

                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.school-breaks.edit', $schoolBreak) }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>

                                    <form method="POST"
                                          action="{{ route('dashboard.institution.school-breaks.destroy', $schoolBreak) }}"
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
                    {{ $schoolBreaks->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-calendar-days',
                    'title' => 'Még nincs rögzített iskolai szünet',
                    'text' => 'Vidd fel az első intézményi szünetet.',
                    'buttonText' => 'Új szünet',
                    'buttonIcon' => 'fa-solid fa-plus',
                    'buttonUrl' => route('dashboard.institution.school-breaks.create')
                ])
            @endif
        </div>
    </div>
</div>
@endsection
