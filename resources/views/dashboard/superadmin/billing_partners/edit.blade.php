@extends('layouts.superadmin')

@section('content')
@php
    $selectedInstitutionIds = collect(old('institution_ids', $billingPartner->institutions->pluck('id')->all()))
        ->map(fn ($value) => (int) $value)
        ->all();
@endphp

<form method="POST" action="{{ route('dashboard.billing-partners.update', $billingPartner) }}">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-xl-8 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Számlázási partner szerkesztése</h4>
                </div>
                <div class="card-body">
                    @include('dashboard.superadmin.billing_partners._form')

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('dashboard.billing-partners.index') }}" class="btn btn-outline-secondary">
                            Vissza
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Módosítások mentése
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Intézmények hozzárendelése</h4>
                </div>
                <div class="card-body">
                    @error('institution_ids')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    @if($institutions->isEmpty())
                        <p class="text-muted mb-0">Nincs még hozzárendelhető intézmény.</p>
                    @else
                        <div class="list-group">
                            @foreach($institutions as $institution)
                                @php
                                    $belongsToOtherPartner = $institution->billing_partner_id !== null
                                        && $institution->billing_partner_id !== $billingPartner->id;
                                @endphp
                                <label class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $institution->name }}</div>
                                            @if($belongsToOtherPartner)
                                                <small class="text-muted">Jelenlegi partner: {{ $institution->billingPartner?->name }}</small>
                                            @elseif($institution->billing_partner_id === $billingPartner->id)
                                                <small class="text-muted">Jelenleg ehhez a partnerhez rendelve</small>
                                            @else
                                                <small class="text-muted">Nincs partnerhez rendelve</small>
                                            @endif
                                            <div class="mt-2">
                                                <a href="{{ route('dashboard.institutions.billing-rates.index', $institution) }}" class="btn btn-sm btn-outline-secondary">
                                                    Díjszabások
                                                </a>
                                            </div>
                                        </div>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="institution_ids[]"
                                                   value="{{ $institution->id }}"
                                                   {{ in_array($institution->id, $selectedInstitutionIds, true) ? 'checked' : '' }}
                                                   {{ $belongsToOtherPartner ? 'disabled' : '' }}>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
