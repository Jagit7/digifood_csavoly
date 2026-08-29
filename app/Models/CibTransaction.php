<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CibTransaction extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_UNCERTAIN = 'uncertain';

    protected $fillable = [
        'institution_id',
        'parent_monthly_settlement_payment_id',
        'user_id',
        'guardian_id',
        'pid',
        'trid',
        'order_ref',
        'amount',
        'currency',
        'status',
        'init_rc',
        'init_rt',
        'final_rc',
        'final_rt',
        'anum',
        'last_message_type',
        'merchant_url',
        'customer_url',
        'return_url',
        'init_requested_at',
        'init_completed_at',
        'returned_at',
        'last_status_polled_at',
        'closed_at',
        'failed_at',
        'request_log',
        'response_log',
        'meta',
    ];

    protected $casts = [
        'amount' => 'integer',
        'init_requested_at' => 'datetime',
        'init_completed_at' => 'datetime',
        'returned_at' => 'datetime',
        'last_status_polled_at' => 'datetime',
        'closed_at' => 'datetime',
        'failed_at' => 'datetime',
        'request_log' => 'array',
        'response_log' => 'array',
        'meta' => 'array',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(ParentMonthlySettlementPayment::class, 'parent_monthly_settlement_payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
