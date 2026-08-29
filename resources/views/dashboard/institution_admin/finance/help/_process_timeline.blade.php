<div class="card mb-4">
    <div class="card-header">
        <h4 class="card-title mb-0">A havi pénzügyi folyamat</h4>
    </div>
    <div class="card-body">
        <div class="widget-timeline style-1 finance-help-widget finance-help-timeline">
            <ul class="timeline">
                @foreach($items as $item)
                    <li class="finance-timeline-item">
                        <div class="timeline-badge timeline-step-number {{ $item['badge'] }}">{{ $item['step'] }}</div>
                        <div class="timeline-panel">
                            <div class="media-body">
                                <h5 class="mb-2">{{ $item['title'] }}</h5>
                                <p class="mb-0 text-muted">{{ $item['text'] }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
