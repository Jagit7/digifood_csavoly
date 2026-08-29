<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->boolean('send_kitchen_email')->default(false);
            $table->boolean('payment_notification_enabled')->default(false);
            $table->unsignedTinyInteger('payment_notification_day')->default(5);
            $table->unsignedTinyInteger('ab_menu_choice_deadline_day')->default(20);
            $table->timestamps();

            $table->unique('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_settings');
    }
};
