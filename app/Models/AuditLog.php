<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const ACTION_INSTITUTION_CREATED = 'institution_created';
    public const ACTION_INSTITUTION_UPDATED = 'institution_updated';
    public const ACTION_INSTITUTION_DELETED = 'institution_deleted';
    public const ACTION_INSTITUTION_STATUS_CHANGED = 'institution_status_changed';
    public const ACTION_INSTITUTION_RESTORED = 'institution_restored';
    public const ACTION_PARTNER_BILLING_STATUS_CHANGED = 'partner_billing_status_changed';
    public const ACTION_INSTITUTION_PAYMENT_CREATED = 'institution_payment_created';
    public const ACTION_INSTITUTION_PAYMENT_UPDATED = 'institution_payment_updated';
    public const ACTION_INSTITUTION_PAYMENT_DELETED = 'institution_payment_deleted';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'institution_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
