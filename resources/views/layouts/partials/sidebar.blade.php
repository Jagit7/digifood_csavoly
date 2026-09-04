@php
    $user = auth()->user();
    $context = app(\App\Support\AdminInstitutionContext::class);
    $role = $user?->contextRole() ?? $user?->role;
    $institution = null;
    $availableInstitutions = collect();
    $roleLabels = [
        \App\Models\User::ROLE_INSTITUTION_ADMIN => 'Intézményi admin',
        \App\Models\User::ROLE_INSTITUTION_SECRETARY => 'Intézményi titkár',
        \App\Models\User::ROLE_KITCHEN => 'Konyha',
        \App\Models\User::ROLE_MUNICIPALITY => 'Önkormányzat',
    ];
    $todayDate = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
    $isTodayCountsRoute = request()->routeIs('dashboard.institution.daily.today-counts*');
    $isDietaryChildrenRoute = request()->routeIs('dashboard.institution.daily.dietary-children*');
    $isSuperAdminParentsRoute = request()->routeIs('dashboard.superadmin.parents.*');
    $isSuperAdminChildrenRoute = request()->routeIs('dashboard.superadmin.children.*');
    $isSuperAdminRolesRoute = request()->routeIs('dashboard.superadmin.roles.*');
    $isSuperAdminInstitutionAdminDevicesRoute = request()->routeIs('dashboard.superadmin.institution-admin-devices.*');
    $isSuperAdminReportsRoute = request()->routeIs('dashboard.superadmin.reports.*');
    $isSuperAdminAuditLogsRoute = request()->routeIs('dashboard.superadmin.audit-logs.*');
    $isSuperAdminBillingPartnersRoute = request()->routeIs('dashboard.billing-partners.*');
    $isSuperAdminPartnerMonthlyBillingsRoute = request()->routeIs('dashboard.partner-monthly-billings.*');
    $isSuperAdminSaasBillingSummaryRoute = request()->routeIs('dashboard.saas-billing-summary.*');
    $isSuperAdminRevenueOverviewRoute = request()->routeIs('dashboard.revenue-overview.*');
    $isSuperAdminFinanceRoute = $isSuperAdminPartnerMonthlyBillingsRoute || $isSuperAdminBillingPartnersRoute || $isSuperAdminSaasBillingSummaryRoute || $isSuperAdminRevenueOverviewRoute;
    $isSuperAdminUsersRoute = $isSuperAdminParentsRoute || $isSuperAdminChildrenRoute || $isSuperAdminRolesRoute;
    $isSuperAdminSystemRoute = $isSuperAdminAuditLogsRoute;
    $isInstitutionReportsRoute = request()->routeIs('dashboard.institution.reports.*');
    $isInstitutionEmployeesRoute = request()->routeIs('dashboard.institution.employees.*');
    $isInstitutionEmployeeMealCancellationsRoute = request()->routeIs('dashboard.institution.employees.meal-cancellations.*');
    $isInstitutionMealCancellationsRoute = request()->routeIs('dashboard.institution.meal-cancellations.index')
        || request()->routeIs('dashboard.institution.meal-cancellations.create')
        || request()->routeIs('dashboard.institution.meal-cancellations.store')
        || request()->routeIs('dashboard.institution.meal-cancellations.destroy')
        || request()->routeIs('dashboard.institution.meal-cancellations.recurring.*');
    $isInstitutionBulkMealCancellationsRoute = request()->routeIs('dashboard.institution.meal-cancellations.bulk.*');
    $isInstitutionCommunicationRoute = request()->routeIs('dashboard.institution.communication.emails.*')
        || request()->routeIs('dashboard.institution.communication.parent-activation-invite.*')
        || request()->routeIs('dashboard.institution.communication.employee-activation-invite.*');
    $isDailyMenuOpen = $isTodayCountsRoute
        || $isDietaryChildrenRoute
        || request()->routeIs('dashboard.institution.meal-cancellations.*');
    $dashboardHomeRoute = match ($role) {
        'super_admin' => route('dashboard.superadmin'),
        'institution_admin', 'institution_secretary', 'municipality', 'kitchen' => route('dashboard.institution.home'),
        'parent' => route('parent.dashboard'),
        default => url('/'),
    };

    if (in_array($role, ['institution_admin', 'institution_secretary', 'kitchen', 'municipality'])) {
        $institution = $context->currentInstitution($user);
        $availableInstitutions = $context->availableInstitutions($user);
    }

    // Ugyanaz a jel, mint amit a naptár/napi nézeteknél is használunk
    // (ld. SchoolBreakController): az "A/B + diétás menük" menüpont csak
    // akkor jelenjen meg, ha az intézménynél ténylegesen van aktív A-B
    // menüterv - egyébként a menüpont félrevezető azoknál az
    // intézményeknél, akik nem használják ezt a funkciót.
    $hasActiveAbMenuPlan = $institution
        ? \App\Models\AbMenuPlan::where('institution_id', $institution->id)->where('active', true)->exists()
        : false;
