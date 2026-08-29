<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_meal_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('cancellation_hour')->nullable();
            $table->unsignedTinyInteger('cancellation_minute')->nullable();
            $table->timestamps();
        });

        Schema::create('discount_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('percentage')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['institution_id', 'name', 'percentage']);
            $table->index(['institution_id', 'active']);
        });

        Schema::create('dietary_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 30);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['institution_id', 'type', 'name']);
            $table->index(['institution_id', 'type', 'active']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('dietary_restrictions');
        Schema::dropIfExists('discount_types');
        Schema::dropIfExists('institution_meal_settings');
    }
};
