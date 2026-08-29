<?php

namespace App\Http\Controllers\EmployeePortal;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMealCancellation;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionEmployeePayment;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Dolgozói vezérlőpult - a ParentPortal\ParentDashboardController
 * egyszerűsített megfelelője: nincs "több gyermek" dimenzió, csak a saját
 * dolgozói jogviszony(ok) legfrissebb havi elszámolása és befizetési
 * előzménye jelenik meg összefoglalóként. A "Saját étkezésem" kártya
 * (ld. buildEmployeeCard()) a ParentDashboardController::buildChildCard()
 * mintáját követi - ugyanaz a StudentMealSetting (eater morph) réteg áll
 * mögötte, csak App\Models\InstitutionEmployee eater-re számolva.
 */
class EmployeeDashboardController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar
    ) {}

    public function __invoke(): View
    {
        $user = auth()->user();
        $today = $this->calendar->now()->startOfDay();

        $employees = InstitutionEmployee::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->with([
                'institution.mealPackages' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('display_order')
                    ->orderBy('name'),
                'discountType',
                'dietaryRestrictions',
                'mealSettings.mealPackage',
            ])
            ->orderBy('name')
            ->get();

        $employeeIds = $employees->pluck('id');

        $latestStatement = EmployeeMonthlyPaymentStatement::query()
            ->whereIn('institution_employee_id', $employeeIds)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $upcomingCancellableCount = EmployeeMealCancellation::query()
            ->whereIn('institution_employee_id', $employeeIds)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', '>=', $today->toDateString())
            ->count();

        $recentPayments = InstitutionEmployeePayment::query()
            ->whereIn('institution_employee_id', $employeeIds)
            ->where('status', InstitutionEmployeePayment::STATUS_COMPLETED)
            ->orderByDesc('paid_at')
            ->limit(5)
            ->get();

        $employeeCards = $employees->map(fn (InstitutionEmployee $employee) => $this->buildEmployeeCard($employee, $today));

        return view('employee.dashboard', [
            'employees' => $employees,
            'latestStatement' => $latestStatement,
            'upcomingCancellableCount' => $upcomingCancellableCount,
            'recentPayments' => $recentPayments,
            'employeeCards' => $employeeCards,
        ]);
    }

    private function buildEmployeeCard(InstitutionEmployee $employee, CarbonImmutable $today): array
    {
        $currentMealSetting = $this->currentMealSetting($employee, $today);
        $dietaryNames = $employee->dietaryRestrictions->pluck('name')->values();

        return [
            'model' => $employee,
            'name' => $employee->name,
            'initials' => $this->initials($employee->name),
            'institution' => $employee->institution?->name ?? 'Nincs intézmény',
            'meal_status' => $this->mealStatusLabel($employee, $currentMealSetting),
            'meal_package' => $this->mealPackageLabel($employee, $currentMealSetting),
            'discount' => $this->discountLabel($employee),
            'dietary' => $dietaryNames,
            'details_url' => route('employee.account'),
            'meal_url' => route('employee.meal-cancellations'),
        ];
    }

    private function currentMealSetting(InstitutionEmployee $employee, CarbonImmutable $today): ?StudentMealSetting
    {
        return $employee->mealSettings->first(function (StudentMealSetting $setting) use ($today) {
            $validFrom = CarbonImmutable::parse($setting->valid_from, $this->calendar->timezone())->startOfDay();
            $validTo = $setting->valid_to
                ? CarbonImmutable::parse($setting->valid_to, $this->calendar->timezone())->startOfDay()
                : null;

            return $validFrom->lte($today) && ($validTo === null || $validTo->gte($today));
        });
    }

    private function mealStatusLabel(InstitutionEmployee $employee, ?StudentMealSetting $currentMealSetting): string
    {
        if (! $employee->active) {
            return 'Inaktív';
        }

        if (! $currentMealSetting) {
            return 'Nincs aktív étkeztetés';
        }

        return match ($currentMealSetting->mode) {
            StudentMealSetting::MODE_PACKAGE => 'Aktív menücsomag',
            StudentMealSetting::MODE_CUSTOM => 'Egyedi étkezések',
            default => 'Intézményi alapértelmezett',
        };
    }

    private function mealPackageLabel(InstitutionEmployee $employee, ?StudentMealSetting $currentMealSetting): string
    {
        if (! $currentMealSetting) {
            return 'Nincs aktív étkezés';
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_PACKAGE) {
            return $currentMealSetting->mealPackage?->name ?? 'Még nincs csomag megadva';
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_CUSTOM) {
            return 'Egyedi étkezések';
        }

        $defaultPackage = $employee->institution?->mealPackages?->firstWhere('is_default', true);

        return $defaultPackage?->name ?? 'Intézményi alapértelmezett';
    }

    private function discountLabel(InstitutionEmployee $employee): ?string
    {
        $discount = $employee->discountType;

        if (! $discount) {
            return null;
        }

        if ((int) $discount->percentage > 0) {
            return $discount->percentage.'% kedvezmény';
        }

        if (mb_stripos($discount->name, 'nélkül') !== false || mb_stripos($discount->name, 'nelkul') !== false) {
            return null;
        }

        return $discount->name;
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
