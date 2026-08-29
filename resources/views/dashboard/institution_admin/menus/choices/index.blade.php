@extends('layouts.superadmin')

@section('title', 'Menüválasztások')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Menüválasztások',
        'subtitle' => 'A/B menüválasztás indítása és állapotkövetése',
    ])

    <div class="alert alert-info">
        A menüválasztás elindításával a szülők számára elérhetővé válik az A/B menü választása,
        és a rendszer minden érintett szülőnek automatikusan e-mail értesítést küld a választás
        megkezdéséről és a határidőről.
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">A/B menütervek</h4>
        </div>
        <div class="card-body">
            @if($plans->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Időszak</th>
                            <th>Megnevezés</th>
                            <th>Állapot</th>
                            <th>Megnyitás időpontja</th>
                            <th>Választási határidő</th>
                            <th>Menünapok</th>
                            <th class="text-end">Művelet</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($plans as $plan)
                            <tr>
                                <td>{{ $plan->valid_from?->format('Y.m.d.') }} - {{ $plan->valid_to?->format('Y.m.d.') }}</td>
                                <td>
                                    <strong>{{ $plan->title ?: 'A/B menüterv' }}</strong>
                                    <div class="small text-muted mt-1">
                                        {{ $plan->active ? 'Aktív terv' : 'Inaktív terv' }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $plan->selection_state_badge }} light">
                                        {{ $plan->selection_state_label }}
                                    </span>
                                </td>
                                <td>{{ $plan->published_at?->format('Y.m.d. H:i') ?: '—' }}</td>
                                <td>{{ $plan->selection_deadline?->format('Y.m.d. H:i:s') ?: '—' }}</td>
                                <td>{{ $plan->items_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.menus.ab.show', $plan) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        Részletek
                                    </a>

                                    @if($plan->can_open_selection)
                                        <form method="POST"
                                              action="{{ route('dashboard.institution.menus.choices.open', $plan) }}"
                                              class="d-inline confirm-form"
                                              data-title="Biztosan elindítja a menüválasztást?"
                                              data-text="A művelet után a szülők számára elérhetővé válik a választás, és minden érintett szülő e-mail értesítést kap róla."
                                              data-confirm-button-text="Indítás"
                                              data-cancel-button-text="Mégse">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                Menüválasztás indítása
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $plans->links('vendor.pagination.digifood') }}
            @else
                <div class="text-muted">Még nincs importált A/B menüterv.</div>
            @endif
        </div>
    </div>
@endsection
