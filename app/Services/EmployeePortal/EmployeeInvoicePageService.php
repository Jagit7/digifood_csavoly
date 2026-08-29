<?php

namespace App\Services\EmployeePortal;

use App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A dolgozói portál "Számláim" oldala - a
 * App\Services\ParentPortal\ParentInvoicePageService mintáját követi, egy
 * lényeges eltéréssel: a dolgozóknak nincs önálló App\Models\InstitutionInvoice
 * rekordjuk (az institution_invoices tábla csak child_id/guardian_id FK-t
 * ismer). A számla-adatok (invoice_number, invoice_status, invoice_url,
 * invoice_pdf_path, invoiced_at) közvetlenül az
 * App\Models\PaymentObligation\EmployeeMonthlyPaymentStatement rekordba
 * vannak beágyazva, ezért ez a szolgáltatás ezt a táblát listázza (csak
 * azokat a sorokat, amelyeknél a számlázás ténylegesen elindult, azaz van
 * kitöltött invoice_status mezőjük), nem egy külön Invoice modellt.
 */
class EmployeeInvoicePageService
{
    public function buildPageData(User $user, ?string $yearFilter): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $currentYear = (int) $today->year;
        $employeeIds = $this->resolveScope($user);
        $normalizedYearFilter = $this->normalizeYearFilter($yearFilter);
        $baseQuery = $this->visibleStatementsQuery($employeeIds);
        $stats = $this->buildStats(clone $baseQuery, $currentYear);
        $paginator = $this->paginateStatements(clone $baseQuery, $normalizedYearFilter, $currentYear);

