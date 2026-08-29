<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\ClassCancellation;
use App\Models\ClassGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassCancellationController extends Controller
{
    public function index(): View
    {
        $institution = $this->currentAdminInstitution();
        $baseQuery = ClassCancellation::where('institution_id', $institution->id);

        $classCancellations = (clone $baseQuery)
            ->with(['classGroup.schoolYear', 'creator'])
            ->latest('date_from')
            ->paginate(15);
        $today = now()->toDateString();

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)
                ->whereDate('date_from', '<=', $today)
                ->whereDate('date_to', '>=', $today)
                ->count(),
            'upcoming' => (clone $baseQuery)->whereDate('date_from', '>', $today)->count(),
            'past' => (clone $baseQuery)->whereDate('date_to', '<', $today)->count(),
        ];

        return view('dashboard.institution_admin.class-cancellations.index', compact(
            'institution',
            'classCancellations',
            'stats'
        ));
    }

    public function create(): View
    {
        $institution = $this->currentAdminInstitution();
        $classGroups = $this->availableGroups($institution->id, $institution->type);

        return view('dashboard.institution_admin.class-cancellations.create', compact(
            'institution',
            'classGroups'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        $validated = $this->validateCancellation($request, $institution->id, $institution->type);
        $classGroup = $this->findClassGroup(
            $institution->id,
            $institution->type,
            (int) $validated['class_group_id']
        );
        $this->ensureNoOverlap($classGroup->id, $validated['date_from'], $validated['date_to']);

        ClassCancellation::create([
            'institution_id' => $institution->id,
            'class_group_id' => $classGroup->id,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'reason' => filled($validated['reason'] ?? null) ? trim($validated['reason']) : null,
            'affected_children_count' => $this->activeChildrenCount($classGroup),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('dashboard.institution.class-cancellations.index')
            ->with('success', $institution->type === 'ovoda'
                ? 'A csoportszintű lemondás sikeresen rögzítve.'
                : 'Az osztályszintű lemondás sikeresen rögzítve.');
    }

    public function edit(ClassCancellation $classCancellation): View
    {
        $institution = $this->currentAdminInstitution();
        $this->authorizeCancellation($classCancellation, $institution->id);
        $classGroups = $this->availableGroups(
            $institution->id,
            $institution->type,
            $classCancellation->class_group_id
        );

        return view('dashboard.institution_admin.class-cancellations.edit', compact(
            'classCancellation',
            'institution',
            'classGroups'
        ));
    }

    public function update(Request $request, ClassCancellation $classCancellation): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        $this->authorizeCancellation($classCancellation, $institution->id);
        $validated = $this->validateCancellation($request, $institution->id, $institution->type);
        $classGroup = $this->findClassGroup(
            $institution->id,
            $institution->type,
            (int) $validated['class_group_id']
        );
        $this->ensureNoOverlap(
            $classGroup->id,
            $validated['date_from'],
            $validated['date_to'],
            $classCancellation->id
        );

        $classCancellation->update([
            'class_group_id' => $classGroup->id,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'reason' => filled($validated['reason'] ?? null) ? trim($validated['reason']) : null,
            'affected_children_count' => $this->activeChildrenCount($classGroup),
        ]);

        return redirect()
            ->route('dashboard.institution.class-cancellations.index')
            ->with('success', $institution->type === 'ovoda'
                ? 'A csoportszintű lemondás sikeresen módosítva.'
                : 'Az osztályszintű lemondás sikeresen módosítva.');
    }

    public function destroy(ClassCancellation $classCancellation): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        $this->authorizeCancellation($classCancellation, $institution->id);
        $classCancellation->delete();

        return redirect()
            ->route('dashboard.institution.class-cancellations.index')
            ->with('success', 'A csoportos lemondás törölve.');
    }

    private function validateCancellation(Request $request, int $institutionId, string $institutionType): array
    {
        return $request->validate([
            'class_group_id' => [
                'required',
                'integer',
                Rule::exists('class_groups', 'id')->where(fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->where('group_type', $this->groupType($institutionType))),
            ],
            'date_from' => ['required', 'date', 'after_or_equal:today'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'reason' => ['nullable', 'string', 'max:191'],
        ], [
            'class_group_id.exists' => 'A kiválasztott osztály vagy csoport nem tartozik az intézményhez.',
            'date_from.after_or_equal' => 'A lemondás kezdete nem lehet korábbi a mai napnál.',
        ]);
    }

    private function availableGroups(int $institutionId, string $institutionType, ?int $includeId = null)
    {
        return ClassGroup::where('institution_id', $institutionId)
            ->where('group_type', $this->groupType($institutionType))
            ->where(function ($query) use ($includeId) {
                $query->where('active', true)
                    ->when($includeId, fn ($query) => $query->orWhere('id', $includeId));
            })
            ->with('schoolYear')
            ->orderByDesc('school_year_id')
            ->orderByRaw('grade_level IS NULL')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();
    }

    private function findClassGroup(int $institutionId, string $institutionType, int $classGroupId): ClassGroup
    {
        return ClassGroup::where('institution_id', $institutionId)
            ->where('group_type', $this->groupType($institutionType))
            ->findOrFail($classGroupId);
    }

    private function ensureNoOverlap(
        int $classGroupId,
        string $dateFrom,
        string $dateTo,
        ?int $ignoreId = null
    ): void {
        $overlapExists = ClassCancellation::where('class_group_id', $classGroupId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereDate('date_from', '<=', $dateTo)
            ->whereDate('date_to', '>=', $dateFrom)
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'date_from' => 'Erre az osztályra vagy csoportra a megadott időszakban már van lemondás.',
            ]);
        }
    }

    private function activeChildrenCount(ClassGroup $classGroup): int
    {
        return $classGroup->children()
            ->wherePivot('status', 'active')
            ->count();
    }

    private function authorizeCancellation(ClassCancellation $classCancellation, int $institutionId): void
    {
        abort_if($classCancellation->institution_id !== $institutionId, 403);
    }

    private function groupType(string $institutionType): string
    {
        return $institutionType === 'ovoda' ? 'kindergarten_group' : 'school_class';
    }
}
