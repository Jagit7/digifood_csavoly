<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClassGroup extends Model
{
    protected $fillable = [
        'institution_id', 'school_year_id', 'name', 'grade_level', 'section', 'group_type', 'active',
    ];

    protected $casts = [
        'grade_level' => 'integer',
        'active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'class_group_memberships')
            ->withPivot(['status', 'joined_on', 'left_on'])
            ->withTimestamps();
    }
}
