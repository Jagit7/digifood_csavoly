<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Egy osztály/csoport egy e-mail címzettje a "Napi létszám e-mailek"
 * funkcióhoz. Egy osztályhoz/csoporthoz tetszőlegesen sok sor tartozhat -
 * ld. DailyHeadcountEmailService::syncGroupRecipients().
 */
class InstitutionDailyHeadcountEmailRecipient extends Model
{
    protected $fillable = [
        'institution_id',
        'group_name',
        'email',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
