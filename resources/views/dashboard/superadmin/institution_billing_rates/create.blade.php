@extends('layouts.superadmin')

@section('content')
<div class="row">
    <div class="col-xl-10 col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Új díjszabás</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('dashboard.institutions.billing-rates.store', $institution) }}">
                    @csrf

                    @include('dashboard.superadmin.institution_billing_rates._form')

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('dashboard.institutions.billing-rates.index', $institution) }}" class="btn btn-outline-secondary">
                            Vissza
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Díjszabás mentése
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
