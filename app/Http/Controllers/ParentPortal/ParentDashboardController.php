<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use App\Services\InstitutionCalendarService;
use Illuminate\Support\Collection;

class ParentDashboardController extends Controller
{
    public function __construct(
        private readonly InstitutionCalendarService $calendar
    ) {
    }

    public function __invoke(): View
    {
        $user = auth()->user();
        $now = $this->calendar->now();
        $today = $now->startOfDay();

        $guardianIds = $user->guardians()
            ->where('active', true)
            ->pluck('guardians.id');

        $children = Child::query()
            ->whereHas('guardians', fn ($query) => $query->whereIn('guardians.id', $guardianIds))
            ->with([
                'institution.setting',
                'institution.mealSetting',
                'institution.mealPackages' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('display_order')
                    ->orderBy('name'),
                'discountType',
                'dietaryRestrictions' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                'guardians' => fn ($query) => $query
                    ->whereIn('guardians.id', $guardianIds)
                    ->orderBy('last_name')
                    ->orderBy('first_name'),
                'mealSettings' => fn ($query) => $query
                    ->with('mealPackage')
                    ->orderByDesc('valid_from')
                    ->orderByDesc('id'),
            ])
            ->orderBy('name')
            ->get();

        $childIds = $children->pluck('id');
        $institutions = $children->pluck('institution')->filter()->unique('id')->values();
        $institutionWindows = $institutions
            ->mapWithKeys(fn ($institution) => [$institution->id => $this->calendar->cancellationWindow($institution->id, $now)]);

        $statements = $this->loadStatements($childIds, $today);
        $paymentsByStatement = $this->loadPaymentsByStatement($statements->pluck('id'));
        $currentStatements = $statements
            ->where('year', $today->year)
            ->where('month', $today->month)
            ->values();
        $closedStatements = $statements
            ->where('status', MonthlyPaymentStatement::STATUS_CLOSED)
            ->values();

        $financialOverview = $this->buildFinancialOverview($currentStatements, $paymentsByStatement, $today);
        $outstandingDebt = $this->buildOutstandingDebtSummary($closedStatements, $paymentsByStatement);
        $deadlineSummary = $this->buildDeadlineSummary($institutionWindows);
        $historyChart = $this->buildHistoryChart($closedStatements);
        $timeline = $this->buildUpcomingTimeline($statements, $institutionWindows, $today, $children->count() > 1);
        $cancellationSummary = $this->buildCancellationSummary($currentStatements, $deadlineSummary);
        $childCards = $children->map(fn (Child $child) => $this->buildChildCard($child, $currentStatements, $today));
        $recentFinance = $this->buildRecentFinanceItems($childIds);
        $quickActions = $this->quickActions();
        $statsCards = $this->buildStatsCards(
            childrenCount: $children->count(),
            financialOverview: $financialOverview,
            outstandingDebt: $outstandingDebt,
            deadlineSummary: $deadlineSummary
        );

        return view('parent.dashboard', [
            'greeting' => $this->greeting($now, $user->name),
            'todayLabel' => $this->formatLongDate($today),
            'statsCards' => $statsCards,
            'childrenCount' => $children->count(),
            'childCards' => $childCards,
            'timeline' => $timeline,
            'financialOverview' => $financialOverview,
            'financialDonut' => $this->buildFinancialDonut($financialOverview),
            'historyChart' => $historyChart,
            'cancellationSummary' => $cancellationSummary,
            'quickActions' => $quickActions,
            'recentFinance' => $recentFinance,
        ]);
    }

    private function loadStatements(Collection $childIds, CarbonImmutable $today): Collection
    {
        if ($childIds->isEmpty()) {
            return collect();
        }

        $from = $today->subMonths(6)->startOfMonth();
        $to = $today->addMonths(1)->endOfMonth();
        $fromKey = ((int) $from->year * 100) + (int) $from->month;
        $toKey = ((int) $to->year * 100) + (int) $to->month;

        return MonthlyPaymentStatement::query()
            ->whereIn('child_id', $childIds)
            ->whereRaw('(year * 100 + month) between ? and ?', [$fromKey, $toKey])
            ->with([
                'child.institution.mealSetting',
                'mealPackage',
                'days.cancellation',
                'days.classCancellation',
                'days.schoolBreak',
            ])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('child_id')
            ->get();
    }

