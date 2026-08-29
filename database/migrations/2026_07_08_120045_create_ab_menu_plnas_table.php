<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_menu_plans', function (Blueprint $table) {

            $table->id();

            $table->foreignId('institution_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title')->nullable();

            $table->date('valid_from');
            $table->date('valid_to');

            $table->boolean('active')->default(true);

            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['institution_id', 'active']);
            $table->index(['institution_id', 'valid_from']);
            $table->index(['institution_id', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_menu_plans');
    }
};