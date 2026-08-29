<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_guardian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relationship_type', 60)->nullable();
            $table->boolean('is_legal_representative')->default(false);
            $table->boolean('has_no_custody')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('receives_family_allowance')->default(false);
            $table->timestamps();

            $table->unique(['child_id', 'guardian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_guardian');
    }
};
