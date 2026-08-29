@php
    $connectedGuardianInstitutions = auth()->user()?->guardians()
        ->where('active', true)
        ->with('institution.setting')
        ->get()
        ->pluck('institution')
        ->filter() ?? collect();

    $institutions = $connectedGuardianInstitutions
        ->pluck('name')
        ->filter()
        ->unique()
        ->values();

    $institutionLabel = match (true) {
        $institutions->isEmpty() => 'Kapcsolt intézmény',
        $institutions->count() === 1 => $institutions->first(),
        default => $institutions->count().' kapcsolt intézmény',
    };

    // Felhasználói kérés: ha egyetlen kapcsolt intézménynél sincs
    // bekapcsolva a számlázás / kártyás fizetés, a hozzájuk tartozó
    // menüpontok ne jelenjenek meg. Egy szülőnek elvileg több
    // intézményben is lehet gyermeke, ezért ANY (van-e legalább egy
    // olyan intézmény, ahol be van kapcsolva) a helyes logika - ha akár
    // egyetlen intézményhez is releváns lenne a menüpont, az mindenkinek
    // látszik, mert az adott aloldal amúgy is csak a releváns
    // gyermekekre/intézményre szűr.
    $anyInvoicingEnabled = $connectedGuardianInstitutions->contains(fn ($institution) => (bool) ($institution->setting?->invoicing_enabled ?? false));
    $anyCardPaymentEnabled = $connectedGuardianInstitutions->contains(fn ($institution) => (bool) ($institution->setting?->card_payment_enabled ?? false));
    $showInvoicesMenu = $anyInvoicingEnabled;
    $showPaymentsMenu = $anyInvoicingEnabled || $anyCardPaymentEnabled;

    // Felhasználói kérés: a "Menüválasztás" menüpont csak akkor
    // jelenjen meg, ha legalább az egyik kapcsolt intézménynél
    // ténylegesen aktív (elindított, még nyitott vagy lezárt, de
    // tételekkel rendelkező) A/B menüterv van - ugyanazt a lekérdezést
    // használjuk, amivel a ParentMenuChoiceController is eldönti, hogy
    // van-e egyáltalán megjeleníthető szekció (ld.
    // AbMenuSelectionService::relevantPlanForInstitution()), hogy a
    // menüpont és a mögötte lévő oldal tartalma sose menjen szét.
    $abMenuSelectionService = app(\App\Services\Meals\AbMenuSelectionService::class);
    $showMenuChoicesMenu = $connectedGuardianInstitutions->contains(
        fn ($institution) => $abMenuSelectionService->relevantPlanForInstitution($institution->id) !== null
    );
@endphp

<div class="deznav" style="background:linear-gradient(135deg,#12344a 0%,#16445a 25%,#8f3b25 52%,#d94a16 76%,#f27a22 100%);">
    <div class="deznav-scroll">
        <div class="px-3 pt-4 pb-3 border-bottom border-light border-opacity-10" style="margin-bottom:30px;">
            <div class="d-flex align-items-center">
                <div style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;margin-right:12px;">
                    <i class="fa-solid fa-user-group"></i>
                </div>

                <div class="flex-grow-1">
                    <div style="color:#fff;font-weight:600;font-size:14px;line-height:1.3;">
                        {{ auth()->user()?->name ?? 'Szülői felület' }}
                    </div>
                    <div style="color:rgba(255,255,255,.65);font-size:12px;margin-top:2px;">
                        {{ $institutionLabel }}
                    </div>
                </div>
            </div>
        </div>

        <ul class="metismenu" id="menu">
            <li>
                <a class="ai-icon {{ request()->routeIs('parent.dashboard') ? 'mm-active' : '' }}" href="{{ route('parent.dashboard') }}">
                    <i class="flaticon-381-networking"></i>
                    <span class="nav-text">Vezérlőpult</span>
                </a>
            </li>
            <li>
                <a class="ai-icon {{ request()->routeIs('parent.children.*') ? 'mm-active' : '' }}" href="{{ route('parent.children.index') }}">
                    <i class="fa-solid fa-users"></i>
                    <span class="nav-text">Gyermekeim</span>
                </a>
            </li>
            <li>
                <a class="ai-icon {{ request()->routeIs('parent.meal-cancellations') ? 'mm-active' : '' }}" href="{{ route('parent.meal-cancellations') }}">
                    <i class="fa-solid fa-utensils"></i>
                    <span class="nav-text">Lemondások</span>
                </a>
            </li>
            @if($showMenuChoicesMenu)
                <li>
                    <a class="ai-icon {{ request()->routeIs('parent.menu-choices.*') ? 'mm-active' : '' }}" href="{{ route('parent.menu-choices.index') }}">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <span class="nav-text">Menüválasztás</span>
                    </a>
                </li>
            @endif
            <li>
                <a class="ai-icon {{ request()->routeIs('parent.menus.*') ? 'mm-active' : '' }}" href="{{ route('parent.menus.index') }}">
                    <i class="fa-solid fa-file-lines"></i>
                    <span class="nav-text">Feltöltött étlapok</span>
                </a>
            </li>
            <li>
                <a class="ai-icon {{ request()->routeIs('parent.monthly-settlements.*') || request()->routeIs('parent.statements') ? 'mm-active' : '' }}" href="{{ route('parent.monthly-settlements.index') }}">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span class="nav-text">Havi elszámolások</span>
                </a>
            </li>
            @if($showPaymentsMenu)
                <li>
                    <a class="ai-icon {{ request()->routeIs('parent.payments') ? 'mm-active' : '' }}" href="{{ route('parent.payments') }}">
                        <i class="fa-solid fa-wallet"></i>
                        <span class="nav-text">Befizetések</span>
                    </a>
                </li>
            @endif
            @if($showInvoicesMenu)
                <li>
                    <a class="ai-icon {{ request()->routeIs('parent.invoices') ? 'mm-active' : '' }}" href="{{ route('parent.invoices') }}">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span class="nav-text">Számlák</span>
                    </a>
                </li>
            @endif
            <li>
                <a class="ai-icon {{ request()->routeIs('parent.account') ? 'mm-active' : '' }}" href="{{ route('parent.account') }}">
                    <i class="fa-regular fa-user"></i>
                    <span class="nav-text">Fiókom</span>
                </a>
            </li>
            <li>
                <a class="ai-icon {{ request()->routeIs('parent.handbook') ? 'mm-active' : '' }}" href="{{ route('parent.handbook') }}">
                    <i class="fa-solid fa-book"></i>
                    <span class="nav-text">Kézikönyv</span>
                </a>
            </li>
        </ul>
    </div>
</div>
