<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_payment_statements', function (Blueprint $table) {
            $table->string('payment_model', 40)->default('legacy')->after('discount_id');
            $table->unsignedInteger('planned_meal_days')->default(0)->after('payment_model');
            $table->unsignedInteger('previous_month_cancelled_days')->default(0)->after('planned_meal_days');
            $table->unsignedBigInteger('foundation_gross_amount')->default(0)->after('previous_month_cancelled_days');
            $table->unsignedBigInteger('foundation_cancellation_credit')->default(0)->after('foundation_gross_amount');
            $table->bigInteger('foundation_billing_adjustment_amount')->default(0)->after('foundation_cancellation_credit');
            $table->bigInteger('foundation_invoiceable_amount')->default(0)->after('foundation_billing_adjustment_amount');
            $table->bigInteger('foundation_previous_balance')->default(0)->after('foundation_invoiceable_amount');
            $table->bigInteger('foundation_total_payable')->default(0)->after('foundation_previous_balance');
            $table->unsignedBigInteger('kindergarten_gross_amount')->default(0)->after('foundation_total_payable');
            $table->unsignedBigInteger('kindergarten_discount_amount')->default(0)->after('kindergarten_gross_amount');
            $table->unsignedBigInteger('kindergarten_cancellation_credit')->default(0)->after('kindergarten_discount_amount');
            $table->bigInteger('kindergarten_billing_adjustment_amount')->default(0)->after('kindergarten_cancellation_credit');
            $table->bigInteger('kindergarten_invoiceable_amount')->default(0)->after('kindergarten_billing_adjustment_amount');
            $table->bigInteger('kindergarten_previous_balance')->default(0)->after('kindergarten_invoiceable_amount');
            $table->bigInteger('kindergarten_total_payable')->default(0)->after('kindergarten_previous_balance');
            $table->json('calculation_snapshot')->nullable()->after('issues');
        });

        Schema::table('monthly_payment_days', function (Blueprint $table) {
            $table->unsignedBigInteger('foundation_daily_fee')->default(0)->after('payable_amount');
            $table->unsignedBigInteger('foundation_payable_amount')->default(0)->after('foundation_daily_fee');
            $table->unsignedBigInteger('kindergarten_daily_fee')->default(0)->after('foundation_payable_amount');
            $table->unsignedTinyInteger('kindergarten_discount_percent')->default(0)->after('kindergarten_daily_fee');
            $table->unsignedBigInteger('kindergarten_discount_amount')->default(0)->after('kindergarten_discount_percent');
            $table->unsignedBigInteger('kindergarten_payable_amount')->default(0)->after('kindergarten_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_payment_days', function (Blueprint $table) {
            $table->dropColumn([
                'foundation_daily_fee',
                'foundation_payable_amount',
                'kindergarten_daily_fee',
                'kindergarten_discount_percent',
                'kindergarten_discount_amount',
                'kindergarten_payable_amount',
            ]);
        });

        Schema::table('monthly_payment_statements', function (Blueprint $table) {
            $table->dropColumn([
                'payment_model',
                'planned_meal_days',
                'previous_month_cancelled_days',
                'foundation_gross_amount',
                'foundation_cancellation_credit',
                'foundation_billing_adjustment_amount',
                'foundation_invoiceable_amount',
                'foundation_previous_balance',
                'foundation_total_payable',
                'kindergarten_gross_amount',
                'kindergarten_discount_amount',
                'kindergarten_cancellation_credit',
                'kindergarten_billing_adjustment_amount',
                'kindergarten_invoiceable_amount',
                'kindergarten_previous_balance',
                'kindergarten_total_payable',
                'calculation_snapshot',
            ]);
        });
    }
};
