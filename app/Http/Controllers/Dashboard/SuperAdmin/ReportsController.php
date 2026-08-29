<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\EmployeeMealCancellation;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionEmployee;
use App\Models\MealCancellation;
use App\Models\MealCheckIn;
use App\Models\PartnerMonthlyBilling;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $previousMonth = $selectedMonth->subMonth();

        $employeeEaterType = (new InstitutionEmployee)->getMorphClass();

        $monthlyMeals = $this->monthlyMealCounts($selectedMonth);
        $monthlyEmployeeMeals = $this->monthlyMealCounts($selectedMonth, $employeeEaterType);
        $monthlyChildCancellations = $this->monthlyCancellationCounts($selectedMonth);
        $monthlyEmployeeCancellations = $this->monthlyEmployeeCancellationCounts($selectedMonth);
        $monthlyBillingItems = $this->monthlyBillingItems($selectedMonth);

        $institutions = Institution::query()
            ->select('institutions.*')
            ->leftJoinSub($this->activeChildrenCounts(), 'active_children', function ($join) {
                $join->on('active_children.institution_id', '=', 'institutions.id');
            })
            ->leftJoinSub($this->activeEmployeeCounts(), 'active_employees', function ($join) {
                $join->on('active_employees.institution_id', '=', 'institutions.id');
            })
            ->leftJoinSub($this->activeParentCounts(), 'active_parents', function ($join) {
                $join->on('active_parents.institution_id', '=', 'institutions.id');
            })
            ->leftJoinSub($monthlyMeals, 'monthly_meals', function ($join) {
                $join->on('monthly_meals.institution_id', '=', 'institutions.id');
            })
            ->leftJoinSub($monthlyEmployeeMeals, 'monthly_employee_meals', function ($join) {
                $join->on('monthly_employee_meals.institution_id', '=', 'institutions.id');
            })
            ->leftJoinSub($monthlyChildCancellations, 'monthly_child_cancellations', function ($join) {
                $join->on('monthly_child_cancellations.institution_id', '=', 'institutions.id');
            })
            ->leftJoinSub($monthlyEmployeeCancellations, 'monthly_employee_cancellations', function ($join) {
                $join->on('monthly_employee_cancellations.institution_id', '=', 'institutions.id');
            })
            ->leftJoinSub($monthlyBillingItems, 'monthly_billing_items', function ($join) {
                $join->on('monthly_billing_items.institution_id', '=', 'institutions.id');
            })
            ->addSelect([
                DB::raw('COALESCE(active_children.active_children_count, 0) as active_children_count'),
                DB::raw('COALESCE(active_employees.active_employees_count, 0) as active_employees_count'),
                DB::raw('COALESCE(active_parents.active_parents_count, 0) as active_parents_count'),
                DB::raw('COALESCE(monthly_meals.monthly_meals_count, 0) as monthly_meals_count'),
                DB::raw('COALESCE(monthly_employee_meals.monthly_meals_count, 0) as monthly_employee_meals_count'),
                DB::raw('COALESCE(monthly_child_cancellations.monthly_cancellations_count, 0) as monthly_child_cancellations_count'),
                DB::raw('COALESCE(monthly_employee_cancellations.monthly_cancellations_count, 0) as monthly_employee_cancellations_count'),
                DB::raw('COALESCE(monthly_child_cancellations.monthly_cancellations_count, 0) + COALESCE(monthly_employee_cancellations.monthly_cancellations_count, 0) as monthly_cancellations_count'),
                DB::raw('monthly_billing_items.monthly_billing_amount as monthly_billing_amount'),
                DB::raw('monthly_billing_items.monthly_billing_status as monthly_billing_status'),
            ])
            ->orderBy('institutions.name')
            ->paginate(15)
            ->withQueryString();

        $monthlyMealsTotal = $this->monthlyMealTotal($selectedMonth);
        $monthlyEmployeeMealsTotal = $this->monthlyMealTotal($selectedMonth, $employeeEaterType);
        $previousMonthlyMealsTotal = $this->monthlyMealTotal($previousMonth);

        $monthlyChildCancellationsTotal = $this->monthlyCancellationTotal($selectedMonth);
        $monthlyEmployeeCancellationsTotal = $this->monthlyEmployeeCancellationTotal($selectedMonth);
        $monthlyCancellationsTotal = $monthlyChildCancellationsTotal + $monthlyEmployeeCancellationsTotal;

        $previousMonthlyChildCancellationsTotal = $this->monthlyCancellationTotal($previousMonth);
        $previousMonthlyEmployeeCancellationsTotal = $this->monthlyEmployeeCancellationTotal($previousMonth);
        $previousMonthlyCancellationsTotal = $previousMonthlyChildCancellationsTotal + $previousMonthlyEmployeeCancellationsTotal;

        $topStats = [
            'active_institutions' => Institution::query()->where('active', true)->count(),
            'active_children' => Child::query()->where('active', true)->count(),
            'active_employees' => InstitutionEmployee::query()->where('active', true)->count(),
            'active_parents' => $this->activeParentUserCount(),
            'monthly_meals' => $monthlyMealsTotal,
            'monthly_employee_meals' => $monthlyEmployeeMealsTotal,
        ];

        $financialSummary = $this->financialSummary($selectedMonth);

        // TODO: A lemondási arányhoz hiányzik egy megbízható, hónapra összesített "eredetileg tervezett étkezések" adatforrás.
        return view('dashboard.superadmin.reports.index', [
            'institutions' => $institutions,
            'topStats' => $topStats,
            'summary' => [
                'monthly_meals' => $monthlyMealsTotal,
                'monthly_child_meals' => $monthlyMealsTotal - $monthlyEmployeeMealsTotal,
                'monthly_employee_meals' => $monthlyEmployeeMealsTotal,
                'monthly_cancellations' => $monthlyCancellationsTotal,
                'monthly_child_cancellations' => $monthlyChildCancellationsTotal,
                'monthly_employee_cancellations' => $monthlyEmployeeCancellationsTotal,
                'cancellation_ratio' => null,
                'meal_change_percent' => $this->percentageChange($monthlyMealsTotal, $previousMonthlyMealsTotal),
                'cancellation_change_percent' => $this->percentageChange($monthlyCancellationsTotal, $previousMonthlyCancellationsTotal),
            ],
            'financialSummary' => $financialSummary,
            'selectedMonth' => $selectedMonth,
            'selectedMonthQuery' => $selectedMonth->format('Y-m'),
            'selectedMonthLabel' => $this->formatMonthLabel($selectedMonth),
            'previousMonthQuery' => $previousMonth->format('Y-m'),
            'nextMonthQuery' => $selectedMonth->addMonth()->format('Y-m'),
            'currentMonthQuery' => $this->currentMonth()->format('Y-m'),
            'statusMeta' => $this->statusMeta(),
        ]);
    }

    private function activeChildrenCounts()
    {
        return Child::query()
            ->selectRaw('institution_id, COUNT(*) as active_children_count')
            ->where('active', true)
            ->groupBy('institution_id');
    }

    private function activeEmployeeCounts()
    {
        return InstitutionEmployee::query()
            ->selectRaw('institution_id, COUNT(*) as active_employees_count')
            ->where('active', true)
            ->groupBy('institution_id');
    }

    private function activeParentCounts()
    {
        return Guardian::query()
            ->selectRaw('guardians.institution_id, COUNT(DISTINCT guardians.user_id) as active_parents_count')
            ->join('users', 'users.id', '=', 'guardians.user_id')
            ->where('users.role', User::ROLE_PARENT)
            ->where('users.is_active', true)
            ->groupBy('guardians.institution_id');
    }

    private function activeParentUserCount(): int
    {
        return DB::table('users')
            ->where('role', User::ROLE_PARENT)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Havi sikeres étkezési beléptetések intézményenként.
     * $eaterType nélkül gyermek + dolgozó összesen; ha meg van adva, csak az adott étkező típusra szűkítve.
     */
    private function monthlyMealCounts(CarbonImmutable $month, ?string $eaterType = null)
    {
        return MealCheckIn::query()
            ->selectRaw('institution_id, COUNT(*) as monthly_meals_count')
            ->where('status', MealCheckIn::STATUS_SUCCESS)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($eaterType !== null, fn ($query) => $query->where('eater_type', $eaterType))
            ->groupBy('institution_id');
    }

    private function monthlyMealTotal(CarbonImmutable $month, ?string $eaterType = null): int
    {
        return MealCheckIn::query()
            ->where('status', MealCheckIn::STATUS_SUCCESS)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($eaterType !== null, fn ($query) => $query->where('eater_type', $eaterType))
            ->count();
    }

    /**
     * Havi aktív gyermeklemondások intézményenként.
     */
    private function monthlyCancellationCounts(CarbonImmutable $month)
    {
        return MealCancellation::query()
            ->selectRaw('institution_id, COUNT(*) as monthly_cancellations_count')
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->groupBy('institution_id');
    }

    private function monthlyCancellationTotal(CarbonImmutable $month): int
    {
        return MealCancellation::query()
            ->where('status', MealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->count();
    }

    /**
     * Havi aktív dolgozói lemondások intézményenként.
     */
    private function monthlyEmployeeCancellationCounts(CarbonImmutable $month)
    {
        return EmployeeMealCancellation::query()
            ->selectRaw('institution_id, COUNT(*) as monthly_cancellations_count')
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->groupBy('institution_id');
    }

    private function monthlyEmployeeCancellationTotal(CarbonImmutable $month): int
    {
        return EmployeeMealCancellation::query()
            ->where('status', EmployeeMealCancellation::STATUS_ACTIVE)
            ->whereBetween('service_date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->count();
    }

    private function monthlyBillingItems(CarbonImmutable $month)
    {
        return DB::table('partner_monthly_billing_items')
            ->join('partner_monthly_billings', 'partner_monthly_billings.id', '=', 'partner_monthly_billing_items.partner_monthly_billing_id')
            ->selectRaw('partner_monthly_billing_items.institution_id, MAX(partner_monthly_billing_items.net_amount) as monthly_billing_amount, MAX(partner_monthly_billings.status) as monthly_billing_status')
            ->whereDate('partner_monthly_billings.billing_month', $month->toDateString())
            ->groupBy('partner_monthly_billing_items.institution_id');
    }

    private function financialSummary(CarbonImmutable $month): array
    {
        $billings = PartnerMonthlyBilling::query()
            ->whereDate('billing_month', $month->toDateString());

        // TODO: Lejárt és kézzel módosított intézményi tételekhez a partneri havi snapshotokból nem áll rendelkezésre biztos adat.
        return [
            'total_amount' => (clone $billings)->sum('gross_amount'),
            'paid_amount' => (clone $billings)->where('status', 'paid')->sum('gross_amount'),
            'pending_amount' => (clone $billings)->where('status', '!=', 'paid')->sum('gross_amount'),
            'overdue_amount' => null,
            'manual_items_count' => null,
        ];
    }

    private function percentageChange(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : (($current > 0) ? 100 : -100);
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function statusMeta(): array
    {
        return [
            'draft' => ['label' => 'Tervezet', 'class' => 'badge badge-warning light'],
            'invoiced' => ['label' => 'Számlázva', 'class' => 'badge badge-info light'],
            'paid' => ['label' => 'Fizetve', 'class' => 'badge badge-success light'],
            'missing' => ['label' => 'Nincs adat', 'class' => 'badge badge-secondary light'],
        ];
    }

    private function resolveMonth(?string $value): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $this->currentMonth();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value . '-01', config('app.timezone'))->startOfMonth();
        } catch (\Throwable) {
            return $this->currentMonth();
        }
    }

    private function currentMonth(): CarbonImmutable
    {
        return CarbonImmutable::now(config('digifood.business_timezone', config('app.timezone')))->startOfMonth();
    }

    private function formatMonthLabel(CarbonImmutable $month): string
    {
        $months = [
            1 => 'január',
            2 => 'február',
            3 => 'március',
            4 => 'április',
            5 => 'május',
            6 => 'június',
            7 => 'július',
            8 => 'augusztus',
            9 => 'szeptember',
            10 => 'október',
            11 => 'november',
            12 => 'december',
        ];

        return sprintf('%d. %s', $month->year, $months[$month->month]);
    }
}
