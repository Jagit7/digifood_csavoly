<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImport extends Model
{
    public const PROFILE_SCHOOL_STANDARD = 'school_standard';
    public const PROFILE_SCHOOL_CUSTOM = 'school_custom';
    public const PROFILE_KINDERGARTEN = 'kindergarten';

    protected $fillable = [
        'institution_id', 'created_by', 'profile', 'status', 'original_file_name',
        'stored_file_path', 'file_size', 'group_name', 'school_year', 'row_count',
        'created_count', 'updated_count', 'skipped_count', 'error_count', 'errors',
        'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'completed_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Ld. imports/index.blade.php és imports/show.blade.php - korábban itt
    // mindenhol be volt égetve az "Iskolai Excel sablon" felirat, ez most
    // profilonként helyes címkét ad (pl. óvodai importnál).
    public function getProfileLabelAttribute(): string
    {
        return match ($this->profile) {
            self::PROFILE_SCHOOL_STANDARD => 'Iskolai Excel sablon',
            self::PROFILE_SCHOOL_CUSTOM => 'Egyedi iskolai Excel',
            self::PROFILE_KINDERGARTEN => 'Óvodai Excel',
            default => 'Ismeretlen importtípus',
        };
    }
}