    private function loadPaymentsByStatement(Collection $statementIds): Collection
    {
        if ($statementIds->isEmpty()) {
            return collect();
        }

        return InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as total_paid')
            ->whereIn('monthly_payment_statement_id', $statementIds)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->groupBy('monthly_payment_statement_id')
            ->get()
            ->mapWithKeys(fn ($payment) => [(int) $payment->monthly_payment_statement_id => (int) $payment->total_paid]);
    }

    private function buildStatsCards(int $childrenCount, array $financialOverview, array $outstandingDebt, array $deadlineSummary): array
    {
        $cards = [
            [
                'title' => 'Gyermekeim',
                'value' => (string) $childrenCount,
                'value_lines' => [(string) $childrenCount],
                'value_class' => 'df-parent-stat-value-numeric',
                'subtitle' => $childrenCount > 0
                    ? ($childrenCount === 1 ? '1 kapcsolt gyermek' : "{$childrenCount} kapcsolt gyermek")
                    : 'Jelenleg nincs kapcsolt gyermek',
                'helper' => 'Csak a saját kapcsolt gyermekei jelennek meg.',
                'icon' => 'fa-solid fa-child',
                'color' => 'blue',
                'url' => route('parent.children.index'),
            ],
            [
                'title' => 'Aktuális havi fizetendő',
                'value' => $financialOverview['has_statement']
                    ? $this->formatForint($financialOverview['total_payable'])
                    : 'Még nincs elszámolás',
                'value_lines' => [
                    $financialOverview['has_statement']
                        ? $this->formatForint($financialOverview['total_payable'])
                        : 'Még nincs elszánolás',
                ],
                'value_class' => $financialOverview['has_statement']
                    ? 'df-parent-stat-value-numeric'
                    : 'df-parent-stat-value-text',
                'subtitle' => $financialOverview['has_statement']
                    ? (($financialOverview['overpayment_amount'] ?? 0) > 0
                        ? 'Fennmaradó túlfizetés: ' . $this->formatForint($financialOverview['overpayment_amount'])
                        : $financialOverview['month_label'])
                    : 'Az aktuális hónaphoz még nem készült elszámolás.',
                'helper' => $financialOverview['has_statement']
                    ? 'Az összes kapcsolt gyermek aktuális havi fizetendője.'
                    : 'A fizetendő összeg csak elkészült elszámolás után jelenik meg.',
                'icon' => 'fa-solid fa-wallet',
                'color' => 'green',
                'url' => route('parent.monthly-settlements.index'),
            ],
            [
                'title' => 'Fennálló tartozás',
                'value' => $outstandingDebt['has_debt']
                    ? $this->formatForint($outstandingDebt['amount'])
                    : 'Nincs tartozás',
                'value_lines' => [
                    $outstandingDebt['has_debt']
                        ? $this->formatForint($outstandingDebt['amount'])
                        : 'Nincs tartozás',
                ],
                'value_class' => $outstandingDebt['has_debt']
                    ? 'df-parent-stat-value-numeric'
                    : 'df-parent-stat-value-text',
                'subtitle' => $outstandingDebt['has_debt']
                    ? $outstandingDebt['summary']
                    : 'Nincs nyitott, lezárt fizetési kötelezettség.',
                'helper' => $outstandingDebt['has_debt']
                    ? 'Csak a még ki nem fizetett, lezárt elszámolások szerepelnek itt.'
                    : 'A lezárt elszámolások jelenleg rendezettek.',
                'icon' => 'fa-solid fa-file-invoice-dollar',
                'color' => 'orange',
                'url' => route('parent.payments'),
            ],
            [
                'title' => 'Következő lemondási határidő',
                'value' => $deadlineSummary['value'],
                'value_lines' => $deadlineSummary['value_lines'],
                'value_class' => 'df-parent-stat-value-datetime',
                'subtitle' => $deadlineSummary['subtitle'],
                'helper' => $deadlineSummary['helper'],
                'icon' => 'fa-solid fa-clock',
                'color' => 'purple',
                'url' => route('parent.meal-cancellations'),
            ],
        ];

        return array_map(function (array $card) use ($financialOverview, $outstandingDebt, $deadlineSummary) {
            if (($card['icon'] ?? null) === 'fa-solid fa-wallet') {
                $card['value_lines'] = [
                    $financialOverview['has_statement']
                        ? $this->formatForint($financialOverview['total_payable'])
                        : html_entity_decode('M&eacute;g nincs elsz&aacute;mol&aacute;s', ENT_QUOTES, 'UTF-8'),
                ];
                $card['value_class'] = $financialOverview['has_statement']
                    ? 'df-parent-stat-value-numeric'
                    : 'df-parent-stat-value-text';
            }

            if (($card['icon'] ?? null) === 'fa-solid fa-file-invoice-dollar') {
                $card['value_lines'] = [
                    $outstandingDebt['has_debt']
                        ? $this->formatForint($outstandingDebt['amount'])
                        : html_entity_decode('Nincs tartoz&aacute;s', ENT_QUOTES, 'UTF-8'),
                ];
                $card['value_class'] = $outstandingDebt['has_debt']
                    ? 'df-parent-stat-value-numeric'
                    : 'df-parent-stat-value-text';
            }

            if (($card['icon'] ?? null) === 'fa-solid fa-clock') {
                $card['value_lines'] = $deadlineSummary['has_deadline']
                    ? $deadlineSummary['value_lines']
                    : [
                        html_entity_decode('Nincs m&oacute;dos&iacute;that&oacute;', ENT_QUOTES, 'UTF-8'),
                        html_entity_decode('&eacute;tkez&eacute;s', ENT_QUOTES, 'UTF-8'),
                    ];
                $card['value_class'] = 'df-parent-stat-value-datetime';
            }

            return $card;
        }, $cards);
    }

