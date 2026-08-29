<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealSetting;
use Illuminate\Database\Seeder;

class InstitutionReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $discounts = [
            ['Kedvezmény nélkül', 0],
            ['Rendszeres gyermekvédelmi kedvezmény', 100],
            ['Rendszeres gyermekvédelmi kedvezmény', 50],
            ['Nevelésbe vett gyermek', 100],
            ['Nagycsaládos kedvezmény', 100],
            ['Nagycsaládos kedvezmény', 50],
            ['Egy főre jutó jövedelem alapján meghatározott kedvezmény', 100],
            ['Tartós beteg', 100],
            ['Tartós beteg', 50],
            ['SNI', 50],
            ['Tartós beteg a családban', 100],
        ];

        $restrictions = [
            ['Glutén', DietaryRestriction::TYPE_INTOLERANCE],
            ['Tej', DietaryRestriction::TYPE_INTOLERANCE],
            ['Tojás', DietaryRestriction::TYPE_ALLERGEN],
            ['Mogyoró', DietaryRestriction::TYPE_ALLERGEN],
        ];

        Institution::query()->orderBy('id')->each(function (Institution $institution) use ($discounts, $restrictions) {
            InstitutionMealSetting::firstOrCreate([
                'institution_id' => $institution->id,
            ]);

            foreach ($discounts as $sortOrder => [$name, $percentage]) {
                $discount = DiscountType::firstOrCreate(
                    [
                        'institution_id' => $institution->id,
                        'name' => $name,
                        'percentage' => $percentage,
                    ],
                    [
                        'active' => true,
                        'sort_order' => $sortOrder,
                    ]
                );

                if ($name === 'Kedvezmény nélkül' && $percentage === 0) {
                    Child::where('institution_id', $institution->id)
                        ->whereNull('discount_type_id')
                        ->update(['discount_type_id' => $discount->id]);
                }
            }

            foreach ($restrictions as $sortOrder => [$name, $type]) {
                DietaryRestriction::firstOrCreate(
                    [
                        'institution_id' => $institution->id,
                        'name' => $name,
                        'type' => $type,
                    ],
                    [
                        'active' => true,
                        'sort_order' => $sortOrder,
                    ]
                );
            }
        });
    }
}
