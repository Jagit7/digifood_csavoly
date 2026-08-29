<?php

namespace App\Models\PaymentObligation;

use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeMonthlyPaymentStatement extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_SENT_TO_INVOICING = 'sent_to_invoicing';
    public const STATUS_INVOICED = 'invoiced';
    public const INVOICE_PROVIDER_BILLINGO = 'billingo';
    public const INVOICE_PROVIDER_MANUAL = 'manual';
    public const INVOICE_PROVIDER_SZAMLAZZ_HU = 'szamlazz_hu';
    public const INVOICE_STATUS_DRAFT = 'draft';
    public const INVOICE_STATUS_ISSUED = 'issued';
    public const INVOICE_STATUS_CANCELLED = 'cancelled';
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'institution_id',
        'institution_employee_id',
        'year',
        'month',
        'meal_package_id',
        'discount_id',
        'meal_amount',
        'previous_cancellation_credit',
        'billing_adjustment_amount',
        'invoiceable_amount',
        'previous_balance',
        'total_payable',
        'due_date',
        'status',
        'issues',
        'calculated_at',
        'reviewed_at',
        'reviewed_by',
        'closed_at',
        'closed_by',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'invoice_number',
        'invoice_provider',
        'invoice_status',
        'invoice_url',
        'invoice_pdf_path',
        'invoiced_at',
        'payment_status',
        'paid_at',
        'payment_reference',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'meal_amount' => 'integer',
        'previous_cancellation_credit' => 'integer',
        'billing_adjustment_amount' => 'integer',
        'invoiceable_amount' => 'integer',
        'previous_balance' => 'integer',
        'total_payable' => 'integer',
        'due_date' => 'date',
        'issues' => 'array',
        'calculated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(InstitutionEmployee::class, 'institution_employee_id');
    }

    public function mealPackage(): BelongsTo
    {
        return $this->belongsTo(InstitutionMealPackage::class, 'meal_package_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(DiscountType::class, 'discount_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function days(): HasMany
    {
        return $this->hasMany(EmployeeMonthlyPaymentDay::class)
            ->orderBy('date');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
