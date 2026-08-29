<?php

namespace App\Services\PaymentObligation;

use App\Models\Institution;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EmployeeMonthlyPaymentStatementListService
{
    public function query(Institution $institution, Carbon $period, array $filters = []): Builder
    {
        return EmployeeMonthlyPaymentStatement::query()
            ->with([
                'employee.discountType',
                'mealPackage',
            ])
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->when($this->filled($filters, 'search'), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->whereHas('employee', function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($this->filled($filters, 'meal_package_id'), fn (Builder $query) => $query->where('meal_package_id', (int) $filters['meal_package_id']))
            ->when($this->filled($filters, 'discount_type_id'), fn (Builder $query) => $query->where('discount_id', (int) $filters['discount_type_id']))
            ->when(($filters['meal_status'] ?? null) === 'participant', fn (Builder $query) => $query->where('meal_amount', '>', 0))
            ->when(($filters['meal_status'] ?? null) === 'non_participant', fn (Builder $query) => $query->where('meal_amount', 0))
            ->when(($filters['payment_status'] ?? null) === 'draft', fn (Builder $query) => $query->where('status', EmployeeMonthlyPaymentStatement::STATUS_DRAFT))
            ->when(($filters['payment_status'] ?? null) === 'closed', fn (Builder $query) => $query->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED))
            ->when(($filters['payment_status'] ?? null) === 'payable', fn (Builder $query) => $query->where('total_payable', '>', 0))
            ->when(($filters['payment_status'] ?? null) === 'zero', fn (Builder $query) => $query->where('invoiceable_amount', '<=', 0))
            ->orderBy('institution_employee_id');
    }

    private function filled(array $filters, string $key): bool
    {
        return array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '';
    }
}
