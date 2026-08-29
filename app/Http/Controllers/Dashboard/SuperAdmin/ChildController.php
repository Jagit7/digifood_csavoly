<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\Institution;
use App\Models\StudentMealSetting;
use App\Services\Billing\DigifoodMonthlyFeeOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class ChildController extends Controller
{
    public function __construct(private readonly DigifoodMonthlyFeeOverviewService $digifoodFeeService)
    {
    }

    public function index(Request $request): View
    {
        $today = now(config('digifood.business_timezone', config('app.timezone')))->toDateString();

        $institutions = Institution::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $groups = Child::query()
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        $stats = [
            'total' => Child::query()->count(),
            'active' => Child::query()->where('active', true)->count(),
            'eating' => StudentMealSetting::query()
                ->whereDate('valid_from', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('valid_to')
                        ->orWhereDate('valid_to', '>=', $today);
                })
                ->distinct()
                ->count('student_id'),
            'discount_or_dietary' => Child::query()
                ->where(function ($query) {
                    $query->whereHas('discountType', fn ($query) => $query->where('percentage', '>', 0))
                        ->orWhereHas('dietaryRestrictions');
                })
                ->count(),
        ];

        $digifoodFee = $this->digifoodFeeService->calculate($today);

        $children = Child::query()
            ->select('children.*')
            ->join('institutions', 'institutions.id', '=', 'children.institution_id')
            ->with([
                'institution:id,name,type',
                'guardians' => fn ($query) => $query
                    ->orderBy('last_name')
                    ->orderBy('first_name'),
                'discountType:id,name,percentage',
                'dietaryRestrictions:id,institution_id,name,type',
                'classGroups' => fn ($query) => $query
                    ->orderByRaw("CASE WHEN class_group_memberships.status = 'active' THEN 0 ELSE 1 END")
                    ->orderByDesc('class_group_memberships.joined_on')
                    ->orderByDesc('class_group_memberships.id'),
            ])
            ->withCount('guardians')
            ->when($request->filled('institution_id'), function ($query) use ($request) {
                $query->where('children.institution_id', (int) $request->integer('institution_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('children.name', 'like', '%'.trim((string) $request->input('search')).'%');
            })
            ->when($request->filled('group_name'), function ($query) use ($request) {
                $query->where('children.group_name', $request->input('group_name'));
            })
            ->when(in_array($request->input('status'), ['active', 'inactive'], true), function ($query) use ($request) {
                $query->where('children.active', $request->input('status') === 'active');
            })
            ->when(in_array($request->input('meal_status'), ['eating', 'not_eating'], true), function ($query) use ($request, $today) {
                if ($request->input('meal_status') === 'eating') {
                    $query->whereHas('mealSettings', fn ($query) => $this->applyCurrentMealSettingScope($query, $today));

                    return;
                }

                $query->whereDoesntHave('mealSettings', fn ($query) => $this->applyCurrentMealSettingScope($query, $today));
            })
            ->orderBy('institutions.name')
            ->orderBy('children.name')
            ->paginate(20)
            ->withQueryString();

        $currentMealSettings = $this->currentMealSettingsForChildren(
            $children->getCollection()->pluck('id'),
            $today
        );

        $children->getCollection()->transform(function (Child $child) use ($currentMealSettings) {
            $child->setRelation('currentMealSetting', $currentMealSettings->get($child->id));
            $child->setRelation('currentClassGroup', $this->resolveCurrentClassGroup($child));

            return $child;
        });

        return view('dashboard.superadmin.children.index', compact(
            'children',
            'groups',
            'institutions',
            'stats',
            'digifoodFee'
        ));
    }

    public function show(Child $child): View
    {
        $today = now(config('digifood.business_timezone', config('app.timezone')))->toDateString();

        $child->load([
            'institution:id,name,type',
            'guardians' => fn ($query) => $query
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'discountType:id,name,percentage',
            'dietaryRestrictions:id,institution_id,name,type',
            'classGroups' => fn ($query) => $query
                ->orderByRaw("CASE WHEN class_group_memberships.status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('class_group_memberships.joined_on')
                ->orderByDesc('class_group_memberships.id'),
        ]);

        $child->setRelation(
            'currentMealSetting',
            $this->currentMealSettingsForChildren(collect([$child->id]), $today)->get($child->id)
        );
        $child->setRelation('currentClassGroup', $this->resolveCurrentClassGroup($child));

        $institutionUrl = Route::has('dashboard.institutions.edit') && $child->institution
            ? route('dashboard.institutions.edit', $child->institution)
            : null;

        return view('dashboard.superadmin.children.show', [
            'child' => $child,
            'institutionUrl' => $institutionUrl,
        ]);
    }

    private function applyCurrentMealSettingScope($query, string $today): void
    {
        $query->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            });
    }

    private function currentMealSettingsForChildren(Collection $childIds, string $today): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        return StudentMealSetting::query()
            ->with([
                'mealPackage:id,name',
                'mealTypes' => fn ($query) => $query->with('mealType:id,name'),
            ])
            ->whereIn('student_id', $childIds)
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $settings) => $settings->first());
    }

    private function resolveCurrentClassGroup(Child $child)
    {
        $classGroups = $child->classGroups ?? collect();

        $activeGroup = $classGroups->first(fn ($group) => $group->pivot->status === 'active');

        return $activeGroup ?: $classGroups->first();
    }

}
