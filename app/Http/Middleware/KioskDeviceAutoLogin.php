<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Kiosk\KioskDeviceBindingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ha a kioszk böngészője már be van kötve (ld. KioskDeviceBindingService /
 * AuthController::login()), és a látogató még nincs bejelentkezve, ez a
 * middleware csendben bejelentkezteti a hozzá tartozó kioszk-felhasználót -
 * így a gépet naponta nem kell újra e-mail/jelszó gépeléssel indítani.
 *
 * Ez a middleware SOSEM jelentkeztet ki senkit, és nem érint semmilyen
 * fizetési/számlázási funkciót - kizárólag a /kiosk útvonalcsoportban fut,
 * az 'auth' middleware ELŐTT (ld. routes/web.php).
 */
class KioskDeviceAutoLogin
{
    public function __construct(
        private readonly KioskDeviceBindingService $deviceBinding
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $setting = $this->deviceBinding->resolveSettingForRequest($request);

        if ($setting === null) {
            return $next($request);
        }

        $kioskUser = User::query()
            ->where('institution_id', $setting->institution_id)
            ->where('role', User::ROLE_MEAL_KIOSK)
            ->where('is_active', true)
            ->first();

        if ($kioskUser === null) {
            return $next($request);
        }

        Auth::login($kioskUser);
        $request->session()->regenerate();
        $this->deviceBinding->touchLastUsed($setting);

        return $next($request);
    }
}