@endphp

<div class="deznav" style="background:linear-gradient(135deg,#12344a 0%,#16445a 25%,#8f3b25 52%,#d94a16 76%,#f27a22 100%);">
    <div class="deznav-scroll">

        {{-- INTÉZMÉNY FEJLÉC --}}
        {{-- INTÉZMÉNY FEJLÉC --}}
        @if($institution)
            <div class="px-3 pt-4 pb-3 border-bottom border-light border-opacity-10" style="margin-bottom:30px;">
                <div class="d-flex align-items-center">
                    <div style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;margin-right:12px;">
                        <i class="fa-solid fa-building"></i>
                    </div>

                    <div class="flex-grow-1">
                        <div style="color:#fff;font-weight:600;font-size:14px;line-height:1.3;">
                            {{ $institution->name }}
                        </div>
                        <div style="color:rgba(255,255,255,.65);font-size:12px;margin-top:2px;">
                            {{ $roleLabels[$role] ?? 'Kezelőfelület' }}
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="px-3 pt-4 pb-3 border-bottom border-light border-opacity-10" style="margin-bottom:30px;">
                <div class="d-flex align-items-center">
                    <div style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;margin-right:12px;">
                        <i class="fa-solid fa-building"></i>
                    </div>

                    <div class="flex-grow-1">
                        <div style="color:#fff;font-weight:600;font-size:14px;line-height:1.3;">
                            Superadmin
                        </div>
                        <div style="color:rgba(255,255,255,.65);font-size:12px;margin-top:2px;">
                            Kezelőfelület
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <ul class="metismenu" id="menu">

            {{-- SUPERADMIN --}}
            @if($role === 'super_admin')

                <li class="{{ $isDailyMenuOpen ? 'mm-active' : '' }}">
                    <a class="ai-icon" href="{{ $dashboardHomeRoute }}">
                        <i class="flaticon-381-networking"></i>
                        <span class="nav-text">Áttekintés</span>
                    </a>
                </li>

                <li class="{{ $isDailyMenuOpen ? 'mm-active' : '' }}">
                    <a class="has-arrow ai-icon {{ $isDailyMenuOpen ? 'mm-active' : '' }}" href="javascript:void(0);" aria-expanded="{{ $isDailyMenuOpen ? 'true' : 'false' }}">
                        <i class="fa-regular fa-building fw-bold"></i>
                        <span class="nav-text">Intézmények</span>
                    </a>
                    <ul aria-expanded="{{ $isDailyMenuOpen ? 'true' : 'false' }}">
                        <li><a href="{{ route('dashboard.institutions.index') }}">Intézménylista</a></li>
                        <li><a href="{{ route('dashboard.institutions.create') }}">Új intézmény felvétele</a></li>
                        <li><a href="{{ route('dashboard.institutions.trashed') }}">Törölt intézmények</a></li>
                        <li><a href="{{ route('dashboard.admin-access.index') }}">Intézményi felhasználók</a></li>
                        <li><a href="{{ route('dashboard.admin-access.invite.create') }}">Felhasználó meghívása</a></li>
                    </ul>
                </li>

                <li class="{{ $isSuperAdminUsersRoute ? 'mm-active' : '' }}">
                    <a class="has-arrow ai-icon {{ $isSuperAdminUsersRoute ? 'mm-active' : '' }}" href="javascript:void(0);" aria-expanded="{{ $isSuperAdminUsersRoute ? 'true' : 'false' }}">
                        <i class="fa-regular fa-user fw-bold"></i>
                        <span class="nav-text">Felhasználók</span>
                    </a>
                    <ul aria-expanded="{{ $isSuperAdminUsersRoute ? 'true' : 'false' }}">
                        <li><a href="{{ route('dashboard.superadmin.parents.index') }}" class="{{ $isSuperAdminParentsRoute ? 'mm-active' : '' }}">Szülők</a></li>
                        <li><a href="{{ route('dashboard.superadmin.children.index') }}" class="{{ $isSuperAdminChildrenRoute ? 'mm-active' : '' }}">Gyerekek / tanulók</a></li>
                        <li><a href="{{ route('dashboard.admin-access.index') }}">Intézményi felhasználók</a></li>
                        <li><a href="{{ route('dashboard.superadmin.roles.index') }}" class="{{ $isSuperAdminRolesRoute ? 'mm-active' : '' }}">Szerepkörök &amp; jogosultságok</a></li>
                        <li><a href="{{ route('dashboard.superadmin.institution-admin-devices.index') }}" class="{{ $isSuperAdminInstitutionAdminDevicesRoute ? 'mm-active' : '' }}">Eszköz-jóváhagyások</a></li>
                    </ul>
                </li>

                <li class="{{ $isSuperAdminFinanceRoute ? 'mm-active' : '' }}">
                    <a class="has-arrow ai-icon {{ $isSuperAdminFinanceRoute ? 'mm-active' : '' }}" href="javascript:void(0);" aria-expanded="{{ $isSuperAdminFinanceRoute ? 'true' : 'false' }}">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span class="nav-text">Pénzügy &amp; számlázás</span>
                    </a>
                    <ul aria-expanded="{{ $isSuperAdminFinanceRoute ? 'true' : 'false' }}">
                        <li><a href="{{ route('dashboard.revenue-overview.index') }}" class="{{ $isSuperAdminRevenueOverviewRoute ? 'mm-active' : '' }}">Bevétel áttekintés</a></li>
                        <li><a href="{{ route('dashboard.partner-monthly-billings.index') }}" class="{{ $isSuperAdminPartnerMonthlyBillingsRoute ? 'mm-active' : '' }}">Partneri ügyfél számlázás</a></li>
                        <li><a href="{{ route('dashboard.billing-partners.index') }}" class="{{ $isSuperAdminBillingPartnersRoute ? 'mm-active' : '' }}">Számlázási partnerek</a></li>
                        <li><a href="{{ route('dashboard.saas-billing-summary.index') }}" class="{{ $isSuperAdminSaasBillingSummaryRoute ? 'mm-active' : '' }}">Közvetlen intézményi számlázás (SaaS)</a></li>
                    </ul>
                </li>

                <li class="{{ $isSuperAdminReportsRoute ? 'mm-active' : '' }}">
                    <a class="ai-icon" href="{{ route('dashboard.superadmin.reports.index') }}">
                        <i class="fa-solid fa-chart-column"></i>
                        <span class="nav-text">Riportok</span>
                    </a>
                </li>

                <li class="{{ $isSuperAdminSystemRoute ? 'mm-active' : '' }}">
                    <a class="has-arrow ai-icon {{ $isSuperAdminSystemRoute ? 'mm-active' : '' }}" href="javascript:void(0);" aria-expanded="{{ $isSuperAdminSystemRoute ? 'true' : 'false' }}">
                        <i class="flaticon-381-settings-2"></i>
                        <span class="nav-text">Rendszer</span>
                    </a>
                    <ul aria-expanded="{{ $isSuperAdminSystemRoute ? 'true' : 'false' }}">
                        <li><a href="{{ route('dashboard.superadmin.audit-logs.index') }}" class="{{ $isSuperAdminAuditLogsRoute ? 'mm-active' : '' }}">Műveleti napló</a></li>
                    </ul>
                </li>

            @endif

            {{-- INTÉZMÉNYI ADMIN / KONYHA / ÖNKORMÁNYZAT --}}
            @if(in_array($role, ['institution_admin', 'institution_secretary', 'kitchen', 'municipality']))

                <li>
                    <a class="ai-icon" href="{{ route('dashboard.institution.home') }}">
                        <i class="flaticon-381-networking"></i>
                        <span class="nav-text">Áttekintés</span>
                    </a>
                </li>

                <li class="{{ $isDailyMenuOpen ? 'mm-active' : '' }}">
                    <a class="has-arrow ai-icon {{ $isDailyMenuOpen ? 'mm-active' : '' }}" href="javascript:void(0);" aria-expanded="{{ $isDailyMenuOpen ? 'true' : 'false' }}">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span class="nav-text">Napi működés</span>
                    </a>
                    <ul aria-expanded="{{ $isDailyMenuOpen ? 'true' : 'false' }}">
                        <li>
                            <a href="{{ route('dashboard.institution.daily.today-counts', ['date' => $todayDate]) }}"
                               class="{{ $isTodayCountsRoute ? 'mm-active' : '' }}">
                                Mai létszám
                            </a>
                        </li>
                        @if($role === 'institution_admin')
                            <li>
                                <a href="{{ route('dashboard.institution.meal-cancellations.index') }}"
                                   class="{{ $isInstitutionMealCancellationsRoute ? 'mm-active' : '' }}">
                                    Egyéni lemondások
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.meal-cancellations.bulk.create') }}"
                                   class="{{ $isInstitutionBulkMealCancellationsRoute ? 'mm-active' : '' }}">
                                    Csoportos lemondás
                                </a>
                            </li>
                        @else
                            <li><a href="javascript:void(0);">Lemondások</a></li>
                        @endif
                        <li>
                            <a href="{{ route('dashboard.institution.daily.dietary-children', ['date' => $todayDate]) }}"
                               class="{{ $isDietaryChildrenRoute ? 'mm-active' : '' }}">
                                Diétás étkezők
                            </a>
                        </li>
                    </ul>
                </li>

                @if($role === 'institution_admin')
                    <li>
                        <a class="has-arrow ai-icon" href="javascript:void(0);" aria-expanded="false">
                            <i class="fa-solid fa-calendar-check"></i>
                            <span class="nav-text">Tanítási napok</span>
                        </a>
                        <ul aria-expanded="false">
                            <li>
                                <a href="{{ route('dashboard.institution.school-breaks.index') }}">
                                    Iskolai szünetek
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.school-breaks.working-days.index') }}">
                                    Tanítási / óvodai szombatok
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.class-cancellations.index') }}">
                                    {{ $institution?->type === 'ovoda' ? 'Csoportszintű lemondások' : 'Osztályszintű lemondások' }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.school-breaks.calendar') }}">
                                    Intézményi naptár
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="{{ $isInstitutionCommunicationRoute ? 'mm-active' : '' }}">
                        <a class="has-arrow ai-icon {{ $isInstitutionCommunicationRoute ? 'mm-active' : '' }}"
                           href="javascript:void(0);"
                           aria-expanded="{{ $isInstitutionCommunicationRoute ? 'true' : 'false' }}">
                            <i class="fa-solid fa-envelope"></i>
                            <span class="nav-text">Kommunikáció</span>
                        </a>
                        <ul aria-expanded="{{ $isInstitutionCommunicationRoute ? 'true' : 'false' }}">
                            <li>
                                <a href="{{ route('dashboard.institution.communication.emails.create') }}"
                                   class="{{ request()->routeIs('dashboard.institution.communication.emails.create') ? 'mm-active' : '' }}">
                                    E-mail küldés
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.communication.emails.index') }}"
                                   class="{{ request()->routeIs('dashboard.institution.communication.emails.index') || request()->routeIs('dashboard.institution.communication.emails.show') ? 'mm-active' : '' }}">
                                    Küldési előzmények
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.communication.parent-activation-invite.index') }}"
                                   class="{{ request()->routeIs('dashboard.institution.communication.parent-activation-invite.*') ? 'mm-active' : '' }}">
                                    Fiókaktiválási meghívó
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.communication.employee-activation-invite.index') }}"
                                   class="{{ request()->routeIs('dashboard.institution.communication.employee-activation-invite.*') ? 'mm-active' : '' }}">
                                    Dolgozói fiókaktiválási meghívó
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if(in_array($role, ['institution_admin', 'kitchen']))
                    <li>
                        <a class="has-arrow ai-icon" href="javascript:void(0);" aria-expanded="false">
                            <i class="fa-solid fa-utensils"></i>
                            <span class="nav-text">Étlap &amp; menük</span>
                        </a>
                        <ul aria-expanded="false">
                            <li><a href="{{ route('dashboard.institution.menus.index') }}">Heti étlapok</a></li>
                            <li><a href="{{ route('dashboard.institution.menus.create') }}">Új étlap feltöltése</a></li>
                            @if($hasActiveAbMenuPlan)
                                <li><a href="{{ route('dashboard.institution.menus.ab.index') }}">A/B + diétás menük</a></li>
                            @endif
                            <li><a href="{{ route('dashboard.institution.menus.choices.index') }}">Menüválasztások</a></li>
                        </ul>
                    </li>
                @endif

                @if(in_array($role, ['institution_admin', 'institution_secretary']))
                    <li>
                        <a class="has-arrow ai-icon" href="javascript:void(0);" aria-expanded="false">
                            <i class="fa-solid fa-file-import"></i>
                            <span class="nav-text">Adatimport</span>
                        </a>
                        <ul aria-expanded="false">
                            <li>
                                <a href="{{ route('dashboard.institution.imports.index') }}">
                                    Import áttekintés
                                </a>
                            </li>
                            @if($institution?->type === 'iskola')
                                <li>
                                    <a href="{{ route('dashboard.institution.imports.school-standard.create') }}">
                                        Iskolai Excel sablon
                                    </a>
                                </li>
                                <li><a href="javascript:void(0);" class="text-muted">Egyedi iskolai Excel</a></li>
                            @elseif($institution?->type === 'ovoda')
                                <li><a href="javascript:void(0);" class="text-muted">Óvodai Excel</a></li>
                            @endif
                        </ul>
                    </li>

                    <li>
                        <a class="has-arrow ai-icon" href="javascript:void(0);" aria-expanded="false">
                            <i class="fa-solid fa-users"></i>
                            <span class="nav-text">Személyek</span>
                        </a>
                        <ul aria-expanded="false">
                            <li>
                                <a href="{{ route('dashboard.institution.children.index') }}">
                                    Gyermeklista
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.children.meal-participants.index') }}">
                                    Étkező gyermekek
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.parents.index') }}">
                                    Szülők / gondviselők
                                </a>
                            </li>
                            @if($role === 'institution_admin')
                                <li>
                                    <a href="{{ route('dashboard.institution.billing-addresses.index') }}" class="{{ request()->routeIs('dashboard.institution.billing-addresses.*') ? 'mm-active' : '' }}">
                                        Alapadatok (indulás előtt)
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('dashboard.institution.employees.index') }}" class="{{ $isInstitutionEmployeesRoute ? 'mm-active' : '' }}">
                                        Dolgozók
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('dashboard.institution.employees.meal-cancellations.index') }}" class="{{ $isInstitutionEmployeeMealCancellationsRoute ? 'mm-active' : '' }}">
                                        Dolgozói lemondások
                                    </a>
                                </li>
                            @endif
                            <li>
                                <a href="{{ route('dashboard.institution.class-groups.index') }}">
                                    {{ $institution?->type === 'ovoda' ? 'Csoportok' : 'Osztályok' }}
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.institution.children.barcodes.index') }}">
                                    Vonalkódos kártyák
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif

                @if(in_array($role, ['institution_admin', 'municipality']))
                    <li>
                        <a class="has-arrow ai-icon" href="javascript:void(0);" aria-expanded="false">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                            <span class="nav-text">Pénzügyek</span>
                        </a>
                        <ul aria-expanded="false">
                            @if($role === 'institution_admin')
                                <li><a href="{{ route('dashboard.institution.finance.payments') }}">Befizetések</a></li>
                            @endif
                            <li><a href="{{ route('dashboard.institution.finance.debts') }}">Tartozások</a></li>
                            <li><a href="{{ route('dashboard.institution.finance.invoices') }}">Számlák</a></li>
                            @if($role === 'institution_admin')
                                <li>
                                    <a href="{{ route('dashboard.institution.payment-obligations.index') }}">
                                        Fizetési kötelezettségek
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('dashboard.institution.employee-payment-obligations.index') }}">
                                        Dolgozói elszámolások
                                    </a>
                                </li>
                                <li><a href="{{ route('dashboard.institution.finance.help') }}">Pénzügyi súgó</a></li>
                            @endif
                            <li><a href="{{ route('dashboard.institution.finance.exports') }}">Exportok</a></li>
                        </ul>
                    </li>
                @endif

                @if($role === 'institution_admin')
                    <li>
                        <a class="has-arrow ai-icon" href="javascript:void(0);" aria-expanded="false">
                            <i class="fa-regular fa-building fw-bold"></i>
                            <span class="nav-text">Intézmény</span>
                        </a>
                        <ul aria-expanded="false">
                            <li><a href="{{ route('dashboard.institution.profile.edit') }}">Profil</a></li>
                            <li><a href="{{ route('dashboard.institution.settings.invoicing.edit') }}">Számlázás és fizetés</a></li>
                            <li><a href="{{ route('dashboard.institution.settings.edit') }}">Beállítások</a></li>
                            <li><a href="{{ route('dashboard.institution.daily-headcount-emails.index') }}">Napi létszám e-mailek</a></li>
                            <li><a href="{{ route('dashboard.institution.meal-types.index') }}">Étkezés beállításai</a></li>
                            <li><a href="{{ route('dashboard.institution.meal-packages.index') }}">Menücsomagok</a></li>
                            <li>
                                <a href="{{ route('dashboard.institution.reference-data.index') }}">
                                    Kedvezmények és szabályok
                                </a>
                            </li>
                            <li><a href="{{ route('dashboard.institution.contacts.index') }}">Kapcsolattartók</a></li>
                        </ul>
                    </li>
                @endif

                <li class="{{ $isInstitutionReportsRoute ? 'mm-active' : '' }}">
                    <a href="{{ route('dashboard.institution.reports.index') }}" class="ai-icon">
                        <i class="flaticon-381-television"></i>
                        <span class="nav-text">Riportok</span>
                    </a>
                </li>

                <li class="{{ request()->routeIs('dashboard.institution.handbook') ? 'mm-active' : '' }}">
                    <a href="{{ route('dashboard.institution.handbook') }}" class="ai-icon">
                        <i class="fa-solid fa-book"></i>
                        <span class="nav-text">Kézikönyv</span>
                    </a>
                </li>

            @endif

        </ul>
    </div>
</div>
