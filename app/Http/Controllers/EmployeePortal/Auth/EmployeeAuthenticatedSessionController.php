<?php

namespace App\Http\Controllers\EmployeePortal\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmployeeAuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->role === User::ROLE_EMPLOYEE && Auth::user()?->is_active) {
            return redirect()->route('employee.dashboard');
        }

        return view('employee.auth.login');
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

        if (! $user || $user->role !== User::ROLE_EMPLOYEE) {
            return $this->reject($request, 'A dolgozói felületre csak dolgozói szerepkörű felhasználó léphet be.');
        }

        if (! $user->is_active) {
            return $this->reject($request, 'A felhasználói fiók inaktív. Kérjük, vegye fel a kapcsolatot az intézménnyel.');
        }

        if (! $user->employees()->where('active', true)->exists()) {
            return $this->reject($request, 'Ehhez a felhasználóhoz nincs aktív dolgozói kapcsolat rendelve.');
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route('employee.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Szándékos kijelentkezés után mindig a fő (általános) bejelentkező
        // oldalra irányítunk, nem a dolgozói login URL-re - ugyanaz a
        // felhasználó gyakran szülői jogviszonnyal is rendelkezik, és így
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
            ->route('employee.login')
            ->withErrors(['email' => $message])
            ->withInput($request->only('email'));
    }
}
