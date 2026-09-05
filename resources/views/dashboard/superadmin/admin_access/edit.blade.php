@extends('layouts.superadmin')

@section('content')

<div class="row">

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Intézményi felhasználó szerkesztése</h4>
            </div>

            <div class="card-body">

                <form method="POST" action="{{ route('dashboard.admin-access.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Név</label>
                        <div class="col-sm-9">
                            <input type="text"
                                   name="name"
                                   class="form-control"
                                   value="{{ old('name', $user->name) }}"
                                   required>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">E-mail</label>
                        <div class="col-sm-9">
                            <input type="email"
                                   name="email"
                                   class="form-control"
                                   value="{{ old('email', $user->email) }}"
                                   required>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Szerepkör</label>
                        <div class="col-sm-9">
                            <select name="role" class="form-control">
                                @foreach($roleLabels as $key => $label)
                                    @if($key !== 'super_admin')
                                        <option value="{{ $key }}" {{ $user->role === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Intézmények</label>
                        <div class="col-sm-9">
                            <select name="institutions[]" class="form-control" multiple>
                                @foreach($institutions as $institution)
                                    <option value="{{ $institution->id }}"
                                        {{ in_array($institution->id, old('institutions', $selectedIds)) ? 'selected' : '' }}>
                                        {{ $institution->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Új jelszó</label>
                        <div class="col-sm-9">
                            <input type="password"
                                   name="password"
                                   class="form-control">
                            <small class="text-muted">Csak akkor add meg, ha cserélni akarod.</small>
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Jelszó megerősítés</label>
                        <div class="col-sm-9">
                            <input type="password"
                                   name="password_confirmation"
                                   class="form-control">
                        </div>
                    </div>

                    @if($user->role === \App\Models\User::ROLE_INSTITUTION_SECRETARY)
                        <div class="mb-3 row">
                            <label class="col-sm-3 col-form-label">Számla sztornózása</label>
                            <div class="col-sm-9">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="cancel_invoices" value="0">

                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="cancel_invoices"
                                           value="1"
                                           {{ old('cancel_invoices', $canCancelInvoices) ? 'checked' : '' }}>

                                    <label class="form-check-label">
                                        Jogosult a kiállított számlák sztornózására
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Csak intézményi titkár szerepkörnél állítható - intézményi admin
                                    és szuperadmin mindig jogosult.
                                </small>
                            </div>
                        </div>
                    @endif

                    <div class="mb-4 row">
                        <label class="col-sm-3 col-form-label">Állapot</label>
                        <div class="col-sm-9">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">

                                <input class="form-check-input"
                                       type="checkbox"
                                       name="is_active"
                                       value="1"
                                       {{ old('is_active', $user->is_active) ? 'checked' : '' }}>

                                <label class="form-check-label">
                                    Aktív felhasználó
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="{{ route('dashboard.admin-access.index') }}"
                           class="btn btn-light">
                            Vissza
                        </a>

                        <button type="submit" class="btn btn-primary">
                            Mentés
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Felhasználó állapota</h4>
            </div>

            <div class="card-body">
                <p><strong>Meghívó elfogadva:</strong></p>

                @if($user->accepted_invitation_at)
                    <span class="badge bg-success">Igen</span>
                    <div class="small text-muted mt-2">
                        {{ $user->accepted_invitation_at->format('Y.m.d. H:i') }}
                    </div>
                @else
                    <span class="badge bg-warning text-dark">Még nem</span>
                @endif

                <hr>

                <p><strong>Jelszó beállítva:</strong></p>

                @if($user->password)
                    <span class="badge bg-success">Igen</span>
                @else
                    <span class="badge bg-danger">Nem</span>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection
