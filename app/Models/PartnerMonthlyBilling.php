<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerMonthlyBilling extends Model
{
    protected $fillable = [
        'billing_partner_id',
        'billing_month',
        'total_children',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'status',
        'invoice_number',
        'invoiced_at',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'billing_month' => 'date',
        'total_children' => 'integer',
        'net_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'invoiced_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function billingPartner(): BelongsTo
    {
        return $this->belongsTo(BillingPartner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartnerMonthlyBillingItem::class);
    }
}
