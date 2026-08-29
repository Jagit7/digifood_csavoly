<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->string('ab_menu_notification_subject', 191)->nullable()->after('ab_menu_choice_deadline_day');
            $table->text('ab_menu_notification_body')->nullable()->after('ab_menu_notification_subject');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn(['ab_menu_notification_subject', 'ab_menu_notification_body']);
        });
    }
};
