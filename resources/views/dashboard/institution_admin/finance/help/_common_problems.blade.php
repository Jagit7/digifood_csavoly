<div class="card mb-4">
    <div class="card-header">
        <h4 class="card-title mb-0">Gyakori problémák</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($problems as $problem)
                <div class="col-12">
                    <div class="finance-help-problem-card p-4">
                        <h5 class="mb-3">{{ $problem['title'] }}</h5>
                        <p class="mb-2"><strong>Valószínű ok:</strong> {{ $problem['cause'] }}</p>
                        <p class="mb-0"><strong>Javasolt ellenőrzés vagy megoldás:</strong> {{ $problem['solution'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
