<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_meal_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_meal_package_id')->nullable()->constrained('institution_meal_packages')->nullOnDelete();
            $table->string('mode', 30);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'institution_id', 'valid_from', 'valid_to'], 'student_meal_settings_validity_idx');
        });

        Schema::create('student_meal_setting_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_meal_setting_id')
                ->constrained(
                    table: 'student_meal_settings',
                    indexName: 'student_meal_setting_items_setting_fk'
                )
                ->cascadeOnDelete();
            $table->foreignId('institution_meal_type_id')
                ->constrained(
                    table: 'institution_meal_types',
                    indexName: 'student_meal_setting_items_type_fk'
                )
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['student_meal_setting_id', 'institution_meal_type_id'],
                'student_meal_setting_items_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_meal_setting_items');
        Schema::dropIfExists('student_meal_settings');
    }
};
