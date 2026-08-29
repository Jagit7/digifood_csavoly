<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            // Az intézmény által alkalmazott ÁFA-kulcs (%) a fizetési
            // kötelezettségek (havi elszámolások) bruttósításához. Alapból 0
            // (a korábbi, ÁFA-mentes számítást nem változtatja meg), de
            // intézményenként megadható a tényleges kulcs (pl. 5.00 vagy
            // 27.00), ld. InstitutionSetting::grossAmount().
            $table->decimal('vat_rate', 5, 2)->default(0)->after('payment_due_day');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });
    }
};
