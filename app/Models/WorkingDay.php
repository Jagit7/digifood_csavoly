<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingDay extends Model
{
    protected $fillable = [
        'institution_id',
        'date',
        'name',
        'type',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}