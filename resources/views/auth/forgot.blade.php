@extends('auth.template_auth')

@section('titre')
Digifood - jelszó emlékeztető
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
             JOBB OLDALI JELSZÓ EMLÉKEZTETŐ
        ========================================================== --}}
        <div class="df-auth-form-column">

            <div class="df-auth-form-wrapper">

                <div class="login-form">

                    <div class="login-head">

                        <h3 class="title">
                            Elfelejtette jelszavát?
                        </h3>

                        <p>
                            Adja meg az e-mail címét, és elküldjük a jelszó-visszaállítási linket.
                        </p>

                    </div>


                    <h6 class="login-title">
                        <span>Jelszó emlékeztető</span>
                    </h6>


                    @if (session('status'))

                        <div class="alert alert-success mt-3">
                            {{ session('status') }}
                        </div>

                    @endif


                    <form
                        method="POST"
                        action="{{ route('auth.forgot') }}">

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


                        {{-- Küldés --}}
                        <div class="text-center mb-4">

                            <button
                                type="submit"
                                class="btn btn-primary btn-block w-100">

                                Jelszó-visszaállító link küldése

                            </button>

                        </div>


                        {{-- Vissza a belépéshez --}}
                        <p class="text-center mb-0">

                            Mégsem felejtette el?

                            <a
                                class="btn-link text-primary"
                                href="{{ route('auth.login') }}">

                                Vissza a belépéshez

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

}

</style>

@endpush