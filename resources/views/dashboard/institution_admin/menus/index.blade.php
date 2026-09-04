@extends('layouts.superadmin')

@section('title', 'Étlapok')

@section('content')

<div class="container-fluid">

	@include('layouts.partials.components.ui.page-header', [
		'title' => 'Étlapok',
		'subtitle' => 'Az intézmény heti, diétás és A/B menüi',
		'buttonText' => 'Új étlap feltöltése',
		'buttonIcon' => 'fa-solid fa-plus',
		'buttonUrl' => route('dashboard.institution.menus.create')
	])

	<div class="row">

		@include('layouts.partials.components.ui.stats-card', [
			'title' => 'Aktív heti étlapok',
			'value' => $activeWeeklyMenus,
			'subtitle' => 'Aktuális vagy jövőbeli heti menük',
			'icon' => 'fa-solid fa-calendar-days',
			'color' => 'green'
		])

		@include('layouts.partials.components.ui.stats-card', [
			'title' => 'Diétás menük',
			'value' => $dietaryMenus,
			'subtitle' => 'Feltöltött diétás étlapok',
			'icon' => 'fa-solid fa-leaf',
			'color' => 'orange'
		])

		@include('layouts.partials.components.ui.stats-card', [
			'title' => 'A/B menük',
			'value' => $abMenus,
			'subtitle' => 'Választható heti menük',
			'icon' => 'fa-solid fa-utensils',
			'color' => 'blue'
		])

		@include('layouts.partials.components.ui.stats-card', [
			'title' => 'Következő feltöltés',
			'value' => $nextWeekLabel,
			'subtitle' => 'Következő feltöltendő hét',
			'icon' => 'fa-solid fa-cloud-arrow-up',
			'color' => 'purple',
			'size' => 'small'
		])

	</div>

	<div class="card">
		<div class="card-header d-flex justify-content-between align-items-center">
			<h4 class="card-title mb-0">Feltöltött étlapok</h4>
		</div>

		<div class="card-body">

			@if($menus->count())

				<div class="table-responsive">
					<table class="table table-hover table-responsive-md align-middle">

						<thead>
							<tr>
								<th width="70">#</th>
								<th>Hét</th>
								<th>Típus</th>
								<th>Fájl</th>
								<th>Feltöltő</th>
								<th>Feltöltve</th>
								<th>Állapot</th>
								<th width="140" class="text-end">Műveletek</th>
							</tr>
						</thead>

						<tbody>

							@foreach($menus as $menu)

								<tr>
									<td>{{ ($menus->firstItem() ?? 0) + $loop->index }}</td>

									<td>
										<strong>
											{{ $menu->week_start?->format('Y.m.d.') }}
											–
											{{ $menu->week_end?->format('Y.m.d.') }}
										</strong>

										@if($menu->has_saturday)
											<br>
											<small class="text-warning">
												Szombat:
												{{ $menu->saturday_date?->format('Y.m.d.') }}
											</small>
										@endif
									</td>

									<td>

										@switch($menu->type)

											@case('weekly')
												<span class="badge badge-success light">Heti étlap</span>
												@break

											@case('dietary')
												<span class="badge badge-warning light">Diétás</span>
												@break

											@case('ab')
												<span class="badge badge-primary light">A/B menü</span>
												@break

											@default
												<span class="badge badge-secondary light">
													{{ $menu->type }}
												</span>

										@endswitch

									</td>

									<td>

										<strong>{{ $menu->file_name }}</strong>

										@if($menu->file_size)
											<br>
											<small class="text-muted">
												{{ number_format($menu->file_size / 1024 / 1024,2,',',' ') }} MB
											</small>
										@endif

									</td>

									<td>
										{{ $menu->creator?->name ?? '-' }}
									</td>

									<td>
										{{ $menu->created_at?->format('Y.m.d. H:i') }}
									</td>

									<td>

										@if($menu->active)
											<span class="badge badge-success light">
												Aktív
											</span>
										@else
											<span class="badge badge-danger light">
												Inaktív
											</span>
										@endif

									</td>

									<td class="text-end">
										<a href="{{ route('dashboard.institution.menus.show', $menu) }}"
										class="btn btn-xs btn-outline-primary"
										title="Megtekintés"
										aria-label="Megtekintés">
											<i class="fa-solid fa-eye"></i>
										</a>

										<a href="{{ route('dashboard.institution.menus.download', $menu) }}"
										class="btn btn-xs btn-outline-secondary"
										title="Letöltés">
											<i class="fa fa-download"></i>
										</a>

										<a href="{{ route('dashboard.institution.menus.edit', $menu) }}"
										class="btn btn-xs btn-outline-warning"
										title="Szerkesztés">
											<i class="fa fa-edit"></i>
										</a>

										<form action="{{ route('dashboard.institution.menus.destroy', $menu) }}"
											method="POST"
											class="d-inline delete-form">
											@csrf
											@method('DELETE')

											<button type="submit"
													class="btn btn-xs btn-outline-danger"
													title="Törlés">
												<i class="fa fa-trash"></i>
											</button>
										</form>
									</td>

								</tr>

							@endforeach

						</tbody>

					</table>
				</div>

				<div class="mt-4">
					{{ $menus->links('vendor.pagination.digifood') }}
				</div>

			@else

				@include('layouts.partials.components.ui.empty-state', [
					'icon' => 'fa-solid fa-utensils',
					'title' => 'Még nincs feltöltött étlap',
					'text' => 'Töltsd fel az első heti, diétás vagy A/B étlapot.',
					'buttonText' => 'Új étlap feltöltése',
					'buttonIcon' => 'fa-solid fa-plus',
					'buttonUrl' => route('dashboard.institution.menus.create')
				])

			@endif

		</div>
	</div>

</div>

@endsection
