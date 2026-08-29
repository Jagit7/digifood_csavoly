<div class="card mb-4">
    <div class="card-header">
        <h4 class="card-title mb-0">Részletes szabályok</h4>
    </div>
    <div class="card-body">
        <div class="accordion" id="financeHelpRulesAccordion">
            @foreach($rules as $rule)
                <div class="accordion-item border mb-3 rounded-3 overflow-hidden">
                    <h2 class="accordion-header" id="heading-{{ $rule['key'] }}">
                        <button class="accordion-button @if(!$loop->first) collapsed @endif finance-help-rule-title"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse-{{ $rule['key'] }}"
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                aria-controls="collapse-{{ $rule['key'] }}">
                            {{ $rule['title'] }}
                        </button>
                    </h2>
                    <div id="collapse-{{ $rule['key'] }}"
                         class="accordion-collapse collapse @if($loop->first) show @endif"
                         aria-labelledby="heading-{{ $rule['key'] }}"
                         data-bs-parent="#financeHelpRulesAccordion">
                        <div class="accordion-body text-muted">
                            {{ $rule['text'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
