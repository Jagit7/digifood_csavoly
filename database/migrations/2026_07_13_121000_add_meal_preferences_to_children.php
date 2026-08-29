<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->foreignId('discount_type_id')
                ->nullable()
                ->after('institution_id')
                ->constrained('discount_types')
                ->nullOnDelete();
        });

        Schema::create('child_dietary_restriction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dietary_restriction_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['child_id', 'dietary_restriction_id'], 'child_dietary_restriction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_dietary_restriction');

        Schema::table('children', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_type_id');
        });
    }
};
