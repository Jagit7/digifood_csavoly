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
        Schema::create('school_breaks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('institution_id')
                ->constrained('institutions')
                ->cascadeOnDelete();

            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');

            $table->string('type')->default('school_break'); 
            // school_break / holiday / maintenance / other

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['institution_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_breaks');
    }
};
