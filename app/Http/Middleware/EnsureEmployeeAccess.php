<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to(route('employee.login', [], false));
        }

        if ($user->role !== User::ROLE_EMPLOYEE) {
            return $this->logoutAndRedirect($request, 'A dolgozói felületre csak dolgozói szerepkörű felhasználó léphet be.');
        }

        if (! $user->is_active) {
            return $this->logoutAndRedirect($request, 'A felhasználói fiók inaktív, ezért a dolgozói felület nem érhető el.');
        }

        if (! $user->employees()->where('active', true)->exists()) {
            return $this->logoutAndRedirect($request, 'Ehhez a felhasználóhoz nincs aktív dolgozói kapcsolat rendelve.');
        }

        return $next($request);
    }

    private function logoutAndRedirect(Request $request, string $message): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->to(route('employee.login', [], false))
            ->withErrors(['email' => $message]);
    }
}
