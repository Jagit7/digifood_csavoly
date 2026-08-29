<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->boolean('daily_attendance_email_enabled')->default(false)->after('kitchen_notification_emails');
            $table->time('daily_attendance_email_send_time')->default('07:30:00')->after('daily_attendance_email_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_attendance_email_enabled', 'daily_attendance_email_send_time']);
        });
    }
};
