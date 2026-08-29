<?php

namespace App\Services\EmployeePortal;

use App\Models\InstitutionEmployee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A dolgozói portál "Fiókom" oldala - a
 * App\Services\ParentPortal\ParentAccountService egyszerűsített
 * megfelelője. A szülői mintától eltérően itt nincs számlázási profil
 * (BillingProfile) dimenzió: a dolgozói havi elszámolás számla-adatai
 * közvetlenül az EmployeeMonthlyPaymentStatement rekordba vannak beágyazva,
 * nincs a dolgozó által szerkeszthető, önálló számlázási név/cím. A
 * bankszámla-adatokat (bank_account_holder/bank_account_number) a dolgozó a
 * dolgozói felületen itt sem módosíthatja - azokat csak az intézmény
 * adminisztrátora kezelheti, pontosan úgy, ahogy a szülőknél
 * (ld. ParentAccountService::updatePersonalData() megjegyzését).
 */
class EmployeeAccountService
{
    public function buildPageData(User $user): array
    {
        $employees = $this->activeEmployees($user);
        $primary = $employees->first();
        $institutionNames = $employees->pluck('institution.name')->filter()->unique()->values();

        return [
            'user' => $user,
            'employee' => $primary,
            'institutionNames' => $institutionNames,
            'stats' => [
                'profile_completeness' => $this->profileCompleteness($user, $primary),
                'last_login_value' => $this->formatLastLoginValue($user),
                'last_login_subtitle' => 'Utolsó sikeres belépés',
            ],
            'nonEditableInfo' => 'Az intézményi hozzárendelést és a bankszámla-adatokat az intézmény adminisztrátoránál lehet módosíttatni.',
        ];
    }

    public function updatePersonalData(User $user, array $validated): void
    {
        $employees = $this->activeEmployeesOrFail($user);
        $normalizedEmail = $this->normalizeEmail($validated['email']);
        $name = trim($validated['full_name']);
        $phone = $this->emptyToNull($validated['phone'] ?? null);

        DB::transaction(function () use ($user, $employees, $name, $normalizedEmail, $phone): void {
            $user->forceFill([
                'name' => $name,
                'email' => $normalizedEmail,
                'phone' => $phone,
            ])->save();

            $employees->each(function (InstitutionEmployee $employee) use ($name, $normalizedEmail, $phone): void {
                $employee->fill([
                    'name' => $name,
                    'email' => $normalizedEmail,
                    'phone' => $phone,
                ])->save();
            });
        });
    }

    public function updateAddress(User $user, array $validated): void
    {
        $employee = $this->primaryEmployeeOrFail($user);

        $employee->fill([
            'country' => $this->emptyToNull($validated['country'] ?? null),
            'postal_code' => $this->emptyToNull($validated['postal_code'] ?? null),
            'city' => $this->emptyToNull($validated['city'] ?? null),
            'street_name' => $this->emptyToNull($validated['street_name'] ?? null),
            'street_type' => $this->emptyToNull($validated['street_type'] ?? null),
            'house_number' => $this->emptyToNull($validated['house_number'] ?? null),
            'floor' => $this->emptyToNull($validated['floor'] ?? null),
            'door' => $this->emptyToNull($validated['door'] ?? null),
        ])->save();
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();
    }

    public function profileCompleteness(User $user, ?InstitutionEmployee $employee): int
    {
        $fields = [
            $user->name,
            $user->email,
            $user->phone ?: $employee?->phone,
            $employee?->postal_code,
            $employee?->city,
            $this->employeeAddressLine($employee),
        ];

        $filled = collect($fields)
            ->filter(fn ($value) => filled(trim((string) $value)))
            ->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    private function activeEmployees(User $user): Collection
    {
        return InstitutionEmployee::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->with('institution')
            ->orderBy('name')
            ->get();
    }

    private function activeEmployeesOrFail(User $user): Collection
    {
        $employees = $this->activeEmployees($user);

        if ($employees->isEmpty()) {
            abort(404);
        }

        return $employees;
    }

    private function primaryEmployeeOrFail(User $user): InstitutionEmployee
    {
        $employee = $this->activeEmployees($user)->first();

        if ($employee === null) {
            abort(404);
        }

        return $employee;
    }

    private function employeeAddressLine(?InstitutionEmployee $employee): string
    {
        if ($employee === null) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $this->emptyToNull($employee->street_name),
            $this->emptyToNull($employee->street_type),
            $this->emptyToNull($employee->house_number),
            $this->emptyToNull($employee->floor),
            $this->emptyToNull($employee->door),
        ])));
    }

    private function formatLastLoginValue(User $user): string
    {
        if ($user->last_login_at === null) {
            return 'Nincs adat';
        }

        $lastLogin = $user->last_login_at->timezone(config('app.timezone'));
        $today = now(config('app.timezone'))->startOfDay();

        if ($lastLogin->copy()->startOfDay()->equalTo($today)) {
            return 'Ma, '.$lastLogin->format('H:i');
        }

        return $lastLogin->locale('hu')->isoFormat('YYYY. MMMM D.');
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }
}
