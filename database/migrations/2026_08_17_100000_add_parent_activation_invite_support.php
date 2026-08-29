<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->string('type', 40)->default('custom')->after('institution_id');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->timestamp('activation_email_sent_at')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('activation_email_sent_at');
        });
    }
};
