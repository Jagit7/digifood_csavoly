<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_payment_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('meal_package_id')->nullable()->constrained('institution_meal_packages')->nullOnDelete();
            $table->foreignId('discount_id')->nullable()->constrained('discount_types')->nullOnDelete();
            $table->unsignedBigInteger('meal_amount')->default(0);
            $table->unsignedBigInteger('previous_cancellation_credit')->default(0);
            $table->bigInteger('billing_adjustment_amount')->default(0);
            $table->bigInteger('invoiceable_amount')->default(0);
            $table->bigInteger('previous_balance')->default(0);
            $table->bigInteger('total_payable')->default(0);
            $table->string('status', 30)->default('draft');
            $table->json('issues')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['child_id', 'year', 'month'], 'monthly_payment_statements_child_month_unique');
            $table->index(['institution_id', 'year', 'month', 'status'], 'monthly_payment_statements_lookup_idx');
        });

        Schema::create('monthly_payment_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_payment_statement_id')
                ->constrained('monthly_payment_statements')
                ->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 40);
            $table->unsignedBigInteger('original_daily_price')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedBigInteger('payable_amount')->default(0);
            $table->foreignId('cancellation_id')->nullable()->constrained('meal_cancellations')->nullOnDelete();
            $table->foreignId('school_break_id')->nullable()->constrained('school_breaks')->nullOnDelete();
            $table->foreignId('class_cancellation_id')->nullable()->constrained('class_cancellations')->nullOnDelete();
            $table->foreignId('working_day_id')->nullable()->constrained('working_days')->nullOnDelete();
            $table->boolean('manually_modified')->default(false);
            $table->string('original_status', 40)->nullable();
            $table->unsignedBigInteger('original_payable_amount')->nullable();
            $table->string('modification_reason', 191)->nullable();
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();

            $table->unique(['monthly_payment_statement_id', 'date'], 'monthly_payment_days_statement_date_unique');
            $table->index(['date', 'status'], 'monthly_payment_days_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_payment_days');
        Schema::dropIfExists('monthly_payment_statements');
    }
};
