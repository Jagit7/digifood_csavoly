@extends('auth.template_auth')

@section('titre')
Digifood - jelszó beállítása
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
             JOBB OLDALI JELSZÓ-BEÁLLÍTÓ PANEL
        ========================================================== --}}
        <div class="df-auth-form-column">

            <div class="df-auth-form-wrapper">

                <div class="login-form">

                    <div class="login-head">
                        <h3 class="title">Jelszó beállítása</h3>
                        <p>Aktiválja a Digifood szülői hozzáférést az új jelszó megadásával.</p>
                    </div>

                    <h6 class="login-title"><span>Szülői fiók aktiválása</span></h6>

                    @if ($errors->has('token'))
                        <div class="alert alert-danger">
                            {{ $errors->first('token') }}
                        </div>
                    @endif

                    @if ($errors->has('email'))
                        <div class="alert alert-danger">
                            {{ $errors->first('email') }}
                        </div>
                    @endif

                    <div class="alert alert-light border mb-4">
                        <div><strong>Gondviselő:</strong> {{ $display_name }}</div>
                        <div><strong>E-mail:</strong> {{ $email }}</div>
                        @if (($institution_names ?? collect())->isNotEmpty())
                            <div><strong>Intézmény:</strong> {{ $institution_names->implode(', ') }}</div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('parent.activation.store', ['token' => $token]) }}">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label">E-mail</label>
                            <input type="email" class="form-control" value="{{ $email }}" disabled>
                        </div>

                        <div class="mb-4">
                            <label class="form-label required">Új jelszó</label>
                            <input type="password"
                                   name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required
                                   minlength="8">

                            @error('password')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror

                            <small class="text-muted d-block mt-1">Legalább 8 karakter hosszú legyen.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label required">Új jelszó megerősítése</label>
                            <input type="password"
                                   name="password_confirmation"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required
                                   minlength="8">
                        </div>

                        <div class="text-center mb-3">
                            <button type="submit" class="btn btn-primary btn-block w-100">Fiók aktiválása</button>
                        </div>
                    </form>

                    <div class="text-center text-muted small">
                        Az aktiváló link {{ $expires_at->timezone(config('app.timezone'))->format('Y.m.d. H:i') }} időpontig használható fel.
                    </div>

                </div>

            </div>

        </div>

    </div>
</div>

@endsection


@push('styles')

<style>

/* =========================================================
   OLDAL ALAPBEÁLLÍTÁS
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
   JOBB OLDALI PANEL
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
   FORM MÉRETE
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
