<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_monthly_payment_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('institution_employee_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('meal_package_id')->nullable();
            $table->unsignedBigInteger('discount_id')->nullable();
            $table->unsignedBigInteger('meal_amount')->default(0);
            $table->unsignedBigInteger('previous_cancellation_credit')->default(0);
            $table->bigInteger('billing_adjustment_amount')->default(0);
            $table->bigInteger('invoiceable_amount')->default(0);
            $table->bigInteger('previous_balance')->default(0);
            $table->bigInteger('total_payable')->default(0);
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->json('issues')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->string('reopen_reason', 191)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('invoice_provider', 30)->nullable();
            $table->string('invoice_status', 30)->nullable();
            $table->text('invoice_url')->nullable();
            $table->string('invoice_pdf_path', 191)->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->string('payment_status', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();

            $table->foreign('institution_id', 'emp_stmt_institution_fk')
                ->references('id')
                ->on('institutions')
                ->cascadeOnDelete();
            $table->foreign('institution_employee_id', 'emp_stmt_employee_fk')
                ->references('id')
                ->on('institution_employees')
                ->cascadeOnDelete();
            $table->foreign('meal_package_id', 'emp_stmt_package_fk')
                ->references('id')
                ->on('institution_meal_packages')
                ->nullOnDelete();
            $table->foreign('discount_id', 'emp_stmt_discount_fk')
                ->references('id')
                ->on('discount_types')
                ->nullOnDelete();
            $table->foreign('reviewed_by', 'emp_stmt_reviewed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('closed_by', 'emp_stmt_closed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('reopened_by', 'emp_stmt_reopened_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->unique(
                ['institution_id', 'institution_employee_id', 'year', 'month'],
                'emp_stmt_period_uniq'
            );
            $table->index(
                ['institution_id', 'year', 'month', 'status'],
                'emp_stmt_lookup_idx'
            );
        });

        Schema::create('employee_monthly_payment_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_monthly_payment_statement_id');
            $table->date('date');
            $table->string('status', 40);
            $table->unsignedBigInteger('original_daily_price')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedBigInteger('payable_amount')->default(0);
            $table->unsignedBigInteger('school_break_id')->nullable();
            $table->unsignedBigInteger('working_day_id')->nullable();
            $table->boolean('manually_modified')->default(false);
            $table->string('original_status', 40)->nullable();
            $table->unsignedBigInteger('original_payable_amount')->nullable();
            $table->string('modification_reason', 191)->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_monthly_payment_statement_id', 'emp_day_stmt_fk')
                ->references('id')
                ->on('employee_monthly_payment_statements')
                ->cascadeOnDelete();
            $table->foreign('school_break_id', 'emp_day_break_fk')
                ->references('id')
                ->on('school_breaks')
                ->nullOnDelete();
            $table->foreign('working_day_id', 'emp_day_workday_fk')
                ->references('id')
                ->on('working_days')
                ->nullOnDelete();
            $table->foreign('modified_by', 'emp_day_modified_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->unique(
                ['employee_monthly_payment_statement_id', 'date'],
                'emp_day_stmt_date_uniq'
            );
            $table->index(['date', 'status'], 'emp_day_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_monthly_payment_days');
        Schema::dropIfExists('employee_monthly_payment_statements');
    }
};
