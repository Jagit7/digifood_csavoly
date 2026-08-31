@php
	$user = auth()->user();
	$context = app(\App\Support\AdminInstitutionContext::class);
	$role = $user?->contextRole() ?? $user?->role;
	$showInstitutionContext = in_array($role, ['institution_admin', 'institution_secretary', 'kitchen', 'municipality'], true);
	$institution = $showInstitutionContext ? $context->currentInstitution($user) : null;
	$availableInstitutions = $showInstitutionContext ? $context->availableInstitutions($user) : collect();
	$hasMultipleInstitutions = $availableInstitutions->count() > 1;
@endphp

<div class="header">
	<div class="header-content">
		<nav class="navbar navbar-expand">
			<div class="collapse navbar-collapse justify-content-between">
				<div class="header-left">
					<div class="dashboard_bar">
						Kezelőfelület
					</div>
					@if($institution)
						<div class="df-header-institution-switcher" title="{{ $institution->name }}">
							@if($hasMultipleInstitutions)
								<form action="{{ route('dashboard.institution.context.update') }}" method="POST" class="df-header-institution-form">
									@csrf
									<label for="header-institution-switcher" class="visually-hidden">Aktív intézmény</label>
									<div class="df-header-institution-control">
										<span class="df-header-institution-icon" aria-hidden="true">
											<i class="fa-solid fa-school"></i>
										</span>
										<select
											id="header-institution-switcher"
											name="institution_id"
											class="df-header-institution-select"
											title="{{ $institution->name }}"
											onchange="this.form.submit()"
										>
											@foreach($availableInstitutions as $switchableInstitution)
												<option value="{{ $switchableInstitution->id }}" @selected((int) $switchableInstitution->id === (int) $institution->id)>
													{{ $switchableInstitution->name }}
												</option>
											@endforeach
										</select>
									</div>
								</form>
							@else
								<div class="df-header-institution-control df-header-institution-control--static">
									<span class="df-header-institution-icon" aria-hidden="true">
										<i class="fa-solid fa-school"></i>
									</span>
									<span class="df-header-institution-name">{{ $institution->name }}</span>
								</div>
							@endif
						</div>
					@endif
				</div>
				<ul class="navbar-nav header-right">
					<li class="nav-item dropdown notification_dropdown">
						<a class="nav-link bell dz-theme-mode" href="javascript:void(0);">
							<i id="icon-light" class="fas fa-sun"></i>
							<i id="icon-dark" class="fas fa-moon"></i>
						</a>
					</li>
					<li class="nav-item dropdown header-profile">
						<a class="nav-link" href="javascript:void(0)" role="button" data-bs-toggle="dropdown">
							<svg class="header-profile-icon text-primary flex-shrink-0" xmlns="http://www.w3.org/2000/svg"
								width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
								stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
								<circle cx="12" cy="7" r="4"></circle>
							</svg>
							<div class="header-info">
								<span class="text-black"><strong>@greeting</strong></span>
								<p class="fs-12 mb-0">{{ auth()->user()->role_label }}</p>
							</div>
						</a>
						<div class="dropdown-menu dropdown-menu-end">
							<a href="{{ route('dashboard.profile.edit') }}" class="dropdown-item ai-icon">
								<svg id="icon-user1" xmlns="http://www.w3.org/2000/svg" class="text-primary"
									width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
									<circle cx="12" cy="7" r="4"></circle>
								</svg>
								<span class="ms-2">Saját profil</span>
							</a>
							<form method="POST" action="{{ route('auth.logout') }}">
								@csrf
								<button type="submit" class="dropdown-item ai-icon border-0 bg-transparent w-100 text-start">
									<svg id="icon-logout" xmlns="http://www.w3.org/2000/svg" class="text-danger"
										width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
										<polyline points="16 17 21 12 16 7"></polyline>
										<line x1="21" y1="12" x2="9" y2="12"></line>
									</svg>
									<span class="ms-2">Kilépés</span>
								</button>
							</form>
						</div>
					</li>
				</ul>
			</div>
		</nav>
	</div>
</div>
