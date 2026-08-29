<div class="{{ $colClass ?? 'col-xl-3 col-lg-6 col-md-6' }} mb-4 df-stat-col">
	<div class="card df-stat-card df-stat-{{ $color ?? 'blue' }} h-100">
		<div class="card-body {{ $bodyClass ?? '' }}">
			<div class="{{ $contentClass ?? '' }}">
				<span class="opacity-75">{{ $title ?? '' }}</span>

				@if(($size ?? 'normal') === 'small')
					<h4 class="text-white mt-2 mb-1">{{ $value ?? 0 }}</h4>
				@else
					<h2 class="text-white mt-2 mb-1">{{ $value ?? 0 }}</h2>
				@endif

				@if(!empty($subtitle))
					<small class="opacity-75">{{ $subtitle }}</small>
				@endif
			</div>

			<i class="{{ $icon ?? 'fa-solid fa-chart-bar' }} df-stat-icon {{ $iconClass ?? '' }}"></i>
		</div>
	</div>
</div>
