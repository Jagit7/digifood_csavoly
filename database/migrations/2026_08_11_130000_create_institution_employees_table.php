<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_type_id')->nullable()->constrained('discount_types')->nullOnDelete();
            $table->string('name', 191);
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address_type', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('street_name', 191)->nullable();
            $table->string('street_type', 50)->nullable();
            $table->string('house_number', 30)->nullable();
            $table->string('floor', 20)->nullable();
            $table->string('door', 20)->nullable();
            $table->text('bank_account_holder')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->string('barcode_token', 64)->nullable()->unique();
            $table->timestamp('barcode_generated_at')->nullable();
            $table->timestamp('barcode_disabled_at')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'active']);
            $table->index(['institution_id', 'name']);
            $table->index(['institution_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_employees');
    }
};
