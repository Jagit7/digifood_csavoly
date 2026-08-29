<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->foreignId('barcode_kiosk_meal_type_id')
                ->nullable()
                ->after('barcode_kiosk_pin_hash')
                ->constrained('institution_meal_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropForeign(['barcode_kiosk_meal_type_id']);
            $table->dropColumn('barcode_kiosk_meal_type_id');
        });
    }
};
