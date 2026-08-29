<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Az indulás előtti adattisztításhoz: az adminisztrátorok soronként
 * bepipálhatják, hogy egy gyermek adatait (cím, gondviselő, számlázás)
 * már manuálisan átnézték és rendben találták - így a Gyermeklistán
 * nyomon követhető, hol tart az ellenőrzés, és több munkatárs is
 * párhuzamosan dolgozhat rajta anélkül, hogy kétszer néznék át
 * ugyanazt a rekordot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->timestamp('data_verified_at')->nullable()->after('active');
            $table->foreignId('data_verified_by')
                ->nullable()
                ->after('data_verified_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropConstrainedForeignId('data_verified_by');
            $table->dropColumn('data_verified_at');
        });
    }
};