    private function buildFinancialOverview(Collection $currentStatements, Collection $paymentsByStatement, CarbonImmutable $today): array
    {
        if ($currentStatements->isEmpty()) {
            return [
                'has_statement' => false,
                'month_label' => $today->locale('hu')->isoFormat('YYYY. MMMM'),
                'total_payable' => null,
                'paid_total' => null,
                'remaining' => null,
                'completion_percent' => null,
                'status' => ['label' => 'Még nincs elszámolás', 'class' => 'bg-light text-muted border'],
            ];
        }

        $totalPayable = (int) $currentStatements->sum('total_payable');
        $paidTotal = (int) $currentStatements->sum(
            fn (MonthlyPaymentStatement $statement) => (int) ($paymentsByStatement->get($statement->id) ?? 0)
        );
        $remaining = max(0, $totalPayable - $paidTotal);
        $completionPercent = $totalPayable > 0
            ? (int) round(min(100, ($paidTotal / $totalPayable) * 100))
            : 0;

        return [
            'has_statement' => true,
            'month_label' => $today->locale('hu')->isoFormat('YYYY. MMMM'),
            // A "total_payable" a meglévő elszámolási mezőből származik és
            // negatív is lehet (túlfizetés esetén) - a kártyán SOHA nem
            // jeleníthető meg negatív "fizetendő"-ként (ld. felhasználói
            // kérés), ezért a megjelenítésre szánt érték itt 0-nál soha nem
            // kisebb, a fennmaradó túlfizetést pedig külön mező hordozza.
            'total_payable' => \App\Support\Finance\SettlementAmountPresenter::payableDisplayAmount($totalPayable),
            'overpayment_amount' => \App\Support\Finance\SettlementAmountPresenter::overpaymentAmount($totalPayable),
            'paid_total' => $paidTotal,
            'remaining' => $remaining,
            'completion_percent' => $completionPercent,
            'status' => $this->financialStatus($currentStatements, $paidTotal, $remaining),
        ];
    }

    private function buildFinancialDonut(array $financialOverview): ?array
    {
        if (! $financialOverview['has_statement']) {
            return null;
        }

        return [
            'series' => [
                max(0, (int) $financialOverview['paid_total']),
                max(0, (int) $financialOverview['remaining']),
            ],
            'percent' => (int) $financialOverview['completion_percent'],
        ];
    }

