<?php

namespace App\Http\Controllers\EmployeePortal;

use App\Http\Controllers\Controller;
use App\Models\InstitutionEmployee;
use App\Models\InstitutionSetting;
use App\Models\MenuChoice;
use App\Services\Meals\AbMenuSelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A dolgozói (tanári) A/B menüválasztás önkiszolgáló oldala - az
 * App\Http\Controllers\ParentPortal\ParentMenuChoiceController pontos
 * mintáját követi, de a dolgozó saját magára választ (nem egy kiválasztott
 * gyermeknek). A MenuChoice rekordokat a polimorf eater_type/eater_id
 * mezőkkel írja/olvassa (ld. App\Services\Meals\AbMenuSelectionService
 * choicesForEmployeesAndPlan()/choiceMapForEmployeesAndItems() stb.) - a
 * gyermek-alapú AB-menü kódhoz (ParentMenuChoiceController, admin
 * MenuChoiceController, AbMenuSelectionService gyermek-metódusai) NEM nyúl.
 */
class EmployeeMenuChoiceController extends Controller
{
    public function __construct(
        private readonly AbMenuSelectionService $selectionService
    ) {
    }

    public function index(): View
    {
        $employees = $this->linkedEmployees();

        $sections = $employees
            ->groupBy('institution_id')
            ->map(function (Collection $institutionEmployees) {
                $institution = $institutionEmployees->first()->institution;
                $plan = $this->selectionService->relevantPlanForInstitution($institution->id);

                if ($plan === null) {
                    return null;
                }

                $setting = $institution->setting
                    ?? InstitutionSetting::firstOrCreate(
                        ['institution_id' => $institution->id],
                        InstitutionSetting::defaults()
                    );

                $plan->loadMissing(['items' => fn ($query) => $query->orderBy('menu_date')]);
                $state = $this->selectionService->selectionState($plan, $setting);

                return [
                    'institution' => $institution,
                    'plan' => $plan,
                    'state' => $state,
                    'state_label' => $this->selectionService->selectionStateLabel($state),
                    'deadline' => $this->selectionService->selectionDeadlineForPlan($plan, $setting),
                    'items' => $plan->items,
                    'employees' => $institutionEmployees,
                    'choice_map' => $this->selectionService->choicesForEmployeesAndPlan(
                        $institutionEmployees->pluck('id'),
                        $plan
                    ),
                ];
            })
            ->filter()
            ->values();

        return view('employee.menu-choices.index', compact('sections'));
    }

    public function update(Request $request, int $employee): RedirectResponse
    {
        $employeeModel = $this->authorizedEmployee($employee);
        $institution = $employeeModel->institution;
        $setting = $institution->setting
            ?? InstitutionSetting::firstOrCreate(
                ['institution_id' => $institution->id],
                InstitutionSetting::defaults()
            );
        $plan = $this->selectionService->relevantPlanForInstitution($institution->id);

        if ($plan === null || $this->selectionService->selectionState($plan, $setting) !== AbMenuSelectionService::STATE_ACTIVE) {
            throw ValidationException::withMessages([
                'choices' => 'A menüválasztási időszak jelenleg nem aktív.',
            ]);
        }

        if ($this->selectionService->isDietaryEmployee($employeeModel)) {
            throw ValidationException::withMessages([
                'choices' => 'Diétás dolgozónál A/B menüválasztás nem szükséges.',
            ]);
        }

        $plan->loadMissing(['items' => fn ($query) => $query->orderBy('menu_date')]);
        $selectableItems = $plan->items
            ->filter(fn ($item) => $this->selectionService->isSelectableItem($item))
            ->values();
        $submittedChoices = (array) $request->input('choices', []);

        $validator = Validator::make(
            ['choices' => $submittedChoices],
            [
                'choices' => ['array'],
                'choices.*' => ['required', 'string', 'in:A,B'],
            ]
        );

        $validator->after(function ($validator) use ($submittedChoices, $selectableItems) {
            $allowedItemIds = $selectableItems->pluck('id')->map(fn ($id) => (string) $id)->all();

            foreach (array_keys($submittedChoices) as $itemId) {
                if (! in_array((string) $itemId, $allowedItemIds, true)) {
                    $validator->errors()->add('choices', 'Csak az aktív menütervhez tartozó menünapok módosíthatók.');

                    break;
                }
            }
        });

        $validator->validate();

        $eaterType = $employeeModel->getMorphClass();

        DB::transaction(function () use ($employeeModel, $institution, $selectableItems, $submittedChoices, $eaterType) {
            foreach ($selectableItems as $item) {
                $choice = $submittedChoices[$item->id] ?? MenuChoice::CHOICE_A;

                if ($choice === MenuChoice::CHOICE_B) {
                    MenuChoice::query()->updateOrCreate(
                        [
                            'institution_id' => $institution->id,
                            'eater_type' => $eaterType,
                            'eater_id' => $employeeModel->id,
                            'ab_menu_item_id' => $item->id,
                        ],
                        [
                            'menu_date' => $item->menu_date?->toDateString(),
                            'choice' => MenuChoice::CHOICE_B,
                            'selected_by' => auth()->id(),
                        ]
                    );

                    continue;
                }

                MenuChoice::query()
                    ->where('institution_id', $institution->id)
                    ->where('eater_type', $eaterType)
                    ->where('eater_id', $employeeModel->id)
                    ->where('ab_menu_item_id', $item->id)
                    ->delete();
            }
        });

        return back()->with('success', 'A menüválasztás sikeresen elmentve.');
    }

    private function linkedEmployees(): Collection
    {
        $user = auth()->user();

        return InstitutionEmployee::query()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->with([
                'institution.setting',
                'dietaryRestrictions' => fn ($query) => $query->where('active', true),
            ])
            ->orderBy('institution_id')
            ->orderBy('name')
            ->get()
            ->values();
    }

    private function authorizedEmployee(int $employeeId): InstitutionEmployee
    {
        $employee = $this->linkedEmployees()->firstWhere('id', $employeeId);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'choices' => 'Csak a saját dolgozói jogviszonyához tartozó menüválasztás módosítható.',
            ]);
        }

        return $employee;
    }
}
