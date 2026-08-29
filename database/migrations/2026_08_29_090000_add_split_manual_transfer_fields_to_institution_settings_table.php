<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->boolean('split_manual_transfer_enabled')->default(false)->after('bank_transfer_account_number');
            $table->string('foundation_account_holder', 191)->nullable()->after('split_manual_transfer_enabled');
            $table->string('foundation_account_number', 64)->nullable()->after('foundation_account_holder');
            $table->string('foundation_transfer_reference', 191)->nullable()->after('foundation_account_number');
            $table->string('kindergarten_account_holder', 191)->nullable()->after('foundation_transfer_reference');
            $table->string('kindergarten_account_number', 64)->nullable()->after('kindergarten_account_holder');
            $table->string('kindergarten_transfer_reference', 191)->nullable()->after('kindergarten_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('institution_settings', function (Blueprint $table) {
            $table->dropColumn([
                'split_manual_transfer_enabled',
                'foundation_account_holder',
                'foundation_account_number',
                'foundation_transfer_reference',
                'kindergarten_account_holder',
                'kindergarten_account_number',
                'kindergarten_transfer_reference',
            ]);
        });
    }
};
