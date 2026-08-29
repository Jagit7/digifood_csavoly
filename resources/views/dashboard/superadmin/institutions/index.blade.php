@extends('layouts.superadmin')
@section('content')
<!-- row -->
<div class="row">
    <!-- BAL OLDAL -->
    <div class="col-xl-12 col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Intézmények listája</h4>
            </div>
            <div class="card-body">
                <div class="basic-form">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="card-title mb-0">Intézmények</h4>
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
                                    <th>Állapot</th>
                                    <th>Létrehozva</th>
                                    <th class="text-end" style="width:120px;">Műveletek</th>
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
                                        {{ $institution->name }}
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

                                    <td>
                                        @if($institution->active)
                                            <span class="badge bg-success">Aktív</span>
                                        @else
                                            <span class="badge bg-secondary">Inaktív</span>
                                        @endif
                                    </td>

                                    <td class="text-muted">
                                        {{ optional($institution->created_at)->format('Y-m-d') ?? '—' }}
                                    </td>

                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 align-items-center">

                                            <a href="{{ route('dashboard.institutions.edit', $institution) }}"
                                               style="padding-top:3px;padding-bottom:3px;padding-left:5px;padding-right:5px;"
                                               class="btn btn-sm btn-outline-primary"
                                               title="Szerkesztés">
                                                <i class="fa fa-pen"></i>
                                            </a>

                                            <form method="POST" action="{{ route('dashboard.institutions.toggle-active', $institution) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        style="padding-top:3px;padding-bottom:3px;padding-left:5px;padding-right:5px;"
                                                        class="btn btn-sm {{ $institution->active ? 'btn-outline-success' : 'btn-outline-secondary' }}"
                                                        title="{{ $institution->active ? 'Inaktiválás' : 'Aktiválás' }}">
                                                    <i class="fa {{ $institution->active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                                </button>
                                            </form>

                                            <form method="POST"
                                                  action="{{ route('dashboard.institutions.destroy', $institution) }}"
                                                  class="d-inline confirm-form"
                                                  data-title="Biztosan kukába helyezed az intézményt?"
                                                  data-text="Az intézmény inaktívvá válik, és átkerül a törölt intézmények közé."
                                                  data-confirm-button-text="Igen, kukába helyezem"
                                                  data-confirm-button-color="#dc3545">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        style="padding-top:3px;padding-bottom:3px;padding-left:5px;padding-right:5px;"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Kukába">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            @php $sor++; @endphp
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center text-muted py-4">
                                        Nincs még felvitt intézmény.
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
</div>
@endsection
