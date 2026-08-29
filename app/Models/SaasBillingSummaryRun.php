<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasBillingSummaryRun extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const TRIGGERED_BY_SCHEDULE = 'schedule';
    public const TRIGGERED_BY_MANUAL = 'manual';

    protected $fillable = [
        'year',
        'month',
        'institution_count',
        'total_amount',
        'status',
        'triggered_by',
        'triggered_by_user_id',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'institution_count' => 'integer',
        'total_amount' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaasBillingSummaryItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Elküldve',
            self::STATUS_FAILED => 'Sikertelen',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'badge-success',
            self::STATUS_FAILED => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    public function getMonthLabelAttribute(): string
    {
        $months = [
            1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
            5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
            9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
        ];

        return sprintf('%d. %s', $this->year, $months[$this->month] ?? $this->month);
    }
}
