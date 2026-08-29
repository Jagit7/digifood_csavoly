@extends('layouts.superadmin')

@section('title', 'Csoportos lemondási műveletek')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Csoportos lemondási műveletek',
        'subtitle' => 'Korábban rögzített batch-ek és összesített eredményeik',
        'buttonText' => 'Új csoportos lemondás',
        'buttonIcon' => 'fa-solid fa-plus',
        'buttonUrl' => route('dashboard.institution.meal-cancellations.bulk.create'),
    ])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Előzmények</h4>
            <span class="text-muted">Találatok: {{ $batches->total() }}</span>
        </div>
        <div class="card-body">
            @if($batches->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Esemény</th>
                                <th>Időszak</th>
                                <th>Gyermekek</th>
                                <th>Létrehozott lemondások</th>
                                <th>Kihagyott alkalmak</th>
                                <th>Rögzítette</th>
                                <th>Létrehozva</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($batches as $batch)
                                <tr>
                                    <td><strong>{{ $batch->event_name }}</strong></td>
                                    <td>{{ $batch->date_from->format('Y.m.d.') }} – {{ $batch->date_to->format('Y.m.d.') }}</td>
                                    <td>{{ $batch->selected_children_count }}</td>
                                    <td>{{ $batch->created_cancellation_count }}</td>
                                    <td>{{ $batch->duplicate_count + $batch->missing_meal_setting_count + $batch->non_service_day_count + $batch->deadline_blocked_count + $batch->class_cancelled_count }}</td>
                                    <td>{{ $batch->creator?->name ?? '—' }}</td>
                                    <td>{{ $batch->created_at?->format('Y.m.d. H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('dashboard.institution.meal-cancellations.bulk.show', $batch) }}" class="btn btn-sm btn-outline-primary">Részletek</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $batches->links('vendor.pagination.digifood') }}</div>
            @else
                <div class="text-muted">Még nincs rögzített csoportos étkezéslemondás.</div>
            @endif
        </div>
    </div>
</div>
@endsection
