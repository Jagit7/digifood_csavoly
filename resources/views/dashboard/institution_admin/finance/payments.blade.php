@extends('layouts.superadmin')

@section('title', 'Befizetések')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Befizetések',
        'subtitle' => 'A beérkezett befizetések és a későbbi online, illetve kézi fizetési csatornák központi oldala.',
    ])

    <div class="card">
        <div class="card-body">
            @include('layouts.partials.components.ui.empty-state', [
                'icon' => 'fa-solid fa-wallet',
                'title' => 'A funkció fejlesztés alatt',
                'text' => 'Ezen az oldalon jelennek majd meg a befizetések a kapcsolódó fizetési adatokkal együtt.',
            ])
        </div>
    </div>
</div>
@endsection
