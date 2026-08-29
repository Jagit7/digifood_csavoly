<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMealCancellation extends Model
{
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_SYSTEM = 'system';

    // A dolgozói portál önkiszolgáló lemondásainak forrása - ld.
    // App\Models\MealCancellation::SOURCE_PARENT párja. Ez különbözteti meg
    // az admin által rögzített lemondástól: csak a SOURCE_EMPLOYEE forrású,
    // saját maga által létrehozott lemondását vonhatja vissza a dolgozó (ld.
    // EmployeeMealCancellationService::revokeAsEmployee()).
    public const SOURCE_EMPLOYEE = 'employee';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'institution_id',
        'institution_employee_id',
        'service_date',
        'source',
        'status',
        'reason',
        'created_by',
        'revoked_by',
        'revoked_at',
    ];

    protected $casts = [
        'service_date' => 'date',
        'revoked_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(InstitutionEmployee::class, 'institution_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
