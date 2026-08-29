@extends('layouts.employee')

@section('page_title', 'Fiókom')

@push('styles')
    <style>
        .df-employee-account-page .df-account-section-card {
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .df-employee-account-page .df-account-main-section {
            padding: 2rem;
        }

        .df-employee-account-page .df-account-main-section + .df-account-main-section {
            border-top: 1px solid rgba(148, 163, 184, 0.22);
        }

        .df-employee-account-page .df-account-section-heading {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .df-employee-account-page .df-account-card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 3rem;
            background: linear-gradient(
                135deg,
                rgba(59, 130, 246, 0.12),
                rgba(14, 165, 233, 0.18)
            );
            color: #1d4ed8;
            font-size: 1.1rem;
        }

        .df-employee-account-page .df-account-card-icon.is-address {
            background: linear-gradient(
                135deg,
                rgba(16, 185, 129, 0.12),
                rgba(34, 197, 94, 0.18)
            );
            color: #047857;
        }

        .df-employee-account-page .df-account-card-icon.is-security {
            background: linear-gradient(
                135deg,
                rgba(139, 92, 246, 0.12),
                rgba(168, 85, 247, 0.18)
            );
            color: #7e22ce;
        }

        .df-employee-account-page .df-account-section-title {
            margin-bottom: 0.25rem;
            color: #0f172a;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .df-employee-account-page .df-account-section-description {
            margin-bottom: 0;
            color: #64748b;
            font-size: 0.925rem;
            line-height: 1.5;
        }

        .df-employee-account-page .df-account-muted-box {
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 1rem;
            background: #f8fafc;
            color: #475569;
        }

        .df-employee-account-page .df-account-form-actions {
            padding-top: 0.25rem;
        }

        .df-employee-account-page .form-label {
            color: #334155;
            font-weight: 600;
        }

        .df-employee-account-page .form-control {
            border-color: #dbe3ec;
            border-radius: 0.75rem;
        }

        .df-employee-account-page .form-control:focus {
            border-color: rgba(59, 130, 246, 0.65);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.12);
        }

        .df-employee-account-page .df-account-readonly-field {
            background-color: #f1f5f9;
            color: #475569;
            cursor: not-allowed;
        }

        @media (max-width: 767.98px) {
            .df-employee-account-page .df-account-main-section {
                padding: 1.5rem;
            }

            .df-employee-account-page .df-account-section-heading {
                gap: 0.85rem;
                margin-bottom: 1.5rem;
            }

            .df-employee-account-page .df-account-card-icon {
                width: 2.75rem;
                height: 2.75rem;
                flex-basis: 2.75rem;
                border-radius: 0.85rem;
            }

            .df-employee-account-page .df-account-form-actions .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="df-employee-account-page">
        @include('layouts.partials.components.ui.page-header', [
            'title' => 'Fiókom',
            'subtitle' => 'Személyes és kapcsolattartási adatainak kezelése.',
        ])

        <div class="row">
            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Profil teljessége',
                'value' => $stats['profile_completeness'].'%',
                'subtitle' => 'Az adatok kitöltöttsége',
                'icon' => 'fa-solid fa-clipboard-check',
                'color' => 'green',
                'colClass' => 'col-xl-4 col-md-6',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => 'Kapcsolt intézmény',
                'value' => $institutionNames->isNotEmpty() ? $institutionNames->implode(', ') : 'Nincs adat',
                'subtitle' => 'Aktív dolgozói jogviszony',
                'icon' => 'fa-solid fa-building',
                'color' => 'blue',
                'colClass' => 'col-xl-4 col-md-6',
                'size' => 'small',
            ])

            @include('layouts.partials.components.ui.stats-card', [
                'title' => $stats['last_login_subtitle'],
                'value' => $stats['last_login_value'],
                'subtitle' => 'Legutóbbi belépés időpontja',
                'icon' => 'fa-solid fa-clock-rotate-left',
                'color' => 'purple',
                'colClass' => 'col-xl-4 col-md-6',
                'size' => 'small',
            ])
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                <i class="fa-solid fa-circle-check me-2"></i>
                {{ session('success') }}
            </div>
        @endif

        <div class="alert df-account-muted-box mb-4">
            <i class="fa-solid fa-circle-info me-2"></i>
            {{ $nonEditableInfo }}
        </div>

        <div class="row g-4" style="margin-bottom:30px;">
            <div class="col-12 col-xl-6">
                <div id="personal-card" class="card df-account-section-card h-100">

                    {{-- Személyes adatok --}}
                    <section class="df-account-main-section">
                        <div class="df-account-section-heading">
                            <div class="df-account-card-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>

                            <div>
                                <h2 class="df-account-section-title">
                                    Személyes adatok
                                </h2>

                                <p class="df-account-section-description">
                                    A kapcsolattartáshoz használt alapadatok.
                                </p>
                            </div>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('employee.account.personal.update') }}"
                            novalidate
                        >
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="full_name" class="form-label">
                                    Teljes név
                                </label>

                                <input
                                    id="full_name"
                                    name="full_name"
                                    type="text"
                                    class="form-control @error('full_name') is-invalid @enderror"
                                    value="{{ old('full_name', $user->name) }}"
                                    maxlength="150"
                                    required
                                >

                                @error('full_name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    E-mail-cím
                                </label>

                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email', $user->email) }}"
                                    maxlength="191"
                                    required
                                >

                                @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="phone" class="form-label">
                                    Telefonszám
                                </label>

                                <input
                                    id="phone"
                                    name="phone"
                                    type="text"
                                    class="form-control @error('phone') is-invalid @enderror"
                                    value="{{ old('phone', $user->phone) }}"
                                    maxlength="50"
                                >

                                @error('phone')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="bank_account_holder" class="form-label">
                                    Számlatulajdonos neve
                                </label>

                                <input
                                    id="bank_account_holder"
                                    type="text"
                                    class="form-control df-account-readonly-field"
                                    value="{{ $employee?->bank_account_holder }}"
                                    readonly
                                    disabled
                                    tabindex="-1"
                                >
                            </div>

                            <div class="mb-2">
                                <label for="bank_account_number" class="form-label">
                                    Bankszámlaszám
                                </label>

                                <input
                                    id="bank_account_number"
                                    type="text"
                                    class="form-control df-account-readonly-field"
                                    value="{{ $employee?->bank_account_number }}"
                                    readonly
                                    disabled
                                    tabindex="-1"
                                >
                            </div>

                            <div class="df-account-muted-box p-3 mb-4">
                                <i class="fa-solid fa-circle-info me-2"></i>
                                A bankszámla-adatok módosítása a dolgozói felületen nem lehetséges. A módosításhoz kérjük, forduljon az intézmény vezetőségéhez.
                            </div>

                            <div class="df-account-form-actions d-grid d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary px-4">
                                    Személyes adatok mentése
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card df-account-section-card h-100">

                    {{-- Lakcím --}}
                    <section class="df-account-main-section" id="address-card">
                        <div class="df-account-section-heading">
                            <div class="df-account-card-icon is-address">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>

                            <div>
                                <h2 class="df-account-section-title">
                                    Lakcím
                                </h2>

                                <p class="df-account-section-description">
                                    Az Ön kapcsolattartási címe a rendszerben.
                                </p>
                            </div>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('employee.account.address.update') }}"
                            novalidate
                        >
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="postal_code" class="form-label">
                                        Irányítószám
                                    </label>

                                    <input
                                        id="postal_code"
                                        name="postal_code"
                                        type="text"
                                        class="form-control @error('postal_code') is-invalid @enderror"
                                        value="{{ old('postal_code', $employee?->postal_code) }}"
                                        maxlength="10"
                                    >

                                    @error('postal_code')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-8">
                                    <label for="city" class="form-label">
                                        Település
                                    </label>

                                    <input
                                        id="city"
                                        name="city"
                                        type="text"
                                        class="form-control @error('city') is-invalid @enderror"
                                        value="{{ old('city', $employee?->city) }}"
                                        maxlength="100"
                                    >

                                    @error('city')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-7">
                                    <label for="street_name" class="form-label">
                                        Közterület neve
                                    </label>

                                    <input
                                        id="street_name"
                                        name="street_name"
                                        type="text"
                                        class="form-control @error('street_name') is-invalid @enderror"
                                        value="{{ old('street_name', $employee?->street_name) }}"
                                        maxlength="255"
                                    >

                                    @error('street_name')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-5">
                                    <label for="street_type" class="form-label">
                                        Közterület jellege
                                    </label>

                                    <input
                                        id="street_type"
                                        name="street_type"
                                        type="text"
                                        class="form-control @error('street_type') is-invalid @enderror"
                                        value="{{ old('street_type', $employee?->street_type) }}"
                                        maxlength="50"
                                    >

                                    @error('street_type')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="house_number" class="form-label">
                                        Házszám
                                    </label>

                                    <input
                                        id="house_number"
                                        name="house_number"
                                        type="text"
                                        class="form-control @error('house_number') is-invalid @enderror"
                                        value="{{ old('house_number', $employee?->house_number) }}"
                                        maxlength="30"
                                    >

                                    @error('house_number')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="floor" class="form-label">
                                        Emelet
                                    </label>

                                    <input
                                        id="floor"
                                        name="floor"
                                        type="text"
                                        class="form-control @error('floor') is-invalid @enderror"
                                        value="{{ old('floor', $employee?->floor) }}"
                                        maxlength="20"
                                    >

                                    @error('floor')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="door" class="form-label">
                                        Ajtó
                                    </label>

                                    <input
                                        id="door"
                                        name="door"
                                        type="text"
                                        class="form-control @error('door') is-invalid @enderror"
                                        value="{{ old('door', $employee?->door) }}"
                                        maxlength="20"
                                    >

                                    @error('door')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="country" class="form-label">
                                        Ország
                                    </label>

                                    <input
                                        id="country"
                                        name="country"
                                        type="text"
                                        class="form-control @error('country') is-invalid @enderror"
                                        value="{{ old('country', $employee?->country) }}"
                                        maxlength="100"
                                    >

                                    @error('country')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>

                            @if($employee === null)
                                <div class="df-account-muted-box p-3 mt-4 mb-0">
                                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                    Jelenleg nincs aktív dolgozói jogviszonya rögzítve, ezért a lakcím nem menthető.
                                </div>
                            @else
                                <div class="df-account-form-actions d-grid d-md-flex justify-content-md-end mt-4">
                                    <button type="submit" class="btn btn-primary px-4">
                                        Lakcím mentése
                                    </button>
                                </div>
                            @endif
                        </form>
                    </section>

                    {{-- Biztonság --}}
                    <section class="df-account-main-section" id="security-card">
                        <div class="df-account-section-heading">
                            <div class="df-account-card-icon is-security">
                                <i class="fa-solid fa-lock"></i>
                            </div>

                            <div>
                                <h2 class="df-account-section-title">
                                    Biztonság
                                </h2>

                                <p class="df-account-section-description">
                                    Jelszavát itt tudja biztonságosan módosítani.
                                </p>
                            </div>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('employee.account.password.update') }}"
                            novalidate
                        >
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label
                                    for="current_password"
                                    class="form-label"
                                >
                                    Jelenlegi jelszó
                                </label>

                                <input
                                    id="current_password"
                                    name="current_password"
                                    type="password"
                                    class="form-control @error('current_password') is-invalid @enderror"
                                    autocomplete="current-password"
                                    required
                                >

                                @error('current_password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    Új jelszó
                                </label>

                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    autocomplete="new-password"
                                    required
                                    minlength="8"
                                >

                                @error('password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label
                                    for="password_confirmation"
                                    class="form-label"
                                >
                                    Új jelszó megerősítése
                                </label>

                                <input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                    minlength="8"
                                >
                            </div>

                            <div class="df-account-form-actions d-grid d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary px-4">
                                    Jelszó módosítása
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
@endsection
