<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A dolgozói (tanári) A/B menüválasztás előkészítő lépése. A menu_choices
 * tábla eddig kizárólag child_id-hoz volt köthető (NOT NULL, FK a children
 * táblára) - ez a migráció ugyanazt a mintát követi, mint a
 * student_meal_settings táblánál már bevált eater_type/eater_id megoldás
 * (ld. 2026_08_11_140000_add_eater_columns_to_student_meal_settings_table),
 * de ITT SZÁNDÉKOSAN TISZTÁN ADDITÍV: nem ír át és nem tölt vissza semmilyen
 * meglévő sort, a gyermekek jelenlegi menüválasztásai és az őket kiszolgáló
 * kód (AbMenuSelectionService, ParentMenuChoiceController, admin
 * MenuChoiceController, kimutatások/e-mail értesítők) VÁLTOZATLANUL a
 * child_id oszlopot használják tovább - egyetlen gyermek-sorhoz sem nyúl.
 * Az új eater_type/eater_id oszlopok kizárólag az ÚJ, még ezután megírandó
 * dolgozói A/B menüválasztás funkcióhoz kellenek, ahol child_id NULL marad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_choices', function (Blueprint $table) {
            $table->string('eater_type', 50)->nullable()->after('child_id');
            $table->unsignedBigInteger('eater_id')->nullable()->after('eater_type');
        });

        // A child_id-t nullable-lé kell tenni, hogy egy dolgozói választás
        // sorában üresen maradhasson (a foreign key-t megtartva a
        // gyermekekre vonatkozó soroknál).
        Schema::table('menu_choices', function (Blueprint $table) {
            $table->dropForeign(['child_id']);
        });

        Schema::table('menu_choices', function (Blueprint $table) {
            $table->unsignedBigInteger('child_id')->nullable()->change();
            $table->foreign('child_id', 'menu_choices_child_id_foreign')
                ->references('id')
                ->on('children')
                ->cascadeOnDelete();
        });

        Schema::table('menu_choices', function (Blueprint $table) {
            // Dolgozói soroknál a child_id NULL, ezért ide külön egyediségi
            // kényszer és index kell (a meglévő menu_choices_child_item_unique
            // és menu_choices_child_date_unique indexek NULL child_id mellett
            // nem érvényesülnének).
            $table->unique(['eater_type', 'eater_id', 'ab_menu_item_id'], 'menu_choices_eater_item_unique');
            $table->index(['institution_id', 'eater_type', 'eater_id'], 'menu_choices_eater_lookup_idx');
        });
    }

    public function down(): void
    {
        $employeeChoiceExists = DB::table('menu_choices')
            ->whereNotNull('eater_type')
            ->where('eater_type', '!=', 'child')
            ->exists();

        if ($employeeChoiceExists) {
            throw new RuntimeException('Nem lehet visszavonni: léteznek nem gyermek (pl. dolgozói) eater_type-ú menüválasztás-rekordok.');
        }

        Schema::table('menu_choices', function (Blueprint $table) {
            $table->dropUnique('menu_choices_eater_item_unique');
            $table->dropIndex('menu_choices_eater_lookup_idx');
        });

        Schema::table('menu_choices', function (Blueprint $table) {
            $table->dropForeign(['child_id']);
        });

        Schema::table('menu_choices', function (Blueprint $table) {
            $table->unsignedBigInteger('child_id')->nullable(false)->change();
            $table->foreign('child_id', 'menu_choices_child_id_foreign')
                ->references('id')
                ->on('children')
                ->cascadeOnDelete();
        });

        Schema::table('menu_choices', function (Blueprint $table) {
            $table->dropColumn(['eater_type', 'eater_id']);
        });
    }
};
