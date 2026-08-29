<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_admin_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('approved_token_hash', 64)
                ->nullable()
                ->index();

            $table->string('approved_ip', 45)->nullable();
            $table->string('approved_user_agent')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('last_used_at')->nullable();

            $table->string('pending_token_hash', 64)
                ->nullable()
                ->index();

            $table->string('pending_ip', 45)->nullable();
            $table->string('pending_user_agent')->nullable();
            $table->timestamp('pending_requested_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_admin_devices');
    }
};