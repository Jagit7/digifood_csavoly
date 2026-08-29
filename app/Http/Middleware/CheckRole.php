<?php

namespace App\Http\Middleware;

use App\Support\AdminInstitutionContext;
use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function __construct(
        private readonly AdminInstitutionContext $context
    ) {}

    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $this->context->hasRole($user, ...$roles)) {
            abort(403, 'Nincs jogosultsága az oldal megtekintéséhez.');
        }

        return $next($request);
    }
}
