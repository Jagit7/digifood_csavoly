<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->string('barcode_token', 64)->nullable()->unique()->after('active');
            $table->timestamp('barcode_generated_at')->nullable()->after('barcode_token');
            $table->timestamp('barcode_disabled_at')->nullable()->after('barcode_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn([
                'barcode_disabled_at',
                'barcode_generated_at',
                'barcode_token',
            ]);
        });
    }
};
