<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->foreignId('monthly_payment_statement_id')
                ->nullable()
                ->constrained('monthly_payment_statements')
                ->nullOnDelete();
            $table->string('invoice_number', 100)->nullable();
            $table->bigInteger('amount');
            $table->timestamp('paid_at');
            $table->string('payment_method', 30);
            $table->string('status', 30);
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'paid_at'], 'institution_payments_institution_paid_at_idx');
            $table->index(['institution_id', 'status'], 'institution_payments_institution_status_idx');
            $table->index(['institution_id', 'payment_method'], 'institution_payments_institution_method_idx');
            $table->index(['institution_id', 'child_id'], 'institution_payments_institution_child_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_payments');
    }
};
