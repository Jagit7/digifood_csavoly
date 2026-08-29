@extends('layouts.superadmin')

@section('title', 'Műveleti napló')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Műveleti napló',
        'subtitle' => 'A fontos, kézzel végzett SuperAdmin műveletek naplózott listája.',
    ])

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Szűrők</h4>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.superadmin.audit-logs.index') }}">
                <div class="row align-items-end">
                    <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
                        <label class="form-label">Dátumtól</label>
                        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
                        <label class="form-label">Dátumig</label>
                        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
                        <label class="form-label">Intézmény</label>
                        <select name="institution_id" class="form-control">
                            <option value="">Összes intézmény</option>
                            @foreach($institutions as $institution)
                                <option value="{{ $institution->id }}" @selected((string) request('institution_id') === (string) $institution->id)>{{ $institution->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 mb-3">
                        <label class="form-label">Művelet</label>
                        <select name="action" class="form-control">
                            <option value="">Összes művelet</option>
                            @foreach($actions as $actionKey => $actionLabel)
                                <option value="{{ $actionKey }}" @selected(request('action') === $actionKey)>{{ $actionLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-4 col-lg-8 col-md-12 mb-3">
                        <label class="form-label">Felhasználó vagy leírás</label>
                        <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Keresés...">
                    </div>
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>Szűrés
                        </button>
                        @if(request()->hasAny(['date_from', 'date_to', 'institution_id', 'action', 'search']))
                            <a href="{{ route('dashboard.superadmin.audit-logs.index') }}" class="btn btn-light">Szűrők törlése</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Naplóbejegyzések</h4>
            <span class="text-muted">Találatok: {{ $auditLogs->total() }}</span>
        </div>
        <div class="card-body">
            @if($auditLogs->count())
                <div class="table-responsive">
                    <table class="table table-hover table-responsive-md align-middle">
                        <thead>
                        <tr>
                            <th>Időpont</th>
                            <th>Felhasználó</th>
                            <th>Intézmény</th>
                            <th>Művelet</th>
                            <th>Leírás</th>
                            <th>Érintett rekord</th>
                            <th class="text-end">Részletek</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($auditLogs as $auditLog)
                            @php
                                $detailId = 'audit-log-details-' . $auditLog->id;
                            @endphp
                            <tr>
                                <td>{{ $auditLog->created_at?->format('Y.m.d. H:i:s') ?: '—' }}</td>
                                <td>
                                    @if($auditLog->user)
                                        <div class="fw-semibold">{{ $auditLog->user->name }}</div>
                                        <div class="small text-muted">{{ $auditLog->user->email }}</div>
                                    @else
                                        <span class="text-muted">Rendszer / ismeretlen</span>
                                    @endif
                                </td>
                                <td>{{ $auditLog->institution?->name ?: '—' }}</td>
                                <td>{{ $actions[$auditLog->action] ?? $auditLog->action }}</td>
                                <td>{{ $auditLog->description }}</td>
                                <td>
                                    @if($auditLog->subject_type || $auditLog->subject_id)
                                        <span class="badge badge-info light">{{ class_basename((string) $auditLog->subject_type) ?: '—' }} #{{ $auditLog->subject_id ?: '—' }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="false" aria-controls="{{ $detailId }}">
                                        Részletek
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="7" class="border-top-0 pt-0">
                                    <div class="collapse" id="{{ $detailId }}">
                                        <div class="border rounded p-3 bg-light">
                                            <div class="row">
                                                <div class="col-lg-6 mb-3">
                                                    <h6>Régi értékek</h6>
                                                    @if($auditLog->old_values)
                                                        <pre class="mb-0 small bg-white border rounded p-3">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    @else
                                                        <div class="text-muted">Nincs rögzített régi érték.</div>
                                                    @endif
                                                </div>
                                                <div class="col-lg-6 mb-3">
                                                    <h6>Új értékek</h6>
                                                    @if($auditLog->new_values)
                                                        <pre class="mb-0 small bg-white border rounded p-3">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    @else
                                                        <div class="text-muted">Nincs rögzített új érték.</div>
                                                    @endif
                                                </div>
                                                <div class="col-lg-4">
                                                    <h6>IP-cím</h6>
                                                    <div>{{ $auditLog->ip_address ?: '—' }}</div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <h6>Subject</h6>
                                                    <div>{{ $auditLog->subject_type ?: '—' }}</div>
                                                    <div class="small text-muted">ID: {{ $auditLog->subject_id ?: '—' }}</div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <h6>Böngészőadat</h6>
                                                    <div class="small text-muted">{{ $auditLog->user_agent ?: '—' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $auditLogs->links('vendor.pagination.digifood') }}
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-clock-rotate-left',
                    'title' => 'Még nincs naplózott művelet',
                    'text' => 'A fontos, kézzel végzett SuperAdmin műveletek itt fognak megjelenni.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
