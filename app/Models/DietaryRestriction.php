<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DietaryRestriction extends Model
{
    public const TYPE_ALLERGEN = 'allergen';

    public const TYPE_INTOLERANCE = 'intolerance';

    protected $fillable = [
        'institution_id', 'name', 'type', 'active', 'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            InstitutionEmployee::class,
            'institution_employee_dietary_restriction'
        )->withTimestamps();
    }
}
