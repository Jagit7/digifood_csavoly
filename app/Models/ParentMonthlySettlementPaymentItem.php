<?php

namespace App\Models;

use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentMonthlySettlementPaymentItem extends Model
{
    protected $fillable = [
        'parent_monthly_settlement_payment_id',
        'child_id',
        'monthly_payment_statement_id',
        'amount',
        'paid_amount',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_amount' => 'integer',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ParentMonthlySettlementPayment::class, 'parent_monthly_settlement_payment_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(MonthlyPaymentStatement::class, 'monthly_payment_statement_id');
    }
}
