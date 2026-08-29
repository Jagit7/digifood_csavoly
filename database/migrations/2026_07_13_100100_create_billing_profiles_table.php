<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_type', 30)->default('guardian');
            $table->string('billing_name', 191);
            $table->string('tax_number', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('email', 191)->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('employer_reference')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['institution_id', 'active']);
            $table->index(['institution_id', 'billing_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};
