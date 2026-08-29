@php
    $institutions = auth()->user()?->employees()
        ->where('active', true)
        ->with('institution')
        ->get()
        ->pluck('institution.name')
        ->filter()
        ->unique()
        ->values() ?? collect();

    $institutionLabel = match (true) {
        $institutions->isEmpty() => 'Kapcsolt intézmény',
        $institutions->count() === 1 => $institutions->first(),
        default => $institutions->count().' kapcsolt intézmény',
    };
@endphp

<div class="deznav" style="background:linear-gradient(135deg,#12344a 0%,#16445a 25%,#8f3b25 52%,#d94a16 76%,#f27a22 100%);">
    <div class="deznav-scroll">
        <div class="px-3 pt-4 pb-3 border-bottom border-light border-opacity-10" style="margin-bottom:30px;">
            <div class="d-flex align-items-center">
                <div style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;margin-right:12px;">
                    <i class="fa-solid fa-id-badge"></i>
                </div>

                <div class="flex-grow-1">
                    <div style="color:#fff;font-weight:600;font-size:14px;line-height:1.3;">
                        {{ auth()->user()?->name ?? 'Dolgozói felület' }}
                    </div>
                    <div style="color:rgba(255,255,255,.65);font-size:12px;margin-top:2px;">
                        {{ $institutionLabel }}
                    </div>
                </div>
            </div>
        </div>

        <ul class="metismenu" id="menu">
            <li>
                <a class="ai-icon {{ request()->routeIs('employee.dashboard') ? 'mm-active' : '' }}" href="{{ route('employee.dashboard') }}">
                    <i class="flaticon-381-networking"></i>
                    <span class="nav-text">Vezérlőpult</span>
                </a>
            </li>
            @if (Route::has('employee.meal-cancellations'))
                <li>
                    <a class="ai-icon {{ request()->routeIs('employee.meal-cancellations') ? 'mm-active' : '' }}" href="{{ route('employee.meal-cancellations') }}">
                        <i class="fa-solid fa-utensils"></i>
                        <span class="nav-text">Étkezéseim és lemondásaim</span>
                    </a>
                </li>
            @endif
            @if (Route::has('employee.menu-choices.index'))
                <li>
                    <a class="ai-icon {{ request()->routeIs('employee.menu-choices.*') ? 'mm-active' : '' }}" href="{{ route('employee.menu-choices.index') }}">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <span class="nav-text">Menüválasztás</span>
                    </a>
                </li>
            @endif
            @if (Route::has('employee.invoices'))
                <li>
                    <a class="ai-icon {{ request()->routeIs('employee.invoices') ? 'mm-active' : '' }}" href="{{ route('employee.invoices') }}">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span class="nav-text">Számláim</span>
                    </a>
                </li>
            @endif
            @if (Route::has('employee.payments'))
                <li>
                    <a class="ai-icon {{ request()->routeIs('employee.payments') ? 'mm-active' : '' }}" href="{{ route('employee.payments') }}">
                        <i class="fa-solid fa-wallet"></i>
                        <span class="nav-text">Befizetéseim</span>
                    </a>
                </li>
            @endif
            @if (Route::has('employee.account'))
                <li>
                    <a class="ai-icon {{ request()->routeIs('employee.account') ? 'mm-active' : '' }}" href="{{ route('employee.account') }}">
                        <i class="fa-regular fa-user"></i>
                        <span class="nav-text">Fiókom</span>
                    </a>
                </li>
            @endif
        </ul>
    </div>
</div>
