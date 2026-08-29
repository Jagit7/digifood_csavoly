<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendance_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('group_name', 100);
            $table->date('attendance_date');
            $table->string('status', 30)->default('queued');
            $table->json('recipient_emails')->nullable();
            $table->string('subject', 191)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'group_name', 'attendance_date'], 'daily_attendance_email_logs_unique');
            $table->index(['status', 'scheduled_at'], 'daily_attendance_email_logs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendance_email_logs');
    }
};
