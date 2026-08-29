<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_meal_cancellation_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('created_by');
            $table->string('event_name', 191);
            $table->string('meal_scope', 64)->default('all_configured_meals');
            $table->string('reason', 191)->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('selected_children_count')->default(0);
            $table->unsignedInteger('service_days_count')->default(0);
            $table->unsignedInteger('planned_cancellation_count')->default(0);
            $table->unsignedInteger('created_cancellation_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('missing_meal_setting_count')->default(0);
            $table->unsignedInteger('non_service_day_count')->default(0);
            $table->unsignedInteger('deadline_blocked_count')->default(0);
            $table->unsignedInteger('class_cancelled_count')->default(0);
            $table->timestamps();

            $table->index(['institution_id', 'created_at'], 'bulk_meal_cancel_batch_institution_created');
        });

        Schema::create('bulk_meal_cancellation_batch_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('bulk_meal_cancellation_batch_id');
            $table->unsignedBigInteger('child_id');
            $table->unsignedBigInteger('meal_cancellation_id')->nullable();
            $table->date('service_date');
            $table->string('child_name', 191);
            $table->string('group_name', 191)->nullable();
            $table->string('grade_label', 32)->nullable();
            $table->string('result_code', 64);
            $table->string('result_label', 191);
            $table->timestamps();

            $table->index(
                ['bulk_meal_cancellation_batch_id', 'result_code'],
                'bulk_meal_cancel_batch_items_batch_result'
            );
            $table->index(
                ['bulk_meal_cancellation_batch_id', 'child_id', 'service_date'],
                'bulk_meal_cancel_batch_items_batch_child_date'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_meal_cancellation_batch_items');
        Schema::dropIfExists('bulk_meal_cancellation_batches');
    }
};
