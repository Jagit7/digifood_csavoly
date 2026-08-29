<?php

namespace App\Models;

use App\Models\PaymentObligation\FinancialAdjustment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Child extends Model
{
    protected $fillable = [
        'institution_id',
        'discount_type_id',
        'name',
        'educational_identifier',
        'group_name',
        'school_year',
        'source_type',
        'active',
        'data_verified_at',
        'data_verified_by',
        'barcode_token',
        'barcode_generated_at',
        'barcode_disabled_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'data_verified_at' => 'datetime',
        // A vonalkód-token titkosítva tárolódik (fizikai vonalkód-
        // hamisítás elleni védelem egy DB-dump kiszivárgása esetén). A
        // pontos egyezés szerinti keresés emiatt NEM ezen a mezőn, hanem
        // a barcode_token_hash (determinisztikus SHA-256) oszlopon
        // keresztül történik - ld. EaterBarcodeService / MealEligibilityService.
        'barcode_token' => 'encrypted',
        'barcode_generated_at' => 'datetime',
        'barcode_disabled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Child $child) {
            if ($child->discount_type_id || ! $child->institution_id) {
                return;
            }

            $defaultDiscount = DiscountType::firstOrCreate(
                [
                    'institution_id' => $child->institution_id,
                    'name' => 'Kedvezmény nélkül',
                    'percentage' => 0,
                ],
                [
                    'active' => true,
                    'sort_order' => 0,
                ]
            );

            $child->discount_type_id = $defaultDiscount->id;
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function dataVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'data_verified_by');
    }

    public function isDataVerified(): bool
    {
        return $this->data_verified_at !== null;
    }

    public function discountType(): BelongsTo
    {
        return $this->belongsTo(DiscountType::class);
    }

    public function discountPeriods(): HasMany
    {
        return $this->hasMany(ChildDiscountPeriod::class)
            ->orderByDesc('valid_from')
            ->orderByDesc('id');
    }

    public function dietaryRestrictions(): BelongsToMany
    {
        return $this->belongsToMany(DietaryRestriction::class, 'child_dietary_restriction')
            ->withTimestamps();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'child_guardian')
            ->withPivot([
                'relationship_type',
                'is_legal_representative',
                'has_no_custody',
                'is_emergency_contact',
                'receives_family_allowance',
            ])
            ->withTimestamps();
    }

    public function billingProfiles(): BelongsToMany
    {
        return $this->belongsToMany(BillingProfile::class, 'billing_profile_child')
            ->withPivot(['is_primary', 'valid_from', 'valid_to'])
            ->withTimestamps();
    }

    public function classGroups(): BelongsToMany
    {
        return $this->belongsToMany(ClassGroup::class, 'class_group_memberships')
            ->withPivot(['status', 'joined_on', 'left_on'])
            ->withTimestamps();
    }

    public function mealCancellations(): HasMany
    {
        return $this->hasMany(MealCancellation::class);
    }

    public function recurringCancellationRules(): HasMany
    {
        return $this->hasMany(RecurringCancellationRule::class);
    }

    public function mealSettings(): HasMany
    {
        return $this->hasMany(StudentMealSetting::class, 'student_id');
    }

    public function eaterMealSettings(): MorphMany
    {
        return $this->morphMany(StudentMealSetting::class, 'eater')
            ->orderByDesc('valid_from')
            ->orderByDesc('id');
    }

    public function monthlyPaymentStatements(): HasMany
    {
        return $this->hasMany(MonthlyPaymentStatement::class);
    }

    public function menuChoices(): HasMany
    {
        return $this->hasMany(MenuChoice::class);
    }

    public function mealCheckIns(): HasMany
    {
        return $this->hasMany(MealCheckIn::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(InstitutionInvoice::class);
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function hasBarcode(): bool
    {
        return filled($this->barcode_token);
    }

    public function hasActiveBarcode(): bool
    {
        return $this->hasBarcode() && $this->barcode_disabled_at === null;
    }

    public function hasDisabledBarcode(): bool
    {
        return $this->hasBarcode() && $this->barcode_disabled_at !== null;
    }

    public function barcodeStatusLabel(): string
    {
        if ($this->hasActiveBarcode()) {
            return 'Aktív';
        }

        if ($this->hasDisabledBarcode()) {
            return 'Letiltva';
        }

        return 'Nincs létrehozva';
    }

    public function barcodeGeneratedAtLabel(): ?string
    {
        return $this->barcode_generated_at?->timezone(config('app.timezone'))->format('Y.m.d. H:i');
    }

    public function discountTypeForDate(\Illuminate\Support\Carbon|string $date): ?DiscountType
    {
        $dateString = $date instanceof \Illuminate\Support\Carbon
            ? $date->toDateString()
            : (string) $date;

        $periods = $this->relationLoaded('discountPeriods')
            ? $this->discountPeriods
            : $this->discountPeriods()->with('discountType')->get();

        $activePeriod = $periods->first(function (ChildDiscountPeriod $period) use ($dateString) {
            return $period->valid_from?->toDateString() <= $dateString
                && ($period->valid_to === null || $period->valid_to->toDateString() >= $dateString);
        });

        return $activePeriod?->discountType ?? $this->discountType;
    }
}
