<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_menu_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('ab_menu_plan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('menu_date');

            $table->text('menu_a')->nullable();
            $table->text('menu_b')->nullable();
            $table->text('menu_dietary')->nullable();

            $table->text('allergens')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique([
                'ab_menu_plan_id',
                'menu_date'
            ]);

            $table->index('menu_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_menu_items');
    }
};