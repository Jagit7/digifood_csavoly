@extends('layouts.superadmin')

@section('title', 'Számlák')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Számlák',
        'subtitle' => 'A kiállított számlák, PDF-ek és a későbbi számlázási műveletek önálló felülete.',
    ])

    <div class="card">
        <div class="card-body">
            @include('layouts.partials.components.ui.empty-state', [
                'icon' => 'fa-solid fa-file-invoice',
                'title' => 'A funkció fejlesztés alatt',
                'text' => 'Ezen az oldalon lesznek elérhetők a számlaadatok és a később bővíthető számlázási műveletek.',
            ])
        </div>
    </div>
</div>
@endsection
