<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionPaymentIndexRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionPaymentUpsertRequest;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\Finance\InstitutionInvoiceService;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Support\AuditLogger;
use App\Support\Finance\PaymentComponent;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionPaymentController extends Controller
{
    public function __construct(
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper,
        private readonly InstitutionInvoiceService $invoiceService,
        private readonly InstitutionPaymentComponentService $componentService,
    ) {}

    public function index(InstitutionPaymentIndexRequest $request): View
    {
        $institution = $this->institution();
        $filters = $request->validated();
        $query = $this->query($institution, $filters);

        $summary = [
            'count' => (clone $query)->count(),
            'amount_total' => (clone $query)->sum('amount'),
            'completed_total' => (clone $query)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->sum('amount'),
            'pending_count' => (clone $query)
                ->where('status', InstitutionPayment::STATUS_PENDING)
                ->count(),
        ];

        $payments = $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.institution_admin.finance.payments.index', [
            'institution' => $institution,
            'payments' => $payments,
            'summary' => $summary,
            'statusOptions' => InstitutionPayment::statusOptions(),
            'paymentMethodOptions' => InstitutionPayment::paymentMethodOptions(),
        ]);
    }

    public function create(): View
    {
        $institution = $this->institution();
        $payment = new InstitutionPayment;
        $payment->fill([
            'child_id' => request('child_id'),
            'guardian_id' => request('guardian_id'),
            'monthly_payment_statement_id' => request('monthly_payment_statement_id'),
            'payment_component' => request('payment_component'),
            'amount' => request('amount'),
        ]);
        $selectedChild = $this->selectedChild($institution, $payment);

        return view('dashboard.institution_admin.finance.payments.create', [
            'institution' => $institution,
            'payment' => $payment,
            'selectedChild' => $selectedChild,
            'selectedGuardians' => $selectedChild ? $this->guardiansForChild($selectedChild->id, $institution->id) : collect(),
            'selectedStatements' => $selectedChild ? $this->statementsForChild($selectedChild->id, $institution->id) : collect(),
            'statusOptions' => InstitutionPayment::statusOptions(),
            'paymentMethodOptions' => InstitutionPayment::paymentMethodOptions(),
            'paymentComponentOptions' => InstitutionPayment::componentOptions(),
            'splitManualTransferEnabled' => (bool) ($institution->setting?->split_manual_transfer_enabled ?? false),
        ]);
    }

    public function store(InstitutionPaymentUpsertRequest $request): RedirectResponse
    {
        $institution = $this->institution();

        $payment = new InstitutionPayment;
        $payment->fill($request->safe()->only([
            'child_id',
            'guardian_id',
            'monthly_payment_statement_id',
            'payment_component',
            'invoice_number',
            'amount',
            'paid_at',
            'payment_method',
            'status',
            'reference',
            'note',
        ]));
        $payment->institution_id = $institution->id;
        $payment->recorded_by = $request->user()->id;
        $payment->save();
        $this->componentService->syncAllocationsForPayment($payment->fresh());

        AuditLogger::log(
            action: AuditLog::ACTION_INSTITUTION_PAYMENT_CREATED,
            description: 'Befizetés rögzítve: '.number_format((float) $payment->amount, 0, ',', ' ').' Ft ('.$payment->reference.').',
            subject: $payment,
            institutionId: $institution->id,
            newValues: $this->auditablePaymentValues($payment),
        );

        return redirect()
            ->route('dashboard.institution.finance.payments.show', $payment)
            ->with('success', 'A befizetés rögzítése sikeres.');
    }

    public function show(InstitutionPayment $payment): View
    {
        $institution = $this->institution();
        $this->authorizePayment($payment, $institution);
        $payment->load(['child', 'guardian', 'monthlyPaymentStatement.child', 'recordedBy', 'allocations.statement']);

        return view('dashboard.institution_admin.finance.payments.show', [
            'institution' => $institution,
            'payment' => $payment,
            'invoiceAction' => $this->invoiceService->paymentInvoiceAction($institution, $payment),
        ]);
    }

    public function edit(InstitutionPayment $payment): View
    {
        $institution = $this->institution();
        $this->authorizePayment($payment, $institution);
        $payment->load(['child', 'guardian', 'monthlyPaymentStatement']);
        $selectedChild = $this->selectedChild($institution, $payment);

        return view('dashboard.institution_admin.finance.payments.edit', [
            'institution' => $institution,
            'payment' => $payment,
            'selectedChild' => $selectedChild,
            'selectedGuardians' => $selectedChild ? $this->guardiansForChild($selectedChild->id, $institution->id) : collect(),
            'selectedStatements' => $selectedChild ? $this->statementsForChild($selectedChild->id, $institution->id) : collect(),
            'statusOptions' => InstitutionPayment::statusOptions(),
            'paymentMethodOptions' => InstitutionPayment::paymentMethodOptions(),
            'paymentComponentOptions' => InstitutionPayment::componentOptions(),
            'splitManualTransferEnabled' => (bool) ($institution->setting?->split_manual_transfer_enabled ?? false),
        ]);
    }

    public function searchChildren(): JsonResponse
    {
        $institution = $this->institution();
        $term = trim((string) request('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $children = Child::query()
            ->where('institution_id', $institution->id)
            ->where(function (Builder $query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('group_name', 'like', "%{$term}%")
                    ->orWhere('educational_identifier', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'group_name', 'educational_identifier']);

        return response()->json([
            'results' => $children->map(fn (Child $child) => [
                'id' => $child->id,
                'text' => collect([
                    $child->name,
                    $child->group_name,
                    $child->educational_identifier,
                ])->filter()->implode(' – '),
            ])->values(),
        ]);
    }

    public function childGuardians(Child $child): JsonResponse
    {
        $institution = $this->institution();
        abort_if($child->institution_id !== $institution->id, 403);

        $guardians = $this->guardiansForChild($child->id, $institution->id);

        return response()->json([
            'results' => $guardians->map(fn (Guardian $guardian) => [
                'id' => $guardian->id,
                'text' => $guardian->full_name,
            ])->values(),
            'auto_select_id' => $guardians->count() === 1 ? $guardians->first()->id : null,
            'message' => $guardians->isEmpty() ? 'A kiválasztott gyermekhez nincs kapcsolt gondviselő.' : null,
        ]);
    }

    public function childStatements(Child $child): JsonResponse
    {
        $institution = $this->institution();
        abort_if($child->institution_id !== $institution->id, 403);

        $statements = $this->statementsForChild($child->id, $institution->id);

        return response()->json([
            'results' => $statements->map(fn (MonthlyPaymentStatement $statement) => $this->statementOptionData($statement))->values(),
        ]);
    }

    public function update(InstitutionPaymentUpsertRequest $request, InstitutionPayment $payment): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizePayment($payment, $institution);

        $beforeValues = $this->auditablePaymentValues($payment);

        $payment->fill($request->safe()->only([
            'child_id',
            'guardian_id',
            'monthly_payment_statement_id',
            'payment_component',
            'invoice_number',
            'amount',
            'paid_at',
            'payment_method',
            'status',
            'reference',
            'note',
        ]));
        $payment->save();
        $this->componentService->syncAllocationsForPayment($payment->fresh());

        $afterValues = $this->auditablePaymentValues($payment->fresh());
        [$oldValues, $newValues] = $this->diffAuditValues($beforeValues, $afterValues);

        if ($oldValues !== [] || $newValues !== []) {
            AuditLogger::log(
                action: AuditLog::ACTION_INSTITUTION_PAYMENT_UPDATED,
                description: 'Befizetés módosítva: '.$payment->reference.'.',
                subject: $payment,
                institutionId: $institution->id,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        }

        return redirect()
            ->route('dashboard.institution.finance.payments.show', $payment)
            ->with('success', 'A befizetés adatai frissültek.');
    }

    public function quickPay(Request $request, MonthlyPaymentStatement $statement): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizeStatement($statement, $institution);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(InstitutionPayment::paymentMethodOptions()))],
            'guardian_id' => [
                'nullable',
                'integer',
                Rule::exists('guardians', 'id')->where(fn ($query) => $query->where('institution_id', $institution->id)),
            ],
            'payment_component' => ['required', Rule::in(PaymentComponent::all())],
            'custom_amount' => ['nullable', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $statementSummary = $this->componentService->buildStatementSummaries(collect([$statement]))->get($statement->id, []);
        $component = $validated['payment_component'];
        $remainingAmount = max(0, (int) ($statementSummary[$component.'_remaining'] ?? 0));

        if ($remainingAmount <= 0) {
            return back()->with('error', 'Ehhez a komponenshez már nincs fennmaradó tartozás.');
        }

        $amount = (int) ($validated['custom_amount'] ?? $remainingAmount);
        $isExactAmount = $amount === $remainingAmount;

        $payment = new InstitutionPayment;
        $payment->fill([
            'child_id' => $statement->child_id,
            'guardian_id' => $validated['guardian_id'] ?? null,
            'monthly_payment_statement_id' => $statement->id,
            'payment_component' => $component,
            'amount' => $amount,
            'paid_at' => now(),
            'payment_method' => $validated['payment_method'],
            'status' => InstitutionPayment::STATUS_COMPLETED,
            'reference' => $validated['reference'] ?? null,
            'note' => $isExactAmount
                ? 'Gyors rögzítés a tartozáslistáról: a pontos összeg érkezett be.'
                : 'Gyors rögzítés a tartozáslistáról: a fennmaradó tartozástól eltérő összeg érkezett.',
        ]);
        $payment->institution_id = $institution->id;
        $payment->recorded_by = $request->user()->id;
        $payment->save();
        $this->componentService->syncAllocationsForPayment($payment->fresh());

        AuditLogger::log(
            action: AuditLog::ACTION_INSTITUTION_PAYMENT_CREATED,
            description: 'Befizetés gyors rögzítése: '.number_format((float) $payment->amount, 0, ',', ' ').' Ft ('.($isExactAmount ? 'pontos összeg' : 'eltérő összeg').').',
            subject: $payment,
            institutionId: $institution->id,
            newValues: $this->auditablePaymentValues($payment),
        );

        return back()->with('success', 'A befizetés rögzítve lett.');
    }

    public function destroy(InstitutionPayment $payment): RedirectResponse
    {
        $institution = $this->institution();
        $this->authorizePayment($payment, $institution);

        AuditLogger::log(
            action: AuditLog::ACTION_INSTITUTION_PAYMENT_DELETED,
            description: 'Befizetés törölve: '.number_format((float) $payment->amount, 0, ',', ' ').' Ft ('.$payment->reference.').',
            subject: $payment,
            institutionId: $institution->id,
            oldValues: $this->auditablePaymentValues($payment),
        );

        $payment->delete();

        return redirect()
            ->route('dashboard.institution.finance.payments')
            ->with('success', 'A befizetés törölve lett.');
    }

    private function query(Institution $institution, array $filters = []): Builder
    {
        return InstitutionPayment::query()
            ->with(['child', 'guardian', 'monthlyPaymentStatement', 'recordedBy', 'allocations'])
            ->where('institution_id', $institution->id)
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('child', fn (Builder $childQuery) => $childQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('guardian', function (Builder $guardianQuery) use ($search) {
                            $guardianQuery->where('last_name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['payment_method']), fn (Builder $query) => $query->where('payment_method', $filters['payment_method']))
            ->when(! empty($filters['date_from']), fn (Builder $query) => $query->whereDate('paid_at', '>=', Carbon::parse($filters['date_from'])->toDateString()))
            ->when(! empty($filters['date_to']), fn (Builder $query) => $query->whereDate('paid_at', '<=', Carbon::parse($filters['date_to'])->toDateString()));
    }

    private function selectedChild(Institution $institution, ?InstitutionPayment $payment): ?Child
    {
        $childId = old('child_id', $payment?->child_id);

        if (! $childId) {
            return null;
        }

        return Child::query()
            ->where('institution_id', $institution->id)
            ->find($childId, ['id', 'name', 'group_name', 'educational_identifier', 'active']);
    }

    private function guardiansForChild(int $childId, int $institutionId)
    {
        return Guardian::query()
            ->where('institution_id', $institutionId)
            ->whereHas('children', fn (Builder $query) => $query->where('children.id', $childId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function statementsForChild(int $childId, int $institutionId)
    {
        $statements = MonthlyPaymentStatement::query()
            ->with('child:id,name')
            ->where('institution_id', $institutionId)
            ->where('child_id', $childId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->get();

        $summaries = $this->componentService->buildStatementSummaries($statements);

        return $statements->map(function (MonthlyPaymentStatement $statement) use ($summaries) {
            $statement->setAttribute('financial_summary', $summaries->get($statement->id, []));

            return $statement;
        });
    }

    private function statementOptionData(MonthlyPaymentStatement $statement): array
    {
        $periods = $this->periodHelper->fromStatement($statement);
        $totalPayable = (int) $statement->total_payable;
        $financialSummary = (array) ($statement->financial_summary
            ?? $this->componentService->buildStatementSummaries(collect([$statement]))->get($statement->id, []));
        $completedPaymentsTotal = (int) ($financialSummary['paid_total'] ?? 0);
        $remainingAmount = (int) ($financialSummary['remaining_total'] ?? max(0, $totalPayable - $completedPaymentsTotal));
        $isSettled = $remainingAmount <= 0;
        $labelAmount = $isSettled ? 'Rendezve' : number_format($remainingAmount, 0, ',', ' ').' Ft';

        return [
            'id' => $statement->id,
            'text' => collect([
                $statement->child?->name,
                $periods['payment_period_label'],
                $labelAmount,
            ])->filter()->implode(' – '),
            'child_name' => $statement->child?->name,
            'payment_period_label' => $periods['payment_period_label'],
            'meal_period_label' => $periods['meal_period_label'],
            'credit_period_label' => $periods['credit_period_label'],
            'total_payable' => $totalPayable,
            'completed_payments_total' => $completedPaymentsTotal,
            'remaining_amount' => $remainingAmount,
            'foundation_remaining_amount' => (int) ($financialSummary['foundation_remaining'] ?? 0),
            'kindergarten_remaining_amount' => (int) ($financialSummary['kindergarten_remaining'] ?? 0),
            'foundation_paid_amount' => (int) ($financialSummary['foundation_paid'] ?? 0),
            'kindergarten_paid_amount' => (int) ($financialSummary['kindergarten_paid'] ?? 0),
            'is_split' => (bool) ($financialSummary['is_split'] ?? false),
            'is_settled' => $isSettled,
            'disabled' => $isSettled,
        ];
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    private function authorizePayment(InstitutionPayment $payment, Institution $institution): void
    {
        abort_if($payment->institution_id !== $institution->id, 403);
    }

    private function authorizeStatement(MonthlyPaymentStatement $statement, Institution $institution): void
    {
        abort_if($statement->institution_id !== $institution->id, 403);
    }

    private function auditablePaymentValues(InstitutionPayment $payment): array
    {
        return [
            'child_id' => $payment->child_id,
            'guardian_id' => $payment->guardian_id,
            'monthly_payment_statement_id' => $payment->monthly_payment_statement_id,
            'payment_component' => $payment->payment_component,
            'invoice_number' => $payment->invoice_number,
            'amount' => $payment->amount,
            'paid_at' => optional($payment->paid_at)->toDateTimeString(),
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'reference' => $payment->reference,
            'note' => $payment->note,
        ];
    }

    private function diffAuditValues(array $beforeValues, array $afterValues): array
    {
        $oldValues = [];
        $newValues = [];

        foreach ($afterValues as $key => $value) {
            $beforeValue = $beforeValues[$key] ?? null;

            if ($beforeValue !== $value) {
                $oldValues[$key] = $beforeValue;
                $newValues[$key] = $value;
            }
        }

        return [$oldValues, $newValues];
    }
}
