<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class StudentMealSetting extends Model
{
    public const MODE_INSTITUTION_DEFAULT = 'institution_default';
    public const MODE_PACKAGE = 'package';
    public const MODE_CUSTOM = 'custom';
    public const CLOSURE_REASON_TRANSFER = 'institution_transfer';
    public const CLOSURE_REASON_RELATION_ENDED = 'student_relation_ended';
    public const CLOSURE_REASON_CANCELLED = 'meal_service_cancelled';
    public const CLOSURE_REASON_OTHER = 'other';

    public const MODE_LABELS = [
        self::MODE_INSTITUTION_DEFAULT => 'Intézményi alapértelmezett',
        self::MODE_PACKAGE => 'Menücsomag',
        self::MODE_CUSTOM => 'Egyedi étkezések',
    ];

    public const CLOSURE_REASON_LABELS = [
        self::CLOSURE_REASON_TRANSFER => 'Intézményváltás',
        self::CLOSURE_REASON_RELATION_ENDED => 'Tanulói jogviszony megszűnése',
        self::CLOSURE_REASON_CANCELLED => 'Étkezés végleges lemondása',
        self::CLOSURE_REASON_OTHER => 'Egyéb',
    ];

    protected $fillable = [
        'student_id',
        'eater_type',
        'eater_id',
        'institution_id',
        'institution_meal_package_id',
        'mode',
        'valid_from',
        'valid_to',
        'created_by',
        'note',
        'closed_by',
        'closed_at',
        'closure_reason',
        'closure_note',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'created_by' => 'integer',
        'eater_id' => 'integer',
        'closed_by' => 'integer',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (StudentMealSetting $setting) {
            if (
                $setting->eater_type === null
                && $setting->eater_id === null
                && $setting->student_id !== null
            ) {
                $setting->eater_type = 'child';
                $setting->eater_id = $setting->student_id;
            }

            if (
                $setting->student_id === null
                && $setting->eater_type === 'child'
                && $setting->eater_id !== null
            ) {
                $setting->student_id = (int) $setting->eater_id;
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'student_id');
    }

    public function eater(): MorphTo
    {
        return $this->morphTo();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function mealPackage(): BelongsTo
    {
        return $this->belongsTo(InstitutionMealPackage::class, 'institution_meal_package_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentMealSettingItem::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function mealTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            InstitutionMealType::class,
            'student_meal_setting_items',
            'student_meal_setting_id',
            'institution_meal_type_id'
        )->withPivot('display_order')
            ->withTimestamps()
            ->orderByPivot('display_order');
    }

    public function modeLabel(): string
    {
        return self::MODE_LABELS[$this->mode] ?? $this->mode;
    }

    public function closureReasonLabel(): ?string
    {
        if (! filled($this->closure_reason)) {
            return null;
        }

        return self::CLOSURE_REASON_LABELS[$this->closure_reason] ?? $this->closure_reason;
    }

    public function wasClosedManually(): bool
    {
        return $this->closed_at !== null || filled($this->closure_reason) || $this->closed_by !== null;
    }

    public function isClosedForDate(\Illuminate\Support\Carbon|string $date): bool
    {
        $dateString = $date instanceof \Illuminate\Support\Carbon
            ? $date->toDateString()
            : (string) $date;

        return $this->valid_to !== null && $this->valid_to->toDateString() < $dateString;
    }

    public function scopeForEater($query, EloquentModel $eater)
    {
        return $query->where(function ($query) use ($eater) {
            $query->where('eater_type', $eater->getMorphClass())
                ->where('eater_id', $eater->getKey());

            /*
            * Védőháló: a saving() hook (ld. fent) minden mentésnél
            * kitölti az eater_type/eater_id mezőket a legacy
            * student_id-ból, de ha ez egy sornál mégsem történt meg
            * (pl. egy közvetlen DB-írás/import a modell mentési
            * eseményének megkerülésével, vagy egy, az eater-mezők
            * bevezetése körüli, valamiért ki nem töltött régi sor) -
            * anélkül az a sor szó szerint eltűnne minden eater-alapú
            * lekérdezésből (settingsForEater, currentSettingForEater,
            * stb.), és a felület úgy viselkedne, mintha a gyermeknek
            * nem is lenne étkezési beállítása, holott van. Ezért
            * gyermek eaternél a legacy student_id alapján is
            * engedjük az egyezést.
            */
            if ($eater instanceof Child) {
                $query->orWhere(function ($query) use ($eater) {
                    $query->whereNull('eater_type')
                        ->where('student_id', $eater->getKey());
                });
            }
        });
    }

    public function scopeActiveOn($query, \Illuminate\Support\Carbon|string $date)
    {
        $dateString = $date instanceof \Illuminate\Support\Carbon
            ? $date->toDateString()
            : (string) $date;

        return $query
            ->whereDate('valid_from', '<=', $dateString)
            ->where(function ($query) use ($dateString) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $dateString);
            });
    }

    public function scopeStartingAfter($query, \Illuminate\Support\Carbon|string $date)
    {
        $dateString = $date instanceof \Illuminate\Support\Carbon
            ? $date->toDateString()
            : (string) $date;

        return $query
            ->whereDate('valid_from', '>', $dateString)
            ->whereNull('closed_at')
            ->whereNull('closed_by')
            ->whereNull('closure_reason')
            ->where(function ($query) {
                $query->whereNull('valid_to')
                    ->orWhereColumn('valid_to', '>=', 'valid_from');
            });
    }
}
