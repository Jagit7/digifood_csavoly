<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('email', 191)->unique();
            $table->string('password');

            // szerepkörök:
            // super_admin
            // institution_admin
            // kitchen
            $table->string('role', 30);

            // melyik intézményhez tartozik
            // super_admin esetén lehet null
            $table->unsignedBigInteger('institution_id')->nullable();

            $table->string('phone', 30)->nullable();

            // opcionális finomjogosultságok
            $table->json('permissions')->nullable();

            $table->boolean('is_active')->default(true);

            // meghívás kezelése
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_invitation_at')->nullable();

            // utolsó belépés
            $table->timestamp('last_login_at')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['role', 'institution_id']);
            $table->index('is_active');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};