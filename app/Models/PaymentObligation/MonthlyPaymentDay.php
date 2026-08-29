<?php

namespace App\Models\PaymentObligation;

use App\Models\ClassCancellation;
use App\Models\MealCancellation;
use App\Models\SchoolBreak;
use App\Models\User;
use App\Models\WorkingDay;
use App\Support\PaymentObligation\MonthlyPaymentDayStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyPaymentDay extends Model
{
    public const STATUS_PAY = 'PAY';
    public const STATUS_CANCELLED_IN_ADVANCE = 'CANCELLED_IN_ADVANCE';
    public const STATUS_CANCELLED_AFTER_CLOSING = 'CANCELLED_AFTER_CLOSING';
    public const STATUS_SCHOOL_BREAK = 'SCHOOL_BREAK';
    public const STATUS_CLASS_CANCELLATION = 'CLASS_CANCELLATION';
    public const STATUS_WEEKEND = 'WEEKEND';
    public const STATUS_WORKING_SATURDAY = 'WORKING_SATURDAY';
    public const STATUS_NO_ACTIVE_MEAL = 'NO_ACTIVE_MEAL';
    public const STATUS_ABSENCE = 'ABSENCE';
    public const STATUS_NO_VALID_PRICE = 'NO_VALID_PRICE';
    public const STATUS_FREE_MEAL = 'FREE_MEAL';
    public const STATUS_MANUALLY_MODIFIED = 'MANUALLY_MODIFIED';

    protected $fillable = [
        'monthly_payment_statement_id',
        'date',
        'status',
        'original_daily_price',
        'discount_percent',
        'payable_amount',
        'foundation_daily_fee',
        'foundation_payable_amount',
        'kindergarten_daily_fee',
        'kindergarten_discount_percent',
        'kindergarten_discount_amount',
        'kindergarten_payable_amount',
        'cancellation_id',
        'school_break_id',
        'class_cancellation_id',
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
        'foundation_daily_fee' => 'integer',
        'foundation_payable_amount' => 'integer',
        'kindergarten_daily_fee' => 'integer',
        'kindergarten_discount_percent' => 'integer',
        'kindergarten_discount_amount' => 'integer',
        'kindergarten_payable_amount' => 'integer',
        'manually_modified' => 'boolean',
        'original_payable_amount' => 'integer',
        'modified_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(MonthlyPaymentStatement::class, 'monthly_payment_statement_id');
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(MealCancellation::class, 'cancellation_id');
    }

    public function schoolBreak(): BelongsTo
    {
        return $this->belongsTo(SchoolBreak::class);
    }

    public function classCancellation(): BelongsTo
    {
        return $this->belongsTo(ClassCancellation::class);
    }

    public function workingDay(): BelongsTo
    {
        return $this->belongsTo(WorkingDay::class);
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function statusMeta(?string $status = null): array
    {
        return MonthlyPaymentDayStatus::meta($status ?? $this->status);
    }

    public function originalStatusMeta(): array
    {
        return MonthlyPaymentDayStatus::meta($this->original_status ?? $this->status);
    }
}
