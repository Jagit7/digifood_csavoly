<?php

namespace App\Services\EmployeePortal;

use App\Mail\EmployeeAccountActivationMail;
use App\Models\EmployeeAccountActivationToken;
use App\Models\InstitutionEmployee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * 1:1 mása az App\Services\ParentPortal\ParentAccountActivationService-nek,
 * Guardian -> InstitutionEmployee, ROLE_PARENT -> ROLE_EMPLOYEE cserével. Ld.
 * ott a részletes megjegyzéseket (debug-link mód, manuális link élettartama,
 * ütköző user_id kezelése stb.) - itt csak a dolgozó-specifikus eltéréseket
 * jelöljük.
 */
class EmployeeAccountActivationService
{
    private const EXPIRES_IN_MINUTES = 60;

    private const MANUAL_LINK_EXPIRES_IN_MINUTES = 60 * 24 * 7;

    public function requestActivation(string $email): array
    {
        $email = $this->normalizeEmail($email);

        [$employees, $errorStatus] = $this->resolveActivatableEmployees($email);

        if ($errorStatus !== null) {
            return $errorStatus;
        }

        $this->invalidateActiveTokens($email);

        $plainToken = bin2hex(random_bytes(32));
        $token = EmployeeAccountActivationToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ]);

        $activationUrl = route('employee.activation.show', ['token' => $plainToken]);
        $mailPayload = $this->buildMailPayload($employees, $activationUrl);
        $mailable = new EmployeeAccountActivationMail($mailPayload);

        if ($this->shouldSendRealActivationEmail()) {
            Mail::to($email)->send($mailable);
        }

        return [
            'status' => 'activation_created',
            'token_id' => $token->id,
            'activation_url' => $activationUrl,
            'email' => $email,
            'debug_link_available' => $this->isDebugLinkMode(),
        ];
    }

    public function createManualActivationLink(string $email): array
    {
        $email = $this->normalizeEmail($email);

        [, $errorStatus] = $this->resolveActivatableEmployees($email);

        if ($errorStatus !== null) {
            return $errorStatus;
        }

        $this->invalidateActiveTokens($email);

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(self::MANUAL_LINK_EXPIRES_IN_MINUTES);

        EmployeeAccountActivationToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'status' => 'activation_created',
            'activation_url' => route('employee.activation.show', ['token' => $plainToken]),
            'expires_at' => $expiresAt,
            'email' => $email,
        ];
    }

    /**
     * @return array{0: ?Collection, 1: ?array}
     */
    private function resolveActivatableEmployees(string $email): array
    {
        $employees = $this->activationEmployees($email);

        if ($employees->isEmpty()) {
            return [null, ['status' => 'employee_not_found']];
        }

        $users = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        if ($users->contains(fn (User $user) => $user->role !== User::ROLE_EMPLOYEE)) {
            return [null, ['status' => 'non_employee_user_exists']];
        }

        $activeEmployeeUser = $users->first(function (User $user) use ($employees) {
            if ($user->role !== User::ROLE_EMPLOYEE || ! $user->is_active) {
                return false;
            }

            return $employees->contains(fn (InstitutionEmployee $employee) => (int) $employee->user_id === (int) $user->id)
                || $user->employees()->where('active', true)->exists();
        });

        if ($activeEmployeeUser !== null) {
            return [null, [
                'status' => 'already_active_employee',
                'login_url' => route('employee.login'),
            ]];
        }

        return [$employees, null];
    }

    private function shouldSendRealActivationEmail(): bool
    {
        return ! $this->isDebugLinkMode() && (bool) config('mail.employee_activation_delivery_enabled', true);
    }

    private function isDebugLinkMode(): bool
    {
        return app()->runningUnitTests() && (bool) config('mail.employee_activation_debug_link_for_tests', false);
    }

    public function activationViewData(string $plainToken): ?array
    {
        $token = $this->findValidToken($plainToken);

        if ($token === null) {
            return null;
        }

        $employees = $this->activationEmployees($token->email);

        if ($employees->isEmpty()) {
            return null;
        }

        return [
            'email' => $token->email,
            'expires_at' => $token->expires_at,
            'institution_names' => $employees->pluck('institution.name')->filter()->unique()->values(),
            'display_name' => $this->resolveDisplayName($employees),
        ];
    }

    public function activate(string $plainToken, string $password): User
    {
        return DB::transaction(function () use ($plainToken, $password): User {
            $token = EmployeeAccountActivationToken::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if ($token === null || $token->used_at !== null || $token->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => 'Az aktiváló link érvénytelen vagy lejárt.',
                ]);
            }

            $email = $token->email;
            $employees = InstitutionEmployee::query()
                ->with('institution')
                ->where('active', true)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            if ($employees->isEmpty()) {
                throw ValidationException::withMessages([
                    'email' => 'A megadott e-mail címhez nem található aktiválható dolgozói hozzáférés.',
                ]);
            }

            $users = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->get();

            if ($users->contains(fn (User $user) => $user->role !== User::ROLE_EMPLOYEE)) {
                throw ValidationException::withMessages([
                    'email' => 'Ehhez az e-mail címhez már más típusú felhasználói fiók tartozik. Kérjük, vegye fel a kapcsolatot az intézménnyel.',
                ]);
            }

            $user = $users->firstWhere('role', User::ROLE_EMPLOYEE);
            $displayName = $this->resolveDisplayName($employees);
            $primaryEmployee = $employees->sortBy('id')->first();

            if ($user === null) {
                $user = User::forceCreate([
                    'name' => $displayName,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_EMPLOYEE,
                    'institution_id' => $primaryEmployee?->institution_id,
                    'is_active' => true,
                    'invited_at' => now(),
                    'accepted_invitation_at' => now(),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'name' => $displayName,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'accepted_invitation_at' => $user->accepted_invitation_at ?? now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'invited_at' => $user->invited_at ?? now(),
                ])->save();
            }

            $assignableEmployees = $employees->filter(fn (InstitutionEmployee $employee) => $employee->user_id === null || (int) $employee->user_id === (int) $user->id);
            $conflictingEmployees = $employees->reject(fn (InstitutionEmployee $employee) => $employee->user_id === null || (int) $employee->user_id === (int) $user->id);

            if ($assignableEmployees->isEmpty()) {
                Log::warning('Employee activation blocked because every matching employee belongs to another user.', [
                    'email' => $email,
                    'employee_ids' => $employees->pluck('id')->all(),
                    'conflicting_user_ids' => $conflictingEmployees->pluck('user_id')->filter()->unique()->values()->all(),
                ]);

                throw ValidationException::withMessages([
                    'email' => 'A megadott e-mail címhez tartozó dolgozói rekordok jelenleg nem aktiválhatók. Kérjük, vegye fel a kapcsolatot az intézménnyel.',
                ]);
            }

            InstitutionEmployee::query()
                ->whereIn('id', $assignableEmployees->pluck('id'))
                ->update(['user_id' => $user->id]);

            if ($conflictingEmployees->isNotEmpty()) {
                Log::warning('Employee activation skipped conflicting employees already linked to another user.', [
                    'email' => $email,
                    'activated_user_id' => $user->id,
                    'employee_ids' => $conflictingEmployees->pluck('id')->all(),
                    'conflicting_user_ids' => $conflictingEmployees->pluck('user_id')->filter()->unique()->values()->all(),
                ]);
            }

            $token->forceFill([
                'used_at' => now(),
            ])->save();

            EmployeeAccountActivationToken::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->where('id', '!=', $token->id)
                ->update(['used_at' => now()]);

            return $user->fresh();
        });
    }

    public function loginActivatedEmployee(User $user): void
    {
        Auth::login($user);
    }

    private function findValidToken(string $plainToken): ?EmployeeAccountActivationToken
    {
        $token = EmployeeAccountActivationToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || $token->used_at !== null || $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    private function activationEmployees(string $email): Collection
    {
        return InstitutionEmployee::query()
            ->with('institution')
            ->where('active', true)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->get();
    }

    private function buildMailPayload(Collection $employees, string $activationUrl): array
    {
        $institutionNames = $employees->pluck('institution.name')->filter()->unique()->values();

        return [
            'recipientName' => $this->resolveDisplayName($employees),
            'institutionName' => $institutionNames->count() === 1
                ? $institutionNames->first()
                : 'Digifood partnerintézmények',
            'activationUrl' => $activationUrl,
            'expiresInMinutes' => self::EXPIRES_IN_MINUTES,
            'institutionNames' => $institutionNames,
        ];
    }

    private function invalidateActiveTokens(string $email): void
    {
        EmployeeAccountActivationToken::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function resolveDisplayName(Collection $employees): string
    {
        return (string) ($employees->pluck('name')->filter()->first() ?: 'Dolgozó');
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
