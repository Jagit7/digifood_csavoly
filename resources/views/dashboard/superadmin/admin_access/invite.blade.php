@extends('layouts.superadmin')

@section('content')
<div class="row">

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-1">Új intézményi felhasználó meghívása</h4>
                <div class="text-muted small">Intézményi admin vagy intézményi titkár meghívása intézményhez</div>
            </div>

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Hiba történt!</strong>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('dashboard.admin-access.invite.store') }}">
                    @csrf

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Intézmény</label>
                        <div class="col-sm-9">
                            <select name="institution_id" class="form-control @error('institution_id') is-invalid @enderror" required>
                                <option value="">-- válassz intézményt --</option>
                                @foreach($institutions as $institution)
                                    <option value="{{ $institution->id }}" {{ old('institution_id') == $institution->id ? 'selected' : '' }}>
                                        {{ $institution->name }} @if($institution->institution_code) ({{ $institution->institution_code }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('institution_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Név</label>
                        <div class="col-sm-9">
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">E-mail</label>
                        <div class="col-sm-9">
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4 row">
                        <label class="col-sm-3 col-form-label">Szerepkör</label>
                        <div class="col-sm-9">
                            <select name="role" class="form-control @error('role') is-invalid @enderror" required>
                                <option value="">Válassz szerepkört</option>
                                <option value="institution_admin" @selected(old('role') === 'institution_admin')>Intézményi admin</option>
                                <option value="institution_secretary" @selected(old('role') === 'institution_secretary')>Intézményi titkár</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="{{ route('dashboard.admin-access.index') }}" class="btn btn-light">Vissza</a>
                        <button type="submit" class="btn btn-primary">Meghívó létrehozása</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Tudnivalók</h4>
            </div>

            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">A meghívó link 1 napig érvényes.</li>
                    <li class="list-group-item">A felhasználó a linken állítja be a jelszavát.</li>
                    <li class="list-group-item">Elfogadás után automatikusan hozzá lesz rendelve az intézményhez.</li>
                </ul>
            </div>
        </div>
    </div>

</div>
@endsection
