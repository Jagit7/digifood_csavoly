<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_invoices', function (Blueprint $table) {
            $table->foreignId('institution_payment_id')
                ->nullable()
                ->after('monthly_payment_statement_id')
                ->constrained('institution_payments')
                ->nullOnDelete();

            $table->unique('institution_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('institution_invoices', function (Blueprint $table) {
            $table->dropUnique(['institution_payment_id']);
            $table->dropConstrainedForeignId('institution_payment_id');
        });
    }
};
