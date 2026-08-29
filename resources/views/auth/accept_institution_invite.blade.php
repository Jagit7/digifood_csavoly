@extends('auth.template_auth')

@section('titre')
Digifood - meghívó elfogadása
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
             JOBB OLDALI MEGHÍVÓ ELFOGADÁSA
        ========================================================== --}}
        <div class="df-auth-form-column">

            <div class="df-auth-form-wrapper">

                <div class="login-form">

                    <div class="login-head">

                        <h3 class="title">
                            Meghívó elfogadása
                        </h3>

                        <p>
                            {{
                                match ($invitation->role) {
                                    \App\Models\User::ROLE_INSTITUTION_SECRETARY => 'Meghívást kapott intézményi titkárként a Digifood rendszerbe.',
                                    \App\Models\User::ROLE_INSTITUTION_ADMIN => 'Meghívást kapott intézményi adminként a Digifood rendszerbe.',
                                    default => 'Állítsa be a jelszavát a Digifood rendszerhez.',
                                }
                            }}
                        </p>

                    </div>


                    <h6 class="login-title">
                        <span>Jelszó beállítása</span>
                    </h6>


                    @include('layouts.partials.flash')


                    {{-- Meghívó adatai --}}
                    <div class="alert alert-info df-invitation-info">

                        <div class="fw-semibold mb-1">
                            {{ $invitation->name }}
                        </div>

                        <div>
                            {{ $invitation->email }}
                        </div>

                        <div class="mt-1">
                            Intézmény:
                            <strong>
                                {{ $invitation->institution->name }}
                            </strong>
                        </div>

                    </div>


                    {{-- =================================================
                         JELSZÓ BEÁLLÍTÁSA
                    ================================================== --}}
                    <form
                        method="POST"
                        action="{{ route('institution-invite.complete', $token) }}">

                        @csrf


                        {{-- Jelszó --}}
                        <div class="mb-4 position-relative">

                            <label class="form-label required">
                                Jelszó
                            </label>

                            <input
                                type="password"
                                id="invite-password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                                autocomplete="new-password">

                            @error('password')

                                <small class="text-danger d-block">
                                    {{ $message }}
                                </small>

                            @enderror

                        </div>


                        {{-- Jelszó megerősítése --}}
                        <div class="mb-4 position-relative">

                            <label class="form-label required">
                                Jelszó megerősítése
                            </label>

                            <input
                                type="password"
                                id="invite-password-confirmation"
                                name="password_confirmation"
                                class="form-control"
                                required
                                autocomplete="new-password">

                        </div>


                        {{-- Fiók létrehozása --}}
                        <div class="text-center mb-4">

                            <button
                                type="submit"
                                class="btn btn-primary btn-block w-100">

                                Fiók létrehozása

                            </button>

                        </div>


                        {{-- Már van fiókja --}}
                        <p class="text-center mb-0">

                            Már van fiókja?

                            <a
                                class="btn-link text-primary"
                                href="{{ route('auth.login') }}">

                                Belépek

                            </a>

                        </p>

                    </form>

                </div>

            </div>

        </div>

    </div>
</div>

@endsection


@push('styles')

<style>

/* =========================================================
   ALAP
========================================================= */

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


/* =========================================================
   TELJES AUTH OLDAL
========================================================= */

.login-account.df-auth-page {
    width: 100vw !important;
    height: 100vh !important;
    min-height: 100vh !important;

    margin: 0 !important;
    padding: 0 !important;

    overflow: hidden !important;

    background: #ffffff;
}


/* =========================================================
   FŐ LAYOUT
========================================================= */

.df-auth-layout {
    display: flex;
    align-items: stretch;

    width: 100%;
    height: 100vh;

    margin: 0;
    padding: 0;
}


/* =========================================================
   BAL OLDALI KÉP
========================================================= */

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


/* =========================================================
   JOBB OLDALI FORM
========================================================= */

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


/* =========================================================
   FORM MÉRET
========================================================= */

.df-auth-form-wrapper {
    width: 100%;
    max-width: 560px;

    margin: auto;
}


.df-auth-form-wrapper .login-form {
    width: 100%;
    margin: 0;
}


/* =========================================================
   MEGHÍVÓ ADATDOBOZ
========================================================= */

.df-invitation-info {
    border: 0;
    border-radius: 12px;
    padding: 16px 18px;
}


/* =========================================================
   KISEBB LAPTOP
========================================================= */

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


/* =========================================================
   TABLET
========================================================= */

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


/* =========================================================
   TELEFON
========================================================= */

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


    .df-invitation-info {
        padding: 14px 15px;
    }

}

</style>

@endpush