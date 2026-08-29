<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealCancellation extends Model
{
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_PARENT = 'parent';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'institution_id',
        'child_id',
        'service_date',
        'source',
        'status',
        'reason',
        'created_by',
        'revoked_by',
        'revoked_at',
    ];

    protected $casts = [
        'service_date' => 'date',
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

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
