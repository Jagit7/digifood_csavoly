@php
    $stateBadgeClass = [
        'ok' => 'bg-success-subtle text-success border-success-subtle',
        'warning' => 'bg-warning-subtle text-warning border-warning-subtle',
    ];
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h4 class="card-title mb-0">Az intézmény jelenlegi pénzügyi beállításai</h4>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            @foreach($settingsBlock['cards'] as $item)
                <div class="col-xl-4 col-md-6">
                    <div class="finance-help-setting-card">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="text-muted">{{ $item['label'] }}</span>
                            <span class="badge border {{ $stateBadgeClass[$item['state']] ?? $stateBadgeClass['warning'] }}">
                                {{ $item['state'] === 'ok' ? 'Rendben' : 'Figyelem' }}
                            </span>
                        </div>
                        <div class="finance-help-setting-value mb-2">{{ $item['value'] }}</div>
                        <p class="text-muted mb-0">{{ $item['note'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="alert alert-light border mb-3">
            <div class="d-flex align-items-start gap-3">
                <i class="fa-solid fa-shield-halved text-primary mt-1"></i>
                <div>{{ $settingsBlock['security_note'] }}</div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @foreach($settingsBlock['links'] as $link)
                <a href="{{ $link['url'] }}" class="btn btn-outline-primary">
                    <i class="fa-solid fa-gear me-2"></i>{{ $link['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</div>
