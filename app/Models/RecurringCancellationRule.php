<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringCancellationRule extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'institution_id',
        'child_id',
        'weekday',
        'starts_on',
        'ends_on',
        'source',
        'status',
        'reason',
        'created_by',
        'revoked_by',
        'revoked_at',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'revoked_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
