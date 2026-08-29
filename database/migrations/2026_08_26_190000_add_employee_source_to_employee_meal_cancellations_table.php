<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HIBAJAVÍTÁS: az employee_meal_cancellations tábla "source" oszlopa a
 * létrehozásakor (2026_08_16_093000_create_employee_meal_cancellations_table)
 * csak ENUM('admin', 'system') értékeket engedett, de az
 * App\Models\EmployeeMealCancellation modell SOURCE_EMPLOYEE ('employee')
 * konstansa és az App\Services\EmployeeMealCancellationService::
 * recordSingleAsEmployee() (a dolgozói portál önkiszolgáló lemondása) ezt az
 * értéket próbálja beírni. Ez a séma/kód közötti eltérés már a dolgozói
 * önkiszolgáló lemondás funkció bevezetése óta (2026-08-16) fennállt - nem a
 * mostani felület-átalakítás okozta -, csak eddig soha nem futott le
 * ténylegesen a dolgozói "Lemondás mentése" útvonal. MySQL szigorú módban ez
 * "Data truncated for column 'source'" hibával elutasítja a beszúrást (lásd
 * az 500-as hiba naplóját), ezért a "employee" értéket fel kell venni az
 * ENUM listájába - pontosan úgy, ahogy a szülői oldalon a meal_cancellations
 * tábla source oszlopa is tartalmazza a "parent" értéket.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE employee_meal_cancellations MODIFY `source` ENUM('admin', 'system', 'employee') NOT NULL DEFAULT 'admin'"
            );

            return;
        }

        // Nem MySQL driver (pl. teszteléshez sqlite) esetén az enum
        // kényszer amúgy sem érvényesül karakterlánc-oszlopon, ezért ott
        // nincs teendő.
    }

    public function down(): void
    {
        $employeeSourceExists = DB::table('employee_meal_cancellations')
            ->where('source', 'employee')
            ->exists();

        if ($employeeSourceExists) {
            throw new RuntimeException(
                'Nem lehet visszavonni: léteznek "employee" forrású dolgozói lemondás-rekordok.'
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE employee_meal_cancellations MODIFY `source` ENUM('admin', 'system') NOT NULL DEFAULT 'admin'"
            );
        }
    }
};
