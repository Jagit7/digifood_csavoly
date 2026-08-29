<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('profile', 40);
            $table->string('status', 40)->default('processing');
            $table->string('original_file_name');
            $table->string('stored_file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('group_name', 100)->nullable();
            $table->string('school_year', 20)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'profile']);
            $table->index(['institution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
