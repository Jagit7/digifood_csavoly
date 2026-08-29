<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->string('type', 40);
            $table->bigInteger('amount');
            $table->boolean('affects_invoice')->default(true);
            $table->unsignedSmallInteger('reference_year')->nullable();
            $table->unsignedTinyInteger('reference_month')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason', 191);
            $table->string('document_number', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 191)->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'child_id', 'reference_year', 'reference_month'], 'financial_adjustments_reference_idx');
            $table->index(['source_type', 'source_id'], 'financial_adjustments_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_adjustments');
    }
};
