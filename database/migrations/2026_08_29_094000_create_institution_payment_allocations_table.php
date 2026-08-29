<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('institution_payment_allocations')) {
            Schema::create('institution_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('institution_payment_id')
                    ->constrained('institution_payments', indexName: 'inst_pay_alloc_payment_fk')
                    ->cascadeOnDelete();
                $table->foreignId('monthly_payment_statement_id')->nullable()
                    ->constrained('monthly_payment_statements', indexName: 'inst_pay_alloc_statement_fk')
                    ->nullOnDelete();
                $table->string('payment_component', 30);
                $table->string('allocation_type', 30)->default('statement');
                $table->unsignedBigInteger('amount');
                $table->timestamp('allocated_at')->nullable();
                $table->timestamps();

                $table->index(['monthly_payment_statement_id', 'payment_component'], 'institution_payment_allocations_statement_idx');
                $table->index(['institution_payment_id', 'allocation_type'], 'institution_payment_allocations_payment_idx');
            });

            return;
        }

        Schema::table('institution_payment_allocations', function (Blueprint $table) {
            $table->foreign('institution_payment_id', 'inst_pay_alloc_payment_fk')
                ->references('id')
                ->on('institution_payments')
                ->cascadeOnDelete();
            $table->foreign('monthly_payment_statement_id', 'inst_pay_alloc_statement_fk')
                ->references('id')
                ->on('monthly_payment_statements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_payment_allocations');
    }
};
