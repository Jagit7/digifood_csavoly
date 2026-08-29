<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_employee_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('institution_employee_id');
            $table->unsignedBigInteger('employee_monthly_payment_statement_id')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->bigInteger('amount');
            $table->timestamp('paid_at');
            $table->string('payment_method', 30);
            $table->string('status', 30);
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamps();

            $table->foreign('institution_id', 'emp_pay_institution_fk')
                ->references('id')
                ->on('institutions')
                ->cascadeOnDelete();
            $table->foreign('institution_employee_id', 'emp_pay_employee_fk')
                ->references('id')
                ->on('institution_employees')
                ->cascadeOnDelete();
            $table->foreign('employee_monthly_payment_statement_id', 'emp_pay_stmt_fk')
                ->references('id')
                ->on('employee_monthly_payment_statements')
                ->nullOnDelete();
            $table->foreign('recorded_by', 'emp_pay_recorded_by_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->index(
                ['institution_id', 'paid_at'],
                'emp_pay_paid_at_idx'
            );
            $table->index(
                ['institution_id', 'status'],
                'emp_pay_status_idx'
            );
            $table->index(
                ['institution_id', 'payment_method'],
                'emp_pay_method_idx'
            );
            $table->index(
                ['institution_id', 'institution_employee_id'],
                'emp_pay_employee_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_employee_payments');
    }
};
