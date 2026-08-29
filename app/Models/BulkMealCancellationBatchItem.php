<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkMealCancellationBatchItem extends Model
{
    public const RESULT_CREATED = 'created';

    public const RESULT_ALREADY_CANCELLED = 'already_cancelled';

    public const RESULT_NO_ACTIVE_MEAL = 'no_active_meal';

    public const RESULT_NON_SERVICE_DAY = 'non_service_day';

    public const RESULT_DEADLINE_BLOCKED = 'deadline_blocked';

    public const RESULT_CLASS_CANCELLED = 'class_cancelled';

    protected $fillable = [
        'bulk_meal_cancellation_batch_id',
        'child_id',
        'meal_cancellation_id',
        'service_date',
        'child_name',
        'group_name',
        'grade_label',
        'result_code',
        'result_label',
    ];

    protected $casts = [
        'service_date' => 'date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BulkMealCancellationBatch::class, 'bulk_meal_cancellation_batch_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function cancellation(): BelongsTo
    {
        return $this->belongsTo(MealCancellation::class, 'meal_cancellation_id');
    }
}
