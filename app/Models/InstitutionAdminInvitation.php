<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionAdminInvitation extends Model
{
    protected $fillable = [
        'institution_id',
        'invited_by',
        'name',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}