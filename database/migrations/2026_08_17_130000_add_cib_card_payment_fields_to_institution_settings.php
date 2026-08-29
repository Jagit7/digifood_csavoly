<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->string('cib_terminal_id', 100)->nullable()->after('card_payment_test_mode');
            $table->text('cib_secret_key')->nullable()->after('cib_terminal_id');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn(['cib_terminal_id', 'cib_secret_key']);
        });
    }
};
