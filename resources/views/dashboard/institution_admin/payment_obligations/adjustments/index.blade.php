@extends('layouts.superadmin')

@section('title', 'Pénzügyi korrekciók')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Pénzügyi korrekciók',
        'subtitle' => $statement->child->name . ' · ' . $periods['payment_period_label'] . 'i fizetési elszámolás',
    ])

    <div class="row">
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Összes korrekció', 'value' => $adjustments->total(), 'subtitle' => 'Rögzített pénzügyi tétel', 'icon' => 'fa-solid fa-scale-balanced', 'color' => 'blue'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Aktív tételek', 'value' => $adjustments->getCollection()->whereNull('reversed_at')->count(), 'subtitle' => 'Aktuális oldalon', 'icon' => 'fa-solid fa-circle-check', 'color' => 'green'])
        @include('layouts.partials.components.ui.stats-card', ['title' => 'Sztornózott', 'value' => $adjustments->getCollection()->whereNotNull('reversed_at')->count(), 'subtitle' => 'Aktuális oldalon', 'icon' => 'fa-solid fa-rotate-left', 'color' => 'orange'])
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h4 class="card-title mb-0">Új korrekció rögzítése</h4>
        </div>
        <div class="card-body">
            <div class="alert alert-light border">
                <div><strong>Fizetési hónap:</strong> {{ $periods['payment_period_label'] }}</div>
                <div><strong>Étkezési időszak:</strong> {{ $periods['meal_period_label'] }}</div>
                <div><strong>Jóváírási időszak:</strong> {{ $periods['credit_period_label'] }}</div>
                <div class="mt-2 text-muted">A referencia év és hónap mindig azt a fizetési hónapot jelenti, amelyben a korrekció vagy jóváírás felhasználásra kerül.</div>
            </div>
            <form method="POST" action="{{ route('dashboard.institution.payment-obligations.adjustments.store', $statement) }}">
                @csrf
                <div class="row align-items-end">
                    <div class="col-lg-3 mb-3">
                        <label class="form-label">Típus</label>
                        <select name="type" class="form-control" required>
                            <option value="opening_debt">Nyitó tartozás</option>
                            <option value="opening_credit">Nyitó túlfizetés</option>
                            <option value="debt">Tartozás</option>
                            <option value="credit">Jóváírás</option>
                            <option value="billing_correction">Számlázási helyesbítés</option>
                            <option value="other">Egyéb</option>
                        </select>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Összeg</label>
                        <input type="number" name="amount" class="form-control" min="1" required>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Referencia év</label>
                        <input type="number" name="reference_year" class="form-control" value="{{ $statement->year }}" min="2000" max="2100">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label class="form-label">Referencia hónap</label>
                        <input type="number" name="reference_month" class="form-control" value="{{ $statement->month }}" min="1" max="12">
                    </div>
                    @if($splitManualTransferEnabled)
                        <div class="col-lg-3 mb-3">
                            <label class="form-label">Pénzügyi komponens</label>
                            <select name="payment_component" class="form-control" required>
                                <option value="">Válassz komponenst</option>
                                @foreach($paymentComponentOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-lg-3 mb-3">
                        <div class="form-check">
                            <input type="hidden" name="affects_invoice" value="0">
                            <input type="checkbox" class="form-check-input" id="affects_invoice" name="affects_invoice" value="1" checked>
                            <label class="form-check-label" for="affects_invoice">A {{ $periods['payment_period_label'] }}i fizetési hónapot érinti</label>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label">Pénzügyi dátum</label>
                        <input type="date" name="entry_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-lg-6 mb-3">
                        <label class="form-label">Indoklás</label>
                        <input type="text" name="reason" class="form-control" maxlength="191" required>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="form-label">Bizonylatszám</label>
                        <input type="text" name="document_number" class="form-control" maxlength="100">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i>Rögzítés
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Rögzített tételek</h4>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted">Találatok: {{ $adjustments->total() }}</span>
                <a href="{{ route('dashboard.institution.payment-obligations.show', $statement) }}" class="btn btn-sm btn-light">Vissza a részletezőhöz</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-responsive-md align-middle">
                    <thead>
                    <tr>
                        <th>Típus</th>
                        <th>Komponens</th>
                        <th>Összeg</th>
                        <th>Számlázást érinti</th>
                            <th>Referencia fizetési hónap</th>
                        <th>Pénzügyi dátum</th>
                        <th>Indoklás</th>
                        <th>Rögzítette</th>
                        <th>Állapot</th>
                        <th class="text-end">Műveletek</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($adjustments as $adjustment)
                        <tr>
                            <td>{{ $adjustment->type }}</td>
                            <td>
                                @if($adjustment->payment_component)
                                    {{ $paymentComponentOptions[$adjustment->payment_component] ?? $adjustment->payment_component }}
                                @else
                                    <span class="text-muted">Összesített</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $adjustment->amount > 0 ? 'text-danger' : ($adjustment->amount < 0 ? 'text-success' : 'text-muted') }}">
                                    {{ number_format($adjustment->amount, 0, ',', ' ') }} Ft
                                </span>
                            </td>
                            <td>{{ $adjustment->affects_invoice ? 'Igen' : 'Nem' }}</td>
                            <td>{{ $adjustment->reference_year && $adjustment->reference_month ? sprintf('%04d-%02d', $adjustment->reference_year, $adjustment->reference_month) : 'Nyitó tétel' }}</td>
                            <td>{{ $adjustment->entry_date?->format('Y.m.d.') ?: '—' }}</td>
                            <td>
                                {{ $adjustment->reason }}
                                @if($adjustment->document_number)
                                    <div class="small text-muted">Bizonylat: {{ $adjustment->document_number }}</div>
                                @endif
                            </td>
                            <td>{{ $adjustment->creator?->name ?: '—' }}<div class="small text-muted">{{ $adjustment->created_at?->format('Y.m.d. H:i') }}</div></td>
                            <td>
                                @if($adjustment->reversed_at)
                                    <span class="badge badge-secondary light">Sztornózott</span>
                                    <div class="small text-muted mt-1">{{ $adjustment->reversal_reason }}</div>
                                @else
                                    <span class="badge badge-success light">Aktív</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(!$adjustment->reversed_at)
                                    <button type="button" class="btn btn-xs btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal{{ $adjustment->id }}">
                                        <i class="fa fa-ban"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Még nincs rögzített pénzügyi korrekció.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $adjustments->links('vendor.pagination.digifood') }}
            </div>
        </div>
    </div>
</div>

@foreach($adjustments->getCollection()->whereNull('reversed_at') as $adjustment)
    <div class="modal fade" id="reverseModal{{ $adjustment->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST"
                      action="{{ route('dashboard.institution.payment-obligations.adjustments.reverse', ['statement' => $statement->id, 'adjustment' => $adjustment->id]) }}"
                      class="confirm-form"
                      data-title="Biztosan sztornózni szeretné ezt a korrekciót?"
                      data-text="A sztornózott tétel megmarad a naplóban, de már nem számít bele az egyenlegbe."
                      data-confirm-button-text="Igen, sztornózom">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Korrekció sztornózása</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Bezárás"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Sztornó indoka</label>
                        <textarea name="reversal_reason" class="form-control" rows="3" maxlength="191" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Mégsem</button>
                        <button type="submit" class="btn btn-danger">Sztornózás</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection
