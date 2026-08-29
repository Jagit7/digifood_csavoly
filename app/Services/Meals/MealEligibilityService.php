<?php

namespace App\Services\Meals;

use App\Models\Child;
use App\Models\ClassCancellation;
use App\Models\EmployeeMealCancellation;
use App\Models\EmployeeRecurringCancellationRule;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealType;
use App\Models\MealCancellation;
use App\Models\MenuChoice;
use App\Models\RecurringCancellationRule;
use App\Models\SchoolBreak;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Models\WorkingDay;
use App\Services\Barcodes\EaterBarcodeService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class MealEligibilityService
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly AbMenuSelectionService $abMenuSelection
    ) {}

    public function evaluate(
        Institution $institution,
        User $kioskUser,
        string $barcodeToken,
        InstitutionMealType $mealType
    ): array {
        $date = $this->calendar->now()->startOfDay();
        $serviceDate = $date->toDateString();
        $eater = $this->findEaterByBarcode($barcodeToken);

        if ($eater === null) {
            return $this->reject('unknown_barcode', 'Ismeretlen vonalkód.');
        }

        if ($eater->barcode_disabled_at !== null) {
            return $this->reject('disabled_barcode', 'A vonalkód le van tiltva.', $eater);
        }

        if ((int) $eater->institution_id !== (int) $institution->id) {
            return $this->reject('foreign_institution', 'A vonalkód másik intézményhez tartozik.', $eater);
        }

        if (! $eater->active) {
            return $eater instanceof Child
                ? $this->reject('inactive_child', 'A gyermek nem aktív étkező.', $eater)
                : $this->reject('inactive_employee', 'A dolgozó nem aktív.', $eater);
        }

        $resolvedSetting = $this->resolveMealSetting($institution, $eater, $serviceDate);

        if ($resolvedSetting['setting'] === null || $resolvedSetting['meal_type_ids'] === []) {
            return $this->reject('no_active_meal', 'Erre a napra nincs jogosultsága.', $eater);
        }

        if (! in_array($mealType->id, $resolvedSetting['meal_type_ids'], true)) {
            return $this->reject('meal_type_not_in_package', 'Az adott étkezéstípus nincs a csomagjában.', $eater);
        }

        $isWorkingSaturday = WorkingDay::query()
            ->where('institution_id', $institution->id)
            ->whereDate('date', $serviceDate)
            ->exists();

        if ($date->isWeekend() && ! ($date->isSaturday() && $isWorkingSaturday)) {
            return $this->reject('no_service', 'Erre a napra nincs jogosultsága.', $eater);
        }

        if (! $this->calendar->isServiceDay($institution->id, $serviceDate)) {
            return $this->reject('no_service', 'Erre a napra nincs jogosultsága.', $eater);
        }

        $schoolBreak = SchoolBreak::query()
            ->where('institution_id', $institution->id)
            ->whereDate('start_date', '<=', $serviceDate)
            ->whereDate('end_date', '>=', $serviceDate)
            ->first();

        if ($schoolBreak !== null) {
            return $this->reject('school_break', 'Iskolai szünet van.', $eater);
        }

        if ($eater instanceof Child && $this->hasClassCancellation($institution->id, $eater->id, $serviceDate)) {
            return $this->reject('class_cancellation', 'Osztálylemondás van.', $eater);
        }

        if ($eater instanceof Child && $this->hasCancellation($institution->id, $eater->id, $date)) {
            return $this->reject('cancelled', 'Az étkezést lemondták.', $eater);
        }

        if ($eater instanceof InstitutionEmployee && $this->hasEmployeeCancellation($institution->id, $eater->id, $date)) {
            return $this->reject('cancelled', 'Az étkezést lemondták.', $eater);
        }

        $menuChoice = $eater instanceof Child
            ? $this->resolveMenuChoice($institution->id, $eater->id, $serviceDate)
            : ['status' => 'ok', 'choice' => null];

        if ($menuChoice['status'] !== 'ok') {
            return $this->reject($menuChoice['code'], $menuChoice['message'], $eater);
        }

        return [
            'status' => 'ok',
            'eater' => $eater,
            'eater_type' => $eater->getMorphClass(),
            'child' => $eater instanceof Child ? $eater : null,
            'service_date' => $serviceDate,
            'scanned_at' => $this->calendar->now(),
            'meal_type' => $mealType->loadMissing('mealType'),
            'menu_choice' => $menuChoice['choice'],
            'dietary_restrictions' => $eater->dietaryRestrictions,
            'kiosk_user' => $kioskUser,
        ];
    }

    private function findEaterByBarcode(string $barcodeToken): Child|InstitutionEmployee|null
    {
        $relations = [
            'dietaryRestrictions' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name'),
        ];

        // A barcode_token oszlop titkosítva tárolódik, ezért a beolvasott
        // értéket a determinisztikus barcode_token_hash oszlopon keresztül
        // keressük (ld. EaterBarcodeService::hashToken()), nem a nyers
        // (kereshetetlen) titkosított mezőn.
        $hash = EaterBarcodeService::hashToken($barcodeToken);

        return Child::query()
            ->where('barcode_token_hash', $hash)
            ->with($relations)
            ->first()
            ?? InstitutionEmployee::query()
                ->where('barcode_token_hash', $hash)
                ->with($relations)
                ->first();
    }

    private function resolveMealSetting(Institution $institution, Model $eater, string $date): array
    {
        $setting = StudentMealSetting::query()
            ->with([
                'mealPackage.items',
                'items',
            ])
            ->where('institution_id', $institution->id)
            ->where(function ($query) use ($eater) {
                $query->forEater($eater);

                if ($eater instanceof Child) {
                    $query->orWhere('student_id', $eater->id);
                }
            })
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        if ($setting === null) {
            return [
                'setting' => null,
                'meal_type_ids' => [],
            ];
        }

        if ($setting->mode === StudentMealSetting::MODE_INSTITUTION_DEFAULT) {
            $package = InstitutionMealPackage::query()
                ->with('items')
                ->where('institution_id', $institution->id)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            return [
                'setting' => $setting,
                'meal_type_ids' => $package?->items->pluck('institution_meal_type_id')->map(fn ($id) => (int) $id)->all() ?? [],
            ];
        }

        if ($setting->mode === StudentMealSetting::MODE_PACKAGE) {
            return [
                'setting' => $setting,
                'meal_type_ids' => $setting->mealPackage?->items->pluck('institution_meal_type_id')->map(fn ($id) => (int) $id)->all() ?? [],
            ];
        }

        return [
            'setting' => $setting,
            'meal_type_ids' => $setting->items->pluck('institution_meal_type_id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    private function hasCancellation(int $institutionId, int $childId, CarbonImmutable $date): bool
    {
        $serviceDate = $date->toDateString();
        $weekday = $date->dayOfWeekIso;

        return MealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', $serviceDate)
            ->exists()
            || RecurringCancellationRule::query()
                ->where('institution_id', $institutionId)
                ->where('child_id', $childId)
                ->whereIn('status', [
                    RecurringCancellationRule::STATUS_ACTIVE,
                    RecurringCancellationRule::STATUS_ENDED,
                ])
                ->where('weekday', $weekday)
                ->whereDate('starts_on', '<=', $serviceDate)
                ->where(function ($query) use ($serviceDate) {
                    $query->whereNull('ends_on')
                        ->orWhereDate('ends_on', '>=', $serviceDate);
                })
                ->exists();
    }

    private function hasClassCancellation(int $institutionId, int $childId, string $serviceDate): bool
    {
        return ClassCancellation::query()
            ->join('class_group_memberships', 'class_group_memberships.class_group_id', '=', 'class_cancellations.class_group_id')
            ->join('class_groups', 'class_groups.id', '=', 'class_cancellations.class_group_id')
            ->where('class_cancellations.institution_id', $institutionId)
            ->where('class_groups.institution_id', $institutionId)
            ->where('class_group_memberships.child_id', $childId)
            ->where('class_group_memberships.status', 'active')
            ->whereDate('class_cancellations.date_from', '<=', $serviceDate)
            ->whereDate('class_cancellations.date_to', '>=', $serviceDate)
            ->exists();
    }

    private function hasEmployeeCancellation(int $institutionId, int $employeeId, CarbonImmutable $date): bool
    {
        $serviceDate = $date->toDateString();
        $weekday = $date->dayOfWeekIso;

        return EmployeeMealCancellation::query()
            ->where('institution_id', $institutionId)
            ->where('institution_employee_id', $employeeId)
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereDate('service_date', $serviceDate)
            ->exists()
            || EmployeeRecurringCancellationRule::query()
                ->where('institution_id', $institutionId)
                ->where('institution_employee_id', $employeeId)
                ->whereIn('status', [
                    EmployeeRecurringCancellationRule::STATUS_ACTIVE,
                    EmployeeRecurringCancellationRule::STATUS_ENDED,
                ])
                ->where('weekday', $weekday)
                ->whereDate('starts_on', '<=', $serviceDate)
                ->where(function ($query) use ($serviceDate) {
                    $query->whereNull('ends_on')
                        ->orWhereDate('ends_on', '>=', $serviceDate);
                })
                ->exists();
    }

    private function resolveMenuChoice(int $institutionId, int $childId, string $serviceDate): array
    {
        $resolved = $this->abMenuSelection->resolveChoiceForDate(
            $institutionId,
            $childId,
            $serviceDate,
            false
        );

        if (($resolved['item'] ?? null) === null || ! $this->abMenuSelection->isSelectableItem($resolved['item'])) {
            return ['status' => 'ok', 'choice' => null];
        }

        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        return [
            'status' => 'ok',
            'choice' => $resolved['choice'] ?? MenuChoice::CHOICE_A,
        ];
    }

    private function reject(string $code, string $message, Child|InstitutionEmployee|null $eater = null): array
    {
        return [
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'child' => $eater instanceof Child ? $eater : null,
            'eater' => $eater,
            'eater_type' => $eater?->getMorphClass(),
        ];
    }
}
