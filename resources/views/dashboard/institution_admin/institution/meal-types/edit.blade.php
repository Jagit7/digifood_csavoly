@extends('layouts.superadmin')

@section('title', 'Étkezéstípus szerkesztése')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $institutionMealType->mealType->name . ' szerkesztése',
        'subtitle' => $institution->name . ' · intézményi étkezéstípus beállításai, ára egy helyen',
        'buttonText' => 'Vissza a listához',
        'buttonIcon' => 'fa-solid fa-arrow-left',
        'buttonUrl' => route('dashboard.institution.meal-types.index'),
    ])

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Étkezéstípus beállításai</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('dashboard.institution.meal-types.update', $institutionMealType) }}">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-xl-3 mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                                   name="is_active" value="1"
                                   @checked((bool) old('is_active', $institutionMealType->is_active))>
                            <label class="form-check-label" for="is_active">Aktív</label>
                        </div>
                    </div>
                    <div class="col-xl-3 mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_parent_selectable" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_parent_selectable"
                                   name="is_parent_selectable" value="1"
                                   @checked((bool) old('is_parent_selectable', $institutionMealType->is_parent_selectable))>
                            <label class="form-check-label" for="is_parent_selectable">Szülő választhatja</label>
                        </div>
                    </div>
                    <div class="col-xl-3 mb-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_required" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_required"
                                   name="is_required" value="1"
                                   @checked((bool) old('is_required', $institutionMealType->is_required))>
                            <label class="form-check-label" for="is_required">Kötelező</label>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label" for="display_order">Megjelenési sorrend</label>
                        <input id="display_order" type="number" name="display_order"
                               class="form-control @error('display_order') is-invalid @enderror"
                               value="{{ old('display_order', $institutionMealType->display_order) }}"
                               min="0" max="65535" required>
                        @error('display_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="note">Megjegyzés</label>
                    <textarea id="note" name="note" rows="4"
                              class="form-control @error('note') is-invalid @enderror">{{ old('note', $institutionMealType->note) }}</textarea>
                    @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Mentés
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- =========================================================
         ÁR - korábban külön "Új ár" és "Ártörténet" oldalakon
         kellett kezelni, elszakítva a fenti kapcsolóktól. Mostantól
         az aktuális/következő ár és az új ár felvitele is itt,
         ugyanezen az oldalon történik.
    ========================================================== --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Ár</h4>
            <a href="{{ route('dashboard.institution.meal-types.prices.history', $institutionMealType) }}" class="btn btn-light btn-sm">
                <i class="fa-solid fa-clock-rotate-left me-1"></i>Teljes ártörténet
            </a>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="text-muted small">Jelenleg érvényes ár</div>
                    @if($currentPrice)
                        <div class="fs-4 fw-bold">{{ number_format($currentPrice->price, 0, ',', ' ') }} Ft</div>
                        <div class="small text-muted">
                            {{ $currentPrice->valid_from->format('Y.m.d.') }}-től{{ $currentPrice->valid_to ? ' – ' . $currentPrice->valid_to->format('Y.m.d.') . 'ig' : '' }}
                        </div>
                    @else
                        <div class="text-muted">Nincs jelenleg érvényes ár beállítva.</div>
                    @endif
                </div>
                <div class="col-lg-6">
                    <div class="text-muted small">Következő ár</div>
                    @if($nextPrice)
                        <div class="fs-5 fw-semibold">{{ number_format($nextPrice->price, 0, ',', ' ') }} Ft</div>
                        <div class="small text-muted">{{ $nextPrice->valid_from->format('Y.m.d.') }}-től érvényes</div>
                    @else
                        <div class="text-muted">Nincs beütemezve új ár.</div>
                    @endif
                </div>
            </div>

            <hr class="my-4">

            <h6 class="text-uppercase text-muted small mb-3">Új ár rögzítése</h6>
            <div class="small text-muted mb-3">
                Az új ár mentésekor a jelenleg nyitott árperiódus automatikusan lezárul az új ár kezdőnapja előtti napon.
            </div>
            <form method="POST" action="{{ route('dashboard.institution.meal-types.prices.store', $institutionMealType) }}">
                @csrf
                @php($priceModel = new \App\Models\InstitutionMealPrice())
                @include('dashboard.institution_admin.institution.meal-types.prices._form')
            </form>

            @if($recentPrices->isNotEmpty())
                <hr class="my-4">
                <h6 class="text-uppercase text-muted small mb-3">Legutóbbi árak</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Ár</th>
                            <th>Érvényes ettől</th>
                            <th>Érvényes eddig</th>
                            <th>Létrehozta</th>
                            <th width="70" class="text-end">Művelet</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($recentPrices as $price)
                            <tr>
                                <td><strong>{{ number_format($price->price, 0, ',', ' ') }} Ft</strong></td>
                                <td>{{ $price->valid_from->format('Y.m.d.') }}</td>
                                <td>{{ $price->valid_to?->format('Y.m.d.') ?? '—' }}</td>
                                <td>{{ $price->createdBy?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.meal-types.prices.edit', [$institutionMealType, $price]) }}"
                                       class="btn btn-xs btn-outline-warning" title="Szerkesztés">
                                        <i class="fa fa-pen"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
