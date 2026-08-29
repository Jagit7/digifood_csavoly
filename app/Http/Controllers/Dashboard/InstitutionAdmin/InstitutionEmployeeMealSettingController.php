<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\StudentMealSetting;
use App\Services\Meals\StudentMealSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionEmployeeMealSettingController extends Controller
{
    public function __construct(private readonly StudentMealSettingService $mealSettingService) {}

    public function index(InstitutionEmployee $employee): View
    {
        $institution = $this->institution();
        $this->authorizeEmployee($employee, $institution);
        $today = now()->toDateString();

        return view('dashboard.institution_admin.employees.meal-settings.index', [
            'institution' => $institution,
            'employee' => $employee,
            'settings' => $this->mealSettingService->settingsForEater($employee, $institution),
            'currentSetting' => $this->mealSettingService->currentSettingForEater($employee, $institution, $today),
            'defaultPackage' => $this->mealSettingService->defaultPackage($institution),
            'modeLabels' => StudentMealSetting::MODE_LABELS,
        ]);
    }

    public function create(InstitutionEmployee $employee): View
    {
        $institution = $this->institution();
        $this->authorizeEmployee($employee, $institution);

        return view('dashboard.institution_admin.employees.meal-settings.create', [
            'institution' => $institution,
            'employee' => $employee,
            'setting' => new StudentMealSetting([
                'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            ]),
            'defaultPackage' => $this->mealSettingService->defaultPackage($institution),
            'availablePackages' => $this->mealSettingService->availablePackages($institution),
            'availableMealTypes' => $this->mealSettingService->availableMealTypes($institution),
            'selectedMealTypeIds' => [],
            'modeLabels' => StudentMealSetting::MODE_LABELS,
        ]);
    }

    public function store(Request $request, InstitutionEmployee $employee): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeEmployee($employee, $institution);
        $validated = $this->mealSettingService->validateData($request, $institution);

        $this->mealSettingService->createSetting($employee, $institution, $validated, $request->user()->id);

        return redirect()
            ->route('dashboard.institution.employees.meal-settings.index', $employee)
            ->with('success', 'A dolgozó étkezési beállítása létrehozva.');
    }

    public function show(InstitutionEmployee $employee, StudentMealSetting $mealSetting): View
    {
        $institution = $this->institution();
        $this->authorizeEmployee($employee, $institution);
        $this->authorizeMealSetting($mealSetting, $employee, $institution);

        $mealSetting->load([
            'mealPackage',
            'mealTypes.mealType',
            'createdBy',
        ]);

        return view('dashboard.institution_admin.employees.meal-settings.show', [
            'institution' => $institution,
            'employee' => $employee,
            'mealSetting' => $mealSetting,
            'defaultPackage' => $this->mealSettingService->defaultPackage($institution),
            'modeLabels' => StudentMealSetting::MODE_LABELS,
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeEmployee(InstitutionEmployee $employee, Institution $institution): void
    {
        abort_if($employee->institution_id !== $institution->id, 403);
    }

    private function authorizeMealSetting(
        StudentMealSetting $mealSetting,
        InstitutionEmployee $employee,
        Institution $institution
    ): void {
        abort_if(
            $mealSetting->institution_id !== $institution->id
            || $mealSetting->eater_type !== $employee->getMorphClass()
            || (int) $mealSetting->eater_id !== $employee->id
            || $mealSetting->student_id !== null,
            403
        );
    }
}
