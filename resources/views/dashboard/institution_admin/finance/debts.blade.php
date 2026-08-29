@extends('layouts.superadmin')

@section('title', 'Tartozások')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Tartozások',
        'subtitle' => 'A még nyitott, ki nem fizetett tételek és a későbbi felszólítási folyamatok előkészített oldala.',
    ])

    <div class="card">
        <div class="card-body">
            @include('layouts.partials.components.ui.empty-state', [
                'icon' => 'fa-solid fa-triangle-exclamation',
                'title' => 'A funkció fejlesztés alatt',
                'text' => 'Itt jelennek majd meg a fennálló tartozások, a lejárati információk és a kapcsolódó teendők.',
            ])
        </div>
    </div>
</div>
@endsection
