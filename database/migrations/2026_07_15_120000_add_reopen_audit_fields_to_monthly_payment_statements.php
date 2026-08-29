<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_payment_statements', function (Blueprint $table) {
            $table->timestamp('reopened_at')->nullable()->after('closed_by');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->string('reopen_reason', 191)->nullable()->after('reopened_by');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_payment_statements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['reopened_at', 'reopen_reason']);
        });
    }
};
