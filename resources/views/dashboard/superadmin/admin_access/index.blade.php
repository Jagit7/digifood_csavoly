@extends('layouts.superadmin')

@section('content')
<div class="row">

    <div class="col-xl-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Intézményi felhasználók</h4>
                    <div class="text-muted small">Intézményekhez rendelt adminisztratív hozzáférések és meghívók</div>
                </div>

                <a href="{{ route('dashboard.admin-access.invite.create') }}" class="btn btn-primary">
                    Új meghívó
                </a>
            </div>

            <div class="card-body">

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if(session('invite_url'))
                    <div class="alert alert-info">
                        <strong>Meghívó link:</strong><br>
                        <code>{{ session('invite_url') }}</code>
                    </div>
                @endif

                <form method="GET" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Szerepkör szűrése</label>
                            <select name="role" class="form-control">
                                <option value="">Összes szerepkör</option>
                                @foreach($roles as $roleOption)
                                    <option value="{{ $roleOption }}" {{ $role === $roleOption ? 'selected' : '' }}>
                                        {{ $roleLabels[$roleOption] ?? $roleOption }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">Szűrés</button>
                            <a href="{{ route('dashboard.admin-access.index') }}" class="btn btn-light">Törlés</a>
                        </div>
                    </div>
                </form>

                @forelse($institutions as $institution)
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">{{ $institution->name }}</h5>
                                <div class="text-muted small">
                                    Intézménykód: {{ $institution->institution_code ?? '—' }}
                                </div>
                            </div>

                            @if($institution->active)
                                <div style="margin-left:50px;"><span class="badge bg-success">Aktív intézmény</span></div>
                            @else
                                <div style="margin-left:50px;"><span class="badge bg-secondary">Inaktív intézmény</span></div>
                            @endif
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="70">#</th>
                                        <th>Név</th>
                                        <th>E-mail</th>
                                        <th>Szerepkör</th>
                                        <th>Fiók állapota</th>
                                        <th>Meghívó / jelszó</th>
                                        <th class="text-end">Műveletek</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php($rowNumber = 1)
                                    @foreach($institution->users as $user)
                                        <tr>
                                            <td>{{ $rowNumber++ }}</td>
                                            <td><strong>{{ $user->name }}</strong></td>
                                            <td>{{ $user->email }}</td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    {{ $roleLabels[$user->role] ?? $user->role }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($user->is_active)
                                                    <span class="badge bg-success">Aktív</span>
                                                @else
                                                    <span class="badge bg-danger">Inaktív</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($user->accepted_invitation_at)
                                                    <span class="badge bg-success">Elfogadta</span>
                                                    <div class="small text-muted">
                                                        {{ $user->accepted_invitation_at->format('Y.m.d. H:i') }}
                                                    </div>
                                                @else
                                                    <span class="badge bg-warning text-dark">Még nem fogadta el</span>
                                                @endif

                                                @if($user->password)
                                                    <div class="small text-success mt-1">Jelszó beállítva</div>
                                                @else
                                                    <div class="small text-danger mt-1">Nincs jelszó</div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('dashboard.admin-access.edit', $user) }}"
                                                    class="btn btn-warning shadow btn-xs sharp me-1"
                                                    style="width:100px;margin-bottom:3px;">
                                                    Szerkesztés
                                                </a><br>

                                                <form method="POST"
                                                        action="{{ route('dashboard.admin-access.toggle-active', $user) }}"
                                                        class="d-inline">
                                                    @csrf
                                                    @method('PATCH')

                                                    <button type="submit"
                                                            class="btn shadow btn-xs sharp me-1 {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                                            style="width:100px;">
                                                        {{ $user->is_active ? 'Inaktiválás' : 'Aktiválás' }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @foreach($institution->adminInvitations as $invitation)
                                        <tr class="table-warning">
                                            <td>{{ $rowNumber++ }}</td>
                                            <td><strong>{{ $invitation->name }}</strong></td>
                                            <td>{{ $invitation->email }}</td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    {{ $roleLabels[$invitation->role] ?? $invitation->role }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">Meghívva</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">Még nem fogadta el</span>
                                                <div class="small text-muted">
                                                    Meghívva: {{ $invitation->created_at?->format('Y.m.d. H:i') }}
                                                </div>

                                                @if($invitation->expires_at)
                                                    <div class="small {{ $invitation->expires_at->isPast() ? 'text-danger' : 'text-muted' }}">
                                                        Lejárat: {{ $invitation->expires_at->format('Y.m.d. H:i') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <span class="text-muted small">Elfogadásra vár</span>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @if($institution->users->isEmpty() && $institution->adminInvitations->isEmpty())
                                        <tr>
                                            <td colspan="7" class="text-muted text-center py-4">
                                                Ehhez az intézményhez nincs hozzárendelt felhasználó vagy függőben lévő meghívó.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-info">
                        Nincs rögzített intézmény.
                    </div>
                @endforelse

            </div>
        </div>
    </div>

</div>
@endsection
