<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('working_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('institution_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('date');

            $table->string('name', 191);

            $table->enum('type', [
                'school_saturday',
                'kindergarten_day',
                'extra_working_day',
                'other',
            ])->default('school_saturday');

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['institution_id', 'date']);

            $table->index(['institution_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_days');
    }
};