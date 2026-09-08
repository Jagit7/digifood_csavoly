<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\MealCancellation;
use App\Models\RecurringCancellationRule;
use App\Services\InstitutionCalendarService;
use App\Services\MealCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MealCancellationController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar,
        private readonly MealCancellationService $cancellations
    ) {}

    public function index(Request $request): View
    {
        $institution = $this->currentAdminInstitution();
        $search = trim((string) $request->input('search'));
        $status = in_array($request->input('status'), ['active', 'revoked'], true)
            ? $request->input('status')
            : 'active';
        $today = $this->calendar->now()->toDateString();

        $mealCancellations = MealCancellation::query()
            ->where('institution_id', $institution->id)
            ->where('status', $status)
            ->with(['child:id,name,group_name', 'creator:id,name'])
            ->when($search !== '', fn ($query) => $query->whereHas('child', fn ($query) => $query
                ->where('institution_id', $institution->id)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', $search.'%')
                        ->orWhere('educational_identifier', 'like', $search.'%');
                })))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('service_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('service_date', '<=', $request->input('date_to')))
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'cancellations_page')
            ->withQueryString();

        $recurringRules = RecurringCancellationRule::query()
            ->where('institution_id', $institution->id)
            ->where($status === 'active'
                ? fn ($query) => $query
                    ->where('status', RecurringCancellationRule::STATUS_ACTIVE)
                    ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
                : fn ($query) => $query->where(function ($query) use ($today) {
                    $query->whereIn('status', [
                        RecurringCancellationRule::STATUS_ENDED,
                        RecurringCancellationRule::STATUS_REVOKED,
                    ])->orWhere(fn ($query) => $query
                        ->where('status', RecurringCancellationRule::STATUS_ACTIVE)
                        ->whereDate('ends_on', '<', $today));
                }))
            ->with(['child:id,name,group_name', 'creator:id,name'])
            ->when($search !== '', fn ($query) => $query->whereHas('child', fn ($query) => $query
                ->where('institution_id', $institution->id)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', $search.'%')
                        ->orWhere('educational_identifier', 'like', $search.'%');
                })))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'rules_page')
            ->withQueryString();

        $now = $this->calendar->now();
        $todayStartUtc = $now->startOfDay()->utc();
        $todayEndUtc = $now->endOfDay()->utc();
        $window = $this->calendar->cancellationWindow($institution->id, $now);
        $nextServiceDate = $window['next_service_day']?->toDateString();

        $stats = [
            'recorded_today' => MealCancellation::where('institution_id', $institution->id)
                ->where('status', MealCancellation::STATUS_ACTIVE)
                ->whereBetween('updated_at', [$todayStartUtc, $todayEndUtc])->count()
                + RecurringCancellationRule::where('institution_id', $institution->id)
                    ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->count(),
            'upcoming' => MealCancellation::where('institution_id', $institution->id)
                ->where('status', MealCancellation::STATUS_ACTIVE)
                ->whereDate('service_date', '>=', $today)
                ->count(),
            'recurring' => RecurringCancellationRule::where('institution_id', $institution->id)
                ->where('status', RecurringCancellationRule::STATUS_ACTIVE)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
                ->count(),
            'next_day_total' => $nextServiceDate
                ? $this->cancellations->effectiveCancellationCount($institution->id, $nextServiceDate)
                : 0,
        ];

        return view('dashboard.institution_admin.meal-cancellations.index', compact(
            'institution',
            'mealCancellations',
            'recurringRules',
            'stats',
            'status',
            'window'
        ));
    }

    public function create(Request $request): View
    {
        $institution = $this->currentAdminInstitution();
        $child = null;

        if ($request->filled('child_id') || $request->old('child_id')) {
            $child = Child::where('institution_id', $institution->id)
                ->findOrFail($request->input('child_id', $request->old('child_id')));
        }

        $childSearch = trim((string) $request->input('child_search'));
        $childResults = collect();

        if (! $child && mb_strlen($childSearch) >= 2) {
            $childResults = Child::query()
                ->where('institution_id', $institution->id)
                ->where(function ($query) use ($childSearch) {
                    $query->where('name', 'like', $childSearch.'%')
                        ->orWhere('educational_identifier', 'like', $childSearch.'%');
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'group_name', 'educational_identifier']);
        }

        $window = $this->calendar->cancellationWindow($institution->id);
        $weekdays = $this->weekdays();

        return view('dashboard.institution_admin.meal-cancellations.create', compact(
            'institution',
            'child',
            'childSearch',
            'childResults',
            'window',
            'weekdays'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        $validated = $request->validate([
            'child_id' => [
                'required',
                'integer',
                Rule::exists('children', 'id')->where(fn ($query) => $query
                    ->where('institution_id', $institution->id)),
            ],
            'mode' => ['required', Rule::in(['single', 'range', 'recurring'])],
            'service_date' => ['nullable', 'required_if:mode,single', 'date'],
            'date_from' => ['nullable', 'required_if:mode,range', 'date'],
            'date_to' => ['nullable', 'required_if:mode,range', 'date', 'after_or_equal:date_from'],
            'weekday' => ['nullable', 'required_if:mode,recurring', 'integer', 'between:1,7'],
            'starts_on' => ['nullable', 'required_if:mode,recurring', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:191'],
            'admin_override' => ['sometimes', 'boolean'],
        ]);

        $child = Child::where('institution_id', $institution->id)->findOrFail($validated['child_id']);
        $reason = $validated['reason'] ?? null;

        if ($validated['mode'] === 'single') {
            $count = $this->cancellations->recordSingle(
                $institution,
                $child,
                $validated['service_date'],
                auth()->user(),
                $reason,
                $request->boolean('admin_override')
            );
            $message = "A lemondás sikeresen rögzítve ({$count} étkezési nap).";
        } elseif ($validated['mode'] === 'range') {
            $count = $this->cancellations->recordRange(
                $institution,
                $child,
                $validated['date_from'],
                $validated['date_to'],
                auth()->user(),
                $reason,
                $request->boolean('admin_override')
            );
            $message = "A lemondás sikeresen rögzítve ({$count} étkezési nap).";
        } else {
            $this->cancellations->recordRecurring(
                $institution,
                $child,
                (int) $validated['weekday'],
                $validated['starts_on'],
                $validated['ends_on'] ?? null,
                auth()->user(),
                $reason,
                $request->boolean('admin_override')
            );
            $message = 'A rendszeres lemondás sikeresen rögzítve.';
        }

        return redirect()
            ->route('dashboard.institution.meal-cancellations.index')
            ->with('success', $message);
    }

    public function destroy(MealCancellation $mealCancellation): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        abort_if($mealCancellation->institution_id !== $institution->id, 403);
        $this->cancellations->revokeCancellation($mealCancellation, auth()->user());

        return back()->with('success', 'Az egyéni lemondás visszavonva.');
    }

    public function destroyRecurring(RecurringCancellationRule $recurringCancellationRule): RedirectResponse
    {
        $institution = $this->currentAdminInstitution();
        abort_if($recurringCancellationRule->institution_id !== $institution->id, 403);
        $this->cancellations->revokeRecurringRule($recurringCancellationRule, auth()->user());

        return back()->with('success', 'A rendszeres lemondás lezárva.');
    }

    private function weekdays(): array
    {
        return [
            1 => 'Hétfő',
            2 => 'Kedd',
            3 => 'Szerda',
            4 => 'Csütörtök',
            5 => 'Péntek',
            6 => 'Szombat',
            7 => 'Vasárnap',
        ];
    }
}
