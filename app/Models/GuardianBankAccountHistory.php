<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardianBankAccountHistory extends Model
{
    protected $fillable = [
        'guardian_id',
        'bank_account_holder',
        'bank_account_number',
        'changed_by_user_id',
        'change_source',
        'change_reason',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'bank_account_holder' => 'encrypted',
        'bank_account_number' => 'encrypted',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
