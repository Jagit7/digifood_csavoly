<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkMealCancellationBatch extends Model
{
    public const MEAL_SCOPE_ALL_CONFIGURED = 'all_configured_meals';

    protected $fillable = [
        'institution_id',
        'created_by',
        'event_name',
        'meal_scope',
        'reason',
        'date_from',
        'date_to',
        'selected_children_count',
        'service_days_count',
        'planned_cancellation_count',
        'created_cancellation_count',
        'duplicate_count',
        'missing_meal_setting_count',
        'non_service_day_count',
        'deadline_blocked_count',
        'class_cancelled_count',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkMealCancellationBatchItem::class)
            ->orderBy('service_date')
            ->orderBy('child_name');
    }
}
