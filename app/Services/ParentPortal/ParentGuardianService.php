<?php

namespace App\Services\ParentPortal;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ParentGuardianService
{
    public function activeGuardiansQuery(User $user): HasMany
    {
        return $user->guardians()
            ->where('active', true);
    }

    public function activeGuardians(User $user): Collection
    {
        return $this->activeGuardiansQuery($user)
            ->orderBy('id')
            ->get();
    }

    public function primaryGuardian(?User $user): ?Guardian
    {
        if ($user === null) {
            return null;
        }

        return $this->activeGuardiansQuery($user)
            ->orderBy('id')
            ->first();
    }

    public function linkedChildrenCount(User $user): int
    {
        $guardianIds = $this->activeGuardiansQuery($user)->pluck('guardians.id');

        if ($guardianIds->isEmpty()) {
            return 0;
        }

        return Child::query()
            ->where('active', true)
            ->whereHas('guardians', fn ($query) => $query
                ->whereIn('guardians.id', $guardianIds)
                ->where('guardians.active', true))
            ->distinct('children.id')
            ->count('children.id');
    }
}
