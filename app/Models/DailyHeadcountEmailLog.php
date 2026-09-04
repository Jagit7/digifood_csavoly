<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Egy osztály/csoport egy napi étkezési létszám e-mailjének küldési naplója.
 * Az (institution_id, group_name, headcount_date) egyediség biztosítja,
 * hogy egy adott napra egy osztály/csoport csak egyszer kapjon e-mailt,
 * még akkor is, ha a scheduler parancs percenként újra lefut - ld.
 * DispatchDailyHeadcountEmailsCommand.
 */
class DailyHeadcountEmailLog extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'institution_id',
        'group_name',
        'headcount_date',
        'status',
        'eaters_count',
        'recipient_emails',
        'subject',
        'scheduled_at',
        'sent_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'headcount_date' => 'date',
        'eaters_count' => 'integer',
        'recipient_emails' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
