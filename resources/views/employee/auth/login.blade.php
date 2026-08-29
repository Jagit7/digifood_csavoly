@extends('auth.template_auth')

@section('titre')
Digifood - dolgozói belépés
@endsection

@section('contenu')

<div class="login-account df-auth-page">
    <div class="df-auth-layout">

        {{-- =========================================================
             BAL OLDALI DIGIFOOD VIZUÁLIS PANEL
        ========================================================== --}}
        <div class="df-auth-visual">

            <img
                src="{{ asset('auth/images/login_canva.png') }}"
                alt="Digifood – iskolai és óvodai étkeztetés"
                class="df-auth-image">

        </div>


        {{-- =========================================================
             JOBB OLDALI BELÉPÉSI PANEL
        ========================================================== --}}
        <div class="df-auth-form-column">

            <div class="df-auth-form-wrapper">

                <div class="login-form">

                    {{-- Fejléc --}}
                    <div class="login-head">

                        <h3 class="title">
                            Üdv újra!
                        </h3>

                        <p>
                            Lépjen be a DigiFood dolgozói felületére.
                        </p>

                    </div>


                    {{-- Belépés cím --}}
                    <h6 class="login-title">
                        <span>Dolgozói belépés</span>
                    </h6>


                    {{-- Általános e-mail hiba --}}
                    @if ($errors->has('email'))

                        <div class="alert alert-danger">
                            {{ $errors->first('email') }}
                        </div>

                    @endif


                    {{-- Sikeres visszajelzés (pl. aktiválás után) --}}
                    @if (session('success'))

                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>

                    @endif


                    {{-- =================================================
                         BELÉPÉSI FORM
                    ================================================== --}}
                    <form
                        method="POST"
                        action="{{ route('employee.login.store') }}">

                        @csrf


                        {{-- E-mail --}}
                        <div class="mb-4">

                            <label class="form-label required">
                                E-mail
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control @error('email') is-invalid @enderror"
                                required
                                autocomplete="email"
                                value="{{ old('email') }}">

                            @error('email')

                                <small class="text-danger d-block">
                                    {{ $message }}
                                </small>

                            @enderror

                        </div>


                        {{-- Jelszó --}}
                        <div class="mb-4 position-relative">

                            <label class="form-label required">
                                Jelszó
                            </label>

                            <input
                                type="password"
                                id="dz-password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                                autocomplete="current-password">

                            <span class="show-pass eye">

                                <i class="fa fa-eye-slash"></i>
                                <i class="fa fa-eye"></i>

                            </span>

                            @error('password')

                                <small class="text-danger d-block">
                                    {{ $message }}
                                </small>

                            @enderror

                        </div>


                        {{-- Belépés gomb --}}
                        <div class="text-center mb-4">

                            <button
                                type="submit"
                                class="btn btn-primary btn-block w-100">

                                Belépek

                            </button>

                        </div>

                    </form>


                    {{-- =================================================
                         FIÓK AKTIVÁLÁSA / ELFELEJTETT JELSZÓ
                    ================================================== --}}
                    <div class="d-flex flex-column gap-2 text-center">

                        <a
                            href="{{ route('employee.activation.create') }}"
                            class="btn-link text-primary fw-semibold">

                            Első belépés / Fiók aktiválása

                        </a>

                        <span class="text-muted small">
                            Elfelejtett jelszó funkció előkészítés alatt.
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>
</div>

@endsection


@push('styles')

<style>

html,
body {
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
}

body {
    overflow: hidden;
    background: #ffffff;
}


.login-account.df-auth-page {
    width: 100vw !important;
    height: 100vh !important;
    min-height: 100vh !important;

    margin: 0 !important;
    padding: 0 !important;

    overflow: hidden !important;

    background: #ffffff;
}


.df-auth-layout {
    display: flex;
    align-items: stretch;

    width: 100%;
    height: 100vh;

    margin: 0;
    padding: 0;
}


.df-auth-visual {
    flex: 0 0 auto;

    width: auto;
    height: 100vh;

    margin: 0;
    padding: 0;

    overflow: hidden;

    background: #001d32;
}


.df-auth-image {
    display: block;

    width: auto;
    height: 100vh;

    max-width: none;

    margin: 0;
    padding: 0;

    object-fit: contain;
}


.df-auth-form-column {
    flex: 1 1 0;
    min-width: 0;

    height: 100vh;

    display: flex;
    align-items: center;
    justify-content: center;

    margin: 0;

    padding: 35px 55px !important;

    overflow-y: auto;

    background: #ffffff;
}


.df-auth-form-wrapper {
    width: 100%;
    max-width: 560px;

    margin: auto;
}


.df-auth-form-wrapper .login-form {
    width: 100%;
    margin: 0;
}


@media (max-width: 1350px) and (min-width: 992px) {

    .df-auth-visual {
        width: 58vw;
        height: 100vh;
    }


    .df-auth-image {
        width: 100%;
        height: 100%;

        object-fit: contain;

        background: #001d32;
    }


    .df-auth-form-column {
        padding: 30px 35px !important;
    }


    .df-auth-form-wrapper {
        max-width: 520px;
    }

}


@media (max-width: 991.98px) {

    html,
    body {
        height: auto;
        min-height: 100%;
    }


    body {
        overflow-y: auto;
        overflow-x: hidden;
    }


    .login-account.df-auth-page {
        width: 100% !important;

        height: auto !important;
        min-height: 100vh !important;

        overflow: visible !important;
    }


    .df-auth-layout {
        display: block;

        width: 100%;

        height: auto;
        min-height: 100vh;
    }


    .df-auth-visual {
        width: 100%;
        height: auto;

        margin: 0;
        padding: 0;

        overflow: hidden;

        background: #001d32;
    }


    .df-auth-image {
        display: block;

        width: 100%;
        height: auto;

        max-width: 100%;

        margin: 0;
    }


    .df-auth-form-column {
        width: 100%;
        height: auto;
        min-height: auto;

        padding: 45px 25px 55px !important;

        overflow: visible;
    }


    .df-auth-form-wrapper {
        width: 100%;
        max-width: 600px;
    }

}


@media (max-width: 575.98px) {

    .df-auth-visual {
        width: 100%;
        height: 280px;

        overflow: hidden;

        background: #001d32;
    }


    .df-auth-image {
        width: 100%;
        height: 280px;

        object-fit: cover;
        object-position: top center;
    }


    .df-auth-form-column {
        padding: 32px 18px 45px !important;
    }


    .df-auth-form-wrapper {
        width: 100%;
    }


    .df-auth-form-wrapper .login-head {
        margin-bottom: 22px;
    }


    .df-auth-form-wrapper .login-head .title {
        font-size: 28px;
    }

}

</style>

@endpush
