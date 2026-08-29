<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_meal_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('pricing_mode', 30)->default('component_sum');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'name']);
        });

        Schema::create('institution_meal_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_meal_package_id')
                ->constrained(
                    table: 'institution_meal_packages',
                    indexName: 'im_package_items_package_fk'
                )
                ->cascadeOnDelete();
            $table->foreignId('institution_meal_type_id')
                ->constrained(
                    table: 'institution_meal_types',
                    indexName: 'im_package_items_type_fk'
                )
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['institution_meal_package_id', 'institution_meal_type_id'],
                'im_package_items_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_meal_package_items');
        Schema::dropIfExists('institution_meal_packages');
    }
};
