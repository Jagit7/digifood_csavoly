<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingPartner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'billing_name',
        'tax_number',
        'billing_zip',
        'billing_city',
        'billing_address',
        'billing_email',
        'payment_due_days',
        'vat_rate',
        'invoice_note',
        'active',
    ];

    protected $casts = [
        'payment_due_days' => 'integer',
        'vat_rate' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    public function monthlyBillings(): HasMany
    {
        return $this->hasMany(PartnerMonthlyBilling::class);
    }
}
