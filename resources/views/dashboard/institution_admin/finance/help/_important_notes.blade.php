<div class="card mb-4">
    <div class="card-header">
        <h4 class="card-title mb-0">Fontos tudnivalók</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($notes as $note)
                <div class="col-xl-6">
                    <div class="alert {{ $note['class'] }} finance-help-note-card h-100 mb-0">
                        <div class="d-flex align-items-start gap-3">
                            <i class="{{ $note['icon'] }} mt-1"></i>
                            <div>
                                <h5 class="mb-2">{{ $note['title'] }}</h5>
                                <p class="mb-0">{{ $note['text'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
