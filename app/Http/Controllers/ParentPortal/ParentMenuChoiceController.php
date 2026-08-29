<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Child;
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

class ParentMenuChoiceController extends Controller
{
    public function __construct(
        private readonly AbMenuSelectionService $selectionService
    ) {
    }

    public function index(): View
    {
        $children = $this->linkedChildren();

        $sections = $children
            ->groupBy('institution_id')
            ->map(function (Collection $institutionChildren) {
                $institution = $institutionChildren->first()->institution;
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
                    'children' => $institutionChildren,
                    'choice_map' => $this->selectionService->choicesForChildrenAndPlan(
                        $institutionChildren->pluck('id'),
                        $plan
                    ),
                ];
            })
            ->filter()
            ->values();

        return view('parent.menu-choices.index', compact('sections'));
    }

    public function update(Request $request, int $child): RedirectResponse
    {
        $childModel = $this->authorizedChild($child);
        $institution = $childModel->institution;
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

        if ($this->selectionService->isDietaryChild($childModel)) {
            throw ValidationException::withMessages([
                'choices' => 'Diétás gyermeknél A/B menüválasztás nem szükséges.',
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

        DB::transaction(function () use ($childModel, $institution, $selectableItems, $submittedChoices) {
            foreach ($selectableItems as $item) {
                $choice = $submittedChoices[$item->id] ?? MenuChoice::CHOICE_A;

                if ($choice === MenuChoice::CHOICE_B) {
                    MenuChoice::query()->updateOrCreate(
                        [
                            'institution_id' => $institution->id,
                            'child_id' => $childModel->id,
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
                    ->where('child_id', $childModel->id)
                    ->where(function ($query) use ($item) {
                        $query->where('ab_menu_item_id', $item->id)
                            ->orWhere(function ($legacyQuery) use ($item) {
                                $legacyQuery->whereNull('ab_menu_item_id')
                                    ->whereDate('menu_date', $item->menu_date?->toDateString());
                            });
                    })
                    ->delete();
            }
        });

        return back()->with('success', 'A menüválasztás sikeresen elmentve.');
    }

    private function linkedChildren(): Collection
    {
        $guardianIds = auth()->user()->guardians()->where('active', true)->pluck('guardians.id');

        return Child::query()
            ->where('active', true)
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->with([
                'institution.setting',
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('institution_id')
            ->orderBy('name')
            ->get()
            ->values();
    }

    private function authorizedChild(int $childId): Child
    {
        $guardianIds = auth()->user()->guardians()->where('active', true)->pluck('guardians.id');

        return Child::query()
            ->whereKey($childId)
            ->where('active', true)
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->with([
                'institution.setting',
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->firstOrFail();
    }
}
