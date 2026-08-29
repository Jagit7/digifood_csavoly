@extends('layouts.superadmin')

@section('title', 'Exportok')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Exportok',
        'subtitle' => 'Kártyás exportfelület a pénzügyi adatok gyors XLSX vagy CSV kinyeréséhez.',
    ])

    <form method="POST" action="{{ route('dashboard.institution.finance.exports.download') }}">
        @csrf

        <div class="card mb-4">
            <div class="card-header">
                <h4 class="card-title mb-0">Szűrők és formátum</h4>
            </div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label" for="date_from">Dátumtól</label>
                        <input id="date_from" type="date" name="date_from" class="form-control @error('date_from') is-invalid @enderror" value="{{ old('date_from', request('date_from')) }}">
                        @error('date_from')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label" for="date_to">Dátumig</label>
                        <input id="date_to" type="date" name="date_to" class="form-control @error('date_to') is-invalid @enderror" value="{{ old('date_to', request('date_to')) }}">
                        @error('date_to')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label" for="month">Hónap</label>
                        <input id="month" type="month" name="month" class="form-control @error('month') is-invalid @enderror" value="{{ old('month', request('month')) }}">
                        @error('month')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label" for="group_name">Osztály / csoport</label>
                        <select id="group_name" name="group_name" class="form-control @error('group_name') is-invalid @enderror">
                            <option value="">Összes</option>
                            @foreach($groupOptions as $group)
                                <option value="{{ $group }}" @selected(old('group_name', request('group_name')) === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                        @error('group_name')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label" for="status">Státusz</label>
                        <select id="status" name="status" class="form-control @error('status') is-invalid @enderror">
                            <option value="">Nincs szűrés</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', request('status')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-xl-2 col-lg-4 mb-3">
                        <label class="form-label" for="format">Formátum</label>
                        <select id="format" name="format" class="form-control @error('format') is-invalid @enderror" required>
                            <option value="xlsx" @selected(old('format', request('format', 'xlsx')) === 'xlsx')>XLSX</option>
                            <option value="csv" @selected(old('format', request('format')) === 'csv')>CSV</option>
                        </select>
                        @error('format')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="small text-muted">
                    A státuszszűrő exporttípustól függően értelmezhető. A gyermek- és szülőlisták itt sem töltődnek be tömegesen, csak intézményi scope szerint aggregált export készül.
                </div>
            </div>
        </div>

        <div class="row">
            @foreach($exportCards as $exportType => $card)
                <div class="col-xl-4 col-lg-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div>
                                    <div class="text-muted small text-uppercase mb-1">Export</div>
                                    <h4 class="mb-0">{{ $card['title'] }}</h4>
                                </div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-light" style="width:56px;height:56px;">
                                    <i class="{{ $card['icon'] }} text-{{ $card['color'] === 'orange' ? 'warning' : ($card['color'] === 'purple' ? 'primary' : $card['color']) }}" style="font-size:1.2rem;"></i>
                                </div>
                            </div>

                            <p class="text-muted flex-grow-1">{{ $card['description'] }}</p>

                            <div class="mb-3">
                                <div class="small text-muted mb-2">Elérhető formátumok</div>
                                <div class="d-flex gap-2 flex-wrap">
                                    @foreach($card['formats'] as $format)
                                        <span class="badge bg-light text-dark border">{{ $format }}</span>
                                    @endforeach
                                </div>
                            </div>

                            <button type="submit" name="export_type" value="{{ $exportType }}" class="btn btn-outline-primary w-100">
                                <i class="fa-solid fa-file-export me-1"></i>Export indítása
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </form>
</div>
@endsection
