<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_adjustments', function (Blueprint $table) {
            $table->foreignId('monthly_payment_statement_id')->nullable()->after('child_id')
                ->constrained('monthly_payment_statements')->nullOnDelete();
            $table->string('payment_component', 30)->nullable()->after('type');
            $table->date('entry_date')->nullable()->after('document_number');

            $table->index(['institution_id', 'child_id', 'payment_component'], 'financial_adjustments_component_idx');
        });

        Schema::table('institution_payments', function (Blueprint $table) {
            $table->string('payment_component', 30)->nullable()->after('monthly_payment_statement_id');
            $table->index(['institution_id', 'child_id', 'payment_component'], 'institution_payments_component_idx');
        });
    }

    public function down(): void
    {
        Schema::table('institution_payments', function (Blueprint $table) {
            $table->dropIndex('institution_payments_component_idx');
            $table->dropColumn('payment_component');
        });

        Schema::table('financial_adjustments', function (Blueprint $table) {
            $table->dropIndex('financial_adjustments_component_idx');
            $table->dropForeign(['monthly_payment_statement_id']);
            $table->dropColumn('monthly_payment_statement_id');
            $table->dropColumn(['payment_component', 'entry_date']);
        });
    }
};
