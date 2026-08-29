<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->date('menu_date');
            $table->string('choice', 1);
            $table->timestamps();

            $table->unique(['child_id', 'menu_date'], 'menu_choices_child_date_unique');
            $table->index(['institution_id', 'menu_date'], 'menu_choices_institution_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_choices');
    }
};
