<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbMenuItem extends Model
{
    protected $fillable = [
        'ab_menu_plan_id',
        'menu_date',
        'menu_a',
        'menu_b',
        'menu_dietary',
        'allergens',
        'note',
    ];

    protected $casts = [
        'menu_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AbMenuPlan::class, 'ab_menu_plan_id');
    }

    public function menuChoices(): HasMany
    {
        return $this->hasMany(MenuChoice::class);
    }
}
