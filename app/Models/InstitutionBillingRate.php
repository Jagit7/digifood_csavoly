<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionBillingRate extends Model
{
    protected $fillable = [
        'institution_id',
        'price_per_child',
        'fixed_monthly_fee',
        'minimum_monthly_fee',
        'valid_from',
        'valid_to',
        'note',
    ];

    protected $casts = [
        'price_per_child' => 'decimal:2',
        'fixed_monthly_fee' => 'decimal:2',
        'minimum_monthly_fee' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
