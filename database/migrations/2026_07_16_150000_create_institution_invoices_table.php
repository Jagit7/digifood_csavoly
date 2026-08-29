<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->foreignId('monthly_payment_statement_id')->constrained('monthly_payment_statements')->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_invoice_id', 191)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('status', 30);
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->date('fulfillment_date')->nullable();
            $table->unsignedBigInteger('net_amount');
            $table->unsignedBigInteger('vat_amount')->default(0);
            $table->unsignedBigInteger('gross_amount');
            $table->string('currency', 3)->default('HUF');
            $table->string('payment_method', 50);
            $table->string('customer_name', 191);
            $table->string('customer_email', 191)->nullable();
            $table->string('customer_tax_number', 50)->nullable();
            $table->string('billing_postcode', 20);
            $table->string('billing_city', 100);
            $table->string('billing_address', 191);
            $table->string('invoice_pdf_path', 191)->nullable();
            $table->text('invoice_url')->nullable();
            $table->text('error_message')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('monthly_payment_statement_id');
            $table->index(['institution_id', 'status']);
            $table->index(['institution_id', 'provider']);
            $table->index(['issue_date', 'due_date']);
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_invoices');
    }
};
