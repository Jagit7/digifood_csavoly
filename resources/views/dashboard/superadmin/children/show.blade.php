@extends('layouts.superadmin')

@section('title', 'Gyermek részletei')

@section('content')
@php
    $currentMealSetting = $child->getRelation('currentMealSetting');
    $currentClassGroup = $child->getRelation('currentClassGroup');
    $groupLabel = $child->institution?->type === 'ovoda' ? 'Csoport' : 'Osztály';
    $groupName = $currentClassGroup?->name ?: $child->group_name;
    $mealSettingLabel = match($currentMealSetting?->mode) {
        \App\Models\StudentMealSetting::MODE_INSTITUTION_DEFAULT => 'Intézményi alapértelmezett',
        \App\Models\StudentMealSetting::MODE_PACKAGE => 'Menücsomag',
        \App\Models\StudentMealSetting::MODE_CUSTOM => 'Egyedi étkezések',
        default => null,
    };
@endphp

<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => $child->name,
        'subtitle' => 'Gyermek- és tanulói adatok csak olvasható nézetben.',
        'buttonText' => 'Vissza a gyermeklistához',
        'buttonUrl' => route('dashboard.superadmin.children.index'),
    ])

    <div class="row">
        <div class="col-xl-4 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Alapadatok</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Gyermek neve</div>
                        <div class="fw-semibold">{{ $child->name }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Belső azonosító</div>
                        <div>{{ $child->id }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Oktatási azonosító</div>
                        <div>{{ $child->educational_identifier ?: 'Nincs megadva' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Intézmény</div>
                        <div>
                            {{ $child->institution?->name ?: 'Nincs intézmény' }}
                            @if($institutionUrl)
                                <div class="mt-2">
                                    <a href="{{ $institutionUrl }}" class="btn btn-xs btn-outline-primary">Intézmény adatlapja</a>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-muted small">{{ $groupLabel }}</div>
                        <div>{{ $groupName ?: 'Nincs megadva' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Státusz</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Gyermek státusza</div>
                        <div>
                            @if($child->active)
                                <span class="badge badge-success light">Aktív</span>
                            @else
                                <span class="badge badge-secondary light">Inaktív</span>
                            @endif
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Étkezési státusz</div>
                        <div>
                            @if($currentMealSetting)
                                <span class="badge badge-success light">Étkező</span>
                            @else
                                <span class="badge badge-secondary light">Nem étkező</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-muted small">Vonalkód / kártyaazonosító</div>
                        @if($child->hasBarcode())
                            <div class="fw-semibold">{{ $child->barcode_token }}</div>
                            <div class="small text-muted">{{ $child->barcodeStatusLabel() }}</div>
                        @else
                            <div>Nincs létrehozva</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Korlátozások</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">
                        Ezen a superadmin oldalon a gyermek adatai csak megtekinthetők. Szerkesztés, törlés, gondviselői
                        kapcsolatok módosítása, étkezési beállítás, kedvezmény vagy más felhasználóként történő belépés
                        innen nem érhető el.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Kapcsolódó gondviselők</h4>
                </div>
                <div class="card-body">
                    @if($child->guardians->count())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                <tr>
                                    <th>Név</th>
                                    <th>Kapcsolat</th>
                                    <th>Elérhetőség</th>
                                    <th>Státuszok</th>
                                    <th class="text-end">Részletek</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($child->guardians as $guardian)
                                    <tr>
                                        <td>{{ $guardian->full_name }}</td>
                                        <td>{{ $guardian->pivot->relationship_type ?: 'Nincs megadva' }}</td>
                                        <td>
                                            <div>{{ $guardian->email ?: 'Nincs e-mail' }}</div>
                                            <div class="small text-muted">{{ $guardian->phone ?: 'Nincs telefonszám' }}</div>
                                        </td>
                                        <td>
                                            @if($guardian->pivot->is_legal_representative)
                                                <span class="badge badge-success light me-1 mb-1">Törvényes képviselő</span>
                                            @endif
                                            @if($guardian->pivot->is_emergency_contact)
                                                <span class="badge badge-info light me-1 mb-1">Vészhelyzeti kapcsolattartó</span>
                                            @endif
                                            @if(!$guardian->pivot->is_legal_representative && !$guardian->pivot->is_emergency_contact)
                                                <span class="text-muted">Nincs kiemelt státusz</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('dashboard.superadmin.parents.show', $guardian) }}"
                                               class="btn btn-xs btn-outline-primary">
                                                Megnyitás
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <span class="badge badge-warning light">Nincs kapcsolódó gondviselő</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Étkezés, kedvezmény, diéta</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Aktív étkezési beállítás</div>
                        @if($currentMealSetting)
                            <div class="fw-semibold">{{ $mealSettingLabel }}</div>
                            <div class="small text-muted">
                                Érvényesség: {{ $currentMealSetting->valid_from?->format('Y.m.d.') }}
                                @if($currentMealSetting->valid_to)
                                    - {{ $currentMealSetting->valid_to->format('Y.m.d.') }}
                                @else
                                    - visszavonásig
                                @endif
                            </div>
                            <div class="mt-2">
                                @if($currentMealSetting->mealPackage)
                                    <span class="badge badge-primary light me-1 mb-1">{{ $currentMealSetting->mealPackage->name }}</span>
                                @endif
                                @foreach($currentMealSetting->mealTypes as $mealType)
                                    @if($mealType->mealType?->name)
                                        <span class="badge badge-info light me-1 mb-1">{{ $mealType->mealType->name }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div>Nem állapítható meg aktív étkezési beállítás.</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Kedvezmény</div>
                        @if($child->discountType)
                            <span class="badge badge-primary light">{{ $child->discountType->name }}</span>
                        @else
                            <div>Nincs megadva</div>
                        @endif
                    </div>

                    <div>
                        <div class="text-muted small">Diétás korlátozások</div>
                        @forelse($child->dietaryRestrictions as $restriction)
                            <span class="badge badge-warning light me-1 mb-1">{{ $restriction->name }}</span>
                        @empty
                            <div>Nincs megadva</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