    private function buildOutstandingDebtSummary(Collection $closedStatements, Collection $paymentsByStatement): array
    {
        $debtStatements = $closedStatements
            ->map(function (MonthlyPaymentStatement $statement) use ($paymentsByStatement) {
                $paid = (int) ($paymentsByStatement->get($statement->id) ?? 0);
                $remaining = max(0, (int) $statement->total_payable - $paid);

                return [
                    'statement' => $statement,
                    'remaining' => $remaining,
                ];
            })
            ->filter(fn (array $item) => $item['remaining'] > 0)
            ->values();

        $amount = (int) $debtStatements->sum('remaining');

        return [
            'has_debt' => $amount > 0,
            'amount' => $amount,
            'summary' => $debtStatements->isNotEmpty()
                ? ($debtStatements->count() === 1
                    ? '1 lezárt elszámolás még nyitott.'
                    : $debtStatements->count().' lezárt elszámolás még nyitott.')
                : 'Nincs nyitott tartozás.',
        ];
    }

    private function buildDeadlineSummary(Collection $institutionWindows): array
    {
        $candidate = $institutionWindows
            ->filter(fn (array $window) => $window['configured'] && $window['earliest_cancellable_day'] && $window['cutoff'])
            ->sortBy([
                fn (array $window) => $window['earliest_cancellable_day']?->timestamp ?? PHP_INT_MAX,
                fn (array $window) => $window['cutoff']?->timestamp ?? PHP_INT_MAX,
            ])
            ->first();

        if (! $candidate) {
            return [
                'has_deadline' => false,
                'value' => 'Nincs módosítható étkezés',
                'subtitle' => 'A következő időszakban nem található módosítható étkezési nap.',
                'helper' => 'A lemondási határidő az intézményi beállításoktól függ.',
                'value_lines' => ['Nincs módosítható', 'étkezés'],
                'date' => null,
            ];
        }

        $date = $candidate['earliest_cancellable_day'];
        $cutoff = $candidate['cutoff'];

        return [
            'has_deadline' => true,
            'value' => $date->locale('hu')->isoFormat('MMMM D.').' · határidő '.sprintf('%02d:%02d', $cutoff->hour, $cutoff->minute),
            'subtitle' => 'A legközelebbi még módosítható étkezési nap.',
            'helper' => 'A lemondás az intézmény napi határidejéig módosítható.',
            'value_lines' => [
                $date->locale('hu')->isoFormat('MMMM D.'),
                sprintf('%02d:%02d-ig', $cutoff->hour, $cutoff->minute),
            ],
            'date' => $date,
        ];
    }

    private function buildHistoryChart(Collection $closedStatements): array
    {
        $months = $closedStatements
            ->groupBy(fn (MonthlyPaymentStatement $statement) => sprintf('%04d-%02d', $statement->year, $statement->month))
            ->map(function (Collection $statements, string $monthKey) {
                [$year, $month] = array_map('intval', explode('-', $monthKey));
                $date = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $this->calendar->timezone());

                return [
                    'key' => $monthKey,
                    'label' => $date->locale('hu')->isoFormat('YYYY. MMM'),
                    'total' => (int) $statements->sum('total_payable'),
                ];
            })
            ->sortBy('key')
            ->take(-6)
            ->values();

