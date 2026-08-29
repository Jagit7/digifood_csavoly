<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A kioszk "vezérlő kártyája" - egy, kifejezetten erre a célra generált
 * vonalkód (nem tartozik diákhoz/dolgozóhoz), amivel a kioszk gépén a
 * beolvasóval lehet majd aktiválni/lezárni a kioszkot, billentyűzet és
 * egér nélkül. A tárolás módja ugyanazt a mintát követi, mint a
 * gyermek/dolgozó vonalkódoknál (ld. 2026_08_17_160000_encrypt_barcode_tokens):
 * a nyers token titkosítva (encrypted cast) kerül tárolásra, a beolvasásos
 * kereséshez pedig egy külön, determinisztikus (SHA-256) hash oszlop
 * szolgál.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->text('barcode_kiosk_control_token')->nullable()->after('barcode_kiosk_pin_hash');
            $table->string('barcode_kiosk_control_token_hash', 64)->nullable()->unique()->after('barcode_kiosk_control_token');
            $table->timestamp('barcode_kiosk_control_generated_at')->nullable()->after('barcode_kiosk_control_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'barcode_kiosk_control_token',
                'barcode_kiosk_control_token_hash',
                'barcode_kiosk_control_generated_at',
            ]);
        });
    }
};
