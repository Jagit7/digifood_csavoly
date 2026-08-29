<?php

namespace App\Services\Children;

use App\Models\BillingProfile;
use App\Models\Child;
use Illuminate\Support\Collection;

class ChildGuardianPresentationService
{
    public function applyGuardianDisplayOrder($query): void
    {
        $query->orderByDesc('child_guardian.is_legal_representative')
            ->orderBy('guardians.id');
    }

    public function applyCurrentPrimaryBillingConstraint($query, int $institutionId, ?string $today = null): void
    {
        $today ??= now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();

        $query->where('billing_profiles.institution_id', $institutionId)
            ->where('billing_profiles.active', true)
            ->where('billing_profile_child.is_primary', true)
            ->where(function ($inner) use ($today) {
                $inner->whereNull('billing_profile_child.valid_from')
                    ->orWhereDate('billing_profile_child.valid_from', '<=', $today);
            })
            ->where(function ($inner) use ($today) {
                $inner->whereNull('billing_profile_child.valid_to')
                    ->orWhereDate('billing_profile_child.valid_to', '>=', $today);
            });
    }

    public function decorateChildren(Collection $children, int $institutionId, ?string $today = null): Collection
    {
        return $children->map(function (Child $child) use ($institutionId, $today) {
            return $this->decorateChild($child, $institutionId, $today);
        });
    }

    public function decorateChild(Child $child, int $institutionId, ?string $today = null): Child
    {
        $today ??= now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();

        $guardians = ($child->guardians ?? collect())->values();
        $guardian1 = $guardians->get(0);
        $guardian2 = $guardians->get(1);
        $billingProfile = $this->resolveCurrentPrimaryBillingProfile($child, $institutionId, $today);
        $billingGuardian = $billingProfile?->guardian;
        $billingGuardianSlot = null;
        $billingGuardianNote = null;

        if ($billingGuardian !== null) {
            if ($guardian1 && (int) $guardian1->id === (int) $billingGuardian->id) {
                $billingGuardianSlot = 1;
            } elseif ($guardian2 && (int) $guardian2->id === (int) $billingGuardian->id) {
                $billingGuardianSlot = 2;
            } elseif (! $guardians->contains(fn ($guardian) => (int) $guardian->id === (int) $billingGuardian->id)) {
                $billingGuardianNote = 'A számlázási profilhoz tartozó gondviselő nincs a gyermekhez kapcsolva.';
            } else {
                $billingGuardianNote = 'A számlázási profilhoz tartozó gondviselő nem a megjelenített első két gondviselő egyike.';
            }
        }

        $child->setAttribute('display_guardian_1', $guardian1);
        $child->setAttribute('display_guardian_2', $guardian2);
        $child->setAttribute('display_billing_profile', $billingProfile);
        $child->setAttribute('display_billing_guardian', $billingGuardian);
        $child->setAttribute('display_billing_guardian_slot', $billingGuardianSlot);
        $child->setAttribute('display_billing_guardian_note', $billingGuardianNote);

        return $child;
    }

    private function resolveCurrentPrimaryBillingProfile(Child $child, int $institutionId, string $today): ?BillingProfile
    {
        $profiles = $child->billingProfiles ?? collect();

        return $profiles
            ->first(fn (BillingProfile $profile) => $this->isCurrentPrimaryBillingProfile($profile, $institutionId, $today));
    }

    private function isCurrentPrimaryBillingProfile(BillingProfile $profile, int $institutionId, string $today): bool
    {
        $validFrom = $this->normalizePivotDate($profile->pivot?->valid_from);
        $validTo = $this->normalizePivotDate($profile->pivot?->valid_to);

        return (int) $profile->institution_id === $institutionId
            && (bool) $profile->active
            && (bool) ($profile->pivot?->is_primary ?? false)
            && ($validFrom === '' || $validFrom <= $today)
            && ($validTo === '' || $validTo >= $today);
    }

    private function normalizePivotDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) ($value ?? ''));
    }
}
