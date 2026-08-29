<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MealCheckIn extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DUPLICATE = 'duplicate';

    protected $fillable = [
        'institution_id',
        'child_id',
        'eater_type',
        'eater_id',
        'institution_meal_type_id',
        'menu_choice',
        'service_date',
        'scanned_at',
        'barcode_token_suffix',
        'kiosk_user_id',
        'status',
        'rejection_reason',
        'success_key',
    ];

    protected $casts = [
        'service_date' => 'date',
        'scanned_at' => 'datetime',
        'eater_id' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function eater(): MorphTo
    {
        return $this->morphTo();
    }

    public function mealType(): BelongsTo
    {
        return $this->belongsTo(InstitutionMealType::class, 'institution_meal_type_id');
    }

    public function kioskUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kiosk_user_id');
    }

    public function scopeForEater($query, EloquentModel $eater)
    {
        return $query->where('eater_type', $eater->getMorphClass())
            ->where('eater_id', $eater->getKey());
    }
}
