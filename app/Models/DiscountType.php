<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountType extends Model
{
    protected $fillable = [
        'institution_id', 'name', 'percentage', 'active', 'sort_order',
    ];

    protected $casts = [
        'percentage' => 'integer',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function childDiscountPeriods(): HasMany
    {
        return $this->hasMany(ChildDiscountPeriod::class);
    }
}
