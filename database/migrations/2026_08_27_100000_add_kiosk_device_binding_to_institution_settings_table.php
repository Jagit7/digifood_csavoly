<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->string('barcode_kiosk_device_token_hash', 64)->nullable()->unique()->after('barcode_kiosk_control_generated_at');
            $table->timestamp('barcode_kiosk_device_bound_at')->nullable()->after('barcode_kiosk_device_token_hash');
            $table->timestamp('barcode_kiosk_device_last_used_at')->nullable()->after('barcode_kiosk_device_bound_at');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'barcode_kiosk_device_token_hash',
                'barcode_kiosk_device_bound_at',
                'barcode_kiosk_device_last_used_at',
            ]);
        });
    }
};
