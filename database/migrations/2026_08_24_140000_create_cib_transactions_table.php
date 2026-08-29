<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cib_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_monthly_settlement_payment_id')->constrained('parent_monthly_settlement_payments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->string('pid', 7);
            $table->string('trid', 16)->unique();
            $table->string('order_ref', 40)->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('HUF');
            $table->string('status', 20)->default('pending')->index();
            $table->string('init_rc', 12)->nullable();
            $table->string('init_rt', 191)->nullable();
            $table->string('final_rc', 12)->nullable();
            $table->string('final_rt', 191)->nullable();
            $table->string('anum', 20)->nullable();
            $table->string('last_message_type', 10)->nullable();
            $table->string('merchant_url', 191);
            $table->string('customer_url', 191);
            $table->string('return_url', 191);
            $table->timestamp('init_requested_at')->nullable();
            $table->timestamp('init_completed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('last_status_polled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('request_log')->nullable();
            $table->json('response_log')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'status'], 'cib_transactions_institution_status_idx');
            $table->index(['parent_monthly_settlement_payment_id', 'status'], 'cib_transactions_payment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cib_transactions');
    }
};
