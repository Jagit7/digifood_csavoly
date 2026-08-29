<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\ManualInvoiceUpdateRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\ManualPaymentDayUpdateRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\PaymentObligationIndexRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\ReopenMonthRequest;
use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Services\PaymentObligation\MonthlyPaymentStatementExportService;
use App\Services\PaymentObligation\MonthlyPaymentStatementListService;
use App\Services\PaymentObligation\MonthlyPaymentSummaryExportService;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use App\Support\PaymentObligation\MonthlyPaymentStatementDetailPresenter;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentObligationController extends Controller
{
    public function __construct(
        private readonly PaymentObligationCalculatorService $calculator,
        private readonly MonthlyPaymentStatementDetailPresenter $detailPresenter,
        private readonly MonthlyPaymentStatementExportService $childExportService,
        private readonly MonthlyPaymentSummaryExportService $summaryExportService,
        private readonly MonthlyPaymentStatementListService $statementListService,
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper,
        private readonly InstitutionPaymentComponentService $componentService
    ) {}

    public function index(PaymentObligationIndexRequest $request): View
    {
        $institution = $this->institution();
        $institutionSetting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
        $period = $this->resolvePeriod($request->input('month'));
        $query = $this->statementListService->query($institution, $period, $request->validated());
        $periods = $this->periodHelper->fromMonth($period);

        $statements = $query->paginate(15)->withQueryString();
        $statements->getCollection()->load('days');
        $financialSummaries = $this->componentService->buildStatementSummaries($statements->getCollection());
        $statements->getCollection()->transform(function (MonthlyPaymentStatement $statement) use ($financialSummaries) {
            $statement->setAttribute('financial_summary', $financialSummaries->get($statement->id, []));

            return $statement;
        });

        $statsQuery = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month);
        $statsStatements = (clone $statsQuery)->get();
        $statsSummaries = $this->componentService->buildStatementSummaries($statsStatements);
        $closeSummary = $this->calculator->summarizeCloseMonth($institution, $period);
        $closedCount = (clone $statsQuery)->where('status', MonthlyPaymentStatement::STATUS_CLOSED)->count();
        $statementCount = (clone $statsQuery)->count();
        $isClosed = $statementCount > 0 && $closedCount === $statementCount;
        $isPartiallyClosed = $closedCount > 0 && $closedCount < $statementCount;
        $isOpen = ! $isClosed && ! $isPartiallyClosed;

        return view('dashboard.institution_admin.payment_obligations.index', [
            'institution' => $institution,
            'institutionSetting' => $institutionSetting,
            'period' => $period,
            'periods' => $periods,
            'statements' => $statements,
            'classGroups' => ClassGroup::query()->where('institution_id', $institution->id)->orderBy('name')->get(),
            'mealPackages' => InstitutionMealPackage::query()->where('institution_id', $institution->id)->orderBy('name')->get(),
            'discountTypes' => DiscountType::query()->where('institution_id', $institution->id)->orderBy('sort_order')->orderBy('name')->get(),
            'stats' => [
                'children' => (clone $statsQuery)->count(),
                'planned_meal_days' => (clone $statsQuery)->sum('planned_meal_days'),
                'previous_month_cancelled_days' => (clone $statsQuery)->sum('previous_month_cancelled_days'),
                'invoiceable_total' => (clone $statsQuery)->sum('invoiceable_amount'),
                'foundation_total' => (clone $statsQuery)->sum('foundation_total_payable'),
                'kindergarten_total' => (clone $statsQuery)->sum('kindergarten_total_payable'),
                'foundation_outstanding' => (int) $statsStatements->sum(function (MonthlyPaymentStatement $statement) use ($statsSummaries) {
                    return max(0, (int) ($statsSummaries->get($statement->id, [])['foundation_balance'] ?? 0));
                }),
                'kindergarten_outstanding' => (int) $statsStatements->sum(function (MonthlyPaymentStatement $statement) use ($statsSummaries) {
                    return max(0, (int) ($statsSummaries->get($statement->id, [])['kindergarten_balance'] ?? 0));
                }),
                'total_payable' => (clone $statsQuery)->sum('total_payable'),
                'closed' => $closedCount,
                'issues' => (clone $statsQuery)->get()->sum(fn (MonthlyPaymentStatement $statement) => count($statement->issues ?? [])),
            ],
            'closeSummary' => $closeSummary,
            'isClosed' => $isClosed,
            'isPartiallyClosed' => $isPartiallyClosed,
            'isOpen' => $isOpen,
            'statementCount' => $statementCount,
        ]);
    }

    public function updateInvoice(
        ManualInvoiceUpdateRequest $request,
        MonthlyPaymentStatement $statement
    ): RedirectResponse {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        if (! $setting->invoicing_enabled || $setting->invoicing_provider !== InstitutionSetting::INVOICING_PROVIDER_MANUAL) {
            return redirect()
                ->route('dashboard.institution.payment-obligations.index', [
                    'month' => sprintf('%04d-%02d', $statement->year, $statement->month),
                ])
                ->with('error', 'Kézi számlaszám csak kézi számlázási módnál menthető.');
        }

        $invoiceNumber = $request->validated('invoice_number');
        $isNewInvoiceNumber = filled($invoiceNumber) && $invoiceNumber !== $statement->invoice_number;

        $statement->update([
            'invoice_number' => $invoiceNumber,
            'invoice_provider' => filled($invoiceNumber)
                ? MonthlyPaymentStatement::INVOICE_PROVIDER_MANUAL
                : null,
            'invoice_status' => filled($invoiceNumber)
                ? MonthlyPaymentStatement::INVOICE_STATUS_ISSUED
                : null,
            'invoiced_at' => filled($invoiceNumber)
                ? ($isNewInvoiceNumber ? now() : $statement->invoiced_at)
                : null,
        ]);

        return redirect()
            ->route('dashboard.institution.payment-obligations.index', [
                'month' => sprintf('%04d-%02d', $statement->year, $statement->month),
            ])
            ->with('success', 'A kézi számlaszám mentése sikeres.')
            ->with('manual_invoice_success', true);
    }

    public function recalculate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $institution = $this->institution();
        $period = $this->resolvePeriod($validated['month']);

        $isClosed = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', MonthlyPaymentStatement::STATUS_CLOSED)
            ->exists();

        if ($isClosed) {
            return redirect()
                ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A lezárt hónap nem számolható újra. Előbb nyisd újra a hónapot.');
        }

        $result = $this->calculator->recalculateMonth($institution, $period);

        if (($result['children'] ?? 0) < 1) {
            return redirect()
                ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'Ehhez a hónaphoz nincs újraszámolható, aktív étkezési adat.');
        }

        return redirect()
            ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
            ->with('success', "Újraszámítás kész: {$result['children']} gyermek, {$result['created']} új és {$result['updated']} frissített kimutatás.");
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
                ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A hónap nem zárható le, amíg hiányos vagy hibás rekordok vannak.');
        }

        if (($result['already_closed'] ?? false) === true) {
            return redirect()
                ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A kiválasztott hónap már le van zárva.');
        }

        if (($result['closed'] ?? 0) < 1) {
            return redirect()
                ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'A kiválasztott hónapban nincs lezárható kimutatás.');
        }

        return redirect()
            ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
            ->with('success', 'A kiválasztott hónap lezárva.');
    }

    public function reopen(ReopenMonthRequest $request): RedirectResponse
    {
        $institution = $this->institution();
        $period = $this->resolvePeriod($request->validated('month'));
        $closedCount = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->where('status', MonthlyPaymentStatement::STATUS_CLOSED)
            ->count();

        if ($closedCount < 1) {
            return redirect()
                ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'Csak lezárt vagy részben lezárt hónap nyitható újra.');
        }

        $reopened = $this->calculator->reopenMonth(
            $institution,
            $period,
            $request->user(),
            $request->string('reopen_reason')->toString()
        );

        if ($reopened < 1) {
            return redirect()
                ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
                ->with('error', 'Nem találtam újranyitható lezárt kimutatást a kiválasztott hónapban.');
        }

        return redirect()
            ->route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')])
            ->with('success', "A kiválasztott hónap újranyitva. Érintett kimutatások: {$reopened}.");
    }

    public function show(Request $request, MonthlyPaymentStatement $statement): View
    {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);
        $institutionSetting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
        $statement->load([
            'child.discountType',
            'mealPackage',
            'days.cancellation',
            'days.schoolBreak',
            'days.classCancellation',
            'days.workingDay',
            'days.modifiedBy',
        ]);

        $highlightDate = $request->input('date');
        $periods = $this->periodHelper->fromStatement($statement);
        $adjustments = $statement->child->financialAdjustments()
            ->with('creator')
            ->where('institution_id', $institution->id)
            ->where(function ($query) use ($statement) {
                $query->where('reference_year', $statement->year)
                    ->where('reference_month', $statement->month)
                    ->orWhereNull('reference_year');
            })
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard.institution_admin.payment_obligations.show', [
            'institution' => $institution,
            'institutionSetting' => $institutionSetting,
            'statement' => $statement,
            'child' => $statement->child,
            'year' => $statement->year,
            'month' => $statement->month,
            'periods' => $periods,
            'highlightDate' => $highlightDate,
            'adjustments' => $adjustments,
            'financialSummary' => $this->componentService->buildStatementSummaries(collect([$statement]))->get($statement->id, []),
            'detailRows' => $this->detailPresenter->rows($statement),
            'statusOptions' => $this->detailPresenter->statusOptions(),
        ]);
    }

    public function updateDay(
        ManualPaymentDayUpdateRequest $request,
        MonthlyPaymentStatement $statement,
        MonthlyPaymentDay $day
    ): RedirectResponse {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);
        abort_if($day->monthly_payment_statement_id !== $statement->id, 404);

        $this->calculator->updateManualDay(
            $day,
            $request->string('status')->toString(),
            $request->integer('payable_amount'),
            $request->string('modification_reason')->toString(),
            $request->user()
        );

        return redirect()
            ->route('dashboard.institution.payment-obligations.show', ['statement' => $statement->id, 'date' => $day->date->toDateString()])
            ->with('success', 'A napi tétel módosítása elmentve.');
    }

    public function resetDay(Request $request, MonthlyPaymentStatement $statement, MonthlyPaymentDay $day): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);
        abort_if($day->monthly_payment_statement_id !== $statement->id, 404);

        $this->calculator->resetManualDay($day);

        return redirect()
            ->route('dashboard.institution.payment-obligations.show', ['statement' => $statement->id, 'date' => $day->date->toDateString()])
            ->with('success', 'A kézi napi módosítás visszaállítva.');
    }

    public function export(Child $child, int $year, int $month): BinaryFileResponse
    {
        return $this->exportChildMonthlyDetails($child, $year, $month);
    }

    public function exportChildMonthlyDetails(Child $child, int $year, int $month): BinaryFileResponse
    {
        $institution = $this->institution();
        abort_if($child->institution_id !== $institution->id, 403);

        $statement = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('child_id', $child->id)
            ->where('year', $year)
            ->where('month', $month)
            ->firstOrFail();

        $statement->load([
            'child.discountType',
            'mealPackage',
            'days.cancellation',
            'days.schoolBreak',
            'days.classCancellation',
            'days.workingDay',
            'days.modifiedBy',
        ]);

        return $this->childExportService->export($statement);
    }

    public function exportMonthlySummary(int $year, int $month): BinaryFileResponse
    {
        $institution = $this->institution();
        $period = Carbon::create($year, $month, 1)->startOfMonth();
        $statements = $this->statementListService
            ->query($institution, $period)
            ->get();

        $statements->load('days');

        return $this->summaryExportService->export($statements, $period);
    }

    /**
     * Nyomtatható, összesítő táblázat a hónap teljes listájáról - ugyanazzal
     * a lekérdezéssel, mint a "Teljes lista Excel-export" (statementListService,
     * szűrők nélkül, a teljes hónap), csak HTML/nyomtatás formátumban.
     */
    public function printMonthlySummary(int $year, int $month): View
    {
        $institution = $this->institution();
        $institutionSetting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
        $period = Carbon::create($year, $month, 1)->startOfMonth();
        $statements = $this->statementListService
            ->query($institution, $period)
            ->get();

        $statements->load('days');

        return view('dashboard.institution_admin.payment_obligations.print', [
            'institution' => $institution,
            'institutionSetting' => $institutionSetting,
            'period' => $period,
            'statements' => $statements,
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizeStatement(MonthlyPaymentStatement $statement, Institution $institution): void
    {
        abort_if($statement->institution_id !== $institution->id, 403);
    }

    private function resolvePeriod(?string $month): Carbon
    {
        return $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfMonth();
    }
}
