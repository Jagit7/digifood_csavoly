<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profile_child', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->unique(['billing_profile_id', 'child_id']);
            $table->index(['child_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profile_child');
    }
};
