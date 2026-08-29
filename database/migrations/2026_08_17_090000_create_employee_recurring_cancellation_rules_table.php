<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_recurring_cancellation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('institution_employee_id');
            $table->unsignedTinyInteger('weekday');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('source', ['admin', 'system'])->default('admin');
            $table->enum('status', ['active', 'ended', 'revoked'])->default('active');
            $table->string('reason', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id', 'emp_recur_cancel_inst_fk')
                ->references('id')
                ->on('institutions')
                ->cascadeOnDelete();
            $table->foreign('institution_employee_id', 'emp_recur_cancel_emp_fk')
                ->references('id')
                ->on('institution_employees')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'emp_recur_cancel_created_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('revoked_by', 'emp_recur_cancel_revoked_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(
                ['institution_id', 'status', 'weekday', 'starts_on', 'ends_on'],
                'emp_recur_cancel_effective_lookup'
            );
            $table->index(
                ['institution_employee_id', 'status', 'weekday', 'starts_on', 'ends_on'],
                'emp_recur_cancel_emp_lookup'
            );
            $table->index(
                ['institution_id', 'status', 'starts_on'],
                'emp_recur_cancel_list_lookup'
            );
            $table->index(['institution_id', 'created_at'], 'emp_recur_cancel_institution_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_recurring_cancellation_rules');
    }
};
