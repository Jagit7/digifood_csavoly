<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_meal_packages', function (Blueprint $table) {
            // Csak 'custom_price' árképzési mód esetén kötelező/kitöltött -
            // ld. InstitutionMealPackage::PRICING_MODE_CUSTOM_PRICE és a
            // hozzá tartozó validáció az InstitutionMealPackageController-ben.
            $table->unsignedBigInteger('custom_price')->nullable()->after('pricing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('institution_meal_packages', function (Blueprint $table) {
            $table->dropColumn('custom_price');
        });
    }
};
