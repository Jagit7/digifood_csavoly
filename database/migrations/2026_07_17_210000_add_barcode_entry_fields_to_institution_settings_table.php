<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->boolean('barcode_entry_enabled')->default(false)->after('ab_menu_choice_deadline_day');
            $table->string('barcode_kiosk_pin_hash', 191)->nullable()->after('barcode_entry_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'barcode_entry_enabled',
                'barcode_kiosk_pin_hash',
            ]);
        });
    }
};
