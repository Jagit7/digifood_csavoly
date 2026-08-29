<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Felhasználói kérés: ha egy intézménynél nincs bekapcsolva a kártyás
// fizetés, de van megadott (befizetésre használható) bankszámlaszáma, a
// szülői felület a havi elszámolásoknál egy vágólapra másolható banki
// átutalási tájékoztatót jelenítsen meg helyette (ld.
// ParentMonthlySettlementService::bankTransferInfo()). Ez a két mező
// teljesen független a kártyás fizetés / Billingo / Számlázz.hu / CIB
// beállításoktól - bármikor kitölthető, akkor is, ha az intézmény egyáltalán
// nem használ online fizetést vagy számlázást.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->string('bank_transfer_account_holder', 191)->nullable()->after('card_payment_test_mode');
            $table->string('bank_transfer_account_number', 64)->nullable()->after('bank_transfer_account_holder');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn(['bank_transfer_account_holder', 'bank_transfer_account_number']);
        });
    }
};
