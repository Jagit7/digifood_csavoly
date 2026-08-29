<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\EmployeePaymentObligationIndexRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\ReopenMonthRequest;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Services\PaymentObligation\EmployeeMonthlyPaymentStatementListService;
use App\Services\PaymentObligation\EmployeePaymentObligationCalculatorService;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EmployeePaymentObligationController extends Controller
{
    public function __construct(
        private readonly EmployeePaymentObligationCalculatorService $calculator,
        private readonly EmployeeMonthlyPaymentStatementListService $statementListService,
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper
    ) {}

    public function index(EmployeePaymentObligationIndexRequest $request): View
    {
        $institution = $this->institution();
        $period = $this->resolvePeriod($request->input('month'));
        $query = $this->statementListService->query($institution, $period, $request->validated());
        $periods = $this->periodHelper->fromMonth($period);

        $statements = $query->paginate(15)->withQueryString();
        $statements->getCollection()->load('days');

        $statsQuery = EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month);
        $closeSummary = $this->calculator->summarizeCloseMonth($institution, $period);
        $closedCount = (clone $statsQuery)->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED)->count();
        $statementCount = (clone $statsQuery)->count();
        $isClosed = $statementCount > 0 && $closedCount === $statementCount;
        $isPartiallyClosed = $closedCount > 0 && $closedCount < $statementCount;
        $isOpen = ! $isClosed && ! $isPartiallyClosed;

        return view('dashboard.institution_admin.employee_payment_obligations.index', [
            'institution' => $institution,
            'period' => $period,
            'periods' => $periods,
            'statements' => $statements,
            'mealPackages' => InstitutionMealPackage::query()->where('institution_id', $institution->id)->orderBy('name')->get(),
            'discountTypes' => DiscountType::query()->where('institution_id', $institution->id)->orderBy('sort_order')->orderBy('name')->get(),
            'stats' => [
                'employees' => (clone $statsQuery)->count(),
                'invoiceable_total' => (clone $statsQuery)->sum('invoiceable_amount'),
                'total_payable' => (clone $statsQuery)->sum('total_payable'),
                'closed' => $closedCount,
                'issues' => (clone $statsQuery)->get()->sum(fn (EmployeeMonthlyPaymentStatement $statement) => count($statement->issues ?? [])),
            ],
            'closeSummary' => $closeSummary,
            'isClosed' => $isClosed,
            'isPartiallyClosed' => $isPartiallyClosed,
            'isOpen' => $isOpen,
            'statementCount' => $statementCount,
        ]);
    }

    public function recalculate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $institution = $this->institution();
        $period = $this->resolvePeriod($validated['month']);

        $isClosed = EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED)
            ->exists();

        if ($isClosed) {
            return redirect()
                ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A lezárt dolgozói hónap nem számolható újra. Előbb nyisd újra a hónapot.');
        }

        $result = $this->calculator->recalculateMonth($institution, $period);

        if (($result['employees'] ?? 0) < 1) {
            return redirect()
                ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'Ehhez a hónaphoz nincs újraszámolható, aktív dolgozói étkezési adat.');
        }

        return redirect()
            ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
            ->with('success', "Újraszámítás kész: {$result['employees']} dolgozó, {$result['created']} új és {$result['updated']} frissített kimutatás.");
    }

    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $institution = $this->institution();
        $period = $this->resolvePeriod($validated['month']);
        $result = $this->calculator->closeMonth($institution, $period, $request->user());

        if ($result['issue_count'] > 0) {
            return redirect()
                ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A dolgozói hónap nem zárható le, amíg hiányos vagy hibás rekordok vannak.');
        }

        if (($result['already_closed'] ?? false) === true) {
            return redirect()
                ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A kiválasztott dolgozói hónap már le van zárva.');
        }

        if (($result['closed'] ?? 0) < 1) {
            return redirect()
                ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A kiválasztott hónapban nincs lezárható dolgozói kimutatás.');
        }

        return redirect()
            ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
            ->with('success', 'A kiválasztott dolgozói hónap lezárva.');
    }

    public function reopen(ReopenMonthRequest $request): RedirectResponse
    {
        $institution = $this->institution();
        $period = $this->resolvePeriod($request->validated('month'));
        $closedCount = EmployeeMonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', EmployeeMonthlyPaymentStatement::STATUS_CLOSED)
            ->count();

        if ($closedCount < 1) {
            return redirect()
                ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'Csak lezárt vagy részben lezárt dolgozói hónap nyitható újra.');
        }

        $reopened = $this->calculator->reopenMonth(
            $institution,
            $period,
            $request->user(),
            $request->string('reopen_reason')->toString()
        );

        if ($reopened < 1) {
            return redirect()
                ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'Nem találtam újranyitható dolgozói kimutatást a kiválasztott hónapban.');
        }

        return redirect()
            ->route('dashboard.institution.employee-payment-obligations.index', ['month' => $period->format('Y-m')])
            ->with('success', "A kiválasztott dolgozói hónap újranyitva. Érintett kimutatások: {$reopened}.");
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function resolvePeriod(?string $month): Carbon
    {
        return $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfMonth();
    }
}
