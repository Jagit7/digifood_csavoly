<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->date('target_service_date');
            $table->string('status', 30)->default('queued');
            $table->json('recipient_emails')->nullable();
            $table->string('subject', 191)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'target_service_date'], 'kitchen_notification_logs_unique');
            $table->index(['status', 'scheduled_at'], 'kitchen_notification_logs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_notification_logs');
    }
};
