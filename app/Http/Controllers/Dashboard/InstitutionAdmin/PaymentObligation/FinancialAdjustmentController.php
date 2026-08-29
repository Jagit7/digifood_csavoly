<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\FinancialAdjustmentReverseRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\FinancialAdjustmentStoreRequest;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\FinancialAdjustment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinancialAdjustmentController extends Controller
{
    public function __construct(
        private readonly PaymentObligationCalculatorService $calculator,
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper
    ) {}

    public function index(MonthlyPaymentStatement $statement): View
    {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);
        $statement->load('child');

        $adjustments = FinancialAdjustment::query()
            ->with('creator')
            ->where('institution_id', $institution->id)
            ->where('child_id', $statement->child_id)
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.institution_admin.payment_obligations.adjustments.index', [
            'institution' => $institution,
            'statement' => $statement,
            'periods' => $this->periodHelper->fromStatement($statement),
            'adjustments' => $adjustments,
            'splitManualTransferEnabled' => (bool) (InstitutionSetting::firstOrCreate(
                ['institution_id' => $institution->id],
                InstitutionSetting::defaults()
            )->split_manual_transfer_enabled),
            'paymentComponentOptions' => FinancialAdjustment::componentOptions(),
        ]);
    }

    public function store(FinancialAdjustmentStoreRequest $request, MonthlyPaymentStatement $statement): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);

        $type = $request->string('type')->toString();
        $amount = $request->integer('amount');
        $signedAmount = in_array($type, [FinancialAdjustment::TYPE_OPENING_CREDIT, FinancialAdjustment::TYPE_CREDIT], true)
            ? -$amount
            : $amount;

        FinancialAdjustment::create([
            'institution_id' => $institution->id,
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'type' => $type,
            'payment_component' => $request->input('payment_component'),
            'amount' => $signedAmount,
            'affects_invoice' => $request->boolean('affects_invoice'),
            'reference_year' => $request->integer('reference_year') ?: $statement->year,
            'reference_month' => $request->integer('reference_month') ?: $statement->month,
            'reason' => $request->string('reason')->toString(),
            'document_number' => $request->input('document_number'),
            'entry_date' => $request->date('entry_date'),
            'created_by' => $request->user()->id,
        ]);

        $this->calculator->refreshStatementTotals($statement);

        return redirect()
            ->route('dashboard.institution.payment-obligations.adjustments.index', $statement)
            ->with('success', 'A pénzügyi korrekció rögzítve lett.');
    }

    public function reverse(
        FinancialAdjustmentReverseRequest $request,
        MonthlyPaymentStatement $statement,
        FinancialAdjustment $adjustment
    ): RedirectResponse {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);
        abort_if($adjustment->institution_id !== $institution->id || $adjustment->child_id !== $statement->child_id, 404);

        $adjustment->update([
            'reversed_at' => now(),
            'reversed_by' => $request->user()->id,
            'reversal_reason' => $request->string('reversal_reason')->toString(),
        ]);

        $this->calculator->refreshStatementTotals($statement);

        return redirect()
            ->route('dashboard.institution.payment-obligations.adjustments.index', $statement)
            ->with('success', 'A korrekció sztornózva lett.');
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
