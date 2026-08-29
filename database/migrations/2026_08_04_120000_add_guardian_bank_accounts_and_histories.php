<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->text('bank_account_holder')->nullable()->after('phone');
            $table->text('bank_account_number')->nullable()->after('bank_account_holder');
        });

        Schema::create('guardian_bank_account_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->text('bank_account_holder')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_source', 30)->default('admin');
            $table->string('change_reason', 255)->nullable();
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->index(['guardian_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_bank_account_histories');

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn([
                'bank_account_holder',
                'bank_account_number',
            ]);
        });
    }
};
