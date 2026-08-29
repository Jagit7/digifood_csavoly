<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionMealPackage extends Model
{
    public const PRICING_MODE_COMPONENT_SUM = 'component_sum';
    public const PRICING_MODE_CUSTOM_PRICE = 'custom_price';

    public const PRICING_MODE_LABELS = [
        self::PRICING_MODE_COMPONENT_SUM => 'Étkezések árának összege',
        self::PRICING_MODE_CUSTOM_PRICE => 'Egyedi csomagár',
    ];

    protected $fillable = [
        'institution_id',
        'name',
        'description',
        'is_active',
        'is_default',
        'display_order',
        'pricing_mode',
        'custom_price',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'display_order' => 'integer',
        'custom_price' => 'integer',
        'created_by' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InstitutionMealPackageItem::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function mealTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            InstitutionMealType::class,
            'institution_meal_package_items'
        )->withPivot('display_order')
            ->withTimestamps()
            ->orderByPivot('display_order');
    }

    public function pricingModeLabel(): string
    {
        return self::PRICING_MODE_LABELS[$this->pricing_mode] ?? $this->pricing_mode;
    }

    public function usesCustomPrice(): bool
    {
        return $this->pricing_mode === self::PRICING_MODE_CUSTOM_PRICE;
    }
}
