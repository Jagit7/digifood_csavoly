<?php

namespace App\Http\Middleware;

use App\Support\AdminInstitutionContext;
use Closure;
use Illuminate\Http\Request;

class EnsureInstitutionContext
{
    public function __construct(
        private readonly AdminInstitutionContext $context
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $this->context->isInstitutionScopedUser($user)) {
            return $next($request);
        }

        $institution = $this->context->currentInstitution($user);

        if (! $institution) {
            abort(403, 'Nincs intézményi hozzáférés ehhez a fiókhoz.');
        }

        $request->attributes->set('currentInstitution', $institution);
        $request->attributes->set('currentInstitutionRole', $this->context->roleFor($user, $institution));

        return $next($request);
    }
}
