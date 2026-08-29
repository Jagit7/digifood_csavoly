<?php

namespace Database\Seeders;

use App\Models\MealType;
use Illuminate\Database\Seeder;

class MealTypeSeeder extends Seeder
{
    public function run(): void
    {
        $mealTypes = [
            ['code' => 'breakfast', 'name' => 'Reggeli', 'default_order' => 1],
            ['code' => 'morning_snack', 'name' => 'Tízórai', 'default_order' => 2],
            ['code' => 'lunch', 'name' => 'Ebéd', 'default_order' => 3],
            ['code' => 'afternoon_snack', 'name' => 'Uzsonna', 'default_order' => 4],
            ['code' => 'dinner', 'name' => 'Vacsora', 'default_order' => 5],
        ];

        foreach ($mealTypes as $mealType) {
            MealType::updateOrCreate(
                ['code' => $mealType['code']],
                [
                    'name' => $mealType['name'],
                    'default_order' => $mealType['default_order'],
                ]
            );
        }
    }
}
