<?php

namespace App\Models\PaymentObligation;

use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionMealPackage;
use App\Models\User;
use App\Support\Finance\PaymentComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MonthlyPaymentStatement extends Model
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
        'child_id',
        'year',
        'month',
        'meal_package_id',
        'discount_id',
        'payment_model',
        'planned_meal_days',
        'previous_month_cancelled_days',
        'meal_amount',
        'previous_cancellation_credit',
        'billing_adjustment_amount',
        'invoiceable_amount',
        'previous_balance',
        'total_payable',
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
        'status',
        'issues',
        'calculation_snapshot',
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
        'planned_meal_days' => 'integer',
        'previous_month_cancelled_days' => 'integer',
        'meal_amount' => 'integer',
        'previous_cancellation_credit' => 'integer',
        'billing_adjustment_amount' => 'integer',
        'invoiceable_amount' => 'integer',
        'previous_balance' => 'integer',
        'total_payable' => 'integer',
        'foundation_gross_amount' => 'integer',
        'foundation_cancellation_credit' => 'integer',
        'foundation_billing_adjustment_amount' => 'integer',
        'foundation_invoiceable_amount' => 'integer',
        'foundation_previous_balance' => 'integer',
        'foundation_total_payable' => 'integer',
        'kindergarten_gross_amount' => 'integer',
        'kindergarten_discount_amount' => 'integer',
        'kindergarten_cancellation_credit' => 'integer',
        'kindergarten_billing_adjustment_amount' => 'integer',
        'kindergarten_invoiceable_amount' => 'integer',
        'kindergarten_previous_balance' => 'integer',
        'kindergarten_total_payable' => 'integer',
        'issues' => 'array',
        'calculation_snapshot' => 'array',
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

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
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
        return $this->hasMany(MonthlyPaymentDay::class)
            ->orderBy('date');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(InstitutionInvoice::class, 'monthly_payment_statement_id')
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(InstitutionInvoice::class, 'monthly_payment_statement_id');
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function usesSplitPaymentModel(): bool
    {
        return $this->payment_model === 'split_manual_transfer';
    }

    public function componentTotalPayable(string $component): int
    {
        return match ($component) {
            PaymentComponent::FOUNDATION => (int) $this->foundation_total_payable,
            PaymentComponent::KINDERGARTEN => (int) $this->kindergarten_total_payable,
            default => (int) $this->total_payable,
        };
    }
}
