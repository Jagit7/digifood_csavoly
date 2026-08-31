<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionAdminInvitation extends Model
{
    public const EXPIRES_IN_HOURS = 24;

    protected $fillable = [
        'institution_id',
        'invited_by',
        'name',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return ! $this->isAccepted() && $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
