<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureParentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('parent.login');
        }

        if ($user->role !== User::ROLE_PARENT) {
            return $this->logoutAndRedirect($request, 'A szülői felületre csak szülői szerepkörű felhasználó léphet be.');
        }

        if (! $user->is_active) {
            return $this->logoutAndRedirect($request, 'A felhasználói fiók inaktív, ezért a szülői felület nem érhető el.');
        }

        if (! $user->guardians()->where('active', true)->exists()) {
            return $this->logoutAndRedirect($request, 'Ehhez a felhasználóhoz nincs aktív gondviselői kapcsolat rendelve.');
        }

        return $next($request);
    }

    private function logoutAndRedirect(Request $request, string $message): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('parent.login')
            ->withErrors(['email' => $message]);
    }
}
