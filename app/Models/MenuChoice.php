<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class MenuChoice extends Model
{
    public const CHOICE_A = 'A';

    public const CHOICE_B = 'B';

    protected $fillable = [
        'institution_id',
        'child_id',
        'eater_type',
        'eater_id',
        'ab_menu_item_id',
        'menu_date',
        'choice',
        'selected_by',
    ];

    protected $casts = [
        'menu_date' => 'date',
        'selected_by' => 'integer',
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

    /**
     * A dolgozói (tanári) A/B menüválasztáshoz - ld. a migráció leírását
     * (2026_08_26_190100_add_eater_columns_to_menu_choices_table). A
     * meglévő gyermek-alapú kód (AbMenuSelectionService,
     * ParentMenuChoiceController, admin MenuChoiceController stb.)
     * továbbra is a child_id oszlopot és a child() relációt használja
     * változatlanul - ez a reláció és a scopeForEater() csak az új,
     * dolgozói választásokhoz készül.
     */
    public function eater(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForEater($query, EloquentModel $eater)
    {
        return $query
            ->where('eater_type', $eater->getMorphClass())
            ->where('eater_id', $eater->getKey());
    }

    public function abMenuItem(): BelongsTo
    {
        return $this->belongsTo(AbMenuItem::class);
    }

    public function selector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }
}