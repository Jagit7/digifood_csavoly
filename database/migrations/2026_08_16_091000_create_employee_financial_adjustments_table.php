<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('institution_employee_id');
            $table->string('type', 40);
            $table->bigInteger('amount');
            $table->boolean('affects_invoice')->default(true);
            $table->unsignedSmallInteger('reference_year')->nullable();
            $table->unsignedTinyInteger('reference_month')->nullable();
            $table->string('source_type', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason', 191);
            $table->string('document_number', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->string('reversal_reason', 191)->nullable();
            $table->timestamps();

            $table->foreign('institution_id', 'emp_adj_institution_fk')
                ->references('id')
                ->on('institutions')
                ->cascadeOnDelete();
            $table->foreign('institution_employee_id', 'emp_adj_employee_fk')
                ->references('id')
                ->on('institution_employees')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'emp_adj_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('reversed_by', 'emp_adj_reversed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(
                ['institution_id', 'institution_employee_id', 'reference_year', 'reference_month'],
                'emp_adj_ref_idx'
            );
            $table->index(['source_type', 'source_id'], 'emp_adj_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_financial_adjustments');
    }
};
