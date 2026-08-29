<div class="row df-page-header align-items-start">
	<div class="col-xl-7 col-lg-7 col-md-7">
		<h3 class="mb-1">{{ $title ?? '' }}</h3>

		@if(!empty($subtitle))
			<p class="text-muted mb-0">{{ $subtitle }}</p>
		@endif
	</div>

	@if(!empty($buttons) && is_iterable($buttons))
		<div class="col-xl-5 col-lg-5 col-md-5 text-md-end mt-3 mt-md-0">
			<div class="d-flex gap-2 justify-content-md-end flex-wrap">
				@foreach($buttons as $button)
					<a href="{{ $button['url'] ?? 'javascript:void(0);' }}"
                       class="{{ $button['class'] ?? 'btn btn-primary' }}"
                       @if(!empty($button['target'])) target="{{ $button['target'] }}" @endif
                       @if(!empty($button['rel'])) rel="{{ $button['rel'] }}" @endif>
						@if(!empty($button['icon']))
							<i class="{{ $button['icon'] }} me-1"></i>
						@endif
						{{ $button['text'] ?? '' }}
					</a>
				@endforeach
			</div>
		</div>
	@elseif(!empty($buttonText) && !empty($buttonUrl))
		<div class="col-xl-4 col-lg-4 col-md-5 text-md-end mt-3 mt-md-0">
			<a href="{{ $buttonUrl }}" class="{{ $buttonClass ?? 'btn btn-primary' }}">
				@if(!empty($buttonIcon))
					<i class="{{ $buttonIcon }} me-1"></i>
				@endif
				{{ $buttonText }}
			</a>
		</div>
	@endif
</div>
