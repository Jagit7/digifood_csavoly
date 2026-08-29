<?php

namespace App\Support;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminInstitutionContext
{
    private const SESSION_INSTITUTION_ID = 'dashboard.selected_institution_id';

    private const SESSION_USER_ID = 'dashboard.selected_institution_user_id';

    private const INSTITUTION_ROLES = [
        User::ROLE_INSTITUTION_ADMIN,
        User::ROLE_INSTITUTION_SECRETARY,
        User::ROLE_KITCHEN,
        User::ROLE_MUNICIPALITY,
    ];

    public function isInstitutionScopedUser(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::INSTITUTION_ROLES, true);
    }

    public function currentInstitution(?User $user): ?Institution
    {
        if (! $this->isInstitutionScopedUser($user)) {
            return $user?->institution;
        }

        $pivotInstitutions = $this->pivotInstitutions($user);
        $selectedInstitutionId = $this->selectedInstitutionIdFor($user);

        if ($selectedInstitutionId !== null) {
            $selectedInstitution = $pivotInstitutions->get($selectedInstitutionId);

            if ($selectedInstitution) {
                $this->persistSelection($user, (int) $selectedInstitution->id);

                return $selectedInstitution;
            }

            $this->clearSelection($user);
        }

        if ($pivotInstitutions->isNotEmpty()) {
            $legacyInstitutionId = $user->institution_id !== null ? (int) $user->institution_id : null;
            $institution = $legacyInstitutionId !== null
                ? $pivotInstitutions->get($legacyInstitutionId)
                : null;

            $institution ??= $pivotInstitutions->first();

            if ($institution) {
                $this->persistSelection($user, (int) $institution->id);
            }

            return $institution;
        }

        if ($selectedInstitutionId !== null) {
            return null;
        }

        $legacyInstitution = $user->institution;

        if ($legacyInstitution !== null) {
            $this->persistSelection($user, (int) $legacyInstitution->id);
        }

        return $legacyInstitution;
    }

    public function availableInstitutions(?User $user): Collection
    {
        if (! $this->isInstitutionScopedUser($user)) {
            return collect($user?->institution ? [$user->institution] : []);
        }

        $institutions = $this->pivotInstitutions($user);

        if ($institutions->isNotEmpty()) {
            return $institutions->values();
        }

        return collect($user->institution ? [$user->institution] : []);
    }

    public function roleFor(?User $user, ?Institution $institution = null): ?string
    {
        if ($user === null) {
            return null;
        }

        if (! $this->isInstitutionScopedUser($user)) {
            return $user->role;
        }

        $institution ??= $this->currentInstitution($user);

        if (! $institution) {
            return $user->role;
        }

        $scopeRole = $this->pivotInstitutions($user)
            ->get($institution->id)?->pivot?->scope_role;

        return is_string($scopeRole) && $scopeRole !== ''
            ? $scopeRole
            : ($user->institution_id === $institution->id ? $user->role : null);
    }

    public function hasRole(?User $user, string ...$roles): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $this->isInstitutionScopedUser($user)) {
            return in_array($user->role, $roles, true);
        }

        $effectiveRole = $this->roleFor($user);

        return $effectiveRole !== null && in_array($effectiveRole, $roles, true);
    }

    public function switchToInstitution(User $user, int $institutionId): ?Institution
    {
        $institution = $this->availableInstitutions($user)
            ->first(fn (Institution $institution) => (int) $institution->id === $institutionId);

        if (! $institution) {
            return null;
        }

        $this->persistSelection($user, $institutionId);

        return $institution;
    }

    public function selectedInstitutionIdFor(User $user): ?int
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        if (! method_exists($request, 'hasSession') || ! $request->hasSession()) {
            return null;
        }

        $session = $request->session();
        $sessionUserId = $session->get(self::SESSION_USER_ID);
        $selectedInstitutionId = $session->get(self::SESSION_INSTITUTION_ID);

        if ((int) $sessionUserId !== (int) $user->id || ! is_numeric($selectedInstitutionId)) {
            return null;
        }

        return (int) $selectedInstitutionId;
    }

    private function persistSelection(User $user, int $institutionId): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = app('request');

        if (! method_exists($request, 'hasSession') || ! $request->hasSession()) {
            return;
        }

        $request->session()->put([
            self::SESSION_USER_ID => (int) $user->id,
            self::SESSION_INSTITUTION_ID => $institutionId,
        ]);
    }

    private function clearSelection(User $user): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = app('request');

        if (! method_exists($request, 'hasSession') || ! $request->hasSession()) {
            return;
        }

        $request->session()->forget([
            self::SESSION_USER_ID,
            self::SESSION_INSTITUTION_ID,
        ]);
    }

    private function pivotInstitutions(User $user): Collection
    {
        return $user->institutions()
            ->get()
            ->keyBy(fn (Institution $institution) => (int) $institution->id);
    }
}
