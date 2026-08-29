@extends('layouts.superadmin')
@section('content')
<!-- row -->
<div class="row">
    <!-- BAL OLDAL -->
    <div class="col-xl-12 col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h4 class="card-title mb-0">Kukába helyezett intézmények</h4>

                <div class="d-flex gap-2">
                    <a href="{{ route('dashboard.institutions.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-arrow-left me-1"></i> Vissza a listára
                    </a>
                </div>
            </div>

            <div class="card-body">

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h4 class="card-title mb-0">Trashed intézmények</h4>
                    <span class="text-muted">Összesen: {{ $institutions->count() ?? 0 }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;"></th>
                                <th>Azonosító</th>
                                <th>Név</th>
                                <th>Típus</th>
                                <th>OM</th>
                                <th>Cím</th>
                                <th>Kapcsolattartó</th>
                                <th>Email</th>
                                <th>Telefon</th>
                                <th>Számlázási név</th>
                                <th>KRÉTA</th>
                                <th>Törölve</th>
                                <th class="text-end" style="width:140px;">Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                        @php $sor = 1; @endphp
                        @forelse($institutions as $institution)
                            <tr>
                                <td>{{ $sor }}.</td>

                                <td class="text-muted fw-semibold">
                                    {{ $institution->institution_code ?? '—' }}
                                </td>

                                <td class="fw-semibold">
                                    {{ $institution->name ?? '—' }}
                                </td>

                                <td>
                                    @php
                                        $typeLabel = match($institution->type) {
                                            'ovoda' => 'Óvoda',
                                            'iskola' => 'Iskola',
                                            'bolcsode' => 'Bölcsőde',
                                            default => $institution->type ?? '—',
                                        };
                                    @endphp
                                    {{ $typeLabel }}
                                </td>

                                <td>{{ $institution->om_identifier ?? '—' }}</td>

                                <td>
                                    @php
                                        $addr = trim(implode(' ', array_filter([
                                            $institution->address_zip,
                                            $institution->address_city,
                                            $institution->address_line,
                                        ])));
                                    @endphp
                                    {{ $addr !== '' ? $addr : '—' }}
                                </td>

                                <td>{{ $institution->contact_name ?? '—' }}</td>
                                <td>{{ $institution->email ?? '—' }}</td>
                                <td>{{ $institution->phone ?? '—' }}</td>
                                <td>{{ $institution->billing_name ?? '—' }}</td>
                                <td>{{ $institution->kreta_code ?? '—' }}</td>

                                <td class="text-muted">
                                    {{ optional($institution->deleted_at)->format('Y-m-d H:i') ?? '—' }}
                                </td>

                                <td class="text-end">
                                    <div class="d-inline-flex gap-2 align-items-center">

                                        {{-- Visszaállítás --}}
                                        <form method="POST" action="{{ route('dashboard.institutions.restore', $institution->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    style="padding-top:3px;padding-bottom:3px;padding-left:5px;padding-right:5px;"
                                                    class="btn btn-sm btn-outline-success"
                                                    title="Visszaállítás">
                                                <i class="fa fa-rotate-left"></i>
                                            </button>
                                        </form>

                                        {{-- (Opcionális) végleges törlés gomb - csak ha van route/controllerművelet --}}
                                        {{--
                                        <form method="POST"
                                              action="{{ route('dashboard.institutions.force-destroy', $institution->id) }}"
                                              class="d-inline delete-form"
                                              data-title="Biztosan végleg törlöd az intézményt?"
                                              data-text="A művelet nem vonható vissza, az intézmény adatai véglegesen törlődnek."
                                              data-confirm-button-text="Igen, végleg törlöm">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    style="padding-top:3px;padding-bottom:3px;padding-left:5px;padding-right:5px;"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Végleges törlés">
                                                <i class="fa fa-trash-can"></i>
                                            </button>
                                        </form>
                                        --}}

                                    </div>
                                </td>
                            </tr>
                        @php $sor++; @endphp
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">
                                    Nincs kukába helyezett intézmény.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
