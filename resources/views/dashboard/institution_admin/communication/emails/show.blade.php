@extends('layouts.superadmin')

@section('title', 'Kampány részletei')

@section('content')
    @include('layouts.partials.components.ui.page-header', [
        'title' => $campaign->subject,
        'subtitle' => 'Kommunikáció / Küldési előzmények',
        'buttons' => [
            [
                'url' => route('dashboard.institution.communication.emails.index'),
                'text' => 'Vissza az előzményekhez',
                'class' => 'btn btn-outline-primary',
                'icon' => 'fa-solid fa-arrow-left',
            ],
        ],
    ])

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Áttekintés</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3"><strong>Tárgy</strong><br>{{ $campaign->subject }}</div>
                    <div class="mb-3"><strong>Létrehozás ideje</strong><br>{{ $campaign->created_at?->format('Y.m.d. H:i') }}</div>
                    <div class="mb-3"><strong>Létrehozó</strong><br>{{ $campaign->creator?->name ?? '-' }}</div>
                    <div class="mb-3">
                        <strong>Státusz</strong><br>
                        <span class="badge {{ $campaign->status_badge_class }} light">{{ $campaign->status_label }}</span>
                    </div>
                    <div class="mb-3"><strong>Címzettek száma</strong><br>{{ $campaign->recipient_count }}</div>
                    <div class="mb-3"><strong>Sikeres</strong><br>{{ $campaign->sent_count }}</div>
                    <div><strong>Sikertelen</strong><br>{{ $campaign->failed_count }}</div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">E-mail tartalma</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info small mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Ez a közös sablon előnézete - a benne látható helyőrzők (pl.
                        <code>#DOLGOZO_NEVE#</code>, <code>#SZULO_NEVE#</code>, <code>#INTEZMENY_NEVE#</code>)
                        a ténylegesen kiküldött levelekben címzettenként külön-külön behelyettesítődnek
                        a lenti "Címzettek" listában szereplő névre. Ez a doboz csak a sablont mutatja,
                        nem az egyes címzetteknek ténylegesen kiküldött, már behelyettesített szöveget.
                    </div>
                    {!! $campaign->safe_body !!}
                </div>

                <div class="card-header">
                    <h4 class="card-title mb-0">Címzettek</h4>
                </div>
                <div class="card-body">
                    @if($recipients->count())
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                <tr>
                                    <th>Név</th>
                                    <th>E-mail</th>
                                    <th>Gyermek(ek)</th>
                                    <th>Osztály(ok)</th>
                                    <th>Állapot</th>
                                    <th>Küldés ideje</th>
                                    <th>Hiba</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($recipients as $recipient)
                                    <tr>
                                        <td>{{ $recipient->recipient_name ?: '-' }}</td>
                                        <td>{{ $recipient->email }}</td>
                                        <td>{{ $recipient->childNamesList() ?: '-' }}</td>
                                        <td>{{ $recipient->classGroupNamesList() ?: '-' }}</td>
                                        <td>
                                            <span class="badge {{ $recipient->status_badge_class }} light">
                                                {{ $recipient->status_label }}
                                            </span>
                                        </td>
                                        <td>{{ $recipient->sent_at?->format('Y.m.d. H:i') ?: '-' }}</td>
                                        <td class="text-wrap">{{ $recipient->error_message ?: '-' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $recipients->links('vendor.pagination.digifood') }}
                    @else
                        <div class="text-muted">Ehhez a kampányhoz még nincs címzett.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
