<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'info@digifood.hu',
            ],
            [
                'name' => 'Digifood Superadmin',
                'password' => Hash::make('9040jtJT11'),
                'role' => User::ROLE_SUPER_ADMIN,
                'institution_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}