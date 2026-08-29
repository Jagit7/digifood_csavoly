<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_payment_statements', function (Blueprint $table) {
            $table->string('invoice_number', 100)->nullable()->after('status');
            $table->string('invoice_provider', 30)->nullable()->after('invoice_number');
            $table->string('invoice_status', 30)->nullable()->after('invoice_provider');
            $table->text('invoice_url')->nullable()->after('invoice_status');
            $table->string('invoice_pdf_path', 191)->nullable()->after('invoice_url');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_pdf_path');
            $table->string('payment_status', 30)->nullable()->after('invoiced_at');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->string('payment_reference', 100)->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_payment_statements', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number',
                'invoice_provider',
                'invoice_status',
                'invoice_url',
                'invoice_pdf_path',
                'invoiced_at',
                'payment_status',
                'paid_at',
                'payment_reference',
            ]);
        });
    }
};
