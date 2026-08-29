<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionMealPackageItem extends Model
{
    protected $fillable = [
        'institution_meal_package_id',
        'institution_meal_type_id',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function mealPackage(): BelongsTo
    {
        return $this->belongsTo(InstitutionMealPackage::class, 'institution_meal_package_id');
    }

    public function institutionMealType(): BelongsTo
    {
        return $this->belongsTo(InstitutionMealType::class);
    }
}
