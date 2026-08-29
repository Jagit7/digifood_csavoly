<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_payment_component_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('component', 30);
            $table->unsignedBigInteger('amount');
            $table->date('valid_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'component', 'valid_from'], 'institution_payment_component_rates_unique');
            $table->index(['institution_id', 'component', 'valid_from'], 'institution_payment_component_rates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_payment_component_rates');
    }
};
