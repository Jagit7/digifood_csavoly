<!DOCTYPE html>
<html lang="hu" class="h-100">

<head>
    <base href="{{ url('/') }}/">
    <title>@yield('titre')</title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href="{{ asset('home/favicon.png') }}">
    <link href="{{ asset('auth/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
    <link class="main-css" href="{{ asset('auth/css/style.css') }}" rel="stylesheet">

    @stack('styles')
</head>

<body class="h-100">

    @yield('contenu')

    <div style="position:fixed; left:0; right:0; bottom:0; z-index:2000; text-align:center; padding:8px 12px; font-size:12px; pointer-events:none;">
        <a href="{{ route('legal.privacy') }}" style="pointer-events:auto; color:#64748b; background:rgba(255,255,255,0.85); padding:4px 12px; border-radius:999px; text-decoration:none;">
            Adatkezelési tájékoztató
        </a>
    </div>

    <!--**********************************
        Scripts
    ***********************************-->
    <!-- Required vendors -->
    <script src="{{ url('auth/vendor/global/global.min.js') }}"></script>
    <script src="{{ url('auth/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ url('auth/js/custom.min.js') }}"></script>

    @stack('scripts')
</body>

</html>