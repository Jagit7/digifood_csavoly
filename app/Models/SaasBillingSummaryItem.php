<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasBillingSummaryItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'saas_billing_summary_run_id',
        'institution_id',
        'institution_name_snapshot',
        'billing_tax_number_snapshot',
        'billing_address_snapshot',
        'children_count',
        'employees_count',
        'eaters_count',
        'rate',
        'amount',
        'status',
        'invoice_number',
        'invoiced_at',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'children_count' => 'integer',
        'employees_count' => 'integer',
        'eaters_count' => 'integer',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'invoiced_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SaasBillingSummaryRun::class, 'saas_billing_summary_run_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Küldve, számlázásra vár',
            self::STATUS_INVOICED => 'Számlázva',
            self::STATUS_PAID => 'Fizetve',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-secondary',
            self::STATUS_INVOICED => 'badge-info',
            self::STATUS_PAID => 'badge-success',
            default => 'badge-secondary',
        };
    }
}
