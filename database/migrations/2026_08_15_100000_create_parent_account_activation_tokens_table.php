<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_account_activation_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191);
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'used_at']);
            $table->index(['expires_at', 'used_at']);
            $table->unique('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_account_activation_tokens');
    }
};
