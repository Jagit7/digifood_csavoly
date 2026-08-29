<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eddig semmi nem akadályozta meg adatbázis-szinten, hogy egy
 * intézménynek egyszerre több "alapértelmezett" (is_default = true)
 * menücsomagja legyen - ez eddig kizárólag alkalmazás-szinten
 * (InstitutionMealPackageController) volt kezelve, ami egy közvetlen
 * DB-írás, hibás jövőbeli kód-változtatás vagy versenyhelyzet esetén
 * könnyen megsérülhetett volna. Ha ez megtörténik, "az" alapértelmezett
 * csomagot kereső lekérdezések (pl.
 * MealEligibilityService/PaymentObligationCalculatorService
 * ->where('is_default', true)->first()) egy nem determinisztikus,
 * tetszőleges csomagot adnának vissza - vagyis egy gyerek
 * véletlenszerűen más árat/étkezéstípust kaphatna hónapról hónapra.
 *
 * MySQL/MariaDB nem támogat közvetlenül "partial unique index"-et
 * (ellentétben pl. a PostgreSQL-lel), ezért egy generált oszlopot
 * (default_institution_id) használunk: ez az institution_id értéket
 * veszi fel, ha is_default = true, egyébként NULL - a NULL értékek
 * pedig nem ütköznek egymással egy unique indexben (több sor lehet
 * NULL), így intézményenként legfeljebb EGY is_default = true sor
 * engedélyezett.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mielőtt a unique constraint-et hozzáadnánk: ha valamilyen okból
        // már most több alapértelmezett csomag lenne ugyanahhoz az
        // intézményhez, intézményenként csak a legkisebb ID-jűt hagyjuk
        // alapértelmezettnek - így a migráció biztosan lefut, és a
        // jövőben már nem alakulhat ki újra ilyen állapot.
        $duplicateInstitutionIds = DB::table('institution_meal_packages')
            ->select('institution_id')
            ->where('is_default', true)
            ->groupBy('institution_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('institution_id');

        foreach ($duplicateInstitutionIds as $institutionId) {
            $keepId = DB::table('institution_meal_packages')
                ->where('institution_id', $institutionId)
                ->where('is_default', true)
                ->orderBy('id')
                ->value('id');

            DB::table('institution_meal_packages')
                ->where('institution_id', $institutionId)
                ->where('is_default', true)
                ->where('id', '!=', $keepId)
                ->update(['is_default' => false]);
        }

        if (! Schema::hasColumn('institution_meal_packages', 'default_institution_id')) {
            Schema::table('institution_meal_packages', function (Blueprint $table) {
                $table->unsignedBigInteger('default_institution_id')
                    ->storedAs('CASE WHEN is_default THEN institution_id ELSE NULL END')
                    ->after('is_default');
            });
        }

        Schema::table('institution_meal_packages', function (Blueprint $table) {
            $table->unique('default_institution_id', 'institution_meal_packages_one_default_per_institution');
        });
    }

    public function down(): void
    {
        Schema::table('institution_meal_packages', function (Blueprint $table) {
            $table->dropUnique('institution_meal_packages_one_default_per_institution');
            $table->dropColumn('default_institution_id');
        });
    }
};