        return [
            'has_data' => $months->isNotEmpty(),
            'categories' => $months->pluck('label')->all(),
            'totals' => $months->pluck('total')->all(),
        ];
    }

    private function buildCancellationSummary(Collection $currentStatements, array $deadlineSummary): array
    {
        $days = $currentStatements
            ->flatMap(fn (MonthlyPaymentStatement $statement) => $statement->days)
            ->values();

        $cancelledStatuses = [
            MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE,
            MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING,
            MonthlyPaymentDay::STATUS_CLASS_CANCELLATION,
        ];

        $cancelledDays = $days
            ->filter(fn (MonthlyPaymentDay $day) => in_array($day->status, $cancelledStatuses, true))
            ->values();

        $savingsRows = $cancelledDays->filter(function (MonthlyPaymentDay $day) {
            return ($day->cancellation_id || $day->class_cancellation_id)
                && $day->original_daily_price > $day->payable_amount;
        });

        return [
            'count' => $cancelledDays->count(),
            'saved_amount' => $savingsRows->isNotEmpty()
                ? (int) $savingsRows->sum(fn (MonthlyPaymentDay $day) => max(0, $day->original_daily_price - $day->payable_amount))
                : null,
            'saved_amount_reliable' => $savingsRows->isNotEmpty(),
            'next_deadline' => $deadlineSummary['has_deadline']
                ? implode(' · ', $deadlineSummary['value_lines'])
                : html_entity_decode('Nincs m&oacute;dos&iacute;that&oacute; &eacute;tkez&eacute;s', ENT_QUOTES, 'UTF-8'),
        ];
    }

    private function buildChildCard(Child $child, Collection $currentStatements, CarbonImmutable $today): array
    {
        /** @var MonthlyPaymentStatement|null $currentStatement */
        $currentStatement = $currentStatements->firstWhere('child_id', $child->id);
        $currentMealSetting = $this->currentMealSetting($child, $today);
        $dietaryNames = $child->dietaryRestrictions->pluck('name')->values();

        return [
            'model' => $child,
            'name' => $child->name,
            'initials' => $this->initials($child->name),
            'institution' => $child->institution?->name ?? 'Nincs intézmény',
            'group_name' => $child->group_name ?: 'Nincs megadva',
            'meal_status' => $this->mealStatusLabel($child, $currentMealSetting),
            'meal_package' => $this->mealPackageLabel($child, $currentMealSetting, $currentStatement),
            'discount' => $this->discountLabel($child),
            'dietary' => $dietaryNames,
            'details_url' => route('parent.children.show', $child),
            'meal_url' => route('parent.meal-cancellations'),
        ];
    }

    private function buildUpcomingTimeline(
        Collection $statements,
        Collection $institutionWindows,
        CarbonImmutable $today,
        bool $showChildName
    ): Collection {
        return $statements
            ->flatMap(function (MonthlyPaymentStatement $statement) {
                return $statement->days->map(fn (MonthlyPaymentDay $day) => [
                    'statement' => $statement,
                    'day' => $day,
                ]);
            })
            ->filter(fn (array $item) => $item['day']->date && $item['day']->date->gte($today))
            // FONTOS: a Collection::sortBy() tömbbel hívva a belső
            // sortByMany()-t futtatja, ami minden elemhez egy KÉT
            // PARAMÉTERES ($a, $b) összehasonlító függvényt vár, ami
            // -1/0/1-et ad vissza - NEM egy egyparaméteres, egy értéket
            // kinyerő closure-t. Az előző kód egyparaméteres closure-öket
            // adott át, amiket a sortByMany "$prop($a, $b)" hívása úgy
            // futtatott le, hogy a $b paramétert figyelmen kívül hagyta, és
            // mindig $a dátumának időbélyegét (egy nagy pozitív számot)
            // adta vissza "összehasonlítási eredményként" - emiatt a usort
            // gyakorlatilag minden párnál "a > b"-t "hitt", és a lista nem
            // dátum szerint rendeződött (a legközelebbi étkezés nem volt
            // feltétlenül az első).
            ->sortBy([
                fn (array $a, array $b) => ($a['day']->date?->timestamp ?? PHP_INT_MAX) <=> ($b['day']->date?->timestamp ?? PHP_INT_MAX),
                fn (array $a, array $b) => ($a['statement']->child?->name ?? '') <=> ($b['statement']->child?->name ?? ''),
            ])
            ->take(5)
            ->values()
            ->map(function (array $item) use ($institutionWindows, $showChildName) {
                /** @var MonthlyPaymentStatement $statement */
                $statement = $item['statement'];
                /** @var MonthlyPaymentDay $day */
                $day = $item['day'];
                $child = $statement->child;
                $window = $institutionWindows->get($child?->institution_id ?? 0);
                $status = $this->timelineStatus($day, $window);
                $isModifiable = $this->isTimelineItemModifiable($day, $window);

                return [
                    'date_label' => $day->date->locale('hu')->isoFormat('MMMM D. dddd'),
                    'child_name' => $showChildName ? $child?->name : null,
                    'meal_label' => $statement->mealPackage?->name
                        ?? ($day->status === MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL ? 'Nincs aktív étkezés' : 'Intézményi étkezés'),
                    'status' => $status,
                    'action_url' => route('parent.meal-cancellations'),
                    'action_label' => in_array($day->status, [
                        MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE,
                        MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING,
                    ], true) ? 'Módosítás' : 'Lemondás',
                    'is_modifiable' => $isModifiable,
                    'reason' => $isModifiable ? null : $this->timelineReason($day, $window),
                ];
            });
    }

    private function buildRecentFinanceItems(Collection $childIds): array
    {
        if ($childIds->isEmpty()) {
            return [
                'latest_payment' => null,
                'latest_invoice' => null,
            ];
        }

        $latestPayment = InstitutionPayment::query()
            ->whereIn('child_id', $childIds)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->with('child')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        $latestInvoice = InstitutionInvoice::query()
            ->whereIn('child_id', $childIds)
            ->with('child')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->first();

        return [
            'latest_payment' => $latestPayment ? [
                'amount' => $this->formatForint((int) $latestPayment->amount),
                'date' => $latestPayment->paid_at?->timezone($this->calendar->timezone())->locale('hu')->isoFormat('YYYY. MMMM D.'),
                'child_name' => $latestPayment->child?->name,
            ] : null,
            'latest_invoice' => $latestInvoice ? [
                'amount' => $this->formatForint((int) $latestInvoice->gross_amount),
                'number' => $latestInvoice->invoice_number ?: 'Sorszám még nincs',
                'status' => InstitutionInvoice::statusMeta($latestInvoice->status),
                'child_name' => $latestInvoice->child?->name,
            ] : null,
        ];
    }

    private function quickActions(): array
    {
        return [
            ['title' => 'Gyermekeim', 'description' => 'Kapcsolt gyermekek adatai', 'icon' => 'fa-solid fa-child', 'url' => route('parent.children.index')],
            ['title' => 'Étkezések és lemondások', 'description' => 'Következő étkezési napok', 'icon' => 'fa-solid fa-utensils', 'url' => route('parent.meal-cancellations')],
            ['title' => 'Havi elszámolások', 'description' => 'Havi fizetendő és státusz', 'icon' => 'fa-solid fa-file-lines', 'url' => route('parent.monthly-settlements.index')],
            ['title' => 'Befizetések', 'description' => 'Rögzített befizetések áttekintése', 'icon' => 'fa-solid fa-credit-card', 'url' => route('parent.payments')],
            ['title' => 'Számlák', 'description' => 'Kiállított számlák és állapotok', 'icon' => 'fa-solid fa-receipt', 'url' => route('parent.invoices')],
            ['title' => 'Fiókom', 'description' => 'Szülői profil és beállítások', 'icon' => 'fa-solid fa-user-gear', 'url' => route('parent.account')],
        ];
    }

    private function currentMealSetting(Child $child, CarbonImmutable $today): ?StudentMealSetting
    {
        return $child->mealSettings->first(function (StudentMealSetting $setting) use ($today) {
            $validFrom = CarbonImmutable::parse($setting->valid_from, $this->calendar->timezone())->startOfDay();
            $validTo = $setting->valid_to
                ? CarbonImmutable::parse($setting->valid_to, $this->calendar->timezone())->startOfDay()
                : null;

            return $validFrom->lte($today) && ($validTo === null || $validTo->gte($today));
        });
    }

    private function mealStatusLabel(Child $child, ?StudentMealSetting $currentMealSetting): string
    {
        if (! $child->active) {
            return 'Inaktív';
        }

        if (! $currentMealSetting) {
            return 'Nincs aktív étkeztetés';
        }

        return match ($currentMealSetting->mode) {
            StudentMealSetting::MODE_PACKAGE => 'Aktív menücsomag',
            StudentMealSetting::MODE_CUSTOM => 'Egyedi étkezések',
            default => 'Intézményi alapértelmezett',
        };
    }

    private function mealPackageLabel(
        Child $child,
        ?StudentMealSetting $currentMealSetting,
        ?MonthlyPaymentStatement $currentStatement
    ): string {
        if ($currentStatement?->mealPackage?->name) {
            return $currentStatement->mealPackage->name;
        }

        if (! $currentMealSetting) {
            return 'Nincs aktív étkezés';
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_PACKAGE) {
            return $currentMealSetting->mealPackage?->name ?? 'Még nincs csomag megadva';
        }

        if ($currentMealSetting->mode === StudentMealSetting::MODE_CUSTOM) {
            return 'Egyedi étkezések';
        }

        /** @var InstitutionMealPackage|null $defaultPackage */
        $defaultPackage = $child->institution?->mealPackages?->firstWhere('is_default', true);

        return $defaultPackage?->name ?? 'Intézményi alapértelmezett';
    }

    private function discountLabel(Child $child): ?string
    {
        $discount = $child->discountType;

        if (! $discount) {
            return null;
        }

        if ((int) $discount->percentage > 0) {
            return $discount->percentage.'% kedvezmény';
        }

        if (mb_stripos($discount->name, 'nélkül') !== false || mb_stripos($discount->name, 'nelkul') !== false) {
            return null;
        }

        return $discount->name;
    }

    private function timelineStatus(MonthlyPaymentDay $day, ?array $window): array
    {
        if ($day->status === MonthlyPaymentDay::STATUS_SCHOOL_BREAK) {
            return ['label' => 'Intézményi szünet', 'class' => 'bg-primary'];
        }

        if ($day->status === MonthlyPaymentDay::STATUS_CLASS_CANCELLATION) {
            return ['label' => 'Osztályszintű lemondás', 'class' => 'bg-warning text-dark'];
        }

        if (in_array($day->status, [
            MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE,
            MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING,
        ], true)) {
            return ['label' => 'Lemondva', 'class' => 'bg-info text-dark'];
        }

        if (! $this->isTimelineItemModifiable($day, $window)) {
            return ['label' => 'Határidő lejárt', 'class' => 'bg-secondary'];
        }

        return ['label' => 'Aktív', 'class' => 'bg-success'];
    }

    private function isTimelineItemModifiable(MonthlyPaymentDay $day, ?array $window): bool
    {
        if (! $window || ! ($window['configured'] ?? false) || ! ($window['earliest_cancellable_day'] ?? null)) {
            return false;
        }

        if (in_array($day->status, [
            MonthlyPaymentDay::STATUS_SCHOOL_BREAK,
            MonthlyPaymentDay::STATUS_CLASS_CANCELLATION,
            MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL,
        ], true)) {
            return false;
        }

        return $day->date->gte($window['earliest_cancellable_day']);
    }

    private function timelineReason(MonthlyPaymentDay $day, ?array $window): string
    {
        if ($day->status === MonthlyPaymentDay::STATUS_SCHOOL_BREAK) {
            return 'Az intézmény ezen a napon nem biztosít étkezést.';
        }

        if ($day->status === MonthlyPaymentDay::STATUS_CLASS_CANCELLATION) {
            return 'Az étkezés csoportszintű lemondás miatt marad el.';
        }

        if ($day->status === MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL) {
            return 'Ehhez a naphoz nincs aktív étkezési beállítás.';
        }

        if (! $window || ! ($window['configured'] ?? false)) {
            return 'Az intézményi lemondási határidő még nincs beállítva.';
        }

        return 'A napi lemondási határidő erre a napra már lejárt.';
    }

    private function financialStatus(Collection $currentStatements, int $paidTotal, int $remaining): array
    {
        if ($remaining <= 0 && $currentStatements->sum('total_payable') > 0) {
            return ['label' => 'Rendezve', 'class' => 'bg-success'];
        }

        if ($paidTotal > 0) {
            return ['label' => 'Részben fizetve', 'class' => 'bg-primary'];
        }

        if ($currentStatements->every(fn (MonthlyPaymentStatement $statement) => $statement->status === MonthlyPaymentStatement::STATUS_CLOSED)) {
            return ['label' => 'Lezárt elszámolás', 'class' => 'bg-info text-dark'];
        }

        return ['label' => 'Elszámolás készül', 'class' => 'bg-warning text-dark'];
    }

    private function greeting(CarbonImmutable $now, string $name): string
    {
        $hour = $now->hour;
        $prefix = match (true) {
            $hour < 11 => 'Jó reggelt',
            $hour < 18 => 'Jó napot',
            default => 'Jó estét',
        };

        return $prefix.', '.$name.'!';
    }

    private function formatLongDate(CarbonImmutable $date): string
    {
        return $date->locale('hu')->isoFormat('YYYY. MMMM D., dddd');
    }

    private function formatForint(?int $amount): string
    {
        return number_format((int) $amount, 0, ',', ' ').' Ft';
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
