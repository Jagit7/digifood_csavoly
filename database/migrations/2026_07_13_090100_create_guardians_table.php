<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 30)->nullable();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('phone_type', 30)->nullable();
            $table->string('address_type', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('street_name')->nullable();
            $table->string('street_type', 50)->nullable();
            $table->string('house_number', 30)->nullable();
            $table->string('floor', 20)->nullable();
            $table->string('door', 20)->nullable();
            $table->string('source_type', 40)->default('manual');
            $table->timestamps();

            $table->index(['institution_id', 'email']);
            $table->index(['institution_id', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
