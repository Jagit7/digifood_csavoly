@extends('layouts.superadmin')

@section('content')
<div class="container-fluid">

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">Étlap szerkesztése</h3>
                        <div class="text-muted">
                            {{ $institution->name ?? 'Intézmény' }}
                        </div>
                    </div>

                    <a href="{{ route('dashboard.institution.menus.index') }}" class="btn btn-light">
                        Vissza az étlapokhoz
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Hiba történt.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('dashboard.institution.menus.update', $menu) }}"
          method="POST"
          enctype="multipart/form-data">
        @csrf
        @method('PUT')

        @include('dashboard.institution_admin.menus._form', [
            'menu' => $menu
        ])
    </form>

</div>
@endsection