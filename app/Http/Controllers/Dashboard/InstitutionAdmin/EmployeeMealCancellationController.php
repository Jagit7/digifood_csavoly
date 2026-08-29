<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMealCancellation;
use App\Models\EmployeeRecurringCancellationRule;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Services\EmployeeMealCancellationService;
use App\Services\InstitutionCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeMealCancellationController extends Controller
{
    public function __construct(
        private readonly EmployeeMealCancellationService $cancellations,
        private readonly InstitutionCalendarService $calendar
    ) {}

    public function index(Request $request): View
    {
        $institution = $this->institution();
        $employees = InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $selectedEmployeeId = $request->integer('institution_employee_id') ?: null;
        $search = trim((string) $request->input('search'));
        $status = in_array($request->input('status'), [
            EmployeeMealCancellation::STATUS_ACTIVE,
            EmployeeMealCancellation::STATUS_REVOKED,
        ], true)
            ? $request->input('status')
            : '';

        $cancellations = EmployeeMealCancellation::query()
            ->where('institution_id', $institution->id)
            ->with([
                'employee:id,name,email',
                'creator:id,name',
                'revoker:id,name',
            ])
            ->when($selectedEmployeeId, fn ($query) => $query->where('institution_employee_id', $selectedEmployeeId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search, $institution) {
                $query->whereHas('employee', function ($query) use ($search, $institution) {
                    $query->where('institution_id', $institution->id)
                        ->where(function ($query) use ($search) {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('service_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('service_date', '<=', $request->input('date_to')))
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'cancellations_page')
            ->withQueryString();

        $today = $this->calendar->now()->toDateString();

        $recurringRules = EmployeeRecurringCancellationRule::query()
            ->where('institution_id', $institution->id)
            ->when($selectedEmployeeId, fn ($query) => $query->where('institution_employee_id', $selectedEmployeeId))
            ->when($status === EmployeeMealCancellation::STATUS_ACTIVE, fn ($query) => $query
                ->where('status', EmployeeRecurringCancellationRule::STATUS_ACTIVE)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today)))
            ->when($status === EmployeeMealCancellation::STATUS_REVOKED, fn ($query) => $query->where(function ($query) use ($today) {
                $query->whereIn('status', [
                    EmployeeRecurringCancellationRule::STATUS_ENDED,
                    EmployeeRecurringCancellationRule::STATUS_REVOKED,
                ])->orWhere(fn ($query) => $query
                    ->where('status', EmployeeRecurringCancellationRule::STATUS_ACTIVE)
                    ->whereDate('ends_on', '<', $today));
            }))
            ->when($search !== '', function ($query) use ($search, $institution) {
                $query->whereHas('employee', function ($query) use ($search, $institution) {
                    $query->where('institution_id', $institution->id)
                        ->where(function ($query) use ($search) {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                });
            })
            ->with(['employee:id,name,email', 'creator:id,name'])
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
            'recorded_today' => EmployeeMealCancellation::where('institution_id', $institution->id)
                ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
                ->whereBetween('updated_at', [$todayStartUtc, $todayEndUtc])->count()
                + EmployeeRecurringCancellationRule::where('institution_id', $institution->id)
                    ->whereBetween('created_at', [$todayStartUtc, $todayEndUtc])->count(),
            'upcoming' => EmployeeMealCancellation::where('institution_id', $institution->id)
                ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
                ->whereDate('service_date', '>=', $today)
                ->count(),
            'recurring' => EmployeeRecurringCancellationRule::where('institution_id', $institution->id)
                ->where('status', EmployeeRecurringCancellationRule::STATUS_ACTIVE)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
                ->count(),
            'next_day_total' => $nextServiceDate
                ? $this->cancellations->effectiveCancellationCount($institution->id, $nextServiceDate)
                : 0,
        ];

        return view('dashboard.institution_admin.employees.meal-cancellations.index', [
            'institution' => $institution,
            'employees' => $employees,
            'cancellations' => $cancellations,
            'recurringRules' => $recurringRules,
            'selectedEmployeeId' => $selectedEmployeeId,
            'status' => $status,
            'stats' => $stats,
            'window' => $window,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $request->merge(['mode' => $request->input('mode', 'single')]);
        $validated = $request->validate([
            'institution_employee_id' => [
                'required',
                'integer',
                Rule::exists('institution_employees', 'id')->where(
                    fn ($query) => $query->where('institution_id', $institution->id)->where('active', true)
                ),
            ],
            'mode' => ['required', Rule::in(['single', 'range', 'recurring'])],
            'service_date' => ['nullable', 'required_if:mode,single', 'date'],
            'date_from' => ['nullable', 'required_if:mode,range', 'date'],
            'date_to' => ['nullable', 'required_if:mode,range', 'date', 'after_or_equal:date_from'],
            'weekday' => ['nullable', 'required_if:mode,recurring', 'integer', 'between:1,7'],
            'starts_on' => ['nullable', 'required_if:mode,recurring', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        $employee = InstitutionEmployee::query()
            ->where('institution_id', $institution->id)
            ->findOrFail($validated['institution_employee_id']);
        $mode = $validated['mode'];
        $reason = $validated['reason'] ?? null;

        if ($mode === 'single') {
            $this->cancellations->recordSingle(
                $institution,
                $employee,
                $validated['service_date'],
                $request->user(),
                $reason
            );
            $message = 'A dolgozói étkezéslemondás sikeresen rögzítve.';
        } elseif ($mode === 'range') {
            $count = $this->cancellations->recordRange(
                $institution,
                $employee,
                $validated['date_from'],
                $validated['date_to'],
                $request->user(),
                $reason
            );
            $message = "A dolgozói lemondás sikeresen rögzítve ({$count} étkezési nap).";
        } else {
            $this->cancellations->recordRecurring(
                $institution,
                $employee,
                (int) $validated['weekday'],
                $validated['starts_on'],
                $validated['ends_on'] ?? null,
                $request->user(),
                $reason
            );
            $message = 'A rendszeres dolgozói lemondás sikeresen rögzítve.';
        }

        return redirect()
            ->route('dashboard.institution.employees.meal-cancellations.index', [
                'institution_employee_id' => $employee->id,
            ])
            ->with('success', $message);
    }

    public function restore(EmployeeMealCancellation $employeeMealCancellation): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($employeeMealCancellation->institution_id !== $institution->id, 403);

        $employeeMealCancellation->loadMissing(['employee', 'institution']);
        $this->cancellations->revoke($employeeMealCancellation, request()->user());

        return back()->with('success', 'A dolgozói lemondás sikeresen visszaállítva.');
    }

    public function destroyRecurring(EmployeeRecurringCancellationRule $employeeRecurringRule): RedirectResponse
    {
        $institution = $this->institution();
        abort_if($employeeRecurringRule->institution_id !== $institution->id, 403);

        $this->cancellations->revokeRecurringRule($employeeRecurringRule, request()->user());

        return back()->with('success', 'A rendszeres dolgozói lemondás lezárva.');
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }
}
