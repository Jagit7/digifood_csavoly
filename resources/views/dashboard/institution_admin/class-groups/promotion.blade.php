@extends('layouts.superadmin')

@section('title', 'Tanévváltás és osztályléptetés')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Tanévváltás és osztályléptetés',
        'subtitle' => 'Ellenőrizd, majd léptesd át a kijelölt osztályokat a következő tanévbe.',
        'buttonText' => 'Vissza az osztályokhoz',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.class-groups.index', array_filter([
            'school_year' => $sourceSchoolYear?->id,
        ])),
    ])

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Kiinduló tanév</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.institution.class-groups.promotion') }}">
                <div class="row align-items-end">
                    <div class="col-lg-5 mb-3">
                        <label class="form-label" for="source_school_year">Tanév</label>
                        <select id="source_school_year" name="source_school_year" class="form-control" onchange="this.form.submit()">
                            @forelse($schoolYears as $schoolYear)
                                <option value="{{ $schoolYear->id }}" @selected($sourceSchoolYear?->id === $schoolYear->id)>
                                    {{ $schoolYear->name }}
                                    @if($schoolYear->is_current)
                                        – aktuális
                                    @endif
                                </option>
                            @empty
                                <option value="">Nincs rögzített tanév</option>
                            @endforelse
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($sourceSchoolYear && $targetYearData)
        <div class="row">
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Kiinduló tanév',
                'value' => $sourceSchoolYear->name,
                'subtitle' => $sourceSchoolYear->starts_on->format('Y.m.d.').' – '.$sourceSchoolYear->ends_on->format('Y.m.d.'),
                'icon' => 'fa-solid fa-calendar-check',
                'color' => 'blue',
            ])
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Következő tanév',
                'value' => $targetYearData['name'],
                'subtitle' => $targetSchoolYear ? 'Már létező tanév' : 'A léptetéskor létrejön',
                'icon' => 'fa-solid fa-calendar-plus',
                'color' => 'green',
            ])
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Léptethető osztályok',
                'value' => $groups->count(),
                'subtitle' => 'Aktív iskolai osztály',
                'icon' => 'fa-solid fa-users',
                'color' => 'purple',
            ])
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Érintett diákok',
                'value' => $groups->sum('active_children_count'),
                'subtitle' => 'A kijelölés még módosítható',
                'icon' => 'fa-solid fa-user-graduate',
                'color' => 'orange',
            ])
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-1">Léptetési előnézet</h4>
                <div class="text-muted small">A nem kijelölt osztályok változatlanul a kiinduló tanévben maradnak.</div>
            </div>
        </div>
        <div class="card-body">
            @if($groups->count())
                <form method="POST" action="{{ route('dashboard.institution.class-groups.promote') }}"
                      class="confirm-form"
                      data-title="Biztosan elindítod a tanévváltást?"
                      data-text="A kijelölt osztályok aktív diákjai átkerülnek a következő tanévbe, a korábbi tagságuk pedig lezárul."
                      data-confirm-button-text="Igen, léptetem">
                    @csrf
                    <input type="hidden" name="source_school_year_id" value="{{ $sourceSchoolYear->id }}">

                    <div class="alert alert-warning">
                        <strong>Fontos:</strong> a művelet a kijelölt osztályok aktív diákjait átteszi a következő tanévbe,
                        a régi osztálytagságokat pedig lezárja. Végzős vagy távozó osztályt ne jelölj ki.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-responsive-md align-middle">
                            <thead>
                            <tr>
                                <th width="70">#</th>
                                <th width="90">Léptetés</th>
                                <th>Jelenlegi osztály</th>
                                <th>Létszám</th>
                                <th>Következő osztály neve</th>
                                <th width="160">Következő évfolyam</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($groups as $group)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="group_ids[]"
                                                   value="{{ $group->id }}" id="group_{{ $group->id }}"
                                                   @checked(in_array($group->id, old('group_ids', $groups->pluck('id')->all())))>
                                        </div>
                                    </td>
                                    <td>
                                        <label for="group_{{ $group->id }}" class="mb-0">
                                            <strong>{{ $group->name }}</strong>
                                            <span class="d-block text-muted small">
                                                {{ $group->grade_level ? $group->grade_level.'. évfolyam' : 'Nincs évfolyam megadva' }}
                                            </span>
                                        </label>
                                    </td>
                                    <td><span class="badge badge-success light">{{ $group->active_children_count }} fő</span></td>
                                    <td>
                                        <input type="text" name="target_names[{{ $group->id }}]" class="form-control"
                                               maxlength="100" value="{{ old('target_names.'.$group->id, $group->suggested_name) }}">
                                    </td>
                                    <td>
                                        <select name="target_grade_levels[{{ $group->id }}]" class="form-control">
                                            <option value="">Nincs megadva</option>
                                            @for($grade = 1; $grade <= 20; $grade++)
                                                <option value="{{ $grade }}" @selected((string) old('target_grade_levels.'.$group->id, $group->suggested_grade_level) === (string) $grade)>
                                                    {{ $grade }}. évfolyam
                                                </option>
                                            @endfor
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('dashboard.institution.class-groups.index', ['school_year' => $sourceSchoolYear->id]) }}"
                           class="btn btn-light">Mégse</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-up-right-dots me-1"></i>
                            Kijelölt osztályok léptetése
                        </button>
                    </div>
                </form>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-users',
                    'title' => 'Nincs léptethető osztály',
                    'text' => 'A kiválasztott tanévben nincs aktív iskolai osztály.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
