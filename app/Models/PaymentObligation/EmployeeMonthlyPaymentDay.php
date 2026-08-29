<?php

namespace App\Models\PaymentObligation;

use App\Models\EmployeeMealCancellation;
use App\Models\SchoolBreak;
use App\Models\User;
use App\Models\WorkingDay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMonthlyPaymentDay extends Model
{
    public const STATUS_PAYABLE = 'payable';
    public const STATUS_WEEKEND = 'weekend';
    public const STATUS_SCHOOL_BREAK = 'school_break';
    public const STATUS_NO_ACTIVE_MEAL = 'no_active_meal';
    public const STATUS_WORKING_SATURDAY = 'working_saturday';
    public const STATUS_FREE_MEAL = 'free_meal';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_monthly_payment_statement_id',
        'date',
        'status',
        'original_daily_price',
        'discount_percent',
        'payable_amount',
        'employee_meal_cancellation_id',
        'school_break_id',
        'working_day_id',
        'manually_modified',
        'original_status',
        'original_payable_amount',
        'modification_reason',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'date' => 'date',
        'original_daily_price' => 'integer',
        'discount_percent' => 'integer',
        'payable_amount' => 'integer',
        'manually_modified' => 'boolean',
        'original_payable_amount' => 'integer',
        'modified_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(EmployeeMonthlyPaymentStatement::class, 'employee_monthly_payment_statement_id');
    }

    public function schoolBreak(): BelongsTo
    {
        return $this->belongsTo(SchoolBreak::class);
    }

    public function employeeMealCancellation(): BelongsTo
    {
        return $this->belongsTo(EmployeeMealCancellation::class);
    }

    public function workingDay(): BelongsTo
    {
        return $this->belongsTo(WorkingDay::class);
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
