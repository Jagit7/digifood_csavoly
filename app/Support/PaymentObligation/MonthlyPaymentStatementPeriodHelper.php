<?php

namespace App\Support\PaymentObligation;

use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class MonthlyPaymentStatementPeriodHelper
{
    public function fromMonth(CarbonInterface $paymentPeriod): array
    {
        $paymentPeriod = Carbon::instance($paymentPeriod)->copy()->startOfMonth();
        $mealPeriod = $paymentPeriod->copy()->addMonth();
        $creditPeriod = $paymentPeriod->copy()->subMonth();

        return [
            'payment_period' => $paymentPeriod,
            'meal_period' => $mealPeriod,
            'credit_period' => $creditPeriod,
            'payment_period_label' => $paymentPeriod->translatedFormat('Y. F'),
            'meal_period_label' => $mealPeriod->translatedFormat('Y. F'),
            'credit_period_label' => $creditPeriod->translatedFormat('Y. F'),
            'payment_period_query' => $paymentPeriod->format('Y-m'),
            'meal_period_query' => $mealPeriod->format('Y-m'),
            'credit_period_query' => $creditPeriod->format('Y-m'),
            'meal_period_days_in_month' => $mealPeriod->daysInMonth,
        ];
    }

    public function fromStatement(MonthlyPaymentStatement $statement): array
    {
        return $this->fromMonth(Carbon::create($statement->year, $statement->month, 1));
    }
}
