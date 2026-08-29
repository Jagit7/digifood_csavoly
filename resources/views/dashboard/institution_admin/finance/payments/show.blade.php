@extends('layouts.superadmin')

@section('title', 'Befizetés részletei')

@section('content')
<div class="container-fluid">
    @php
        $statusMeta = \App\Models\InstitutionPayment::statusMeta($payment->status);
        $methodMeta = \App\Models\InstitutionPayment::paymentMethodMeta($payment->payment_method);
        $statement = $payment->monthlyPaymentStatement;
        $relatedInvoice = $payment->invoice_number ?: $statement?->invoice_number;
    @endphp

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Befizetés részletei',
        'subtitle' => $payment->child?->name ? ('Gyermek: ' . $payment->child->name) : 'Rögzített intézményi befizetés részletezője.',
        'buttons' => [
            [
                'url' => route('dashboard.institution.finance.payments.edit', $payment),
                'text' => 'Szerkesztés',
                'icon' => 'fa-solid fa-pen',
                'class' => 'btn btn-warning',
            ],
            [
                'url' => route('dashboard.institution.finance.payments'),
                'text' => 'Vissza a listához',
                'icon' => 'fa-solid fa-arrow-left',
                'class' => 'btn btn-light',
            ],
        ],
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Összeg',
            'value' => number_format($payment->amount, 0, ',', ' ') . ' Ft',
            'subtitle' => 'Rögzített befizetés',
            'icon' => 'fa-solid fa-wallet',
            'color' => 'green',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Fizetési mód',
            'value' => $methodMeta['label'],
            'subtitle' => 'Rögzített fizetési csatorna',
            'icon' => $methodMeta['icon'],
            'color' => 'blue',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Státusz',
            'value' => $statusMeta['label'],
            'subtitle' => 'Aktuális befizetési állapot',
            'icon' => 'fa-solid fa-circle-check',
            'color' => 'purple',
        ])
        @include('layouts.partials.components.ui.stats-card', [
            'title' => 'Rögzítő',
            'value' => $payment->recordedBy?->name ?: '—',
            'subtitle' => 'A befizetést rögzítő felhasználó',
            'icon' => 'fa-solid fa-user-pen',
            'color' => 'orange',
        ])
    </div>

    @if($invoiceAction)
        <div class="alert alert-info d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <div class="fw-semibold mb-1">Készpénzes befizetéshez kapcsolódó számlázás</div>
                <div>{{ $invoiceAction['helper'] }}</div>
            </div>
            <a href="{{ $invoiceAction['url'] }}" class="{{ $invoiceAction['class'] }}">
                <i class="{{ $invoiceAction['icon'] }} me-1"></i>{{ $invoiceAction['label'] }}
            </a>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Részletes adatok</h4>
            <form method="POST"
                  action="{{ route('dashboard.institution.finance.payments.destroy', $payment) }}"
                  class="confirm-form"
                  data-title="Biztosan törlöd ezt a befizetést?"
                  data-text="A törlés nem vonható vissza."
                  data-confirm-button-text="Igen, törlöm"
                  data-confirm-button-color="#dc3545">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fa-solid fa-trash me-1"></i>Törlés
                </button>
            </form>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">Befizetés dátuma</div>
                        <div class="fw-semibold">{{ $payment->paid_at?->format('Y.m.d. H:i') }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">Hivatkozás</div>
                        <div class="fw-semibold">{{ $payment->reference ?: '—' }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">Gyermek</div>
                        <div class="fw-semibold">{{ $payment->child?->name ?: '—' }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">Szülő / gondviselő</div>
                        <div class="fw-semibold">{{ $payment->guardian?->full_name ?: '—' }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">Kapcsolódó havi kötelezettség</div>
                        <div class="fw-semibold">
                            @if($statement)
                                {{ sprintf('%04d.%02d', $statement->year, $statement->month) }}
                            @else
                                —
                            @endif
                        </div>
                        @if($statement)
                            <div class="small text-muted mt-1">{{ $statement->child?->name }}</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="text-muted small mb-1">Kapcsolódó számla</div>
                        <div class="fw-semibold">{{ $relatedInvoice ?: '—' }}</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="border rounded-3 p-3">
                        <div class="text-muted small mb-1">Megjegyzés</div>
                        <div>{{ $payment->note ?: 'Nincs megjegyzés.' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
