@extends('layouts.superadmin')

@section('content')
<div class="row">
    <div class="col-xl-10 col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Új számlázási partner</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('dashboard.billing-partners.store') }}">
                    @csrf

                    @include('dashboard.superadmin.billing_partners._form')

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('dashboard.billing-partners.index') }}" class="btn btn-outline-secondary">
                            Vissza
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Partner mentése
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
