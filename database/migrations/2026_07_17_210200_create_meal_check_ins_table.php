<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->foreignId('institution_meal_type_id')->nullable()->constrained('institution_meal_types')->nullOnDelete();
            $table->string('menu_choice', 1)->nullable();
            $table->date('service_date');
            $table->timestamp('scanned_at');
            $table->string('barcode_token_suffix', 16)->nullable();
            $table->foreignId('kiosk_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30);
            $table->string('rejection_reason', 100)->nullable();
            $table->string('success_key', 80)->nullable()->unique();
            $table->timestamps();

            $table->index(['institution_id', 'service_date', 'status'], 'meal_check_ins_institution_date_status');
            $table->index(['kiosk_user_id', 'scanned_at'], 'meal_check_ins_kiosk_scanned');
            $table->index(['child_id', 'service_date'], 'meal_check_ins_child_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_check_ins');
    }
};
