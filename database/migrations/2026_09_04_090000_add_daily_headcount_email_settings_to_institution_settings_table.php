<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->boolean('daily_headcount_email_enabled')
                ->default(false);

            $table->time('daily_headcount_email_send_time')
                ->default('07:30:00');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'daily_headcount_email_enabled',
                'daily_headcount_email_send_time',
            ]);
        });
    }
};