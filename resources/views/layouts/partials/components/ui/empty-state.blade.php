<div class="text-center py-5">
	<div class="mb-3">
		<i class="{{ $icon ?? 'fa-regular fa-folder-open' }}" style="font-size:48px;color:#c2c2c2;"></i>
	</div>

	<h5 class="mb-2">{{ $title }}</h5>

	@if(!empty($text))
		<p class="text-muted mb-4">{{ $text }}</p>
	@endif

	@if(!empty($buttonText) && !empty($buttonUrl))
		<a href="{{ $buttonUrl }}" class="btn btn-primary">
			@if(!empty($buttonIcon))
				<i class="{{ $buttonIcon }} me-1"></i>
			@endif
			{{ $buttonText }}
		</a>
	@endif
</div>