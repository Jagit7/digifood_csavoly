@extends('auth.template_auth')

@section('titre')
Digifood - intézményi meghívó
@endsection

@section('contenu')
<div class="login-account df-auth-page">
    <div class="df-auth-layout">
        <div class="df-auth-visual">
            <img
                src="{{ asset('auth/images/login_canva.png') }}"
                alt="Digifood – iskolai és óvodai étkeztetés"
                class="df-auth-image">
        </div>

        <div class="df-auth-form-column">
            <div class="df-auth-form-wrapper">
                <div class="login-form">
                    <div class="login-head">
                        <h3 class="title">{{ $title }}</h3>
                        <p>{{ $lead }}</p>
                    </div>

                    <div class="alert alert-warning border-0 rounded-3 mb-4">
                        {{ $message }}
                    </div>

                    <div class="d-grid gap-2">
                        <a href="{{ route('auth.login') }}" class="btn btn-primary w-100">
                            Belépés
                        </a>
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
}

.df-auth-visual {
    flex: 0 0 auto;
    width: auto;
    height: 100vh;
    overflow: hidden;
    background: #001d32;
}

.df-auth-image {
    display: block;
    width: auto;
    height: 100vh;
    max-width: none;
    object-fit: contain;
}

.df-auth-form-column {
    flex: 1 1 0;
    min-width: 0;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 35px 55px !important;
    overflow-y: auto;
    background: #ffffff;
}

.df-auth-form-wrapper {
    width: 100%;
    max-width: 560px;
    margin: auto;
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
    }

    .df-auth-image {
        width: 100%;
        height: auto;
        max-width: 100%;
        background: #001d32;
    }

    .df-auth-form-column {
        width: 100%;
        height: auto;
        min-height: auto;
        padding: 45px 25px 55px !important;
        overflow: visible;
    }
}

@media (max-width: 575.98px) {
    .df-auth-visual {
        width: 100%;
        height: 280px;
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
}
</style>
@endpush
