<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A befizetések (InstitutionPayment) korábban véglegesen, nyom nélkül
 * törölhetők voltak egy intézményi admin által - ez valós pénzt érintő
 * rekordoknál (különösen már "teljesült" státuszúaknál) elszámoltathatósági
 * kockázat. A soft delete bevezetésével a törlés visszaállítható marad, és
 * a törölt rekord adatai (összeg, dátum, hivatkozás) sem vesznek el.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('institution_payments', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('institution_payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
