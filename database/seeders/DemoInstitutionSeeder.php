<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoInstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::updateOrCreate(
            [
                'institution_code' => 'TFO-0703-488',
            ],
            [
                'name' => 'Testvérkék Ferences Óvoda',
                'type' => 'ovoda',

                'address_zip' => '1025',
                'address_city' => 'Budapest',
                'address_line' => 'Szilfa u. 4.',

                'om_identifier' => '034253',

                'contact_name' => 'Tisch Katalin',
                'email' => 'vezeto@testverkek.hu',
                'phone' => '+36 20 519 89 31',

                'billing_name' => 'Testvérkék Ferences Óvoda',
                'billing_tax_number' => '18077689-2-41',
                'billing_zip' => '1025',
                'billing_city' => 'Budapest',
                'billing_address' => 'Szilfa u. 4.',
                'billing_payment_due_days' => 5,

                'active' => true,
            ]
        );

        $user = User::updateOrCreate(
            [
                'email' => 'jagicza.tamas.tanar@edu.kszj.hu',
            ],
            [
                'name' => 'Jagicza Tamás',
                'password' => Hash::make('9040jtJT11'),
                'role' => 'institution_admin',
                'is_active' => true,
            ]
        );

        // kapcsolat létrehozása, ha még nincs
        $user->institutions()->syncWithoutDetaching([
            $institution->id => [
                'scope_role' => 'institution_admin',
            ],
        ]);
    }
}