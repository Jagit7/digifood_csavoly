<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMealSettingItem extends Model
{
    protected $fillable = [
        'student_meal_setting_id',
        'institution_meal_type_id',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function mealSetting(): BelongsTo
    {
        return $this->belongsTo(StudentMealSetting::class, 'student_meal_setting_id');
    }

    public function institutionMealType(): BelongsTo
    {
        return $this->belongsTo(InstitutionMealType::class);
    }
}
