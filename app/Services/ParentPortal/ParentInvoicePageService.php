<?php

namespace App\Services\ParentPortal;

use App\Models\Child;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ParentInvoicePageService
{
    public function buildPageData(User $user, ?string $yearFilter): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $currentYear = (int) $today->year;
        [$guardianIds, $childIds] = $this->resolveScope($user);
        $normalizedYearFilter = $this->normalizeYearFilter($yearFilter);
        $baseQuery = $this->visibleInvoicesQuery($guardianIds, $childIds);
        $stats = $this->buildStats(clone $baseQuery, $currentYear);
        $paginator = $this->paginateInvoices(clone $baseQuery, $normalizedYearFilter, $currentYear);

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
            'info_text' => 'A számlák a befizetések feldolgozását követően jelennek meg. Amennyiben egy befizetéshez tartozó számlát nem talál, kérjük, forduljon az intézményhez.',
        ];
    }

    public function downloadInvoiceDocument(User $user, InstitutionInvoice $invoice): Response
    {
        $resolvedInvoice = $this->findVisibleInvoice($user, $invoice->id);

        if (filled($resolvedInvoice->invoice_pdf_path)) {
            if (! Storage::disk('public')->exists($resolvedInvoice->invoice_pdf_path)) {
                abort(404);
            }

            $downloadName = ($resolvedInvoice->invoice_number ?: 'szamla-'.$resolvedInvoice->id).'.pdf';

            return Storage::disk('public')->download($resolvedInvoice->invoice_pdf_path, $downloadName);
        }

        if (filled($resolvedInvoice->invoice_url)) {
            return redirect()->away($resolvedInvoice->invoice_url);
        }

        abort(404);
    }

    private function buildStats(Builder $query, int $currentYear): array
    {
        $invoices = $query->get();
        $issuedThisYear = $invoices->filter(function (InstitutionInvoice $invoice) use ($currentYear) {
            return $invoice->issue_date !== null
                && (int) $invoice->issue_date->year === $currentYear
                && $this->countsAsIssued($invoice);
        });
        $latestInvoice = $invoices
            ->filter(fn (InstitutionInvoice $invoice) => $invoice->issue_date !== null)
            ->sortByDesc(fn (InstitutionInvoice $invoice) => $invoice->issue_date?->timestamp ?? 0)
            ->first();
        $downloadableCount = $invoices
            ->filter(fn (InstitutionInvoice $invoice) => $this->hasDownloadableDocument($invoice))
            ->count();

        return [
            'total_count' => $invoices->count(),
            'total_count_label' => $invoices->count().' db',
            'current_year_gross' => (int) $issuedThisYear->sum('gross_amount'),
            'current_year_gross_label' => $this->formatForint((int) $issuedThisYear->sum('gross_amount')),
            'current_year_helper' => $currentYear.'-ban kiállítva',
            'latest_invoice_date_label' => $latestInvoice?->issue_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
            'latest_invoice_helper' => $latestInvoice?->invoice_number ?: 'Még nincs kiállított számla',
            'downloadable_count' => $downloadableCount,
            'downloadable_count_label' => $downloadableCount.' db',
        ];
    }

    private function paginateInvoices(Builder $query, string $yearFilter, int $currentYear): LengthAwarePaginator
    {
        $filteredQuery = $this->applyYearFilter($query, $yearFilter, $currentYear);

        $paginator = $filteredQuery
            ->orderByRaw("CASE WHEN institution_invoices.issue_date IS NULL THEN 0 ELSE 1 END ASC")
            ->orderByDesc('institution_invoices.issue_date')
            ->orderByDesc('institution_invoices.id')
            ->paginate(10)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (InstitutionInvoice $invoice) => $this->mapInvoiceRow($invoice))
        );

        return $paginator;
    }

    private function mapInvoiceRow(InstitutionInvoice $invoice): array
    {
        $childNames = collect([$invoice->child?->name])->filter()->values();
        $statement = $invoice->monthlyPaymentStatement;
        $viewUrl = filled($invoice->invoice_url) ? $invoice->invoice_url : null;
        $downloadable = $this->hasDownloadableDocument($invoice);
        $status = $this->parentStatusMeta($invoice);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?: 'Kiállításra vár',
            'issue_date_label' => $invoice->issue_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
            'fulfillment_date_label' => $invoice->fulfillment_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
            'due_date_label' => $invoice->due_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
            'period_label' => $statement !== null
                ? CarbonImmutable::create($statement->year, $statement->month, 1, 0, 0, 0, config('app.timezone'))
                    ->locale('hu')
                    ->isoFormat('YYYY. MMMM')
                : 'Nincs időszak',
            'child_summary' => $childNames->count() <= 1
                ? ($childNames->first() ?? 'Nincs kapcsolt gyermek')
                : $childNames->count().' gyermek',
            'child_names' => $childNames,
            'gross_amount_label' => $this->formatForint((int) $invoice->gross_amount),
            'payment_method_label' => InstitutionPayment::paymentMethodOptions()[$invoice->payment_method] ?? 'Nincs megadva',
            'status' => $status,
            'has_download' => $downloadable,
            'download_url' => $downloadable
                ? route('parent.invoices.download', ['invoice' => $invoice->id])
                : null,
            'has_view' => $viewUrl !== null,
            'view_url' => $viewUrl,
            'details' => [
                'invoice_number' => $invoice->invoice_number ?: 'Kiállításra vár',
                'issue_date_label' => $invoice->issue_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
                'fulfillment_date_label' => $invoice->fulfillment_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
                'due_date_label' => $invoice->due_date?->locale('hu')->isoFormat('YYYY. MMMM D.') ?? 'Nincs adat',
                'payment_method_label' => InstitutionPayment::paymentMethodOptions()[$invoice->payment_method] ?? 'Nincs megadva',
                'billing_name' => $invoice->customer_name,
                'billing_address' => trim(implode(' ', array_filter([
                    $invoice->billing_postcode,
                    $invoice->billing_city,
                    $invoice->billing_address,
                ]))),
                'child_names' => $childNames,
                'period_label' => $statement !== null
                    ? CarbonImmutable::create($statement->year, $statement->month, 1, 0, 0, 0, config('app.timezone'))
                        ->locale('hu')
                        ->isoFormat('YYYY. MMMM')
                    : 'Nincs időszak',
                'net_amount_label' => $this->formatForint((int) $invoice->net_amount),
                'vat_amount_label' => $this->formatForint((int) $invoice->vat_amount),
                'gross_amount_label' => $this->formatForint((int) $invoice->gross_amount),
                'provider_invoice_id' => $invoice->provider_invoice_id ?: 'Nincs megadva',
                'status_label' => $status['label'],
                'status_class' => $status['class'],
            ],
        ];
    }

    private function visibleInvoicesQuery(Collection $guardianIds, Collection $childIds): Builder
    {
        if ($guardianIds->isEmpty() && $childIds->isEmpty()) {
            return InstitutionInvoice::query()->whereRaw('1 = 0');
        }

        return InstitutionInvoice::query()
            ->where(function (Builder $query) use ($guardianIds, $childIds) {
                $query
                    ->when($guardianIds->isNotEmpty(), fn (Builder $guardiansQuery) => $guardiansQuery->orWhereIn('guardian_id', $guardianIds->all()))
                    ->when($childIds->isNotEmpty(), fn (Builder $childrenQuery) => $childrenQuery->orWhereIn('child_id', $childIds->all()));
            })
            ->with([
                'child',
                'guardian',
                'monthlyPaymentStatement',
            ]);
    }

    private function findVisibleInvoice(User $user, int $invoiceId): InstitutionInvoice
    {
        [$guardianIds, $childIds] = $this->resolveScope($user);

        return $this->visibleInvoicesQuery($guardianIds, $childIds)
            ->whereKey($invoiceId)
            ->firstOrFail();
    }

    private function resolveScope(User $user): array
    {
        $guardianIds = $user->guardians()
            ->where('active', true)
            ->pluck('guardians.id');

        $childIds = Child::query()
            ->whereHas('guardians', fn (Builder $query) => $query->whereIn('guardians.id', $guardianIds->all()))
            ->pluck('id');

        return [$guardianIds, $childIds];
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
            return $query->where(function (Builder $yearQuery) use ($currentYear) {
                $yearQuery
                    ->whereYear('issue_date', $currentYear)
                    ->orWhere(function (Builder $pendingQuery) use ($currentYear) {
                        $pendingQuery
                            ->whereNull('issue_date')
                            ->whereYear('created_at', $currentYear);
                    });
            });
        }

        if ($yearFilter === 'earlier') {
            return $query->where(function (Builder $yearQuery) use ($currentYear) {
                $yearQuery
                    ->whereYear('issue_date', '<', $currentYear)
                    ->orWhere(function (Builder $pendingQuery) use ($currentYear) {
                        $pendingQuery
                            ->whereNull('issue_date')
                            ->whereYear('created_at', '<', $currentYear);
                    });
            });
        }

        return $query;
    }

    private function countsAsIssued(InstitutionInvoice $invoice): bool
    {
        return in_array($invoice->status, [
            InstitutionInvoice::STATUS_ISSUED,
            InstitutionInvoice::STATUS_PAID,
            InstitutionInvoice::STATUS_OVERDUE,
        ], true);
    }

    private function hasDownloadableDocument(InstitutionInvoice $invoice): bool
    {
        return filled($invoice->invoice_pdf_path) || filled($invoice->invoice_url);
    }

    private function parentStatusMeta(InstitutionInvoice $invoice): array
    {
        return match ($invoice->status) {
            InstitutionInvoice::STATUS_DRAFT => ['label' => 'Kiállításra vár', 'class' => 'bg-warning text-dark'],
            InstitutionInvoice::STATUS_PENDING => ['label' => 'Feldolgozás alatt', 'class' => 'bg-primary'],
            InstitutionInvoice::STATUS_ISSUED,
            InstitutionInvoice::STATUS_PAID,
            InstitutionInvoice::STATUS_OVERDUE => ['label' => 'Kiállítva', 'class' => 'bg-success'],
            InstitutionInvoice::STATUS_CANCELLED,
            InstitutionInvoice::STATUS_VOIDED => ['label' => 'Sztornózva', 'class' => 'bg-secondary'],
            InstitutionInvoice::STATUS_FAILED => ['label' => 'Hiba', 'class' => 'bg-danger'],
            default => ['label' => 'Nincs megadva', 'class' => 'bg-light text-muted border'],
        };
    }

    private function formatForint(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }
}
