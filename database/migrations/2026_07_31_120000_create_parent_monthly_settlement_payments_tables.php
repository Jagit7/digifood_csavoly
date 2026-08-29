<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_monthly_settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('guardian_id')->nullable();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('reference', 40)->unique();
            $table->string('idempotency_key', 191)->unique();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('payment_method', 30)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('transaction_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('receipt_url', 191)->nullable();
            $table->string('note', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'pms_payments_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('guardian_id', 'pms_payments_guardian_fk')
                ->references('id')
                ->on('guardians')
                ->nullOnDelete();
            $table->index(['user_id', 'year', 'month'], 'parent_monthly_settlement_payments_user_period_idx');
            $table->index(['user_id', 'status'], 'parent_monthly_settlement_payments_user_status_idx');
        });

        Schema::create('parent_monthly_settlement_payment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_monthly_settlement_payment_id');
            $table->unsignedBigInteger('child_id');
            $table->unsignedBigInteger('monthly_payment_statement_id');
            $table->foreign('parent_monthly_settlement_payment_id', 'pms_payment_items_payment_fk')
                ->references('id')
                ->on('parent_monthly_settlement_payments')
                ->cascadeOnDelete();
            $table->foreign('child_id', 'pms_items_child_fk')
                ->references('id')
                ->on('children')
                ->cascadeOnDelete();
            $table->foreign('monthly_payment_statement_id', 'pms_items_statement_fk')
                ->references('id')
                ->on('monthly_payment_statements')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->timestamps();

            $table->unique(
                ['parent_monthly_settlement_payment_id', 'monthly_payment_statement_id'],
                'parent_monthly_settlement_payment_items_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_monthly_settlement_payment_items');
        Schema::dropIfExists('parent_monthly_settlement_payments');
    }
};
