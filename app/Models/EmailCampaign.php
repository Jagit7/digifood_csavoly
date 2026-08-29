<?php

namespace App\Models;

use App\Support\Html\CampaignHtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIALLY_FAILED = 'partially_failed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_CUSTOM = 'custom';
    public const TYPE_PARENT_ACTIVATION_INVITE = 'parent_activation_invite';
    public const TYPE_EMPLOYEE_ACTIVATION_INVITE = 'employee_activation_invite';

    protected $fillable = [
        'institution_id',
        'created_by',
        'type',
        'subject',
        'body',
        'status',
        'recipient_count',
        'sent_count',
        'failed_count',
        'queued_at',
        'completed_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Piszkozat',
            self::STATUS_QUEUED => 'Sorban',
            self::STATUS_SENDING => 'Küldés alatt',
            self::STATUS_COMPLETED => 'Befejezve',
            self::STATUS_PARTIALLY_FAILED => 'Részben sikertelen',
            self::STATUS_FAILED => 'Sikertelen',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_PARTIALLY_FAILED => 'badge-warning',
            self::STATUS_FAILED => 'badge-danger',
            self::STATUS_SENDING => 'badge-info',
            self::STATUS_QUEUED => 'badge-primary',
            default => 'badge-secondary',
        };
    }

    /**
     * A body mentéskor is tisztításra kerül (ld. EmailCampaignController /
     * ParentActivationInviteController store()), de ez az accessor egy
     * második védelmi vonal: minden helyen, ahol a kampány szövege
     * ténylegesen megjelenik (admin nézet, kiküldött e-mail), ezt kell
     * használni a nyers "body" helyett - így a fix életbe lépése előtt
     * már mentett kampányok is biztonságosan jelennek meg.
     */
    public function getSafeBodyAttribute(): string
    {
        return CampaignHtmlSanitizer::clean($this->body);
    }
}
