<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Institution;
use App\Models\StudentMealSetting;
use App\Services\Meals\StudentMealSettingService;
use App\Services\Navigation\ChildListReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChildMealSettingController extends Controller
{
    public function __construct(
        private readonly StudentMealSettingService $mealSettingService,
        private readonly ChildListReturnService $childListReturnService
    ) {}

    public function index(Request $request, Child $child): View
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $today = now()->toDateString();
        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->query('return_list'),
            (string) $request->query('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        return view('dashboard.institution_admin.children.meal-settings.index', [
            'institution' => $institution,
            'child' => $child,
            'settings' => $this->mealSettingService->settingsForEater($child, $institution),
            'currentSetting' => $this->mealSettingService->currentSettingForEater($child, $institution, $today),
            'latestSetting' => $this->mealSettingService->latestSettingForEater($child, $institution),
            'defaultPackage' => $this->mealSettingService->defaultPackage($institution),
            'modeLabels' => StudentMealSetting::MODE_LABELS,
            'closureReasonLabels' => StudentMealSetting::CLOSURE_REASON_LABELS,
            'returnList' => $returnState['list'],
            'returnQuery' => $returnState['query'],
            'returnUrl' => $returnState['url'],
            'today' => $today,
        ]);
    }

    public function create(Request $request, Child $child): View
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->query('return_list'),
            (string) $request->query('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        return view('dashboard.institution_admin.children.meal-settings.create', [
            'institution' => $institution,
            'child' => $child,
            'setting' => new StudentMealSetting([
                'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            ]),
            'defaultPackage' => $this->mealSettingService->defaultPackage($institution),
            'availablePackages' => $this->mealSettingService->availablePackages($institution),
            'availableMealTypes' => $this->mealSettingService->availableMealTypes($institution),
            'selectedMealTypeIds' => [],
            'modeLabels' => StudentMealSetting::MODE_LABELS,
            'returnList' => $returnState['list'],
            'returnQuery' => $returnState['query'],
        ]);
    }

    public function store(Request $request, Child $child): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $validated = $this->mealSettingService->validateData($request, $institution);

        $this->mealSettingService->createSetting($child, $institution, $validated, $request->user()->id);

        return $this->redirectAfterChange($request, $child)
            ->with('success', 'Az etkezesi beallitas letrehozva.');
    }

    public function edit(Request $request, Child $child, StudentMealSetting $mealSetting): View
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $this->authorizeMealSetting($mealSetting, $child, $institution);
        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->query('return_list'),
            (string) $request->query('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        $mealSetting->load(['mealPackage', 'mealTypes.mealType']);

        return view('dashboard.institution_admin.children.meal-settings.edit', [
            'institution' => $institution,
            'child' => $child,
            'mealSetting' => $mealSetting,
            'defaultPackage' => $this->mealSettingService->defaultPackage($institution),
            'availablePackages' => $this->mealSettingService->availablePackages($institution),
            'availableMealTypes' => $this->mealSettingService->availableMealTypes($institution),
            'selectedMealTypeIds' => $mealSetting->mealTypes->pluck('id')->all(),
            'modeLabels' => StudentMealSetting::MODE_LABELS,
            'returnList' => $returnState['list'],
            'returnQuery' => $returnState['query'],
            'today' => now()->toDateString(),
        ]);
    }

    public function update(Request $request, Child $child, StudentMealSetting $mealSetting): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $this->authorizeMealSetting($mealSetting, $child, $institution);

        $validated = $this->mealSettingService->validateData($request, $institution);

        $result = $this->mealSettingService->updateSetting($mealSetting, $validated, $request->user()->id);

        return $this->redirectAfterChange($request, $child)
            ->with($this->closureFeedback(
                $result,
                'Az étkezési beállítás módosítva.',
                ! $request->user()->isInstitutionSecretary()
            ));
    }

    public function show(Request $request, Child $child, StudentMealSetting $mealSetting): View
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $this->authorizeMealSetting($mealSetting, $child, $institution);
        $returnState = $this->childListReturnService->resolveReturnDestination(
            $request->query('return_list'),
            (string) $request->query('return_query', ''),
            $institution,
            ChildListReturnService::LIST_CHILDREN
        );

        $mealSetting->load([
            'mealPackage',
            'mealTypes.mealType',
            'createdBy',
            'closedBy',
        ]);

        return view('dashboard.institution_admin.children.meal-settings.show', [
            'institution' => $institution,
            'child' => $child,
            'mealSetting' => $mealSetting,
            'defaultPackage' => $this->mealSettingService->defaultPackage($institution),
            'modeLabels' => StudentMealSetting::MODE_LABELS,
            'closureReasonLabels' => StudentMealSetting::CLOSURE_REASON_LABELS,
            'returnList' => $returnState['list'],
            'returnQuery' => $returnState['query'],
        ]);
    }

    public function close(Request $request, Child $child, StudentMealSetting $mealSetting): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $this->authorizeMealSetting($mealSetting, $child, $institution);

        $validated = $request->validate([
            /*
            * Felhasználói kérés: "visszamenőleges dátumra nem lehet
            * megszüntetni" - az utolsó étkezési nap nem lehet a mai
            * napnál korábbi. (A closeSetting() szolgáltatásban is van
            * ugyanerre egy védőháló, ld. StudentMealSettingService.)
            */
            'last_meal_day' => ['required', 'date', 'after_or_equal:today'],
            'closure_reason' => ['required', Rule::in(array_keys(StudentMealSetting::CLOSURE_REASON_LABELS))],
            'closure_note' => ['nullable', 'string', 'max:191', 'required_if:closure_reason,'.StudentMealSetting::CLOSURE_REASON_OTHER],
        ], [
            'last_meal_day.after_or_equal' => 'Az utolsó étkezési nap nem lehet a mai napnál korábbi - visszamenőlegesen nem szüntethető meg az étkezési jogviszony.',
        ]);

        $result = $this->mealSettingService->closeSetting(
            $mealSetting,
            $validated['last_meal_day'],
            $validated['closure_reason'],
            $validated['closure_note'] ?? null,
            $request->user()->id
        );

        return $this->redirectAfterChange($request, $child)
            ->with($this->closureFeedback(
                $result,
                'Az étkezési jogviszony lezárása mentve.',
                ! $request->user()->isInstitutionSecretary()
            ));
    }

    public function reopen(Request $request, Child $child, StudentMealSetting $mealSetting): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeChild($child, $institution);
        $this->authorizeMealSetting($mealSetting, $child, $institution);

        $result = $this->mealSettingService->reopenSetting($mealSetting);

        return $this->redirectAfterChange($request, $child)
            ->with($this->closureFeedback(
                $result,
                'Az étkezési jogviszony újranyitása mentve.',
                ! auth()->user()->isInstitutionSecretary()
            ));
    }

    /**
     * A három gyermeklista bármelyikéről idekattintva a mentés/lezárás/
     * újranyitás után célszerű ugyanoda visszatérni. A cél tudatosan egy
     * előre whitelistelt listaazonosítóból képzett, fix route-ra van
     * korlátozva (nem tetszőleges URL-re), hogy ez semmiképp ne válhasson
     * nyitott redirectté.
     */
    private function redirectAfterChange(Request $request, Child $child): RedirectResponse
    {
        if ($request->filled('return_list') || $request->filled('return_query')) {
            $returnState = $this->childListReturnService->resolveReturnDestination(
                $request->input('return_list'),
                (string) $request->input('return_query', ''),
                $this->institution(),
                ChildListReturnService::LIST_CHILDREN
            );

            return redirect()->route($returnState['route'], $returnState['params']);
        }

        return redirect()->route('dashboard.institution.children.meal-settings.index', $child);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeChild(Child $child, Institution $institution): void
    {
        abort_if($child->institution_id !== $institution->id, 403);
    }

    private function authorizeMealSetting(StudentMealSetting $mealSetting, Child $child, Institution $institution): void
    {
        /*
        * A legacy student_id-t tekintjük elsődlegesnek, ha ki van
        * töltve (gyermekeknél ez mindig meg kell, hogy legyen) - az
        * eater_type/eater_id mezőket a modell saving() hook-ja
        * minden mentésnél kitölti, de egy régebbi, ezt megelőző sor
        * (vagy a modell mentési eseményét megkerülő írás) esetén ezek
        * elméletileg null-ok is lehetnek. Ha ezt is megkövetelnénk,
        * az egy amúgy jogosan a gyermekhez tartozó beállítást is
        * 403-mal utasítana el (ld. StudentMealSetting::scopeForEater()
        * hasonló védőhálóját).
        */
        $belongsToChild = $mealSetting->student_id !== null
            ? $mealSetting->student_id === $child->id
            : ($mealSetting->eater_type === $child->getMorphClass() && (int) $mealSetting->eater_id === $child->id);

        abort_if(
            $mealSetting->institution_id !== $institution->id || ! $belongsToChild,
            403
        );
    }

    private function closureFeedback(array $result, string $successMessage, bool $includeFinancialWarning = true): array
    {
        $payload = ['success' => $successMessage];

        if ($includeFinancialWarning && ($result['locked_months'] ?? []) !== []) {
            $payload['warning'] = 'Van érintett lezárt havi elszámolás: '
                .implode(', ', $result['locked_months'])
                .'. Ezeket a rendszer nem írta át automatikusan, külön pénzügyi korrekció lehet szükséges.';
        }

        return $payload;
    }
}
