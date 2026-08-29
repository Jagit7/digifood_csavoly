<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionDebtIndexRequest;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\Finance\InstitutionDebtService;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\View\View;

class InstitutionDebtController extends Controller
{
    public function __construct(
        private readonly InstitutionDebtService $debtService,
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper
    ) {}

    public function index(InstitutionDebtIndexRequest $request): View
    {
        $institution = $this->institution();
        $filters = $request->validated();
        $query = $this->debtService->query($institution, $filters);
        $summary = $this->debtService->summary($institution, $query);
        $debts = $this->debtService->hydratePage(
            $query
                ->orderByDesc('monthly_payment_statements.year')
                ->orderByDesc('monthly_payment_statements.month')
                ->orderBy('monthly_payment_statements.child_id')
                ->paginate(20)
                ->withQueryString(),
            $institution
        );

        return view('dashboard.institution_admin.finance.debts.index', [
            'institution' => $institution,
            'debts' => $debts,
            'summary' => $summary,
            'groupOptions' => $this->debtService->groupOptions($institution),
            'paymentMethodOptions' => InstitutionPayment::paymentMethodOptions(),
        ]);
    }

    public function show(MonthlyPaymentStatement $statement): View
    {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);
        $statement->load([
            'child.guardians' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
            'mealPackage',
            'discount',
        ]);
        $statement = $this->debtService->hydrateStatement($statement, $institution);
        $payments = $this->debtService->relatedPayments($statement, $institution);

        return view('dashboard.institution_admin.finance.debts.show', [
            'institution' => $institution,
            'statement' => $statement,
            'periods' => $this->periodHelper->fromStatement($statement),
            'payments' => $payments,
            'paymentMethodOptions' => InstitutionPayment::paymentMethodOptions(),
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
}
