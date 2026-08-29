<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParentMonthlySettlementPayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'guardian_id',
        'year',
        'month',
        'reference',
        'idempotency_key',
        'total_amount',
        'payment_method',
        'status',
        'transaction_reference',
        'paid_at',
        'failed_at',
        'refunded_at',
        'receipt_url',
        'note',
        'metadata',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_amount' => 'integer',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function statusMeta(?string $status): array
    {
        return match ($status) {
            self::STATUS_PENDING => ['label' => 'Folyamatban', 'class' => 'bg-warning text-dark'],
            self::STATUS_COMPLETED => ['label' => 'Sikeres', 'class' => 'bg-success'],
            self::STATUS_FAILED => ['label' => 'Sikertelen', 'class' => 'bg-danger'],
            self::STATUS_REFUNDED => ['label' => 'Visszatérítve', 'class' => 'bg-info text-dark'],
            self::STATUS_PARTIALLY_REFUNDED => ['label' => 'Részben visszatérítve', 'class' => 'bg-primary'],
            self::STATUS_CANCELLED => ['label' => 'Megszakítva', 'class' => 'bg-secondary'],
            default => ['label' => 'Nincs megadva', 'class' => 'bg-light text-muted border'],
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ParentMonthlySettlementPaymentItem::class);
    }

    public function cibTransactions(): HasMany
    {
        return $this->hasMany(CibTransaction::class);
    }
}
