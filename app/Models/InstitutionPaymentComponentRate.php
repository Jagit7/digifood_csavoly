<?php

namespace App\Models;

use App\Support\Finance\PaymentComponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionPaymentComponentRate extends Model
{
    protected $fillable = [
        'institution_id',
        'component',
        'amount',
        'valid_from',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'valid_from' => 'date',
    ];

    public static function componentOptions(): array
    {
        return [
            PaymentComponent::FOUNDATION => PaymentComponent::labels()[PaymentComponent::FOUNDATION],
            PaymentComponent::KINDERGARTEN => PaymentComponent::labels()[PaymentComponent::KINDERGARTEN],
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
