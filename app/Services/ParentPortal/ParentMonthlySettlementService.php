<?php

namespace App\Services\ParentPortal;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\ParentMonthlySettlementPayment;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\InstitutionPaymentComponentService;
use App\Support\Finance\PaymentComponent;
use App\Support\PaymentObligation\MonthlyPaymentDayStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ParentMonthlySettlementService
{
    public function __construct(
        private readonly InstitutionPaymentComponentService $componentService
    ) {}

    public function resolveMonth(?string $month): CarbonImmutable
    {
        if (is_string($month) && preg_match('/^(?<year>\d{4})-(?<month>\d{2})$/', $month, $matches) === 1) {
            $year = (int) $matches['year'];
            $monthNumber = (int) $matches['month'];

            if ($monthNumber >= 1 && $monthNumber <= 12) {
                try {
                    return CarbonImmutable::create($year, $monthNumber, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
                } catch (InvalidArgumentException) {
                    return CarbonImmutable::now(config('app.timezone'))->startOfMonth();
                }
            }
        }

        return CarbonImmutable::now(config('app.timezone'))->startOfMonth();
    }

    public function buildPageData(User $user, CarbonImmutable $month): array
    {
        return $this->buildMonthlyData($user, $month);
    }

    public function paymentResult(User $user, ?string $reference): ?array
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return null;
        }

        $payment = ParentMonthlySettlementPayment::query()
            ->where('user_id', $user->id)
            ->where('reference', $reference)
            ->first();

        if (! $payment) {
            return null;
        }

        $cib = (array) data_get($payment->metadata, 'cib', []);

        return [
            'reference' => $payment->reference,
            'status' => ParentMonthlySettlementPayment::statusMeta($payment->status),
            'trid' => $cib['trid'] ?? null,
            'anum' => $cib['anum'] ?? null,
            'rc' => $cib['final_rc'] ?? $cib['init_rc'] ?? null,
            'rt' => $cib['final_rt'] ?? $cib['init_rt'] ?? null,
            'amo' => isset($cib['amount']) ? ((int) $cib['amount']) : (int) $payment->total_amount,
            'invoice_errors' => (array) ($cib['invoice_errors'] ?? []),
        ];
    }

    public function buildInstitutionNotificationData(User $user, Institution $institution, CarbonImmutable $month): array
    {
        return $this->buildMonthlyData($user, $month, $institution->id, false);
    }

    private function buildMonthlyData(
        User $user,
        CarbonImmutable $month,
        ?int $institutionId = null,
        bool $includeHistory = true
    ): array {
        $periods = $this->buildPeriods($month);
        $guardianIds = $user->guardians()
            ->where('active', true)
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId))
            ->pluck('guardians.id');

        $guardians = Guardian::query()
            ->whereIn('id', $guardianIds)
            ->orderBy('id')
            ->get();

        $children = Child::query()
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId))
            ->with([
                'institution.setting',
                'discountType',
            ])
            ->orderBy('name')
            ->get();

        $statements = MonthlyPaymentStatement::query()
            ->whereIn('child_id', $children->pluck('id'))
            ->when($institutionId !== null, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->with([
                'child',
                'discount',
                'mealPackage',
                'days.cancellation',
                'days.classCancellation',
                'days.schoolBreak',
                'days.workingDay',
            ])
            ->get()
            ->keyBy('child_id');

        $paymentsByStatement = $this->componentService->buildStatementSummaries($statements->values());

        $childCards = $children->map(function (Child $child) use ($statements, $paymentsByStatement, $month) {
            /** @var MonthlyPaymentStatement|null $statement */
            $statement = $statements->get($child->id);

            return $this->buildChildCard($child, $statement, $paymentsByStatement->get($statement?->id ?? 0, []), $month);
        })->values();

        $summary = $this->buildSummary($month, $childCards);
        $history = $includeHistory ? $this->loadHistory($user, $month) : collect();
        $currentMonth = CarbonImmutable::now(config('app.timezone'))->startOfMonth();

        return [
            'month' => $month,
            'month_query' => $month->format('Y-m'),
            'month_label' => $month->locale('hu')->isoFormat('YYYY. MMMM'),
            'payment_period_label' => $periods['payment_period_label'],
            'meal_period_label' => $periods['meal_period_label'],
            'credit_period_label' => $periods['credit_period_label'],
            'calculation_help' => $periods['calculation_help'],
            'previous_month_query' => $month->copy()->subMonth()->format('Y-m'),
            'next_month_query' => $month->copy()->addMonth()->format('Y-m'),
            'current_month_query' => $currentMonth->format('Y-m'),
            'show_current_month_link' => $month->format('Y-m') !== $currentMonth->format('Y-m'),
            'children_count' => $children->count(),
            'child_cards' => $childCards,
            'summary' => $summary,
            'history' => $history,
            'primary_guardian' => $guardians->first(),
            'merchant_profile' => $this->merchantProfile($childCards),
            'bank_transfer' => $this->bankTransferInfo($month, $childCards, $summary),
        ];
    }

    public function createPaymentIntent(User $user, CarbonImmutable $month, string $idempotencyKey): ParentMonthlySettlementPayment
    {
        $page = $this->buildPageData($user, $month);
        $summary = $page['summary'];

        abort_unless(hash_equals($summary['payment_intent_key'], $idempotencyKey), 422, 'A fizetési kérés időközben megváltozott. Frissítse az oldalt.');
        abort_unless($summary['can_pay'], 422, 'Ehhez a hónaphoz jelenleg nem indítható közös fizetés.');

        /** @var Guardian|null $guardian */
        $guardian = $page['primary_guardian'];

        return DB::transaction(function () use ($user, $month, $summary, $guardian, $idempotencyKey) {
            // A payment_intent_key/idempotency_key determinisztikus (a
            // hónapból és a fennmaradó összegekből számolt sha1), és a DB-
            // ben unique constraint van rajta. Ha egy korábbi kártyás
            // próbálkozás elbukott (pl. mert a szolgáltató elutasította),
            // a fennmaradó összeg NEM változik, tehát a következő "Fizetés"
            // gombnyomás pontosan ugyanazt a kulcsot generálná újra - egy
            // sima INSERT emiatt unique constraint hibába ütközne, amit
            // eddig semmi nem kezelt, így a szülő nyers 500-as oldalt
            // kapott minden újrapróbálkozásnál. Ezért itt a PENDING mellett
            // a FAILED találatot is kezeljük: azt nem duplikáljuk, hanem
            // újraaktiváljuk (a hozzá tartozó tételek összege ugyanaz marad,
            // hiszen épp ezért egyezik meg az idempotency_key is).
            $existing = ParentMonthlySettlementPayment::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', [
                    ParentMonthlySettlementPayment::STATUS_PENDING,
                    ParentMonthlySettlementPayment::STATUS_FAILED,
                ])
                ->with('items')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->status === ParentMonthlySettlementPayment::STATUS_PENDING) {
                    return $existing;
                }

                $existing->update([
                    'status' => ParentMonthlySettlementPayment::STATUS_PENDING,
                    'failed_at' => null,
                    'note' => 'Előkészített közös szülői fizetés (újrapróbálkozás).',
                ]);

                return $existing->fresh(['items.child', 'items.statement']);
            }

            // A lockForUpdate() fentebb csak akkor tud zárolni, ha MÁR
            // létezik sor - egy tényleges dupla-kattintás (két egyidejű
            // kérés ugyanarra a még nem létező payment_intent_key-re)
            // esetén mindkét tranzakció "nincs találat"-ot lát, és mindkettő
            // megpróbál INSERT-elni. Az idempotency_key oszlopon lévő DB
            // szintű unique constraint (ld. migráció) ezt megakadályozza,
            // de a vesztes tranzakció enélkül egy nyers 500-as hibát
            // (QueryException) dobna a szülőnek. Ezért itt elkapjuk a
            // unique-ütközést, és egyszerűen visszaadjuk a másik kérés
            // által épp létrehozott rekordot.
            try {
                $payment = ParentMonthlySettlementPayment::query()->create([
                    'user_id' => $user->id,
                    'guardian_id' => $guardian?->id,
                    'year' => $month->year,
                    'month' => $month->month,
                    'reference' => $this->generateReference($month),
                    'idempotency_key' => $idempotencyKey,
                    'total_amount' => $summary['remaining_total'],
                    'payment_method' => InstitutionPayment::METHOD_ONLINE,
                    'status' => ParentMonthlySettlementPayment::STATUS_PENDING,
                    'note' => 'Előkészített közös szülői fizetés.',
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                return ParentMonthlySettlementPayment::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->with(['items.child', 'items.statement'])
                    ->firstOrFail();
            }

            foreach ($summary['payable_items'] as $item) {
                $payment->items()->create([
                    'child_id' => $item['child_id'],
                    'monthly_payment_statement_id' => $item['statement_id'],
                    'amount' => $item['remaining_amount'],
                    'paid_amount' => 0,
                ]);
            }

            return $payment->load(['items.child', 'items.statement']);
        });
    }

    /**
     * SQLSTATE 23000 = "Integrity constraint violation" - ez a szabvány
     * kód, amit MySQL/MariaDB, PostgreSQL és SQLite is használ unique/
     * foreign key ütközésnél, így driver-független módon jelzi, hogy a
     * fenti INSERT egy másik, időközben lefutott kérés miatt ütközött.
     */
    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }

    private function buildChildCard(
        Child $child,
        ?MonthlyPaymentStatement $statement,
        array $financialSummary,
        CarbonImmutable $month
    ): array {
        $periods = $this->buildPeriods($month);

        if ($statement === null) {
            return [
                'child' => $child,
                'has_statement' => false,
                'month_label' => $month->locale('hu')->isoFormat('YYYY. MMMM'),
                'payment_period_label' => $periods['payment_period_label'],
                'meal_period_label' => $periods['meal_period_label'],
                'credit_period_label' => $periods['credit_period_label'],
                'group_name' => $child->group_name ?: 'Nincs megadva',
                'status' => ['label' => 'Még nincs elszámolás', 'class' => 'bg-light text-muted border'],
                'issues' => [],
            ];
        }

        $paidAmount = (int) ($financialSummary['paid_total'] ?? 0);
        $remainingAmount = (int) ($financialSummary['remaining_total'] ?? max(0, (int) $statement->total_payable - $paidAmount));
        $baseMealAmount = (int) $statement->days->sum('original_daily_price');
        $mealDays = (int) $statement->planned_meal_days;
        $discountPercent = (int) ($statement->discount?->percentage ?? $child->discountType?->percentage ?? 0);
        $discountAmount = $statement->usesSplitPaymentModel()
            ? (int) $statement->kindergarten_discount_amount
            : (int) $statement->days->sum(function (MonthlyPaymentDay $day) use ($discountPercent) {
                if ($day->original_daily_price <= 0 || $discountPercent <= 0) {
                    return 0;
                }

                return (int) round($day->original_daily_price * ($discountPercent / 100), 0, PHP_ROUND_HALF_UP);
            });
        $advanceCancellationAmount = (int) $statement->days
            ->filter(fn (MonthlyPaymentDay $day) => $day->status === MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE)
            ->sum('original_daily_price');
        $classCancellationAmount = (int) $statement->days
            ->filter(fn (MonthlyPaymentDay $day) => $day->status === MonthlyPaymentDay::STATUS_CLASS_CANCELLATION)
            ->sum('original_daily_price');
        $otherCredits = max(0, (int) $statement->previous_cancellation_credit - $advanceCancellationAmount - $classCancellationAmount);
        $previousBalance = (int) $statement->previous_balance;

        return [
            'child' => $child,
            'statement' => $statement,
            'has_statement' => true,
            'month_label' => $month->locale('hu')->isoFormat('YYYY. MMMM'),
            'payment_period_label' => $periods['payment_period_label'],
            'meal_period_label' => $periods['meal_period_label'],
            'credit_period_label' => $periods['credit_period_label'],
            'group_name' => $child->group_name ?: 'Nincs megadva',
            'status' => $this->statementStatusMeta($statement, $paidAmount, $remainingAmount),
            'meal_days' => $mealDays,
            'base_meal_amount' => $baseMealAmount,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'advance_cancellation_amount' => $advanceCancellationAmount,
            'class_cancellation_amount' => $classCancellationAmount,
            'other_credit_amount' => $otherCredits,
            'correction_amount' => (int) $statement->billing_adjustment_amount,
            'invoiceable_amount' => (int) $statement->invoiceable_amount,
            'previous_balance' => $previousBalance,
            'current_total' => (int) $statement->total_payable,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'financial_summary' => $financialSummary,
            'planned_meal_days' => (int) $statement->planned_meal_days,
            'previous_month_cancelled_days' => (int) $statement->previous_month_cancelled_days,
            'foundation' => $this->componentBreakdown($statement, $financialSummary, PaymentComponent::FOUNDATION),
            'kindergarten' => $this->componentBreakdown($statement, $financialSummary, PaymentComponent::KINDERGARTEN),
            'issues' => $statement->issues ?? [],
            'can_pay' => $statement->isClosed() && $remainingAmount > 0 && empty($statement->issues ?? []),
            'details' => $this->buildDetails($statement, $discountPercent, $remainingAmount, $paidAmount, $periods),
        ];
    }

    private function buildDetails(
        MonthlyPaymentStatement $statement,
        int $discountPercent,
        int $remainingAmount,
        int $paidAmount,
        array $periods
    ): array {
        $dailyRows = $statement->days->map(function (MonthlyPaymentDay $day) use ($discountPercent, $statement) {
            $discountAmount = $statement->usesSplitPaymentModel()
                ? (int) $day->kindergarten_discount_amount
                : ($day->original_daily_price > 0 && $discountPercent > 0
                    ? (int) round($day->original_daily_price * ($discountPercent / 100), 0, PHP_ROUND_HALF_UP)
                    : 0);

            return [
                'date' => $day->date?->format('Y-m-d'),
                'date_label' => $day->date?->locale('hu')->isoFormat('MMM D.'),
                'weekday' => $day->date?->locale('hu')->isoFormat('dddd'),
                'status' => MonthlyPaymentDayStatus::meta($day->status),
                'base_amount' => (int) $day->original_daily_price,
                'foundation_amount' => (int) $day->foundation_payable_amount,
                'kindergarten_base_amount' => (int) $day->kindergarten_daily_fee,
                'discount_amount' => $discountAmount,
                'kindergarten_payable_amount' => (int) $day->kindergarten_payable_amount,
                'payable_amount' => (int) $day->payable_amount,
                'is_cancelled' => in_array($day->status, [
                    MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE,
                    MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING,
                    MonthlyPaymentDay::STATUS_CLASS_CANCELLATION,
                ], true),
                'note' => $day->modification_reason,
            ];
        })->values();

        $breakdown = collect([
            [
                'label' => 'Tervezett étkezési napok',
                'amount' => (int) $statement->planned_meal_days,
                'emphasis' => false,
                'display_as_count' => true,
            ],
            [
                'label' => 'Előző havi jóváírt lemondások',
                'amount' => (int) $statement->previous_month_cancelled_days,
                'emphasis' => false,
                'display_as_count' => true,
            ],
            [
                'label' => 'Zsárica rész',
                'amount' => (int) $statement->foundation_invoiceable_amount,
                'emphasis' => false,
            ],
            [
                'label' => 'Óvodai rész',
                'amount' => (int) $statement->kindergarten_invoiceable_amount,
                'emphasis' => false,
            ],
            [
                'label' => $discountPercent > 0 ? $discountPercent.'%-os kedvezmény az óvodai részen' : 'Óvodai kedvezmény',
                'amount' => -1 * (int) $statement->kindergarten_discount_amount,
                'emphasis' => true,
            ],
            [
                'label' => $periods['credit_period_label'].'i lemondások jóváírása',
                'amount' => -1 * (int) $statement->previous_cancellation_credit,
                'emphasis' => false,
            ],
            [
                'label' => 'Korábbi egyenleg',
                'amount' => (int) $statement->previous_balance,
                'emphasis' => false,
            ],
            [
                'label' => 'Tényleges fizetendő',
                'amount' => (int) $statement->total_payable,
                'emphasis' => true,
            ],
            [
                'label' => 'Korábban befizetve',
                'amount' => -1 * $paidAmount,
                'emphasis' => false,
            ],
            [
                'label' => 'Fennmaradó összeg',
                'amount' => $remainingAmount,
                'emphasis' => true,
            ],
        ])->reject(function (array $row) {
            if (($row['display_as_count'] ?? false) === true) {
                return false;
            }

            return $row['label'] !== 'Havi előírás'
                && $row['label'] !== 'Tényleges fizetendő'
                && $row['label'] !== 'Fennmaradó összeg'
                && $row['amount'] === 0;
        })->values();

        return [
            'breakdown' => $breakdown,
            'daily_rows' => $dailyRows,
        ];
    }

    private function buildSummary(CarbonImmutable $month, Collection $childCards): array
    {
        $statementCards = $childCards->filter(fn (array $card) => $card['has_statement']);
        $totalPayable = (int) $statementCards->sum('current_total');
        $paidTotal = (int) $statementCards->sum('paid_amount');
        $remainingTotal = (int) max(0, $statementCards->sum('remaining_amount'));
        $foundationTotal = (int) $statementCards->sum(fn (array $card) => $card['foundation']['current_total']);
        $foundationPaidTotal = (int) $statementCards->sum(fn (array $card) => $card['foundation']['paid_amount']);
        $foundationRemainingTotal = (int) $statementCards->sum(fn (array $card) => max(0, $card['foundation']['current_balance']));
        $kindergartenTotal = (int) $statementCards->sum(fn (array $card) => $card['kindergarten']['current_total']);
        $kindergartenPaidTotal = (int) $statementCards->sum(fn (array $card) => $card['kindergarten']['paid_amount']);
        $kindergartenRemainingTotal = (int) $statementCards->sum(fn (array $card) => max(0, $card['kindergarten']['current_balance']));
        $hasIssues = $statementCards->contains(fn (array $card) => ! empty($card['issues']));
        $missingClosed = $statementCards->contains(fn (array $card) => ($card['statement']?->status) !== MonthlyPaymentStatement::STATUS_CLOSED);
        $allClosed = $statementCards->isNotEmpty() && ! $missingClosed;
        $dueDate = $this->resolveDueDate($month, $statementCards);
        $paymentEnabled = $this->isPaymentEnabled($statementCards);
        $payableCards = $statementCards->filter(fn (array $card) => $card['can_pay'] && $paymentEnabled);
        $paymentIntentKey = sha1(implode('|', [
            $month->format('Y-m'),
            $statementCards->pluck('statement.id')->implode(','),
            $statementCards->map(fn (array $card) => $card['remaining_amount'])->implode(','),
        ]));
        $statusCard = $this->buildTopStatusCard(
            month: $month,
            statementCards: $statementCards,
            totalPayable: $totalPayable,
            paidTotal: $paidTotal,
            remainingTotal: $remainingTotal,
            hasIssues: $hasIssues,
            allClosed: $allClosed,
            dueDate: $dueDate,
            paymentEnabled: $paymentEnabled
        );

        return [
            'status' => $this->summaryStatusMeta($statementCards, $totalPayable, $paidTotal, $remainingTotal, $hasIssues, $allClosed),
            'children_count' => $statementCards->count(),
            'total_payable' => $totalPayable,
            'paid_total' => $paidTotal,
            'remaining_total' => $remainingTotal,
            'foundation_total' => $foundationTotal,
            'foundation_paid_total' => $foundationPaidTotal,
            'foundation_remaining_total' => $foundationRemainingTotal,
            'kindergarten_total' => $kindergartenTotal,
            'kindergarten_paid_total' => $kindergartenPaidTotal,
            'kindergarten_remaining_total' => $kindergartenRemainingTotal,
            'due_date' => $dueDate,
            'due_date_label' => $dueDate?->locale('hu')->isoFormat('YYYY. MMMM D.'),
            'has_statement' => $statementCards->isNotEmpty(),
            'can_pay' => $payableCards->isNotEmpty() && $remainingTotal > 0,
            'payment_enabled' => $paymentEnabled,
            'payable_items' => $payableCards->map(fn (array $card) => [
                'child_id' => $card['child']->id,
                'statement_id' => $card['statement']->id,
                'remaining_amount' => $card['remaining_amount'],
            ])->values()->all(),
            'payment_intent_key' => $paymentIntentKey,
            'warnings' => $this->buildSummaryWarnings($childCards),
            'status_card' => $statusCard,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $childCards
     * @return array<string, string|null>
     */
    private function merchantProfile(Collection $childCards): array
    {
        $firstCard = $childCards->first(fn (array $card) => isset($card['child']) && $card['child']?->institution !== null);
        $institution = is_array($firstCard) ? $firstCard['child']?->institution : null;

        $name = trim((string) ($institution?->billing_name ?: $institution?->name));
        $address = trim((string) ($institution?->hasCompleteBillingAddress()
            ? $institution?->full_billing_address
            : $institution?->full_address));

        return [
            'name' => $name !== '' ? $name : 'Az intézmény',
            'institution_name' => trim((string) ($institution?->name ?: '')) ?: null,
            'address' => $address !== '' ? $address : null,
            'tax_number' => trim((string) ($institution?->billing_tax_number ?: '')) ?: null,
            'email' => trim((string) ($institution?->email ?: '')) ?: null,
            'phone' => trim((string) ($institution?->phone ?: '')) ?: null,
            'country' => 'Magyarország (HU)',
        ];
    }

    private function resolveDueDate(CarbonImmutable $month, Collection $statementCards): ?CarbonImmutable
    {
        $dueDays = $statementCards
            ->map(fn (array $card) => (int) ($card['child']->institution?->setting?->payment_due_day ?? 0))
            ->filter(fn (int $day) => $day > 0)
            ->map(fn (int $day) => min(28, $day));

        if ($dueDays->isEmpty()) {
            return null;
        }

        return $month->setDay($dueDays->min());
    }

    /**
     * Felhasználói kérés: ha az intézménynél nincs bekapcsolva a kártyás
     * fizetés, de van megadott bankszámlaszáma (ld.
     * InstitutionSetting::hasBankTransferAccount()), a szülői felület egy
     * vágólapra másolható átutalási tájékoztatót jelenítsen meg a
     * fizetendő összeggel - a "Kereskedői összesítő" / CIB kártya helyett,
     * ami eleve csak kártyás fizetéshez értelmezhető (ld. blade nézetek).
     *
     * Ugyanaz a szabály vonatkozik rá, mint a kártyás fizetésre magára
     * (ld. isPaymentEnabled()): ha a szülő gyermekei több különböző
     * intézményhez tartoznak, a fennmaradó összeget NEM szabad egyetlen
     * átutalási tájékoztatóba összevonni, mert a pénz rossz helyre
     * kerülne - ilyenkor nincs megjeleníthető átutalási adat.
     */
    private function bankTransferInfo(CarbonImmutable $month, Collection $childCards, array $summary): array
    {
        $unavailable = ['available' => false];

        if ($summary['payment_enabled'] || $summary['remaining_total'] <= 0) {
            return $unavailable;
        }

        $statementCards = $childCards->filter(fn (array $card) => $card['has_statement']);
        $institutionIds = $statementCards
            ->map(fn (array $card) => $card['child']->institution_id)
            ->unique();

        if ($institutionIds->count() !== 1) {
            return $unavailable;
        }

        $institution = $statementCards->first()['child']?->institution;
        $setting = $institution?->setting;

        if ($setting?->usesSplitManualTransfer()) {
            return [
                'available' => true,
                'is_split' => true,
                'components' => [
                    PaymentComponent::FOUNDATION => [
                        'label' => PaymentComponent::labels()[PaymentComponent::FOUNDATION],
                        'account_holder' => $setting->foundation_account_holder,
                        'account_number' => $setting->foundation_account_number,
                        'amount' => (int) $summary['foundation_remaining_total'],
                        'reference' => $this->componentReference($setting->foundation_transfer_reference, $childNames, $month),
                    ],
                    PaymentComponent::KINDERGARTEN => [
                        'label' => PaymentComponent::labels()[PaymentComponent::KINDERGARTEN],
                        'account_holder' => $setting->kindergarten_account_holder,
                        'account_number' => $setting->kindergarten_account_number,
                        'amount' => (int) $summary['kindergarten_remaining_total'],
                        'reference' => $this->componentReference($setting->kindergarten_transfer_reference, $childNames, $month),
                    ],
                ],
            ];
        }

        if (! $setting?->hasBankTransferAccount()) {
            return $unavailable;
        }

        $accountHolder = trim((string) ($setting->bank_transfer_account_holder ?: $institution?->billing_name ?: $institution?->name ?: ''));
        $childNames = $statementCards
            ->map(fn (array $card) => $card['child']->name)
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            'available' => true,
            'is_split' => false,
            'account_holder' => $accountHolder !== '' ? $accountHolder : null,
            'account_number' => $setting->bank_transfer_account_number,
            'amount' => $summary['remaining_total'],
            'reference' => trim($childNames.' - '.$month->locale('hu')->isoFormat('YYYY. MMMM')),
        ];
    }

    private function buildSummaryWarnings(Collection $childCards): array
    {
        $warnings = [];

        if ($childCards->isEmpty()) {
            return $warnings;
        }

        if ($childCards->contains(fn (array $card) => ! $card['has_statement'])) {
            $warnings[] = 'Az adott hónaphoz még nem készült elszámolás minden kapcsolt gyermekhez.';
        }

        if ($childCards->contains(fn (array $card) => $card['has_statement'] && ($card['statement']?->status) !== MonthlyPaymentStatement::STATUS_CLOSED)) {
            $warnings[] = 'Legalább egy havi elszámolás még nincs lezárva, ezért a közös fizetés csak a lezárt tételekre indítható.';
        }

        if ($childCards->contains(fn (array $card) => collect($card['issues'] ?? [])->isNotEmpty())) {
            $warnings[] = 'Van olyan gyermek, akinél hiányzó ár vagy más elszámolási figyelmeztetés miatt az összeg még nem tekinthető véglegesnek.';
        }

        $institutionIds = $childCards
            ->filter(fn (array $card) => $card['has_statement'])
            ->map(fn (array $card) => $card['child']->institution_id)
            ->unique();

        if ($institutionIds->count() > 1) {
            $warnings[] = 'A gyermekei jelenleg több különböző intézményhez tartoznak. Az online bankkártyás fizetés egyszerre csak egy intézmény felé indítható, ezért ebben a hónapban intézményenként külön kell rendezni a fennmaradó összeget.';
        }

        return $warnings;
    }

    private function buildPeriods(CarbonImmutable $paymentPeriod): array
    {
        $mealPeriod = $paymentPeriod->addMonth();
        $creditPeriod = $paymentPeriod->subMonth();

        return [
            'payment_period_label' => $paymentPeriod->locale('hu')->isoFormat('YYYY. MMMM'),
            'meal_period_label' => $mealPeriod->locale('hu')->isoFormat('YYYY. MMMM'),
            'credit_period_label' => $creditPeriod->locale('hu')->isoFormat('YYYY. MMMM'),
            'calculation_help' => [
                'title' => 'Hogyan számoljuk a havi fizetendő összeget?',
                'body' => 'Az adott fizetési hónapban mindig a következő étkezési hónap tervezett napjait számoljuk, és ebből külön jóváírjuk az előző hónap szabályosan jóváírható lemondásait. A Zsárica és az óvodai rész külön összegként, külön egyenleggel jelenik meg.',
                'meal_hint' => 'Most a '.$mealPeriod->locale('hu')->isoFormat('YYYY. MMMM').'i étkezési időszak kerül elszámolásra.',
                'credit_hint' => 'Az elszámolásban a '.$creditPeriod->locale('hu')->isoFormat('YYYY. MMMM').'i jóváírható lemondások kerülnek levonásra.',
            ],
        ];
    }

    private function loadHistory(User $user, CarbonImmutable $month): Collection
    {
        return ParentMonthlySettlementPayment::query()
            ->where('user_id', $user->id)
            ->with(['items.child'])
            ->orderByDesc('created_at')
            ->take(12)
            ->get()
            ->map(function (ParentMonthlySettlementPayment $payment) use ($month) {
                $paymentMonth = CarbonImmutable::create($payment->year, $payment->month, 1, 0, 0, 0, config('app.timezone'));

                return [
                    'date' => $payment->created_at?->timezone(config('app.timezone'))->locale('hu')->isoFormat('YYYY. MMMM D. HH:mm'),
                    'month_label' => $paymentMonth->locale('hu')->isoFormat('YYYY. MMMM'),
                    'children' => $payment->items->pluck('child.name')->filter()->unique()->values(),
                    'total_amount' => (int) $payment->total_amount,
                    'payment_method' => InstitutionPayment::paymentMethodMeta($payment->payment_method),
                    'transaction_reference' => $payment->transaction_reference ?: $payment->reference,
                    'status' => ParentMonthlySettlementPayment::statusMeta($payment->status),
                    'receipt_url' => $payment->receipt_url,
                    'cib' => [
                        'trid' => data_get($payment->metadata, 'cib.trid'),
                        'anum' => data_get($payment->metadata, 'cib.anum'),
                        'rc' => data_get($payment->metadata, 'cib.final_rc', data_get($payment->metadata, 'cib.init_rc')),
                        'rt' => data_get($payment->metadata, 'cib.final_rt', data_get($payment->metadata, 'cib.init_rt')),
                        'amo' => data_get($payment->metadata, 'cib.amount', $payment->total_amount),
                    ],
                    'items' => $payment->items->map(fn ($item) => [
                        'child_name' => $item->child?->name,
                        'amount' => (int) $item->amount,
                    ])->values(),
                    'is_selected_month' => $payment->year === $month->year && $payment->month === $month->month,
                ];
            });
    }

    private function statementStatusMeta(MonthlyPaymentStatement $statement, int $paidAmount, int $remainingAmount): array
    {
        if (($statement->issues ?? []) !== []) {
            return ['label' => 'Még nem végleges', 'class' => 'bg-warning'];
        }

        if ($statement->total_payable <= 0) {
            return ['label' => 'Nincs fizetendő összeg', 'class' => 'bg-light text-muted border'];
        }

        if ($remainingAmount <= 0) {
            return ['label' => 'Kifizetve', 'class' => 'bg-success'];
        }

        if ($paidAmount > 0) {
            return ['label' => 'Részben fizetve', 'class' => 'bg-primary'];
        }

        if ($statement->isClosed()) {
            return ['label' => 'Fizetésre vár', 'class' => 'bg-danger'];
        }

        return ['label' => 'Lezárás alatt', 'class' => 'bg-info'];
    }

    private function summaryStatusMeta(
        Collection $statementCards,
        int $totalPayable,
        int $paidTotal,
        int $remainingTotal,
        bool $hasIssues,
        bool $allClosed
    ): array {
        if ($statementCards->isEmpty()) {
            return ['label' => 'Még nincs kiszámítva', 'class' => 'bg-light text-muted border'];
        }

        if ($hasIssues) {
            return ['label' => 'Még nem végleges', 'class' => 'bg-warning'];
        }

        if ($totalPayable <= 0) {
            return ['label' => 'Nincs fizetendő összeg', 'class' => 'bg-light text-muted border'];
        }

        if ($remainingTotal <= 0) {
            return ['label' => 'Kifizetve', 'class' => 'bg-success'];
        }

        if ($paidTotal > 0) {
            return ['label' => 'Részben fizetve', 'class' => 'bg-primary'];
        }

        if ($allClosed) {
            return ['label' => 'Fizetésre vár', 'class' => 'bg-danger'];
        }

        return ['label' => 'Lezárt', 'class' => 'bg-info'];
    }

    private function generateReference(CarbonImmutable $month): string
    {
        return sprintf(
            'CSAL-%s-%s',
            $month->format('Ym'),
            Str::upper(Str::random(6))
        );
    }

    private function isPaymentEnabled(Collection $statementCards): bool
    {
        if ($statementCards->isEmpty()) {
            return false;
        }

        // Egy közös bankkártyás fizetés a gyakorlatban csak EGYETLEN
        // intézmény kártyás terminálján/bankszámláján keresztül megy át
        // (ld. ParentMonthlySettlementController::store() - az intézményt
        // az első fizetési tétel gyermekéből határozza meg, a teljes
        // összeget pedig CardPaymentService::initiate() ennek az egy
        // intézménynek a beállításaival terheli meg). Ha a szülőnek több
        // különböző intézményben is van gyermeke ugyanabban a hónapban, a
        // több intézmény összegét NEM szabad egyetlen tranzakcióba
        // összevonni, mert a másik intézmény(ek) pénze rossz helyre
        // kerülne. Ilyenkor a kártyás fizetés letiltásra kerül a közös
        // nézetben - a szülőnek intézményenként kell rendeznie.
        $institutionIds = $statementCards
            ->map(fn (array $card) => $card['child']->institution_id)
            ->unique();

        if ($institutionIds->count() > 1) {
            return false;
        }

        return $statementCards->every(function (array $card) {
            return ! ($card['statement']?->usesSplitPaymentModel() ?? false)
                && (bool) ($card['child']->institution?->setting?->card_payment_enabled ?? false);
        });
    }

    private function componentBreakdown(MonthlyPaymentStatement $statement, array $financialSummary, string $component): array
    {
        if (! $statement->usesSplitPaymentModel() && $component === PaymentComponent::KINDERGARTEN) {
            return [
                'gross_amount' => 0,
                'discount_amount' => 0,
                'cancellation_credit' => 0,
                'adjustment_amount' => 0,
                'invoiceable_amount' => 0,
                'previous_balance' => 0,
                'current_total' => 0,
                'paid_amount' => 0,
                'current_balance' => 0,
            ];
        }

        if (! $statement->usesSplitPaymentModel()) {
            return [
                'gross_amount' => (int) $statement->meal_amount,
                'discount_amount' => 0,
                'cancellation_credit' => (int) $statement->previous_cancellation_credit,
                'adjustment_amount' => (int) $statement->billing_adjustment_amount,
                'invoiceable_amount' => (int) $statement->invoiceable_amount,
                'previous_balance' => (int) $statement->previous_balance,
                'current_total' => (int) $statement->total_payable,
                'paid_amount' => (int) ($financialSummary['paid_total'] ?? 0),
                'current_balance' => (int) ($financialSummary['foundation_balance'] ?? 0),
            ];
        }

        return match ($component) {
            PaymentComponent::FOUNDATION => [
                'gross_amount' => (int) $statement->foundation_gross_amount,
                'discount_amount' => 0,
                'cancellation_credit' => (int) $statement->foundation_cancellation_credit,
                'adjustment_amount' => (int) $statement->foundation_billing_adjustment_amount,
                'invoiceable_amount' => (int) $statement->foundation_invoiceable_amount,
                'previous_balance' => (int) $statement->foundation_previous_balance,
                'current_total' => (int) $statement->foundation_total_payable,
                'paid_amount' => (int) ($financialSummary['foundation_paid'] ?? 0),
                'current_balance' => (int) ($financialSummary['foundation_balance'] ?? 0),
            ],
            PaymentComponent::KINDERGARTEN => [
                'gross_amount' => (int) $statement->kindergarten_gross_amount,
                'discount_amount' => (int) $statement->kindergarten_discount_amount,
                'cancellation_credit' => (int) $statement->kindergarten_cancellation_credit,
                'adjustment_amount' => (int) $statement->kindergarten_billing_adjustment_amount,
                'invoiceable_amount' => (int) $statement->kindergarten_invoiceable_amount,
                'previous_balance' => (int) $statement->kindergarten_previous_balance,
                'current_total' => (int) $statement->kindergarten_total_payable,
                'paid_amount' => (int) ($financialSummary['kindergarten_paid'] ?? 0),
                'current_balance' => (int) ($financialSummary['kindergarten_balance'] ?? 0),
            ],
        };
    }

    private function componentReference(?string $template, string $childNames, CarbonImmutable $month): string
    {
        $base = trim((string) $template);

        if ($base === '') {
            return trim($childNames.' - '.$month->locale('hu')->isoFormat('YYYY. MMMM'));
        }

        return str_replace(
            ['{children}', '{month}'],
            [$childNames, $month->locale('hu')->isoFormat('YYYY. MMMM')],
            $base
        );
    }

    private function buildTopStatusCard(
        CarbonImmutable $month,
        Collection $statementCards,
        int $totalPayable,
        int $paidTotal,
        int $remainingTotal,
        bool $hasIssues,
        bool $allClosed,
        ?CarbonImmutable $dueDate,
        bool $paymentEnabled
    ): array {
        $childCount = (int) $statementCards->count();
        $daysRemaining = $dueDate !== null
            ? $month->nowWithSameTz()->startOfDay()->diffInDays($dueDate->startOfDay(), false)
            : null;
        $canPay = $allClosed && ! $hasIssues && $remainingTotal > 0 && $paymentEnabled;

        if ($statementCards->isEmpty() || $hasIssues || ! $allClosed) {
            return [
                'status' => 'preparing',
                'title' => 'Elszámolás előkészítés alatt',
                'description' => 'Az intézmény még nem zárta le ezt a hónapot. A végleges összeg később jelenik meg.',
                'icon' => 'fa-regular fa-clock',
                'color_class' => 'border-warning-subtle bg-warning-subtle',
                'remaining_amount' => $remainingTotal,
                'paid_amount' => $paidTotal,
                'due_date' => $dueDate,
                'due_date_label' => $dueDate?->locale('hu')->isoFormat('YYYY. MMMM D.'),
                'days_remaining' => null,
                'days_remaining_label' => null,
                'can_pay' => false,
                'child_count' => $childCount,
                'button_text' => null,
                'show_family_hint' => $childCount >= 2,
            ];
        }

        if ($totalPayable <= 0 || $remainingTotal <= 0 && $totalPayable <= 0) {
            return [
                'status' => 'no_amount_due',
                'title' => 'Nincs fizetendő összeg',
                'description' => 'Erre a hónapra nem keletkezett fizetendő összeg.',
                'icon' => 'fa-regular fa-circle-check',
                'color_class' => 'border-light bg-light text-dark',
                'remaining_amount' => 0,
                'paid_amount' => $paidTotal,
                'due_date' => $dueDate,
                'due_date_label' => $dueDate?->locale('hu')->isoFormat('YYYY. MMMM D.'),
                'days_remaining' => null,
                'days_remaining_label' => null,
                'can_pay' => false,
                'child_count' => $childCount,
                'button_text' => null,
                'show_family_hint' => $childCount >= 2,
            ];
        }

        if ($remainingTotal <= 0) {
            return [
                'status' => 'paid',
                'title' => 'Rendezve',
                'description' => 'Köszönjük! A havi díj befizetésre került.',
                'icon' => 'fa-solid fa-check',
                'color_class' => 'border-success-subtle bg-success-subtle text-success-emphasis',
                'remaining_amount' => 0,
                'paid_amount' => $paidTotal,
                'due_date' => $dueDate,
                'due_date_label' => $dueDate?->locale('hu')->isoFormat('YYYY. MMMM D.'),
                'days_remaining' => null,
                'days_remaining_label' => null,
                'can_pay' => false,
                'child_count' => $childCount,
                'button_text' => null,
                'show_family_hint' => $childCount >= 2,
            ];
        }

        if ($paidTotal > 0) {
            return [
                'status' => 'partially_paid',
                'title' => 'Részben fizetve',
                'description' => 'A fennmaradó összeg a fizetési határidőig rendezhető.',
                'icon' => 'fa-solid fa-wallet',
                'color_class' => 'border-primary-subtle bg-primary-subtle text-primary-emphasis',
                'remaining_amount' => $remainingTotal,
                'paid_amount' => $paidTotal,
                'due_date' => $dueDate,
                'due_date_label' => $dueDate?->locale('hu')->isoFormat('YYYY. MMMM D.'),
                'days_remaining' => $daysRemaining,
                'days_remaining_label' => $this->formatDaysRemaining($daysRemaining),
                'can_pay' => $canPay,
                'child_count' => $childCount,
                'button_text' => $canPay ? 'Fennmaradó összeg befizetése' : null,
                'show_family_hint' => $childCount >= 2,
            ];
        }

        return [
            'status' => 'ready_to_pay',
            'title' => 'Fizetés indítható',
            'description' => 'Az elszámolás végleges. A teljes fennmaradó összeg egyetlen fizetéssel rendezhető.',
            'icon' => 'fa-solid fa-credit-card',
            'color_class' => 'border-danger-subtle bg-white text-dark',
            'remaining_amount' => $remainingTotal,
            'paid_amount' => $paidTotal,
            'due_date' => $dueDate,
            'due_date_label' => $dueDate?->locale('hu')->isoFormat('YYYY. MMMM D.'),
            'days_remaining' => $daysRemaining,
            'days_remaining_label' => $this->formatDaysRemaining($daysRemaining),
            'can_pay' => $canPay,
            'child_count' => $childCount,
            'button_text' => $canPay ? 'Teljes összeg befizetése' : null,
            'show_family_hint' => $childCount >= 2,
        ];
    }

    private function formatDaysRemaining(?int $daysRemaining): ?string
    {
        if ($daysRemaining === null) {
            return null;
        }

        if ($daysRemaining < 0) {
            return 'Lejárt';
        }

        if ($daysRemaining === 0) {
            return 'Ma jár le';
        }

        if ($daysRemaining === 1) {
            return '1 nap van hátra';
        }

        return $daysRemaining.' nap van hátra';
    }
}
