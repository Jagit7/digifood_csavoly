<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionMealType extends Model
{
    protected $fillable = [
        'institution_id',
        'meal_type_id',
        'is_active',
        'is_parent_selectable',
        'is_required',
        'display_order',
        'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_parent_selectable' => 'boolean',
        'is_required' => 'boolean',
        'display_order' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function mealType(): BelongsTo
    {
        return $this->belongsTo(MealType::class);
    }

    public function mealPrices(): HasMany
    {
        return $this->hasMany(InstitutionMealPrice::class);
    }

    public function mealCheckIns(): HasMany
    {
        return $this->hasMany(MealCheckIn::class);
    }
}
