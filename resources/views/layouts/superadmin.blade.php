<!DOCTYPE html>
<html lang="hu">
<head>
    @include('layouts.partials.styles')
    @stack('styles')
</head>
<body>

<div id="preloader">
    <div class="sk-three-bounce">
        <div class="sk-child sk-bounce1"></div>
        <div class="sk-child sk-bounce2"></div>
        <div class="sk-child sk-bounce3"></div>
    </div>
</div>

<div id="main-wrapper">

    @include('layouts.partials.nav-header')
    @include('layouts.partials.header')
    @include('layouts.partials.sidebar')

    <div class="content-body default-height">
        <div class="container-fluid">
            @include('layouts.partials.flash')
            @yield('content')
        </div>
    </div>

    @include('layouts.partials.footer')

</div>

@include('layouts.partials.scripts')
@stack('scripts')

</body>
</html>