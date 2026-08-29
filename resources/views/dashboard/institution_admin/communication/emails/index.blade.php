@extends('layouts.superadmin')

@section('title', 'Küldési előzmények')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Küldési előzmények',
        'subtitle' => 'Kommunikáció / Küldési előzmények',
        'buttons' => [
            [
                'url' => route('dashboard.institution.communication.emails.create'),
                'text' => 'Új e-mail küldése',
                'class' => 'btn btn-primary',
                'icon' => 'fa-solid fa-paper-plane',
            ],
        ],
    ])

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Kampányok</h4>
        </div>
        <div class="card-body">
            @if($campaigns->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>Dátum</th>
                            <th>Tárgy</th>
                            <th>Címzettek</th>
                            <th>Sikeres</th>
                            <th>Sikertelen</th>
                            <th>Állapot</th>
                            <th>Létrehozta</th>
                            <th class="text-end">Művelet</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($campaigns as $campaign)
                            <tr>
                                <td>{{ $campaign->created_at?->format('Y.m.d. H:i') }}</td>
                                <td>{{ $campaign->subject }}</td>
                                <td>{{ $campaign->recipient_count }}</td>
                                <td>{{ $campaign->sent_count }}</td>
                                <td>{{ $campaign->failed_count }}</td>
                                <td>
                                    <span class="badge {{ $campaign->status_badge_class }} light">
                                        {{ $campaign->status_label }}
                                    </span>
                                </td>
                                <td>{{ $campaign->creator?->name ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('dashboard.institution.communication.emails.show', $campaign) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        Részletek
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $campaigns->links('vendor.pagination.digifood') }}
            @else
                <div class="text-muted">Még nincs kiküldött vagy sorba állított kampány.</div>
            @endif
        </div>
    </div>
@endsection
