<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_period_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('recipient_email', 191);
            $table->string('status', 30)->default('queued');
            $table->string('subject', 191)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['institution_id', 'user_id', 'year', 'month'],
                'payment_period_notification_logs_unique'
            );
            $table->index(['status', 'queued_at'], 'payment_period_notification_logs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_period_notification_logs');
    }
};
