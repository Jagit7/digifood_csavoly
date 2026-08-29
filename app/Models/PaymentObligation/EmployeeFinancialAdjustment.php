<?php

namespace App\Models\PaymentObligation;

use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFinancialAdjustment extends Model
{
    public const TYPE_OPENING_DEBT = 'opening_debt';
    public const TYPE_OPENING_CREDIT = 'opening_credit';
    public const TYPE_DEBT = 'debt';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_BILLING_CORRECTION = 'billing_correction';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'institution_id',
        'institution_employee_id',
        'type',
        'amount',
        'affects_invoice',
        'reference_year',
        'reference_month',
        'source_type',
        'source_id',
        'reason',
        'document_number',
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
        'reversed_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(InstitutionEmployee::class, 'institution_employee_id');
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
}
