@extends('layouts.superadmin')

@section('title', 'Kedvezmények és szabályok')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Kedvezmények és szabályok',
        'subtitle' => 'Intézményi kedvezmények, lemondási határidő és étrendi törzsadatok',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Aktív kedvezmények',
            'value' => $discounts->where('active', true)->count(),
            'subtitle' => $discounts->count().' rögzített típus',
            'icon' => 'fa-solid fa-percent',
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Allergének',
            'value' => $allergens->where('active', true)->count(),
            'subtitle' => 'Aktív választható elem',
            'icon' => 'fa-solid fa-triangle-exclamation',
            'color' => 'orange',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Ételérzékenységek',
            'value' => $intolerances->where('active', true)->count(),
            'subtitle' => 'Aktív választható elem',
            'icon' => 'fa-solid fa-utensils',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Lemondási határidő',
            'value' => $mealSetting->cancellation_hour === null
                ? 'Nincs beállítva'
                : sprintf('%02d:%02d', $mealSetting->cancellation_hour, $mealSetting->cancellation_minute),
            'subtitle' => 'A következő étkezési napra',
            'icon' => 'fa-solid fa-clock',
            'color' => 'green',
            'size' => 'small',
        ])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-1">Következő napi lemondási határidő</h4>
            <div class="text-muted small">
                Eddig az időpontig mondhatják le a szülők a következő étkezési napot.
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.reference-data.cancellation.update') }}">
                @csrf
                @method('PUT')
                <div class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="cancellation_hour">Óra</label>
                        <select id="cancellation_hour" name="cancellation_hour" class="form-control" required>
                            <option value="">Válassz...</option>
                            @for($hour = 0; $hour <= 23; $hour++)
                                <option value="{{ $hour }}" @selected((string) old('cancellation_hour', $mealSetting->cancellation_hour) === (string) $hour)>
                                    {{ sprintf('%02d', $hour) }} óra
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="cancellation_minute">Perc</label>
                        <select id="cancellation_minute" name="cancellation_minute" class="form-control" required>
                            @foreach([0, 15, 30, 45] as $minute)
                                <option value="{{ $minute }}" @selected((string) old('cancellation_minute', $mealSetting->cancellation_minute) === (string) $minute)>
                                    {{ sprintf('%02d', $minute) }} perc
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Határidő mentése
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-1">Kedvezménytípusok</h4>
            <div class="text-muted small">A név és a százalék együtt azonosítja a kedvezményt.</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.reference-data.discounts.store') }}" class="mb-4">
                @csrf
                <div class="row align-items-end">
                    <div class="col-lg-6 mb-3">
                        <label class="form-label" for="discount_name">Megnevezés</label>
                        <input id="discount_name" type="text" name="name" class="form-control" maxlength="100" required>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label" for="discount_percentage">Kedvezmény</label>
                        <div class="input-group">
                            <input id="discount_percentage" type="number" name="percentage" class="form-control"
                                   min="0" max="100" value="0" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="discount_active" checked>
                            <label class="form-check-label" for="discount_active">Aktív</label>
                        </div>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i>Hozzáadás
                        </button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-responsive-md align-middle">
                    <thead>
                    <tr>
                        <th width="70">#</th>
                        <th>Megnevezés</th>
                        <th width="140">Kedvezmény</th>
                        <th width="120">Állapot</th>
                        <th width="130" class="text-end">Műveletek</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($discounts as $discount)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $discount->name }}</strong></td>
                            <td><span class="badge badge-primary light">{{ $discount->percentage }}%</span></td>
                            <td>
                                <span class="badge {{ $discount->active ? 'badge-success' : 'badge-secondary' }} light">
                                    {{ $discount->active ? 'Aktív' : 'Inaktív' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-xs btn-outline-warning"
                                        data-bs-toggle="modal" data-bs-target="#discountModal{{ $discount->id }}"
                                        title="Szerkesztés">
                                    <i class="fa fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('dashboard.institution.reference-data.discounts.destroy', $discount) }}"
                                      class="d-inline delete-form"
                                      data-title="Biztosan törlöd ezt a kedvezményt?"
                                      data-text="A kedvezménytípus eltűnik a választható törzsadatok közül.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Törlés">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            @include('dashboard.institution_admin.reference-data.restriction-card', [
                'title' => 'Allergének',
                'type' => 'allergen',
                'items' => $allergens,
                'inputId' => 'allergen_name',
            ])
        </div>
        <div class="col-xl-6">
            @include('dashboard.institution_admin.reference-data.restriction-card', [
                'title' => 'Ételérzékenységek',
                'type' => 'intolerance',
                'items' => $intolerances,
                'inputId' => 'intolerance_name',
            ])
        </div>
    </div>

    @foreach($discounts as $discount)
        <div class="modal fade" id="discountModal{{ $discount->id }}" tabindex="-1"
             aria-labelledby="discountModalLabel{{ $discount->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('dashboard.institution.reference-data.discounts.update', $discount) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title" id="discountModalLabel{{ $discount->id }}">Kedvezménytípus szerkesztése</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Megnevezés</label>
                                <input type="text" name="name" class="form-control" maxlength="100"
                                       value="{{ $discount->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Kedvezmény százaléka</label>
                                <div class="input-group">
                                    <input type="number" name="percentage" class="form-control" min="0" max="100"
                                           value="{{ $discount->percentage }}" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" value="1"
                                       id="discountModalActive{{ $discount->id }}" @checked($discount->active)>
                                <label class="form-check-label" for="discountModalActive{{ $discount->id }}">Aktív</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                            <button type="submit" class="btn btn-primary">Mentés</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
