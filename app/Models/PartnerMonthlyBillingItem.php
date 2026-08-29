<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerMonthlyBillingItem extends Model
{
    protected $fillable = [
        'partner_monthly_billing_id',
        'institution_id',
        'institution_name_snapshot',
        'child_count',
        'price_per_child',
        'fixed_monthly_fee',
        'minimum_monthly_fee',
        'net_amount',
        'calculation_description',
    ];

    protected $casts = [
        'child_count' => 'integer',
        'price_per_child' => 'decimal:2',
        'fixed_monthly_fee' => 'decimal:2',
        'minimum_monthly_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function monthlyBilling(): BelongsTo
    {
        return $this->belongsTo(PartnerMonthlyBilling::class, 'partner_monthly_billing_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
