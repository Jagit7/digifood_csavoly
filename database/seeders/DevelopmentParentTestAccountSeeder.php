<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentParentTestAccountSeeder extends Seeder
{
    public const NAME = 'Teszt Szülő';
    public const EMAIL = 'szulo.teszt@digifood.test';
    public const PASSWORD = 'Teszt1234!';

    public function run(): void
    {
        $user = User::withTrashed()->firstOrNew([
            'email' => self::EMAIL,
        ]);

        if ($user->trashed()) {
            $user->restore();
        }

        $guardian = $this->findExistingGuardian($user);
        $child = $this->resolveChild($guardian);

        $user->fill([
            'name' => self::NAME,
            'password' => Hash::make(self::PASSWORD),
            'role' => User::ROLE_PARENT,
            'institution_id' => $child->institution_id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->save();

        if (! $guardian) {
            $guardian = new Guardian();
        }

        $guardian->fill([
            'institution_id' => $child->institution_id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szülő',
            'email' => self::EMAIL,
            'phone' => '+3615550102',
            'source_type' => 'manual',
            'active' => true,
        ]);
        $guardian->save();

        Guardian::query()
            ->where('user_id', $user->id)
            ->whereKeyNot($guardian->id)
            ->update([
                'active' => false,
            ]);

        $guardian->children()->sync([
            $child->id => [
                'relationship_type' => 'Édesanya',
                'is_legal_representative' => true,
                'has_no_custody' => false,
                'is_emergency_contact' => true,
                'receives_family_allowance' => true,
            ],
        ]);

        $this->command?->info(sprintf(
            'Teszt szülői fiók kész: %s (%s) -> %s [child_id=%d]',
            self::NAME,
            self::EMAIL,
            $child->name,
            $child->id
        ));
    }

    private function findExistingGuardian(User $user): ?Guardian
    {
        return Guardian::query()
            ->when($user->exists, function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('email', self::EMAIL);
            }, function ($query) {
                $query->where('email', self::EMAIL);
            })
            ->orderBy('id')
            ->first();
    }

    private function resolveChild(?Guardian $guardian): Child
    {
        if ($guardian) {
            $existingChild = $guardian->children()
                ->where('children.active', true)
                ->whereHas('mealSettings', $this->activeMealSettingsScope())
                ->whereHas('monthlyPaymentStatements', $this->currentYearStatementsScope())
                ->orderBy('children.id')
                ->first();

            if ($existingChild) {
                return $existingChild;
            }
        }

        return $this->selectChild();
    }

    private function selectChild(): Child
    {
        $child = Child::query()
            ->where('active', true)
            ->whereHas('mealSettings', $this->activeMealSettingsScope())
            ->whereHas('monthlyPaymentStatements', $this->currentYearStatementsScope())
            ->withCount([
                'guardians',
                'mealSettings as active_meal_settings_count' => $this->activeMealSettingsScope(),
                'monthlyPaymentStatements as current_year_statements_count' => $this->currentYearStatementsScope(),
            ])
            ->orderByDesc('current_year_statements_count')
            ->orderByDesc('active_meal_settings_count')
            ->orderBy('guardians_count')
            ->orderBy('id')
            ->first();

        if ($child) {
            return $child;
        }

        return Child::query()
            ->where('active', true)
            ->orderBy('id')
            ->firstOrFail();
    }

    private function activeMealSettingsScope(): \Closure
    {
        return function ($query) {
            $today = now()->toDateString();

            $query->whereDate('valid_from', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('valid_to')
                        ->orWhereDate('valid_to', '>=', $today);
                });
        };
    }

    private function currentYearStatementsScope(): \Closure
    {
        return function ($query) {
            $query->where('year', '>=', (int) now()->year);
        };
    }
}
