<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('educational_identifier', 32)->nullable();
            $table->string('group_name', 100)->nullable();
            $table->string('school_year', 20)->nullable();
            $table->string('source_type', 40)->default('manual');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['institution_id', 'educational_identifier']);
            $table->index(['institution_id', 'group_name']);
            $table->index(['institution_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
