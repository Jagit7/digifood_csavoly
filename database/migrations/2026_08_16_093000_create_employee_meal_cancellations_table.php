<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_meal_cancellations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('institution_employee_id');
            $table->date('service_date');
            $table->enum('source', ['admin', 'system'])->default('admin');
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->string('reason', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id', 'emp_meal_cancel_inst_fk')
                ->references('id')
                ->on('institutions')
                ->cascadeOnDelete();
            $table->foreign('institution_employee_id', 'emp_meal_cancel_emp_fk')
                ->references('id')
                ->on('institution_employees')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'emp_meal_cancel_created_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('revoked_by', 'emp_meal_cancel_revoked_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->unique(
                ['institution_employee_id', 'service_date'],
                'emp_meal_cancel_date_uniq'
            );
            $table->index(
                ['institution_id', 'status', 'service_date'],
                'emp_meal_cancel_inst_idx'
            );
            $table->index(
                ['institution_employee_id', 'status', 'service_date'],
                'emp_meal_cancel_emp_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_meal_cancellations');
    }
};
