<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PaymentObligation\EmployeeFinancialAdjustment;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;

class InstitutionEmployee extends Model
{
    protected $fillable = [
        'institution_id',
        'user_id',
        'activation_email_sent_at',
        'discount_type_id',
        'name',
        'email',
        'phone',
        'address_type',
        'country',
        'postal_code',
        'city',
        'street_name',
        'street_type',
        'house_number',
        'floor',
        'door',
        'bank_account_holder',
        'bank_account_number',
        'source_type',
        'active',
        'barcode_token',
        'barcode_generated_at',
        'barcode_disabled_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'bank_account_holder' => 'encrypted',
        'bank_account_number' => 'encrypted',
        // ld. App\Models\Child ugyanerről a mezőről - a keresés a
        // barcode_token_hash oszlopon keresztül történik.
        'barcode_token' => 'encrypted',
        'barcode_generated_at' => 'datetime',
        'barcode_disabled_at' => 'datetime',
        'activation_email_sent_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function discountType(): BelongsTo
    {
        return $this->belongsTo(DiscountType::class);
    }

    public function dietaryRestrictions(): BelongsToMany
    {
        return $this->belongsToMany(
            DietaryRestriction::class,
            'institution_employee_dietary_restriction'
        )->withTimestamps();
    }

    public function mealSettings(): MorphMany
    {
        return $this->morphMany(StudentMealSetting::class, 'eater')
            ->orderByDesc('valid_from')
            ->orderByDesc('id');
    }

    public function monthlyPaymentStatements(): HasMany
    {
        return $this->hasMany(EmployeeMonthlyPaymentStatement::class);
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(EmployeeFinancialAdjustment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstitutionEmployeePayment::class);
    }

    public function employeeMealCancellations(): HasMany
    {
        return $this->hasMany(EmployeeMealCancellation::class);
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
}
