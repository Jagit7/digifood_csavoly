<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutionMealPrice extends Model
{
    protected $fillable = [
        'institution_meal_type_id',
        'price',
        'valid_from',
        'valid_to',
        'created_by',
        'note',
    ];

    protected $casts = [
        'price' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'created_by' => 'integer',
    ];

    public function institutionMealType(): BelongsTo
    {
        return $this->belongsTo(InstitutionMealType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
