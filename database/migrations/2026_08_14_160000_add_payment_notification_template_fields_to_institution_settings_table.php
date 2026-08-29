<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->string('payment_notification_subject', 191)->nullable()->after('payment_notification_day');
            $table->longText('payment_notification_body')->nullable()->after('payment_notification_subject');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_notification_subject',
                'payment_notification_body',
            ]);
        });
    }
};
