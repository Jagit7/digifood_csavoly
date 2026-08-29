<?php

namespace App\Http\Controllers\Dashboard\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingPartner;
use App\Models\PartnerMonthlyBilling;
use App\Support\AuditLogger;
use App\Services\Billing\PartnerMonthlyBillingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PartnerMonthlyBillingController extends Controller
{
    public function __construct(
        private readonly PartnerMonthlyBillingService $billingService
    ) {
    }

    public function index(Request $request): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));

        $billingPartners = BillingPartner::query()
            ->where('active', true)
            ->withCount('institutions')
            ->with([
                'monthlyBillings' => fn ($query) => $query
                    ->whereDate('billing_month', $selectedMonth->toDateString())
                    ->select([
                        'id',
                        'billing_partner_id',
                        'billing_month',
                        'total_children',
                        'net_amount',
                        'vat_amount',
                        'gross_amount',
                        'status',
                        'invoice_number',
                    ]),
            ])
            ->orderBy('name')
            ->get();

        $stats = [
            'active_partners' => $billingPartners->count(),
            'linked_institutions' => $billingPartners->sum('institutions_count'),
            'snapshot_children' => $billingPartners->sum(
                fn (BillingPartner $partner) => (int) optional($partner->monthlyBillings->first())->total_children
            ),
            'monthly_gross_cents' => $billingPartners->reduce(
                fn (int $carry, BillingPartner $partner) => $carry + $this->decimalToCents(optional($partner->monthlyBillings->first())->gross_amount),
                0
            ),
        ];

        return view('dashboard.superadmin.partner_monthly_billings.index', [
            'billingPartners' => $billingPartners,
            'stats' => $stats,
            'selectedMonth' => $selectedMonth,
            'selectedMonthQuery' => $selectedMonth->format('Y-m'),
            'selectedMonthLabel' => $this->formatMonthLabel($selectedMonth),
            'previousMonthQuery' => $selectedMonth->subMonth()->format('Y-m'),
            'nextMonthQuery' => $selectedMonth->addMonth()->format('Y-m'),
            'currentMonthQuery' => $this->currentMonth()->format('Y-m'),
        ]);
    }

    public function show(Request $request, BillingPartner $billingPartner): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));

        $billingPartner->loadCount('institutions');
        $billingPartner->load([
            'monthlyBillings' => fn ($query) => $query
                ->whereDate('billing_month', $selectedMonth->toDateString())
                ->with(['items' => fn ($itemQuery) => $itemQuery->orderBy('institution_name_snapshot')]),
        ]);

        $monthlyBilling = $billingPartner->monthlyBillings->first();

        return view('dashboard.superadmin.partner_monthly_billings.show', [
            'billingPartner' => $billingPartner,
            'monthlyBilling' => $monthlyBilling,
            'selectedMonth' => $selectedMonth,
            'selectedMonthQuery' => $selectedMonth->format('Y-m'),
            'selectedMonthLabel' => $this->formatMonthLabel($selectedMonth),
            'previousMonthQuery' => $selectedMonth->subMonth()->format('Y-m'),
            'nextMonthQuery' => $selectedMonth->addMonth()->format('Y-m'),
            'currentMonthQuery' => $this->currentMonth()->format('Y-m'),
        ]);
    }

    public function store(Request $request, BillingPartner $billingPartner): RedirectResponse
    {
        $selectedMonth = $this->resolveMonth($request->input('month'));

        try {
            $this->billingService->createSnapshot($billingPartner, $selectedMonth);
        } catch (DomainException $exception) {
            return redirect()
                ->route('dashboard.partner-monthly-billings.show', [
                    'billingPartner' => $billingPartner,
                    'month' => $selectedMonth->format('Y-m'),
                ])
                ->withErrors(['monthly_billing' => $exception->getMessage()])
                ->withInput(['month' => $selectedMonth->format('Y-m')]);
        }

        return redirect()
            ->route('dashboard.partner-monthly-billings.show', [
                'billingPartner' => $billingPartner,
                'month' => $selectedMonth->format('Y-m'),
            ])
            ->with('success', 'A havi számlázási pillanatkép sikeresen létrejött.');
    }

    public function recalculate(Request $request, PartnerMonthlyBilling $monthlyBilling): RedirectResponse
    {
        $billingPartner = $monthlyBilling->billingPartner()->firstOrFail();
        $monthQuery = $monthlyBilling->billing_month?->format('Y-m') ?? $this->currentMonth()->format('Y-m');

        try {
            $this->billingService->recalculateSnapshot($monthlyBilling);
        } catch (DomainException $exception) {
            return redirect()
                ->route('dashboard.partner-monthly-billings.show', [
                    'billingPartner' => $billingPartner,
                    'month' => $monthQuery,
                ])
                ->withErrors(['monthly_billing' => $exception->getMessage()]);
        }

        return redirect()
            ->route('dashboard.partner-monthly-billings.show', [
                'billingPartner' => $billingPartner,
                'month' => $monthQuery,
            ])
            ->with('success', 'A havi számlázási tervezet sikeresen újraszámolva.');
    }

    public function markAsInvoiced(Request $request, PartnerMonthlyBilling $monthlyBilling): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ], [
            'invoice_number.required' => 'A számlaszám megadása kötelező.',
            'invoice_number.max' => 'A számlaszám legfeljebb 100 karakter lehet.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('dashboard.partner-monthly-billings.show', [
                    'billingPartner' => $monthlyBilling->billing_partner_id,
                    'month' => $monthlyBilling->billing_month?->format('Y-m') ?? $this->currentMonth()->format('Y-m'),
                ])
                ->withErrors($validator)
                ->withInput();
        }

        try {
            [$billingPartner, $monthQuery] = DB::transaction(function () use ($monthlyBilling, $validator) {
                $lockedMonthlyBilling = PartnerMonthlyBilling::query()
                    ->whereKey($monthlyBilling->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedMonthlyBilling->status !== 'draft') {
                    throw new DomainException('Csak tervezet állapotú havi adat jelölhető számlázottnak.');
                }

                $lockedMonthlyBilling->update([
                    'status' => 'invoiced',
                    'invoice_number' => $validator->validated()['invoice_number'],
                    'note' => $validator->validated()['note'] ?? null,
                    'invoiced_at' => now(),
                    'paid_at' => null,
                ]);

                AuditLogger::log(
                    action: AuditLog::ACTION_PARTNER_BILLING_STATUS_CHANGED,
                    description: 'Partneri havi számlázási státusz módosítva',
                    subject: $lockedMonthlyBilling,
                    oldValues: [
                        'billing_month' => $lockedMonthlyBilling->billing_month?->format('Y-m'),
                        'billing_partner_id' => $lockedMonthlyBilling->billing_partner_id,
                        'old_status' => 'draft',
                    ],
                    newValues: [
                        'billing_month' => $lockedMonthlyBilling->billing_month?->format('Y-m'),
                        'billing_partner_id' => $lockedMonthlyBilling->billing_partner_id,
                        'new_status' => 'invoiced',
                        'invoice_number' => $lockedMonthlyBilling->invoice_number,
                    ],
                );

                return [
                    $lockedMonthlyBilling->billingPartner()->firstOrFail(),
                    $lockedMonthlyBilling->billing_month?->format('Y-m') ?? $this->currentMonth()->format('Y-m'),
                ];
            });
        } catch (DomainException $exception) {
            return $this->redirectToShowWithError($monthlyBilling, $exception->getMessage());
        }

        return redirect()
            ->route('dashboard.partner-monthly-billings.show', [
                'billingPartner' => $billingPartner,
                'month' => $monthQuery,
            ])
            ->with('success', 'A havi adat sikeresen számlázottnak jelölve.');
    }

    public function markAsPaid(PartnerMonthlyBilling $monthlyBilling): RedirectResponse
    {
        try {
            [$billingPartner, $monthQuery] = DB::transaction(function () use ($monthlyBilling) {
                $lockedMonthlyBilling = PartnerMonthlyBilling::query()
                    ->whereKey($monthlyBilling->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedMonthlyBilling->status !== 'invoiced') {
                    throw new DomainException('Csak számlázott havi adat jelölhető fizetettnek.');
                }

                $lockedMonthlyBilling->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                AuditLogger::log(
                    action: AuditLog::ACTION_PARTNER_BILLING_STATUS_CHANGED,
                    description: 'Partneri havi számlázási státusz módosítva',
                    subject: $lockedMonthlyBilling,
                    oldValues: [
                        'billing_month' => $lockedMonthlyBilling->billing_month?->format('Y-m'),
                        'billing_partner_id' => $lockedMonthlyBilling->billing_partner_id,
                        'old_status' => 'invoiced',
                    ],
                    newValues: [
                        'billing_month' => $lockedMonthlyBilling->billing_month?->format('Y-m'),
                        'billing_partner_id' => $lockedMonthlyBilling->billing_partner_id,
                        'new_status' => 'paid',
                    ],
                );

                return [
                    $lockedMonthlyBilling->billingPartner()->firstOrFail(),
                    $lockedMonthlyBilling->billing_month?->format('Y-m') ?? $this->currentMonth()->format('Y-m'),
                ];
            });
        } catch (DomainException $exception) {
            return $this->redirectToShowWithError($monthlyBilling, $exception->getMessage());
        }

        return redirect()
            ->route('dashboard.partner-monthly-billings.show', [
                'billingPartner' => $billingPartner,
                'month' => $monthQuery,
            ])
            ->with('success', 'A havi adat sikeresen fizetettnek jelölve.');
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

    private function decimalToCents(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        [$wholePart, $fractionPart] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $wholePart * 100) + (int) str_pad(substr($fractionPart, 0, 2), 2, '0');
    }

    private function redirectToShowWithError(PartnerMonthlyBilling $monthlyBilling, string $message): RedirectResponse
    {
        return redirect()
            ->route('dashboard.partner-monthly-billings.show', [
                'billingPartner' => $monthlyBilling->billing_partner_id,
                'month' => $monthlyBilling->billing_month?->format('Y-m') ?? $this->currentMonth()->format('Y-m'),
            ])
            ->withErrors(['monthly_billing' => $message]);
    }
}
