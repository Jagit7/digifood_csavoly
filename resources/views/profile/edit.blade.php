@extends('layouts.superadmin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <h2 class="mb-1">Saját profil</h2>
                <p class="text-muted mb-0">Személyes adatok és jelszó módosítása</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('dashboard.profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="border-bottom pb-4 mb-4">
                            <h4 class="card-title mb-4">Személyes adatok</h4>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Név</label>
                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $user->name) }}"
                                    >
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">E-mail-cím</label>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $user->email) }}"
                                    >
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Telefonszám</label>
                                    <input
                                        type="text"
                                        id="phone"
                                        name="phone"
                                        class="form-control @error('phone') is-invalid @enderror"
                                        value="{{ old('phone', $user->phone) }}"
                                    >
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Szerepkör</label>
                                    <div class="form-control bg-light">{{ $user->role_label }}</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Intézmény</label>
                                    <div class="form-control bg-light">{{ $user->institution?->name ?? 'Nincs intézményhez rendelve' }}</div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="card-title mb-4">Jelszó módosítása</h4>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="current_password" class="form-label">Jelenlegi jelszó</label>
                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        class="form-control @error('current_password') is-invalid @enderror"
                                    >
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="password" class="form-label">Új jelszó</label>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                    >
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="password_confirmation" class="form-label">Új jelszó megerősítése</label>
                                    <input
                                        type="password"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        class="form-control @error('password_confirmation') is-invalid @enderror"
                                    >
                                    @error('password_confirmation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary">Változtatások mentése</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
