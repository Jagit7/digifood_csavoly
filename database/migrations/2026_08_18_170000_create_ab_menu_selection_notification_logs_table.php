<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_menu_selection_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ab_menu_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email', 191);
            $table->string('status', 30)->default('queued');
            $table->string('subject', 191)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Egy adott menütervhez egy adott szülő (user) csak egyszer kaphat
            // értesítést - a menüválasztás csak egyszer nyitható meg egy
            // tervhez (ld. AbMenuSelectionService::canOpenSelection()), ez a
            // unique kulcs véd az esetleges dupla dispatch ellen.
            $table->unique(
                ['ab_menu_plan_id', 'user_id'],
                'ab_menu_selection_notification_logs_unique'
            );
            $table->index(['status', 'queued_at'], 'ab_menu_selection_notification_logs_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_menu_selection_notification_logs');
    }
};
