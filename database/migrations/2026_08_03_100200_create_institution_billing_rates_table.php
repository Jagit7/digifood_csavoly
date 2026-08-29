<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_billing_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_per_child', 12, 2)->nullable();
            $table->decimal('fixed_monthly_fee', 12, 2)->nullable();
            $table->decimal('minimum_monthly_fee', 12, 2)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'valid_from']);
            $table->index(['institution_id', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_billing_rates');
    }
};
