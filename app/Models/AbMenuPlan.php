<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbMenuPlan extends Model
{
    protected $fillable = [
        'institution_id',
        'title',
        'valid_from',
        'valid_to',
        'active',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'valid_from'   => 'date',
        'valid_to'     => 'date',
        'published_at' => 'datetime',
        'active'       => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AbMenuItem::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(AbMenuSelectionNotificationLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}