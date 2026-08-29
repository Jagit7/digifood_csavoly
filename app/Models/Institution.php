<?php

namespace App\Models;

use App\Models\PaymentObligation\FinancialAdjustment;
use App\Models\PaymentObligation\EmployeeFinancialAdjustment;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use SoftDeletes;

    public const TYPE_SCHOOL = 'iskola';

    public const TYPE_KINDERGARTEN = 'ovoda';

    protected $table = 'institutions';

    protected $fillable = [
        'name',
        'institution_code',
        'type',

        'address_zip',
        'address_city',
        'address_line',
        'om_identifier',
        'company_registration_number',

        'contact_name',
        'email',
        'phone',

        'billing_name',
        'billing_tax_number',
        'billing_zip',
        'billing_city',
        'billing_address',
        'billing_payment_due_days',
        'billing_partner_id',
        'saas_fee_per_active_eater',

        'kreta_code',
        'szamlazz_partner_id',
        'invoice_prefix',

        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'billing_payment_due_days' => 'integer',
        'saas_fee_per_active_eater' => 'decimal:2',
    ];

    public function billingPartner()
    {
        return $this->belongsTo(BillingPartner::class);
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'institution_user')
            ->withTimestamps()
            ->withPivot(['scope_role']);
    }

    /**
     * A meglévő intézménytípus-mező (type) alapján, ugyanazzal a 'ovoda'
     * értékkel dönt, amit a projekt eddig is használt (ld.
     * DailyOperationController). Csak egy kényelmi/olvashatósági
     * segédmetódus, nem vezet be új, párhuzamos típuskezelést.
     */
    public function isKindergarten(): bool
    {
        return $this->type === self::TYPE_KINDERGARTEN;
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_zip,
            $this->address_city,
            $this->address_line,
        ])->filter()->implode(' ');
    }

    public function getFullBillingAddressAttribute(): string
    {
        return collect([
            $this->billing_zip,
            $this->billing_city,
            $this->billing_address,
        ])->filter()->implode(' ');
    }

    public function hasCompleteBillingAddress(): bool
    {
        return ! empty($this->billing_zip)
            && ! empty($this->billing_city)
            && ! empty($this->billing_address);
    }

    public function adminInvitations()
    {
        return $this->hasMany(\App\Models\InstitutionAdminInvitation::class);
    }

    public function mealSetting()
    {
        return $this->hasOne(InstitutionMealSetting::class);
    }

    public function mealTypes()
    {
        return $this->hasMany(InstitutionMealType::class);
    }

    public function mealPackages()
    {
        return $this->hasMany(InstitutionMealPackage::class);
    }

    public function setting()
    {
        return $this->hasOne(InstitutionSetting::class);
    }

    public function discountTypes()
    {
        return $this->hasMany(DiscountType::class);
    }

    public function dietaryRestrictions()
    {
        return $this->hasMany(DietaryRestriction::class);
    }

    public function mealCancellations()
    {
        return $this->hasMany(MealCancellation::class);
    }

    public function recurringCancellationRules()
    {
        return $this->hasMany(RecurringCancellationRule::class);
    }

    public function contacts()
    {
        return $this->hasMany(InstitutionContact::class);
    }

    public function emailCampaigns()
    {
        return $this->hasMany(EmailCampaign::class);
    }

    public function kitchenNotificationLogs()
    {
        return $this->hasMany(KitchenNotificationLog::class);
    }

    public function paymentPeriodNotificationLogs()
    {
        return $this->hasMany(PaymentPeriodNotificationLog::class);
    }

    public function abMenuSelectionNotificationLogs()
    {
        return $this->hasMany(AbMenuSelectionNotificationLog::class);
    }

    public function employees()
    {
        return $this->hasMany(InstitutionEmployee::class);
    }

    public function monthlyPaymentStatements()
    {
        return $this->hasMany(MonthlyPaymentStatement::class);
    }

    public function employeeMonthlyPaymentStatements()
    {
        return $this->hasMany(EmployeeMonthlyPaymentStatement::class);
    }

    public function invoices()
    {
        return $this->hasMany(InstitutionInvoice::class);
    }

    public function billingRates()
    {
        return $this->hasMany(InstitutionBillingRate::class);
    }

    public function mealCheckIns()
    {
        return $this->hasMany(MealCheckIn::class);
    }

    public function financialAdjustments()
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function employeeFinancialAdjustments()
    {
        return $this->hasMany(EmployeeFinancialAdjustment::class);
    }

    public function employeeMealCancellations()
    {
        return $this->hasMany(EmployeeMealCancellation::class);
    }

    public function dailyAttendanceRecipients()
    {
        return $this->hasMany(InstitutionDailyAttendanceRecipient::class);
    }

    public function dailyAttendanceEmailLogs()
    {
        return $this->hasMany(DailyAttendanceEmailLog::class);
    }
}
