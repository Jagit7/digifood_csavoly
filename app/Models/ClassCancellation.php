<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCancellation extends Model
{
    protected $fillable = [
        'institution_id',
        'class_group_id',
        'date_from',
        'date_to',
        'reason',
        'affected_children_count',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
        'affected_children_count' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
