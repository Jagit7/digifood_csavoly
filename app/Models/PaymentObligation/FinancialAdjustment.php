<?php

namespace App\Models\PaymentObligation;

use App\Models\Child;
use App\Models\Institution;
use App\Models\User;
use App\Support\Finance\PaymentComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAdjustment extends Model
{
    public const TYPE_OPENING_DEBT = 'opening_debt';
    public const TYPE_OPENING_CREDIT = 'opening_credit';
    public const TYPE_DEBT = 'debt';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_CANCELLATION_CREDIT = 'cancellation_credit';
    public const TYPE_BILLING_CORRECTION = 'billing_correction';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'institution_id',
        'child_id',
        'monthly_payment_statement_id',
        'type',
        'payment_component',
        'amount',
        'affects_invoice',
        'reference_year',
        'reference_month',
        'source_type',
        'source_id',
        'reason',
        'document_number',
        'entry_date',
        'created_by',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'integer',
        'affects_invoice' => 'boolean',
        'reference_year' => 'integer',
        'reference_month' => 'integer',
        'entry_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(MonthlyPaymentStatement::class, 'monthly_payment_statement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public static function componentOptions(): array
    {
        return [
            PaymentComponent::FOUNDATION => PaymentComponent::labels()[PaymentComponent::FOUNDATION],
            PaymentComponent::KINDERGARTEN => PaymentComponent::labels()[PaymentComponent::KINDERGARTEN],
        ];
    }
}
