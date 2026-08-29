@extends('layouts.superadmin')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Számlázási partnerek</h4>
                    <p class="text-muted mb-0">Összesen: {{ $billingPartners->count() }}</p>
                </div>
                <a href="{{ route('dashboard.billing-partners.create') }}" class="btn btn-primary">
                    Új számlázási partner
                </a>
            </div>
            <div class="card-body">
                @if($billingPartners->isEmpty())
                    <div class="text-center py-5">
                        <h5 class="mb-2">Még nincs számlázási partner.</h5>
                        <p class="text-muted mb-4">Az első partner létrehozásával később több intézményt is közös számlázási partnerhez tudsz kötni.</p>
                        <a href="{{ route('dashboard.billing-partners.create') }}" class="btn btn-outline-primary">
                            Új számlázási partner
                        </a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Partner neve</th>
                                    <th>Számlázási név</th>
                                    <th>Adószám</th>
                                    <th>Hozzárendelt intézmények</th>
                                    <th>Állapot</th>
                                    <th class="text-end">Művelet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($billingPartners as $billingPartner)
                                    <tr>
                                        <td class="fw-semibold">{{ $billingPartner->name }}</td>
                                        <td>{{ $billingPartner->billing_name ?: '—' }}</td>
                                        <td>{{ $billingPartner->tax_number ?: '—' }}</td>
                                        <td>{{ $billingPartner->institutions_count }}</td>
                                        <td>
                                            <span class="badge {{ $billingPartner->active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $billingPartner->active ? 'Aktív' : 'Inaktív' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('dashboard.billing-partners.edit', $billingPartner) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                Szerkesztés
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
