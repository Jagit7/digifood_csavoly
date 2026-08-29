<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_monthly_payment_days', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_meal_cancellation_id')
                ->nullable()
                ->after('payable_amount');
            $table->foreign('employee_meal_cancellation_id', 'emp_day_meal_cancel_fk')
                ->references('id')
                ->on('employee_meal_cancellations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_monthly_payment_days', function (Blueprint $table) {
            $table->dropForeign(['employee_meal_cancellation_id']);
            $table->dropColumn('employee_meal_cancellation_id');
        });
    }
};
