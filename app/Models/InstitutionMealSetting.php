<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionMealSetting extends Model
{
    protected $fillable = [
        'institution_id', 'cancellation_hour', 'cancellation_minute',
    ];

    protected $casts = [
        'cancellation_hour' => 'integer',
        'cancellation_minute' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
