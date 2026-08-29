<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('default_order')->default(0);
            $table->timestamps();
        });

        Schema::create('institution_meal_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_type_id')->constrained('meal_types');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_parent_selectable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->time('cancellation_deadline_time')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'meal_type_id']);
        });

        Schema::create('institution_meal_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_meal_type_id')
                ->constrained('institution_meal_types')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['institution_meal_type_id', 'valid_from', 'valid_to'], 'institution_meal_prices_validity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_meal_prices');
        Schema::dropIfExists('institution_meal_types');
        Schema::dropIfExists('meal_types');
    }
};
