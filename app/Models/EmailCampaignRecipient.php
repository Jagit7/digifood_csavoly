<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignRecipient extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'email_campaign_id',
        'guardian_id',
        'institution_employee_id',
        'email',
        'recipient_name',
        'child_names',
        'class_group_names',
        'status',
        'sent_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'child_names' => 'array',
        'class_group_names' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(InstitutionEmployee::class, 'institution_employee_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Függőben',
            self::STATUS_QUEUED => 'Sorban',
            self::STATUS_SENDING => 'Küldés alatt',
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
            self::STATUS_SENDING => 'badge-info',
            self::STATUS_QUEUED => 'badge-primary',
            default => 'badge-secondary',
        };
    }

    public function childNamesList(): string
    {
        return collect($this->child_names)->filter()->implode(', ');
    }

    public function classGroupNamesList(): string
    {
        return collect($this->class_group_names)->filter()->implode(', ');
    }
}
