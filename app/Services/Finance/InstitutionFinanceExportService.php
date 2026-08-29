<?php

namespace App\Services\Finance;

use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionFinanceExportRequest;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\PaymentObligation\MonthlyPaymentStatementListService;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstitutionFinanceExportService
{
    public function __construct(
        private readonly MonthlyPaymentStatementListService $statementListService,
        private readonly InstitutionDebtService $debtService,
        private readonly InstitutionInvoiceService $invoiceService,
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper
    ) {
    }

    public function exportCards(): array
    {
        return [
            InstitutionFinanceExportRequest::TYPE_PAYMENT_OBLIGATIONS => [
                'title' => 'Fizetési kötelezettségek',
                'description' => 'Havi gyermekenkénti fizetési kötelezettségek, jóváírásokkal és korrekciókkal.',
                'formats' => ['XLSX', 'CSV'],
                'icon' => 'fa-solid fa-file-lines',
                'color' => 'blue',
            ],
            InstitutionFinanceExportRequest::TYPE_PAYMENTS => [
                'title' => 'Befizetések',
                'description' => 'Minden rögzített befizetés fizetési móddal, státusszal és kapcsolódó hivatkozásokkal.',
                'formats' => ['XLSX', 'CSV'],
                'icon' => 'fa-solid fa-money-bill-transfer',
                'color' => 'green',
            ],
            InstitutionFinanceExportRequest::TYPE_DEBTS => [
                'title' => 'Tartozások',
                'description' => 'A fennmaradó tartozások ugyanazzal a számítási logikával, mint a Tartozások modulban.',
                'formats' => ['XLSX', 'CSV'],
                'icon' => 'fa-solid fa-triangle-exclamation',
                'color' => 'red',
            ],
            InstitutionFinanceExportRequest::TYPE_INVOICES => [
                'title' => 'Számlák',
                'description' => 'A számlák alapadatai, szolgáltatói azonosítói és pénzügyi státuszai.',
                'formats' => ['XLSX', 'CSV'],
                'icon' => 'fa-solid fa-file-invoice',
                'color' => 'orange',
            ],
            InstitutionFinanceExportRequest::TYPE_MONTHLY_SUMMARY => [
                'title' => 'Havi pénzügyi összesítő',
                'description' => 'Hónaponként aggregált pénzügyi összkép kötelezettségekkel, befizetésekkel, tartozásokkal és számlákkal.',
                'formats' => ['XLSX', 'CSV'],
                'icon' => 'fa-solid fa-chart-column',
                'color' => 'purple',
            ],
        ];
    }

    public function groupOptions(Institution $institution): Collection
    {
        return DB::table('children')
            ->where('institution_id', $institution->id)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');
    }

    public function download(Institution $institution, array $filters): BinaryFileResponse
    {
        [$headers, $rows, $meta] = match ($filters['export_type']) {
            InstitutionFinanceExportRequest::TYPE_PAYMENT_OBLIGATIONS => $this->paymentObligationsData($institution, $filters),
            InstitutionFinanceExportRequest::TYPE_PAYMENTS => $this->paymentsData($institution, $filters),
            InstitutionFinanceExportRequest::TYPE_DEBTS => $this->debtsData($institution, $filters),
            InstitutionFinanceExportRequest::TYPE_INVOICES => $this->invoicesData($institution, $filters),
            InstitutionFinanceExportRequest::TYPE_MONTHLY_SUMMARY => $this->monthlySummaryData($institution, $filters),
        };

        return $this->buildFileResponse(
            $institution,
            $filters['export_type'],
            $filters['format'],
            $headers,
            $rows,
            $meta['numeric_columns'] ?? [],
            $meta['date_columns'] ?? [],
            $filters
        );
    }

    private function paymentObligationsData(Institution $institution, array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $queryFilters = [];
        $statementStatus = $this->mapStatementStatus($filters['status'] ?? null);
        if ($statementStatus !== null) {
            $queryFilters['payment_status'] = $statementStatus;
        }

        $query = $this->statementListService->query($institution, $period, $queryFilters);

        if (!empty($filters['group_name'])) {
            $query->whereHas('child', fn (Builder $childQuery) => $childQuery->where('group_name', $filters['group_name']));
        }

        $statements = $query->get();
        $rows = $statements->map(function (MonthlyPaymentStatement $statement) use ($institution) {
            $guardian = $this->statementGuardianName($statement);
            $dueAt = $this->statementDueDate($institution, $statement);
            $periods = $this->periodHelper->fromStatement($statement);

            return [
                $statement->child?->name ?? '',
                $statement->child?->group_name ?? '',
                $guardian,
                $periods['payment_period_label'],
                $periods['meal_period_label'],
                $periods['credit_period_label'],
                (int) $statement->meal_amount,
                (int) $statement->previous_cancellation_credit,
                (int) $statement->invoiceable_amount,
                (int) $statement->billing_adjustment_amount,
                (int) $statement->previous_balance,
                (int) $statement->total_payable,
                $dueAt->format('Y-m-d'),
                $this->statementStatusLabel($statement),
            ];
        })->all();

        return [[
            'Gyermek neve',
            'Osztály/csoport',
            'Gondviselő',
            'Fizetési hónap',
            'Étkezési időszak',
            'Jóváírási időszak',
            'Következő havi étkezések',
            'Előző havi lemondások jóváírása',
            'Havi előírás',
            'Korrekciók',
            'Korábbi egyenleg',
            'Tényleges fizetendő',
            'Fizetési határidő',
            'Státusz',
        ], $rows, [
            'numeric_columns' => [7, 8, 9, 10, 11, 12],
            'date_columns' => [13],
        ]];
    }

    private function paymentsData(Institution $institution, array $filters): array
    {
        $paymentStatus = $this->mapPaymentStatus($filters['status'] ?? null);

        $query = InstitutionPayment::query()
            ->with(['child', 'guardian', 'monthlyPaymentStatement', 'recordedBy'])
            ->where('institution_id', $institution->id)
            ->when($paymentStatus !== null, fn (Builder $query) => $query->where('status', $paymentStatus))
            ->when(!empty($filters['date_from']), fn (Builder $query) => $query->whereDate('paid_at', '>=', Carbon::parse($filters['date_from'])->toDateString()))
            ->when(!empty($filters['date_to']), fn (Builder $query) => $query->whereDate('paid_at', '<=', Carbon::parse($filters['date_to'])->toDateString()))
            ->when(!empty($filters['month']), function (Builder $query) use ($filters) {
                [$year, $month] = explode('-', $filters['month']);
                $query->whereHas('monthlyPaymentStatement', fn (Builder $statementQuery) => $statementQuery
                    ->where('year', (int) $year)
                    ->where('month', (int) $month));
            })
            ->when(!empty($filters['group_name']), fn (Builder $query) => $query->whereHas('child', fn (Builder $childQuery) => $childQuery->where('group_name', $filters['group_name'])))
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        $rows = $query->get()->map(function (InstitutionPayment $payment) {
            $statement = $payment->monthlyPaymentStatement;
            $relatedInvoice = $payment->invoice_number ?: $statement?->invoice_number ?: $statement?->invoice?->invoice_number;

            return [
                optional($payment->paid_at)->format('Y-m-d H:i:s'),
                $payment->child?->name ?? '',
                $payment->guardian?->full_name ?? '',
                (int) $payment->amount,
                InstitutionPayment::paymentMethodOptions()[$payment->payment_method] ?? $payment->payment_method,
                InstitutionPayment::statusOptions()[$payment->status] ?? $payment->status,
                $payment->reference ?? '',
                $statement ? sprintf('%04d.%02d', $statement->year, $statement->month) : '',
                $relatedInvoice ?: '',
                $payment->recordedBy?->name ?? '',
            ];
        })->all();

        return [[
            'Befizetés dátuma',
            'Gyermek',
            'Gondviselő',
            'Összeg',
            'Fizetési mód',
            'Státusz',
            'Hivatkozás',
            'Kapcsolódó kötelezettség',
            'Kapcsolódó számla',
            'Rögzítő',
        ], $rows, [
            'numeric_columns' => [4],
            'date_columns' => [1],
        ]];
    }

    private function debtsData(Institution $institution, array $filters): array
    {
        $query = $this->debtService->query($institution, [
            'month' => $filters['month'] ?? null,
            'group_name' => $filters['group_name'] ?? null,
            'status' => $this->mapDebtStatus($filters['status'] ?? null),
        ]);

        if (!empty($filters['date_from'])) {
            $query->whereRaw($this->debtDueDateSql($institution) . ' >= ?', [Carbon::parse($filters['date_from'])->startOfDay()->toDateTimeString()]);
        }

        if (!empty($filters['date_to'])) {
            $query->whereRaw($this->debtDueDateSql($institution) . ' <= ?', [Carbon::parse($filters['date_to'])->endOfDay()->toDateTimeString()]);
        }

        $statements = $query->get()->map(fn (MonthlyPaymentStatement $statement) => $this->debtService->hydrateStatement($statement, $institution));

        $rows = $statements->map(function (MonthlyPaymentStatement $statement) {
            return [
                $statement->child?->name ?? '',
                $statement->display_guardians,
                sprintf('%04d.%02d', $statement->year, $statement->month),
                (int) $statement->total_payable,
                (int) $statement->completed_payments_total,
                (int) $statement->remaining_amount,
                $statement->due_at->format('Y-m-d'),
                (int) $statement->late_days,
                $statement->debt_status['primary']['label'],
            ];
        })->all();

        return [[
            'Gyermek',
            'Gondviselő',
            'Hónap',
            'Fizetési kötelezettség',
            'Befizetett összeg',
            'Fennmaradó tartozás',
            'Fizetési határidő',
            'Késedelmi napok',
            'Státusz',
        ], $rows, [
            'numeric_columns' => [4, 5, 6, 8],
            'date_columns' => [7],
        ]];
    }

    private function invoicesData(Institution $institution, array $filters): array
    {
        $invoiceStatus = $this->mapInvoiceStatus($filters['status'] ?? null);

        $query = $this->invoiceService->query($institution, [
            'month' => $filters['month'] ?? null,
            'provider' => null,
            'status' => $invoiceStatus,
            'payment_method' => null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ]);

        if (!empty($filters['group_name'])) {
            $query->whereHas('child', fn (Builder $childQuery) => $childQuery->where('group_name', $filters['group_name']));
        }

        $rows = $query->orderByDesc('institution_invoices.issue_date')
            ->orderByDesc('institution_invoices.id')
            ->get()
            ->map(function (InstitutionInvoice $invoice) use ($institution) {
                $effectiveStatus = $invoice->effective_status ?? $this->invoiceService->findForInstitution($institution, $invoice)->effective_status;

                return [
                    $invoice->invoice_number ?: '',
                    $invoice->child?->name ?? '',
                    $invoice->guardian?->full_name ?? $invoice->customer_name,
                    InstitutionInvoice::providerOptions()[$invoice->provider] ?? $invoice->provider,
                    (int) $invoice->gross_amount,
                    $invoice->issue_date?->format('Y-m-d') ?: '',
                    $invoice->fulfillment_date?->format('Y-m-d') ?: '',
                    $invoice->due_date?->format('Y-m-d') ?: '',
                    InstitutionPayment::paymentMethodOptions()[$invoice->payment_method] ?? $invoice->payment_method,
                    InstitutionInvoice::statusOptions()[$effectiveStatus] ?? $effectiveStatus,
                    $invoice->provider_invoice_id ?: '',
                ];
            })->all();

        return [[
            'Számlaszám',
            'Gyermek',
            'Gondviselő',
            'Szolgáltató',
            'Bruttó összeg',
            'Kiállítás dátuma',
            'Teljesítés dátuma',
            'Fizetési határidő',
            'Fizetési mód',
            'Státusz',
            'Szolgáltatói azonosító',
        ], $rows, [
            'numeric_columns' => [5],
            'date_columns' => [6, 7, 8],
        ]];
    }

    private function monthlySummaryData(Institution $institution, array $filters): array
    {
        $statementRows = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->when(!empty($filters['month']), function (Builder $query) use ($filters) {
                [$year, $month] = explode('-', $filters['month']);
                $query->where('year', (int) $year)->where('month', (int) $month);
            })
            ->when(!empty($filters['group_name']), fn (Builder $query) => $query->whereHas('child', fn (Builder $childQuery) => $childQuery->where('group_name', $filters['group_name'])))
            ->get(['id', 'child_id', 'year', 'month', 'total_payable']);

        $completedPayments = InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as total_amount')
            ->where('institution_id', $institution->id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->whereNotNull('monthly_payment_statement_id')
            ->groupBy('monthly_payment_statement_id')
            ->get()
            ->keyBy('monthly_payment_statement_id');

        $invoiceRows = InstitutionInvoice::query()
            ->where('institution_id', $institution->id)
            ->when(!empty($filters['month']), function (Builder $query) use ($filters) {
                [$year, $month] = explode('-', $filters['month']);
                $query->whereHas('monthlyPaymentStatement', fn (Builder $statementQuery) => $statementQuery
                    ->where('year', (int) $year)
                    ->where('month', (int) $month));
            })
            ->when(!empty($filters['group_name']), fn (Builder $query) => $query->whereHas('child', fn (Builder $childQuery) => $childQuery->where('group_name', $filters['group_name'])))
            ->get(['id', 'monthly_payment_statement_id', 'gross_amount', 'status', 'due_date', 'document_type']);

        $grouped = [];

        foreach ($statementRows as $statement) {
            $key = sprintf('%04d-%02d', $statement->year, $statement->month);
            $paymentsTotal = (int) ($completedPayments[$statement->id]->total_amount ?? 0);
            $grouped[$key] ??= [
                'month' => $key,
                'children' => [],
                'payment_obligations_total' => 0,
                'completed_payments_total' => 0,
                'remaining_debt_total' => 0,
                'issued_invoices_total' => 0,
                'paid_invoices_total' => 0,
            ];

            $grouped[$key]['children'][$statement->child_id] = true;
            $grouped[$key]['payment_obligations_total'] += (int) $statement->total_payable;
            $grouped[$key]['completed_payments_total'] += $paymentsTotal;
            $grouped[$key]['remaining_debt_total'] += max(0, (int) $statement->total_payable - $paymentsTotal);
        }

        foreach ($invoiceRows as $invoice) {
            $statement = $statementRows->firstWhere('id', $invoice->monthly_payment_statement_id);
            if (!$statement) {
                continue;
            }

            $key = sprintf('%04d-%02d', $statement->year, $statement->month);
            $grouped[$key] ??= [
                'month' => $key,
                'children' => [],
                'payment_obligations_total' => 0,
                'completed_payments_total' => 0,
                'remaining_debt_total' => 0,
                'issued_invoices_total' => 0,
                'paid_invoices_total' => 0,
            ];

            // A sztornó (jóváíró) számla gross_amount-ja negatív (lásd
            // InstitutionInvoiceService::cancel()), ezért az
            // issued_invoices_total összegzésben automatikusan nullázza az
            // eredeti számlát - nem kell külön kezelni.
            $grouped[$key]['issued_invoices_total'] += (int) $invoice->gross_amount;

            // A "kifizetve" állapot befizetés-összeg alapú összehasonlítása
            // csak az eredeti számlákra értelmezhető: a sztornó dokumentum
            // negatív összege miatt a paymentTotal >= gross_amount feltétel
            // (negatív összeggel szemben) tévesen szinte mindig igaz lenne,
            // és hibás (negatív) összeget vinne be a paid_invoices_total-ba.
            // Az eredeti számla sztornózáskor "voided" állapotba kerül, így
            // ő már kiesik ebből az összegzésből - a sztornó-sort egyszerűen
            // kihagyjuk itt.
            if ($invoice->document_type === InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION) {
                continue;
            }

            $paymentTotal = (int) ($completedPayments[$invoice->monthly_payment_statement_id]->total_amount ?? 0);
            $isPaid = $paymentTotal >= (int) $invoice->gross_amount
                && !in_array($invoice->status, [InstitutionInvoice::STATUS_FAILED, InstitutionInvoice::STATUS_CANCELLED, InstitutionInvoice::STATUS_VOIDED], true);

            if ($isPaid) {
                $grouped[$key]['paid_invoices_total'] += (int) $invoice->gross_amount;
            }
        }

        ksort($grouped);

        $rows = collect($grouped)->values()->map(function (array $row) {
            $paymentPeriod = Carbon::createFromFormat('Y-m', $row['month'])->startOfMonth();
            $periods = $this->periodHelper->fromMonth($paymentPeriod);

            return [
                $periods['payment_period_label'],
                $periods['meal_period_label'],
                $periods['credit_period_label'],
                count($row['children']),
                $row['payment_obligations_total'],
                $row['completed_payments_total'],
                $row['remaining_debt_total'],
                $row['issued_invoices_total'],
                $row['paid_invoices_total'],
            ];
        })->all();

        return [[
            'Fizetési hónap',
            'Étkezési időszak',
            'Jóváírási időszak',
            'Gyermekek száma',
            'Fizetési kötelezettségek összege',
            'Completed befizetések összege',
            'Fennálló tartozás',
            'Kiállított számlák összege',
            'Kifizetett számlák összege',
        ], $rows, [
            'numeric_columns' => [4, 5, 6, 7, 8, 9],
        ]];
    }

    private function buildFileResponse(
        Institution $institution,
        string $exportType,
        string $format,
        array $headers,
        array $rows,
        array $numericColumns,
        array $dateColumns,
        array $filters
    ): BinaryFileResponse {
        $fileName = $this->fileName($institution, $exportType, $filters, $format);
        $filePath = tempnam(sys_get_temp_dir(), 'digifood_finance_');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->sheetTitle($exportType));

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$column}1", $header);
        }

        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')
            ->getFont()
            ->setBold(true);
        $sheet->freezePane('A2');

        $rowNumber = 2;
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $this->writeCellValue($sheet, "{$column}{$rowNumber}", $value, $format);
            }

            $rowNumber++;
        }

        $lastDataRow = max(1, $rowNumber - 1);
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastDataRow}");

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        foreach ($numericColumns as $columnIndex) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getStyle("{$column}2:{$column}{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('# ##0');
        }

        foreach ($dateColumns as $columnIndex) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getStyle("{$column}2:{$column}{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('yyyy-mm-dd');
        }

        if ($format === 'xlsx') {
            (new Xlsx($spreadsheet))->save($filePath);
        } else {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(';');
            $writer->setEnclosure('"');
            $writer->setUseBOM(true);
            $writer->save($filePath);
        }

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Excel/CSV formula injection (CWE-1236) elleni védelem: a gondviselő
     * és a gyermek neve/számlázási neve teljesen szabadon szerkeszthető a
     * szülői felületen (ld. ParentAccountService::updateBillingData() /
     * updatePersonalData()). Ha egy ilyen mező '=', '+', '-', '@',
     * tabulátor vagy kocsivissza karakterrel kezdődik, azt Excel/Google
     * Sheets megnyitáskor képletként próbálja értelmezni és lefuttatni
     * (pl. adatszivárgás egy HYPERLINK() képlettel).
     *
     * XLSX-nél a kódbázisban már bevett mintát követjük (ld.
     * MonthlyPaymentSummaryExportService): setCellValueExplicit(...,
     * DataType::TYPE_STRING) - ez megakadályozza, hogy a PhpSpreadsheet
     * automatikus típusfelismerése TYPE_FORMULA-nak jelölje a cellát, a
     * megjelenített szöveg nem változik.
     *
     * A CSV formátumnak viszont nincs saját celltípusa - ott az egyetlen
     * megbízható védelem a vezető karakter elé illesztett aposztróf, amit
     * Excel/Sheets CSV-importnál "kényszerített szöveg" jelzésként kezel
     * (ez az OWASP által is ajánlott szabványos védelem CSV-nél).
     */
    private function writeCellValue(Worksheet $sheet, string $cell, mixed $value, string $format): void
    {
        if (! is_string($value) || $value === '' || ! in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $sheet->setCellValue($cell, $value);

            return;
        }

        if ($format === 'csv') {
            $sheet->setCellValue($cell, "'".$value);

            return;
        }

        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    private function fileName(Institution $institution, string $exportType, array $filters, string $format): string
    {
        $name = Str::of(Str::ascii($institution->name))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        $period = $filters['month']
            ?? (($filters['date_from'] ?? null) && ($filters['date_to'] ?? null)
                ? $filters['date_from'] . '_' . $filters['date_to']
                : now(config('digifood.business_timezone', 'Europe/Budapest'))->format('Y-m-d'));

        return "{$exportType}_{$name}_{$period}.{$format}";
    }

    private function sheetTitle(string $exportType): string
    {
        return match ($exportType) {
            InstitutionFinanceExportRequest::TYPE_PAYMENT_OBLIGATIONS => 'Kötelezettségek',
            InstitutionFinanceExportRequest::TYPE_PAYMENTS => 'Befizetések',
            InstitutionFinanceExportRequest::TYPE_DEBTS => 'Tartozások',
            InstitutionFinanceExportRequest::TYPE_INVOICES => 'Számlák',
            InstitutionFinanceExportRequest::TYPE_MONTHLY_SUMMARY => 'Havi összesítő',
        };
    }

    private function resolvePeriod(array $filters): Carbon
    {
        return !empty($filters['month'])
            ? Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth()
            : now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfMonth();
    }

    private function statementGuardianName(MonthlyPaymentStatement $statement): string
    {
        $billingProfiles = $statement->child?->billingProfiles ?? collect();
        $primaryProfile = $billingProfiles->first(fn ($profile) => (bool) ($profile->pivot->is_primary ?? false));
        $profile = $primaryProfile ?: $billingProfiles->first();

        if ($profile?->billing_name) {
            return (string) $profile->billing_name;
        }

        $guardian = $statement->child?->guardians?->first();

        return $guardian?->full_name ?: '';
    }

    private function statementDueDate(Institution $institution, MonthlyPaymentStatement $statement): Carbon
    {
        $dueDay = (int) optional($institution->setting)->payment_due_day;
        if ($dueDay < 1 || $dueDay > 28) {
            $dueDay = (int) $institution->billing_payment_due_days;
        }
        if ($dueDay < 1 || $dueDay > 28) {
            $dueDay = 5;
        }

        return Carbon::create($statement->year, $statement->month, $dueDay, 0, 0, 0, config('digifood.business_timezone', 'Europe/Budapest'));
    }

    private function statementStatusLabel(MonthlyPaymentStatement $statement): string
    {
        return match (true) {
            $statement->status === MonthlyPaymentStatement::STATUS_CLOSED => 'Lezárt',
            $statement->status === MonthlyPaymentStatement::STATUS_REVIEWED => 'Ellenőrzött',
            $statement->total_payable > 0 => 'Fizetendő',
            default => 'Piszkozat',
        };
    }

    private function mapStatementStatus(?string $status): ?string
    {
        return match ($status) {
            'statement_draft' => 'draft',
            'statement_reviewed' => 'reviewed',
            'statement_closed' => 'closed',
            'statement_payable' => 'payable',
            'statement_zero' => 'zero',
            default => null,
        };
    }

    private function mapPaymentStatus(?string $status): ?string
    {
        return match ($status) {
            'payment_pending' => InstitutionPayment::STATUS_PENDING,
            'payment_completed' => InstitutionPayment::STATUS_COMPLETED,
            'payment_failed' => InstitutionPayment::STATUS_FAILED,
            'payment_refunded' => InstitutionPayment::STATUS_REFUNDED,
            'payment_cancelled' => InstitutionPayment::STATUS_CANCELLED,
            default => null,
        };
    }

    private function mapDebtStatus(?string $status): ?string
    {
        return match ($status) {
            'debt_before_due' => 'before_due',
            'debt_due_today' => 'due_today',
            'debt_overdue' => 'overdue',
            'debt_partial_paid' => 'partial_paid',
            default => null,
        };
    }

    private function mapInvoiceStatus(?string $status): ?string
    {
        return match ($status) {
            'invoice_draft' => InstitutionInvoice::STATUS_DRAFT,
            'invoice_pending' => InstitutionInvoice::STATUS_PENDING,
            'invoice_issued' => InstitutionInvoice::STATUS_ISSUED,
            'invoice_paid' => InstitutionInvoice::STATUS_PAID,
            'invoice_overdue' => InstitutionInvoice::STATUS_OVERDUE,
            'invoice_cancelled' => InstitutionInvoice::STATUS_CANCELLED,
            'invoice_voided' => InstitutionInvoice::STATUS_VOIDED,
            'invoice_failed' => InstitutionInvoice::STATUS_FAILED,
            default => null,
        };
    }

    private function debtDueDateSql(Institution $institution): string
    {
        $dueDay = (int) optional($institution->setting)->payment_due_day;
        if ($dueDay < 1 || $dueDay > 28) {
            $dueDay = (int) $institution->billing_payment_due_days;
        }
        if ($dueDay < 1 || $dueDay > 28) {
            $dueDay = 5;
        }

        return "STR_TO_DATE(CONCAT(monthly_payment_statements.year, '-', LPAD(monthly_payment_statements.month, 2, '0'), '-', LPAD({$dueDay}, 2, '0'), ' 23:59:59'), '%Y-%m-%d %H:%i:%s')";
    }
}
