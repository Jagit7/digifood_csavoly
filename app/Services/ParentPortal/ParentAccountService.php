<?php

namespace App\Services\ParentPortal;

use App\Models\BillingProfile;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ParentAccountService
{
    public function __construct(
        private readonly ParentGuardianService $guardianService
    ) {}

    public function buildPageData(User $user): array
    {
        $guardian = $this->primaryGuardian($user);
        $billingProfile = $guardian?->billingProfiles()
            ->where('active', true)
            ->latest('id')
            ->first();
        $childrenCount = $this->guardianService->linkedChildrenCount($user);
        $profileCompleteness = $this->profileCompleteness($user, $guardian);
        $billingComplete = $this->isBillingComplete($billingProfile);

        return [
            'user' => $user,
            'guardian' => $guardian,
            'billingProfile' => $billingProfile,
            'billingSameAsAddress' => $this->billingSameAsAddress($guardian, $billingProfile),
            'stats' => [
                'children_count' => $childrenCount,
                'children_label' => $childrenCount === 1 ? '1 gyermek' : $childrenCount.' gyermek',
                'profile_completeness' => $profileCompleteness,
                'billing_status' => $billingComplete ? 'Kitöltve' : 'Hiányos',
                'billing_status_subtitle' => 'Számlázási profil',
                'last_login_value' => $this->formatLastLoginValue($user),
                'last_login_subtitle' => 'Utolsó sikeres belépés',
            ],
            'readOnlyNameNotice' => null,
            'nonEditableInfo' => 'A kapcsolt gyermekek, az intézményi hozzárendelés és a jogosultságok módosítását az intézmény adminisztrátoránál lehet kérni.',
        ];
    }

    public function updatePersonalData(User $user, array $validated): void
    {
        // A primaryGuardianOrFail() hívás itt megmarad, hogy 404-et adjon,
        // ha a felhasználóhoz egyáltalán nincs gondviselő rekord rendelve.
        $this->primaryGuardianOrFail($user);
        $guardians = $this->guardianService->activeGuardians($user);
        $normalizedEmail = $this->normalizeEmail($validated['email']);
        [$firstName, $lastName] = $this->splitFullName($validated['full_name']);

        // A bankszámla-adatokat a szülő a szülői felületen nem módosíthatja
        // (csak megtekintheti), ezért ez a metódus szándékosan nem hívja meg
        // a Guardian::syncBankAccountData()-t - a módosítást csak az
        // intézmény adminisztrátora végezheti (lásd
        // Dashboard\InstitutionAdmin\ParentController).
        DB::transaction(function () use ($user, $guardians, $validated, $normalizedEmail, $firstName, $lastName): void {
            $user->forceFill([
                'name' => trim($validated['full_name']),
                'email' => $normalizedEmail,
                'phone' => $this->emptyToNull($validated['phone'] ?? null),
            ])->save();

            $guardians->each(function (Guardian $linkedGuardian) use ($firstName, $lastName, $normalizedEmail, $validated): void {
                $linkedGuardian->fill([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $normalizedEmail,
                    'phone' => $this->emptyToNull($validated['phone'] ?? null),
                ])->save();

                $this->syncBillingProfileEmail($linkedGuardian, $normalizedEmail);
            });
        });
    }

    public function updateAddress(User $user, array $validated): void
    {
        $guardian = $this->primaryGuardianOrFail($user);

        $guardian->fill([
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

    public function updateBillingData(User $user, array $validated): void
    {
        $guardian = $this->primaryGuardianOrFail($user);
        $profile = $guardian->billingProfiles()
            ->where('active', true)
            ->latest('id')
            ->first() ?? new BillingProfile([
                'institution_id' => $guardian->institution_id,
                'guardian_id' => $guardian->id,
                'payer_type' => 'guardian',
                'active' => true,
            ]);

        $billingSameAsAddress = (bool) ($validated['billing_same_as_address'] ?? false);
        $postalCode = $billingSameAsAddress ? $guardian->postal_code : ($validated['billing_postal_code'] ?? null);
        $city = $billingSameAsAddress ? $guardian->city : ($validated['billing_city'] ?? null);
        $address = $billingSameAsAddress ? $this->guardianAddressLine($guardian) : ($validated['billing_address'] ?? null);

        // A számlázási nevet és az adószámot a szülő a szülői felületen nem
        // módosíthatja - ezt a két mezőt szándékosan nem vesszük át a
        // $validated tömbből, azok kezelése kizárólag az intézmény
        // adminisztrátorának feladata (ld. Dashboard\InstitutionAdmin\ParentController).
        // Egy még nem létező profil első mentésekor a gondviselő nevét
        // használjuk alapértelmezett számlázási névként.
        $billingName = $profile->billing_name ?: $guardian->full_name;

        $profile->fill([
            'institution_id' => $guardian->institution_id,
            'guardian_id' => $guardian->id,
            'payer_type' => 'guardian',
            'billing_name' => $billingName,
            'postal_code' => $this->emptyToNull($postalCode),
            'city' => $this->emptyToNull($city),
            'address' => $this->emptyToNull($address),
            'email' => $user->email,
            'active' => true,
        ])->save();
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();
    }

    public function profileCompleteness(User $user, ?Guardian $guardian): int
    {
        $fields = [
            $user->name,
            $user->email,
            $user->phone ?: $guardian?->phone,
            $guardian?->postal_code,
            $guardian?->city,
            $this->guardianAddressLine($guardian),
        ];

        $filled = collect($fields)
            ->filter(fn ($value) => filled(trim((string) $value)))
            ->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    public function isBillingComplete(?BillingProfile $billingProfile): bool
    {
        if ($billingProfile === null) {
            return false;
        }

        return collect([
            $billingProfile->billing_name,
            $billingProfile->postal_code,
            $billingProfile->city,
            $billingProfile->address,
        ])->every(fn ($value) => filled(trim((string) $value)));
    }

    public function billingSameAsAddress(?Guardian $guardian, ?BillingProfile $billingProfile): bool
    {
        if ($guardian === null || $billingProfile === null) {
            return false;
        }

        return $this->emptyToNull($guardian->postal_code) === $this->emptyToNull($billingProfile->postal_code)
            && $this->emptyToNull($guardian->city) === $this->emptyToNull($billingProfile->city)
            && $this->emptyToNull($this->guardianAddressLine($guardian)) === $this->emptyToNull($billingProfile->address);
    }

    public function primaryGuardian(?User $user): ?Guardian
    {
        return $user === null ? null : $this->guardianService->primaryGuardian($user);
    }

    private function primaryGuardianOrFail(User $user): Guardian
    {
        return $this->primaryGuardian($user) ?? abort(404);
    }

    private function syncBillingProfileEmail(Guardian $guardian, string $email): void
    {
        $guardian->billingProfiles()
            ->where('active', true)
            ->get()
            ->each(function (BillingProfile $profile) use ($email): void {
                $profile->fill(['email' => $email])->save();
            });
    }

    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) <= 1) {
            return [$parts[0] ?? trim($fullName), ''];
        }

        $firstName = array_pop($parts);
        $lastName = trim(implode(' ', $parts));

        return [$firstName, $lastName];
    }

    private function guardianAddressLine(?Guardian $guardian): string
    {
        if ($guardian === null) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $this->emptyToNull($guardian->street_name),
            $this->emptyToNull($guardian->street_type),
            $this->emptyToNull($guardian->house_number),
            $this->emptyToNull($guardian->floor),
            $this->emptyToNull($guardian->door),
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