        return [
            'stats' => $stats,
            'year_filter' => $normalizedYearFilter,
            'year_filter_options' => [
                ['value' => 'all', 'label' => 'Összes év'],
                ['value' => 'current', 'label' => $currentYear.'. év'],
                ['value' => 'earlier', 'label' => 'Korábbi évek'],
            ],
            'invoices' => $paginator,
            'has_invoices' => $stats['total_count'] > 0,
            'info_text' => 'A számlák a havi elszámolás lezárását és számlázását követően jelennek meg. Amennyiben egy elszámoláshoz tartozó számlát nem talál, kérjük, forduljon az intézményhez.',
        ];
    }

    public function downloadInvoiceDocument(User $user, EmployeeMonthlyPaymentStatement $statement): Response
    {
        $resolvedStatement = $this->findVisibleStatement($user, $statement->id);

        if (filled($resolvedStatement->invoice_pdf_path)) {
            if (! Storage::disk('public')->exists($resolvedStatement->invoice_pdf_path)) {
                abort(404);
            }

            $downloadName = ($resolvedStatement->invoice_number ?: 'szamla-'.$resolvedStatement->id).'.pdf';

            return Storage::disk('public')->download($resolvedStatement->invoice_pdf_path, $downloadName);
        }

        if (filled($resolvedStatement->invoice_url)) {
            return redirect()->away($resolvedStatement->invoice_url);
        }

        abort(404);
    }

    private function buildStats(Builder $query, int $currentYear): array
    {
        $statements = $query->get();
        $issuedThisYear = $statements->filter(function (EmployeeMonthlyPaymentStatement $statement) use ($currentYear) {
            return $statement->invoiced_at !== null
                && (int) $statement->invoiced_at->year === $currentYear
                && $this->countsAsIssued($statement);
        });
        $latestStatement = $statements
            ->filter(fn (EmployeeMonthlyPaymentStatement $statement) => $statement->invoiced_at !== null)
            ->sortByDesc(fn (EmployeeMonthlyPaymentStatement $statement) => $statement->invoiced_at?->timestamp ?? 0)
            ->first();
        $downloadableCount = $statements
            ->filter(fn (EmployeeMonthlyPaymentStatement $statement) => $this->hasDownloadableDocument($statement))
            ->count();

        return [
            'total_count' => $statements->count(),
            'total_count_label' => $statements->count().' db',
            'current_year_gross' => (int) $issuedThisYear->sum('total_payable'),
            'current_year_gross_label' => $this->formatForint((int) $issuedThisYear->sum('total_payable')),
            'current_year_helper' => $currentYear.'-ban kiállítva',
            'latest_invoice_date_label' => $latestStatement?->invoiced_at?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
            'latest_invoice_helper' => $latestStatement?->invoice_number ?: 'Még nincs kiállított számla',
            'downloadable_count' => $downloadableCount,
            'downloadable_count_label' => $downloadableCount.' db',
        ];
    }

    private function paginateStatements(Builder $query, string $yearFilter, int $currentYear): LengthAwarePaginator
    {
        $filteredQuery = $this->applyYearFilter($query, $yearFilter, $currentYear);

        $paginator = $filteredQuery
            ->orderByDesc('employee_monthly_payment_statements.year')
            ->orderByDesc('employee_monthly_payment_statements.month')
            ->orderByDesc('employee_monthly_payment_statements.id')
            ->paginate(10)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (EmployeeMonthlyPaymentStatement $statement) => $this->mapStatementRow($statement))
        );

        return $paginator;
    }

    private function mapStatementRow(EmployeeMonthlyPaymentStatement $statement): array
    {
        $viewUrl = filled($statement->invoice_url) ? $statement->invoice_url : null;
        $downloadable = $this->hasDownloadableDocument($statement);
        $invoiceStatus = $this->invoiceStatusMeta($statement->invoice_status);
        $paymentStatus = $this->paymentStatusMeta($statement->payment_status);
        $periodLabel = CarbonImmutable::create($statement->year, $statement->month, 1, 0, 0, 0, config('app.timezone'))
            ->locale('hu')
            ->isoFormat('YYYY. MMMM');

        return [
            'id' => $statement->id,
            'invoice_number' => $statement->invoice_number ?: 'Kiállításra vár',
            'invoiced_at_label' => $statement->invoiced_at?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
            'due_date_label' => $statement->due_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
            'period_label' => $periodLabel,
            'total_payable_label' => $this->formatForint((int) $statement->total_payable),
            'invoice_status' => $invoiceStatus,
            'payment_status' => $paymentStatus,
            'has_download' => $downloadable,
            'download_url' => $downloadable
                ? route('employee.invoices.download', ['statement' => $statement->id])
                : null,
            'has_view' => $viewUrl !== null,
            'view_url' => $viewUrl,
            'details' => [
                'invoice_number' => $statement->invoice_number ?: 'Kiállításra vár',
                'invoiced_at_label' => $statement->invoiced_at?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
                'due_date_label' => $statement->due_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
                'period_label' => $periodLabel,
                'meal_amount_label' => $this->formatForint((int) $statement->meal_amount),
                'previous_balance_label' => $this->formatForint((int) $statement->previous_balance),
                'total_payable_label' => $this->formatForint((int) $statement->total_payable),
                'invoice_provider_label' => $this->invoiceProviderLabel($statement->invoice_provider),
                'invoice_status_label' => $invoiceStatus['label'],
                'invoice_status_class' => $invoiceStatus['class'],
                'payment_status_label' => $paymentStatus['label'],
                'payment_status_class' => $paymentStatus['class'],
            ],
        ];
    }

    private function visibleStatementsQuery(Collection $employeeIds): Builder
    {
        if ($employeeIds->isEmpty()) {
            return EmployeeMonthlyPaymentStatement::query()->whereRaw('1 = 0');
        }

        return EmployeeMonthlyPaymentStatement::query()
            ->whereIn('institution_employee_id', $employeeIds->all())
            ->whereNotNull('invoice_status')
            ->with(['employee', 'institution']);
    }

    private function findVisibleStatement(User $user, int $statementId): EmployeeMonthlyPaymentStatement
    {
        $employeeIds = $this->resolveScope($user);

        return $this->visibleStatementsQuery($employeeIds)
            ->whereKey($statementId)
            ->firstOrFail();
    }

    private function resolveScope(User $user): Collection
    {
        return $user->employees()
            ->where('active', true)
            ->pluck('institution_employees.id');
    }

    private function normalizeYearFilter(?string $yearFilter): string
    {
        return in_array($yearFilter, ['all', 'current', 'earlier'], true)
            ? $yearFilter
            : 'all';
    }

    private function applyYearFilter(Builder $query, string $yearFilter, int $currentYear): Builder
    {
        if ($yearFilter === 'current') {
            return $query->where('employee_monthly_payment_statements.year', $currentYear);
        }

        if ($yearFilter === 'earlier') {
            return $query->where('employee_monthly_payment_statements.year', '<', $currentYear);
        }

        return $query;
    }

    private function countsAsIssued(EmployeeMonthlyPaymentStatement $statement): bool
    {
        return $statement->invoice_status === EmployeeMonthlyPaymentStatement::INVOICE_STATUS_ISSUED;
    }

    private function hasDownloadableDocument(EmployeeMonthlyPaymentStatement $statement): bool
    {
        return filled($statement->invoice_pdf_path) || filled($statement->invoice_url);
    }

    private function invoiceStatusMeta(?string $status): array
    {
        return match ($status) {
            EmployeeMonthlyPaymentStatement::INVOICE_STATUS_DRAFT => ['label' => 'Kiállításra vár', 'class' => 'bg-warning text-dark'],
            EmployeeMonthlyPaymentStatement::INVOICE_STATUS_ISSUED => ['label' => 'Kiállítva', 'class' => 'bg-success'],
            EmployeeMonthlyPaymentStatement::INVOICE_STATUS_CANCELLED => ['label' => 'Sztornózva', 'class' => 'bg-secondary'],
            default => ['label' => 'Nincs megadva', 'class' => 'bg-light text-muted border'],
        };
    }

    private function paymentStatusMeta(?string $status): array
    {
        return match ($status) {
            EmployeeMonthlyPaymentStatement::PAYMENT_STATUS_PENDING => ['label' => 'Fizetésre vár', 'class' => 'bg-warning text-dark'],
            EmployeeMonthlyPaymentStatement::PAYMENT_STATUS_PAID => ['label' => 'Kifizetve', 'class' => 'bg-success'],
            EmployeeMonthlyPaymentStatement::PAYMENT_STATUS_FAILED => ['label' => 'Sikertelen', 'class' => 'bg-danger'],
            EmployeeMonthlyPaymentStatement::PAYMENT_STATUS_REFUNDED => ['label' => 'Visszatérítve', 'class' => 'bg-info text-dark'],
            default => ['label' => 'Nincs megadva', 'class' => 'bg-light text-muted border'],
        };
    }

    private function invoiceProviderLabel(?string $provider): string
    {
        return match ($provider) {
            EmployeeMonthlyPaymentStatement::INVOICE_PROVIDER_BILLINGO => 'Billingo',
            EmployeeMonthlyPaymentStatement::INVOICE_PROVIDER_SZAMLAZZ_HU => 'Számlázz.hu',
            EmployeeMonthlyPaymentStatement::INVOICE_PROVIDER_MANUAL => 'Kézi',
            default => 'Nincs megadva',
        };
    }

    private function formatForint(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }
}
