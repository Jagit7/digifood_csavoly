<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Osztályonkénti/csoportonkénti ki-/bekapcsoló a "Napi létszám e-mailek"
 * funkcióhoz. A group_name ugyanaz a mező, amit a Child::group_name és a
 * DailyMealHeadcountService is használ - lásd DailyHeadcountEmailService.
 */
class InstitutionDailyHeadcountEmailGroupSetting extends Model
{
    protected $fillable = [
        'institution_id',
        'group_name',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
