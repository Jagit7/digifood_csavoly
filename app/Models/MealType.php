<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'default_order',
    ];

    protected $casts = [
        'default_order' => 'integer',
    ];

    public function institutionMealTypes(): HasMany
    {
        return $this->hasMany(InstitutionMealType::class);
    }
}
