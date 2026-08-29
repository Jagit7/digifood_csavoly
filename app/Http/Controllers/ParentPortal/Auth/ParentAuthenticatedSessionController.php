<?php

namespace App\Http\Controllers\ParentPortal\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ParentAuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->role === User::ROLE_PARENT && Auth::user()?->is_active) {
            return redirect()->route('parent.dashboard');
        }

        return view('parent.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors(['email' => 'Túl sok sikertelen belépési kísérlet. Kérjük, próbáld újra kb. '.ceil($seconds / 60).' perc múlva.'])
                ->withInput();
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors(['email' => 'Hibás belépési adatok.'])
                ->withInput();
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user || $user->role !== User::ROLE_PARENT) {
            return $this->reject($request, 'A szülői felületre csak szülői szerepkörű felhasználó léphet be.');
        }

        if (! $user->is_active) {
            return $this->reject($request, 'A felhasználói fiók inaktív. Kérjük, vegye fel a kapcsolatot az intézménnyel.');
        }

        if (! $user->guardians()->where('active', true)->exists()) {
            return $this->reject($request, 'Ehhez a felhasználóhoz nincs aktív gondviselői kapcsolat rendelve.');
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route('parent.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Szándékos kijelentkezés után mindig a fő (általános) bejelentkező
        // oldalra irányítunk, nem a szülői login URL-re - ugyanaz a
        // felhasználó gyakran dolgozói jogviszonnyal is rendelkezik, és így
        // nem kell külön URL-t átírnia ahhoz, hogy a másik szerepkörével
        // léphessen be.
        return redirect()->route('auth.login');
    }

    private function reject(Request $request, string $message): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('parent.login')
            ->withErrors(['email' => $message])
            ->withInput($request->only('email'));
    }
}
