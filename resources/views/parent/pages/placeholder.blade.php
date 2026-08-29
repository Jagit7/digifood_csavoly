@extends('layouts.parent')

@section('page_title', $title)

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => $title,
        'subtitle' => 'Előkészített ideiglenes oldal',
    ])

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">{{ $title }}</h4>
                    <p class="mb-0">{{ $description }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
