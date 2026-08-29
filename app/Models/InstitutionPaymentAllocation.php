<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionPaymentAllocation extends Model
{
    public const TYPE_STATEMENT = 'statement';

    public const TYPE_UNAPPLIED = 'unapplied';

    protected $fillable = [
        'institution_payment_id',
        'monthly_payment_statement_id',
        'payment_component',
        'allocation_type',
        'amount',
        'allocated_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'allocated_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InstitutionPayment::class, 'institution_payment_id');
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PaymentObligation\MonthlyPaymentStatement::class, 'monthly_payment_statement_id');
    }
}
