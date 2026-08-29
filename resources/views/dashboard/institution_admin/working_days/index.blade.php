@extends('layouts.superadmin')

@section('title', 'Tanítási / óvodai munkanapok')

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Tanítási / óvodai munkanapok',
        'subtitle' => 'Ledolgozós szombatok és rendkívüli munkanapok',
        'buttonText' => 'Új munkanap',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.school-breaks.working-days.create')
    ])

    <div class="row">

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összes munkanap',
            'value' => $stats['total'],
            'subtitle' => 'Rögzzített napok',
            'icon' => 'fa-solid fa-calendar-days',
            'color' => 'blue'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Idei munkanapok',
            'value' => $stats['this_year'],
            'subtitle' => now()->year,
            'icon' => 'fa-solid fa-calendar-check',
            'color' => 'green'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Következő',
            'value' => $stats['upcoming'],
            'subtitle' => 'Még előttünk áll',
            'icon' => 'fa-solid fa-arrow-right',
            'color' => 'orange'
        ])

        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lejárt',
            'value' => $stats['past'],
            'subtitle' => 'Korábbi munkanapok',
            'icon' => 'fa-solid fa-clock-rotate-left',
            'color' => 'purple'
        ])

    </div>

    <div class="card">

        <div class="card-header">
            <h4 class="card-title mb-0">
                Tanítási / óvodai munkanapok
            </h4>
        </div>

        <div class="card-body">

            @if($workingDays->count())

                <div class="table-responsive">

                    <table class="table table-hover table-responsive-md align-middle">

                        <thead>

                        <tr>
                            <th width="70">#</th>
                            <th>Dátum</th>
                            <th>Megnevezés</th>
                            <th>Típus</th>
                            <th>Megjegyzés</th>
                            <th>Állapot</th>
                            <th width="150" class="text-end">Műveletek</th>
                        </tr>

                        </thead>

                        <tbody>

                        @foreach($workingDays as $workingDay)

                            @php
                                $future = $workingDay->date->isFuture();
                            @endphp

                            <tr>

                                <td>{{ ($workingDays->firstItem() ?? 0) + $loop->index }}</td>

                                <td>
                                    <strong>
                                        {{ $workingDay->date->format('Y.m.d.') }}
                                    </strong>
                                </td>

                                <td>
                                    {{ $workingDay->name }}
                                </td>

                                <td>

                                    @switch($workingDay->type)

                                        @case('school_saturday')
                                            <span class="badge badge-primary light">
                                                Tanítási szombat
                                            </span>
                                            @break

                                        @case('kindergarten_day')
                                            <span class="badge badge-success light">
                                                Óvodai nevelési nap
                                            </span>
                                            @break

                                        @case('extra_working_day')
                                            <span class="badge badge-warning light">
                                                Rendkívüli munkanap
                                            </span>
                                            @break

                                        @default
                                            <span class="badge badge-secondary light">
                                                Egyéb
                                            </span>

                                    @endswitch

                                </td>

                                <td>

                                    @if($workingDay->description)
                                        {{ Str::limit($workingDay->description, 60) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif

                                </td>

                                <td>

                                    @if($future)

                                        <span class="badge badge-warning light">
                                            Következő
                                        </span>

                                    @else

                                        <span class="badge badge-success light">
                                            Megtörtént
                                        </span>

                                    @endif

                                </td>

                                <td class="text-end">

                                    <a href="{{ route('dashboard.institution.school-breaks.working-days.edit', $workingDay) }}"
                                       class="btn btn-xs btn-outline-warning"
                                       title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>

                                    <form method="POST"
                                          action="{{ route('dashboard.institution.school-breaks.working-days.destroy', $workingDay) }}"
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
                    {{ $workingDays->links('vendor.pagination.digifood') }}
                </div>

            @else

                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-calendar-check',
                    'title' => 'Még nincs rögzített munkanap',
                    'text' => 'Vidd fel az első tanítási vagy óvodai munkanapot.',
                    'buttonText' => 'Új munkanap',
                    'buttonIcon' => 'fa-solid fa-plus',
                    'buttonUrl' => route('dashboard.institution.school-breaks.working-days.create')
                ])

            @endif

        </div>

    </div>

</div>
@endsection
