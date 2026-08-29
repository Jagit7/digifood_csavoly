<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\SchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ClassGroupController extends Controller
{
    public function index(Request $request): View
    {
        $institution = $this->currentAdminInstitution();
        $schoolYears = SchoolYear::where('institution_id', $institution->id)
            ->orderByDesc('starts_on')
            ->get();

        $selectedSchoolYear = $request->filled('school_year')
            ? $schoolYears->firstWhere('id', (int) $request->input('school_year'))
            : $schoolYears->firstWhere('is_current', true) ?? $schoolYears->first();

        $groups = collect();
        $stats = ['groups' => 0, 'assigned' => 0, 'unassigned' => 0, 'average' => 0];

        if ($selectedSchoolYear) {
            $groups = ClassGroup::where('institution_id', $institution->id)
                ->where('school_year_id', $selectedSchoolYear->id)
                ->where('group_type', $institution->type === 'ovoda' ? 'kindergarten_group' : 'school_class')
                ->withCount([
                    'children as active_children_count' => fn ($query) => $query
                        ->where('class_group_memberships.status', 'active'),
                ])
                ->orderByRaw('grade_level IS NULL')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get();

            $assigned = DB::table('class_group_memberships')
                ->join('class_groups', 'class_groups.id', '=', 'class_group_memberships.class_group_id')
                ->where('class_groups.institution_id', $institution->id)
                ->where('class_groups.school_year_id', $selectedSchoolYear->id)
                ->where('class_group_memberships.status', 'active')
                ->distinct()
                ->count('class_group_memberships.child_id');

            $unassigned = Child::where('institution_id', $institution->id)
                ->where('active', true)
                ->whereNotExists(function ($query) use ($selectedSchoolYear) {
                    $query->selectRaw('1')
                        ->from('class_group_memberships')
                        ->join('class_groups', 'class_groups.id', '=', 'class_group_memberships.class_group_id')
                        ->whereColumn('class_group_memberships.child_id', 'children.id')
                        ->where('class_groups.school_year_id', $selectedSchoolYear->id)
                        ->where('class_group_memberships.status', 'active');
                })
                ->count();

            $stats = [
                'groups' => $groups->count(),
                'assigned' => $assigned,
                'unassigned' => $unassigned,
                'average' => $groups->count() ? round($assigned / $groups->count(), 1) : 0,
            ];
        }

        return view('dashboard.institution_admin.class-groups.index', compact(
            'institution',
            'schoolYears',
            'selectedSchoolYear',
            'groups',
            'stats'
        ));
    }

    public function show(Request $request, ClassGroup $classGroup): View
    {
        $institution = $this->currentAdminInstitution();
        $canViewBilling = ! auth()->user()->isInstitutionSecretary();
        abort_if($classGroup->institution_id !== $institution->id, 403);

        $classGroup->load('schoolYear');
        $memberIds = $classGroup->children()
            ->wherePivot('status', 'active')
            ->pluck('children.id');

        $stats = [
            'children' => $memberIds->count(),
            'with_guardian' => Child::whereIn('id', $memberIds)->has('guardians')->count(),
            'without_guardian' => Child::whereIn('id', $memberIds)->doesntHave('guardians')->count(),
            'with_billing' => $canViewBilling
                ? DB::table('billing_profile_child')
                    ->whereIn('child_id', $memberIds)
                    ->where('is_primary', true)
                    ->distinct()
                    ->count('child_id')
                : null,
        ];

        $children = $classGroup->children()
            ->wherePivot('status', 'active')
            ->withCount('guardians')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('educational_identifier', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $targetGroups = ClassGroup::where('institution_id', $institution->id)
            ->where('school_year_id', $classGroup->school_year_id)
            ->where('group_type', $classGroup->group_type)
            ->where('active', true)
            ->whereKeyNot($classGroup->id)
            ->orderByRaw('grade_level IS NULL')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();

        return view('dashboard.institution_admin.class-groups.show', compact(
            'institution',
            'classGroup',
            'children',
            'stats',
            'targetGroups',
            'canViewBilling'
        ));
    }

    public function moveChildren(Request $request, ClassGroup $classGroup): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        abort_if($classGroup->institution_id !== $institution->id, 403);

        $validated = $request->validate([
            'child_ids' => ['required', 'array', 'min:1'],
            'child_ids.*' => ['required', 'integer', 'distinct'],
            'target_class_group_id' => ['required', 'integer'],
        ], [
            'child_ids.required' => 'Jelölj ki legalább egy gyermeket.',
            'target_class_group_id.required' => 'Válaszd ki a célosztályt vagy célcsoportot.',
        ]);

        $targetGroup = ClassGroup::where('institution_id', $institution->id)
            ->where('school_year_id', $classGroup->school_year_id)
            ->where('group_type', $classGroup->group_type)
            ->where('active', true)
            ->whereKeyNot($classGroup->id)
            ->findOrFail($validated['target_class_group_id']);

        $childIds = $classGroup->children()
            ->wherePivot('status', 'active')
            ->whereIn('children.id', $validated['child_ids'])
            ->pluck('children.id');

        if ($childIds->count() !== count($validated['child_ids'])) {
            return back()->withInput()->withErrors([
                'child_ids' => 'A kijelölt gyermekek között olyan rekord van, amely nem aktív tagja ennek az osztálynak vagy csoportnak.',
            ]);
        }

        DB::transaction(function () use ($classGroup, $targetGroup, $childIds) {
            DB::table('class_group_memberships')
                ->where('class_group_id', $classGroup->id)
                ->whereIn('child_id', $childIds)
                ->where('status', 'active')
                ->update([
                    'status' => 'transferred',
                    'left_on' => now()->toDateString(),
                    'updated_at' => now(),
                ]);

            foreach ($childIds as $childId) {
                DB::table('class_group_memberships')->updateOrInsert(
                    ['class_group_id' => $targetGroup->id, 'child_id' => $childId],
                    [
                        'status' => 'active',
                        'joined_on' => now()->toDateString(),
                        'left_on' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            Child::whereIn('id', $childIds)->update([
                'group_name' => $targetGroup->name,
                'school_year' => $targetGroup->schoolYear->name,
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('dashboard.institution.class-groups.show', $classGroup)
            ->with('success', sprintf(
                '%d gyermek átkerült a(z) %s %s.',
                $childIds->count(),
                $targetGroup->name,
                $institution->type === 'ovoda' ? 'csoportba' : 'osztályba'
            ));
    }

    public function promotion(Request $request): View
    {
        $institution = $this->currentAdminInstitution();
        abort_if($institution->type === 'ovoda', 404);

        $schoolYears = SchoolYear::where('institution_id', $institution->id)
            ->orderByDesc('starts_on')
            ->get();

        $sourceSchoolYear = $request->filled('source_school_year')
            ? $schoolYears->firstWhere('id', (int) $request->input('source_school_year'))
            : $schoolYears->firstWhere('is_current', true) ?? $schoolYears->first();

        $groups = collect();
        $targetSchoolYear = null;
        $targetYearData = null;

        if ($sourceSchoolYear) {
            $groups = ClassGroup::where('institution_id', $institution->id)
                ->where('school_year_id', $sourceSchoolYear->id)
                ->where('group_type', 'school_class')
                ->where('active', true)
                ->withCount([
                    'children as active_children_count' => fn ($query) => $query
                        ->where('class_group_memberships.status', 'active'),
                ])
                ->orderByRaw('grade_level IS NULL')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get()
                ->map(function (ClassGroup $group) {
                    $group->suggested_name = $this->nextClassName($group);
                    $group->suggested_grade_level = $group->grade_level ? $group->grade_level + 1 : null;

                    return $group;
                });

            $targetYearData = $this->nextSchoolYearData($sourceSchoolYear);
            $targetSchoolYear = SchoolYear::where('institution_id', $institution->id)
                ->where('name', $targetYearData['name'])
                ->first();
        }

        return view('dashboard.institution_admin.class-groups.promotion', compact(
            'institution',
            'schoolYears',
            'sourceSchoolYear',
            'targetSchoolYear',
            'targetYearData',
            'groups'
        ));
    }

    public function promote(Request $request): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        abort_if($institution->type === 'ovoda', 404);

        $validated = $request->validate([
            'source_school_year_id' => ['required', 'integer'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['required', 'integer', 'distinct'],
            'target_names' => ['required', 'array'],
            'target_names.*' => ['nullable', 'string', 'max:100'],
            'target_grade_levels' => ['required', 'array'],
            'target_grade_levels.*' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $sourceSchoolYear = SchoolYear::where('institution_id', $institution->id)
            ->findOrFail($validated['source_school_year_id']);
        $groups = ClassGroup::where('institution_id', $institution->id)
            ->where('school_year_id', $sourceSchoolYear->id)
            ->where('group_type', 'school_class')
            ->where('active', true)
            ->whereIn('id', $validated['group_ids'])
            ->get();

        if ($groups->count() !== count($validated['group_ids'])) {
            return back()->withInput()->withErrors([
                'group_ids' => 'A kijelölt osztályok között érvénytelen vagy másik tanévhez tartozó elem van.',
            ]);
        }

        $selectedGroups = $groups->map(function (ClassGroup $group) use ($validated) {
            return [
                'group' => $group,
                'target_name' => trim((string) ($validated['target_names'][$group->id] ?? '')),
                'target_grade_level' => filled($validated['target_grade_levels'][$group->id] ?? null)
                    ? (int) $validated['target_grade_levels'][$group->id]
                    : null,
            ];
        });

        $groupValidator = Validator::make(
            ['names' => $selectedGroups->pluck('target_name')->all()],
            ['names' => ['required', 'array'], 'names.*' => ['required', 'string', 'max:100', 'distinct']],
            [
                'names.*.required' => 'Minden kijelölt osztályhoz add meg a következő tanévi osztály nevét.',
                'names.*.distinct' => 'A következő tanévi osztálynevek nem lehetnek azonosak.',
            ]
        );

        if ($groupValidator->fails()) {
            return back()->withInput()->withErrors($groupValidator);
        }

        $targetYearData = $this->nextSchoolYearData($sourceSchoolYear);

        $promotionResult = DB::transaction(function () use (
            $institution,
            $sourceSchoolYear,
            $targetYearData,
            $selectedGroups
        ) {
            SchoolYear::where('institution_id', $institution->id)->update(['is_current' => false]);

            $targetSchoolYear = SchoolYear::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => $targetYearData['name'],
                ],
                [
                    'starts_on' => $targetYearData['starts_on'],
                    'ends_on' => $targetYearData['ends_on'],
                    'status' => 'active',
                    'is_current' => true,
                ]
            );
            $targetSchoolYear->update(['status' => 'active', 'is_current' => true]);
            $sourceSchoolYear->update(['status' => 'closed', 'is_current' => false]);

            $promotedChildren = 0;

            foreach ($selectedGroups as $selectedGroup) {
                /** @var ClassGroup $sourceGroup */
                $sourceGroup = $selectedGroup['group'];
                $targetGroup = ClassGroup::updateOrCreate(
                    [
                        'school_year_id' => $targetSchoolYear->id,
                        'name' => $selectedGroup['target_name'],
                    ],
                    [
                        'institution_id' => $institution->id,
                        'grade_level' => $selectedGroup['target_grade_level'],
                        'section' => $sourceGroup->section,
                        'group_type' => 'school_class',
                        'active' => true,
                    ]
                );

                abort_if($targetGroup->institution_id !== $institution->id, 422);

                $childIds = $sourceGroup->children()
                    ->wherePivot('status', 'active')
                    ->pluck('children.id');

                DB::table('class_group_memberships')
                    ->join('class_groups', 'class_groups.id', '=', 'class_group_memberships.class_group_id')
                    ->whereIn('class_group_memberships.child_id', $childIds)
                    ->where('class_groups.school_year_id', $targetSchoolYear->id)
                    ->where('class_groups.id', '!=', $targetGroup->id)
                    ->where('class_group_memberships.status', 'active')
                    ->update([
                        'class_group_memberships.status' => 'transferred',
                        'class_group_memberships.left_on' => $targetSchoolYear->starts_on->toDateString(),
                        'class_group_memberships.updated_at' => now(),
                    ]);

                foreach ($childIds as $childId) {
                    DB::table('class_group_memberships')->updateOrInsert(
                        ['class_group_id' => $targetGroup->id, 'child_id' => $childId],
                        [
                            'status' => 'active',
                            'joined_on' => $targetSchoolYear->starts_on->toDateString(),
                            'left_on' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                DB::table('class_group_memberships')
                    ->where('class_group_id', $sourceGroup->id)
                    ->whereIn('child_id', $childIds)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'promoted',
                        'left_on' => $sourceSchoolYear->ends_on->toDateString(),
                        'updated_at' => now(),
                    ]);

                Child::whereIn('id', $childIds)->update([
                    'group_name' => $targetGroup->name,
                    'school_year' => $targetSchoolYear->name,
                    'updated_at' => now(),
                ]);

                $sourceGroup->update(['active' => false]);
                $promotedChildren += $childIds->count();
            }

            return [
                'target_school_year_id' => $targetSchoolYear->id,
                'promoted_children' => $promotedChildren,
            ];
        });

        return redirect()
            ->route('dashboard.institution.class-groups.index', [
                'school_year' => $promotionResult['target_school_year_id'],
            ])
            ->with('success', sprintf(
                'A tanévváltás elkészült: %d osztály és %d diák átkerült a(z) %s tanévbe.',
                $selectedGroups->count(),
                $promotionResult['promoted_children'],
                $targetYearData['name']
            ));
    }

    private function nextSchoolYearData(SchoolYear $sourceSchoolYear): array
    {
        $startsOn = $sourceSchoolYear->starts_on->copy()->addYear();
        $endsOn = $sourceSchoolYear->ends_on->copy()->addYear();

        return [
            'name' => $startsOn->format('Y').'/'.$endsOn->format('Y'),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
        ];
    }

    private function nextClassName(ClassGroup $group): string
    {
        if (preg_match('/^(\d{1,2})(\.\s*)(.*)$/u', trim($group->name), $parts)) {
            return ((int) $parts[1] + 1).$parts[2].$parts[3];
        }

        if ($group->grade_level) {
            return preg_replace('/^'.preg_quote((string) $group->grade_level, '/').'/', (string) ($group->grade_level + 1), $group->name, 1)
                ?: $group->name;
        }

        return $group->name;
    }
}
