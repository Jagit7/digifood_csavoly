<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentParentPortalSeeder extends Seeder
{
    public const EMAIL = 'dev.parent@digifood.local';
    public const PASSWORD = 'Parent12345!';

    public function run(): void
    {
        $child = Child::query()
            ->where('active', true)
            ->orderBy('id')
            ->firstOrFail();

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Fejlesztoi Szulo',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_PARENT,
                'institution_id' => $child->institution_id,
                'is_active' => true,
            ]
        );

        $guardian = Guardian::updateOrCreate(
            [
                'institution_id' => $child->institution_id,
                'email' => self::EMAIL,
            ],
            [
                'user_id' => $user->id,
                'last_name' => 'Fejlesztoi',
                'first_name' => 'Szulo',
                'phone' => '+3615550101',
                'source_type' => 'manual',
                'active' => true,
            ]
        );

        $guardian->children()->sync([
            $child->id => [
                'relationship_type' => 'Édesanya',
                'is_legal_representative' => true,
                'has_no_custody' => false,
                'is_emergency_contact' => true,
                'receives_family_allowance' => true,
            ],
        ]);
    }
}
