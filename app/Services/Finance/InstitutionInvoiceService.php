<?php

namespace App\Services\Finance;

use App\Models\BillingProfile;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\Providers\BillingoInvoiceProvider;
use App\Services\Finance\Providers\InvoiceProviderInterface;
use App\Services\Finance\Providers\InvoiceProviderPayload;
use App\Services\Finance\Providers\ManualInvoiceProvider;
use App\Services\Finance\Providers\SzamlazzHuInvoiceProvider;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstitutionInvoiceService
{
    public function __construct(
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper
    ) {}

    public function query(Institution $institution, array $filters = []): Builder
    {
        $payments = InstitutionPayment::query()
            ->selectRaw('monthly_payment_statement_id, SUM(amount) as completed_payments_total')
            ->where('institution_id', $institution->id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->whereNotNull('monthly_payment_statement_id')
            ->groupBy('monthly_payment_statement_id');

        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $effectiveStatusSql = $this->effectiveStatusSql($today);

        return InstitutionInvoice::query()
            ->with([
                'child',
                'guardian',
                'monthlyPaymentStatement',
                'creator',
                'originalInvoice',
                'cancellationInvoice',
            ])
            ->leftJoinSub($payments, 'completed_payments', function ($join) {
                $join->on('completed_payments.monthly_payment_statement_id', '=', 'institution_invoices.monthly_payment_statement_id');
            })
            ->select('institution_invoices.*')
            ->selectRaw('COALESCE(completed_payments.completed_payments_total, 0) as completed_payments_total')
            ->selectRaw("{$effectiveStatusSql} as effective_status")
            ->where('institution_invoices.institution_id', $institution->id)
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function (Builder $query) use ($search) {
                    $query->where('institution_invoices.invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('child', fn (Builder $childQuery) => $childQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('guardian', function (Builder $guardianQuery) use ($search) {
                            $guardianQuery->where('last_name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when(! empty($filters['month']), function (Builder $query) use ($filters) {
                [$year, $month] = explode('-', $filters['month']);

                $query->whereHas('monthlyPaymentStatement', function (Builder $statementQuery) use ($year, $month) {
                    $statementQuery->where('year', (int) $year)
                        ->where('month', (int) $month);
                });
            })
            ->when(! empty($filters['provider']), fn (Builder $query) => $query->where('institution_invoices.provider', $filters['provider']))
            ->when(! empty($filters['document_type']), fn (Builder $query) => $query->where('institution_invoices.document_type', $filters['document_type']))
            ->when(! empty($filters['status']), function (Builder $query) use ($filters, $today) {
                $query->whereRaw($this->effectiveStatusSql($today).' = ?', [$filters['status']]);
            })
            ->when(! empty($filters['payment_method']), fn (Builder $query) => $query->where('institution_invoices.payment_method', $filters['payment_method']))
            ->when(! empty($filters['date_from']), fn (Builder $query) => $query->whereDate('institution_invoices.issue_date', '>=', Carbon::parse($filters['date_from'])->toDateString()))
            ->when(! empty($filters['date_to']), fn (Builder $query) => $query->whereDate('institution_invoices.issue_date', '<=', Carbon::parse($filters['date_to'])->toDateString()))
            ->when(($filters['pdf_state'] ?? null) === 'missing', fn (Builder $query) => $query->whereNull('institution_invoices.invoice_pdf_path'))
            ->when(($filters['pdf_state'] ?? null) === 'error', fn (Builder $query) => $query->whereNotNull('institution_invoices.sync_error_message'));
    }

    public function summary(Builder $query): array
    {
        $base = clone $query;
        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString();
        $effectiveStatusSql = $this->effectiveStatusSql($today);

        return [
            'issued_count' => (clone $base)->count(),
            'gross_total' => (clone $base)->sum('institution_invoices.gross_amount'),
            // A sztornó (jóváíró) számla negatív gross_amount-ja a
            // gross_total-ban helyesen nullázza az eredeti számlát, de a
            // "kiegyenlítetlen" (unpaid) és "lejárt" (overdue) kategóriák
            // fogalmilag nem értelmezhetők egy jóváíró dokumentumra - az
            // sosem "kintlévőség", ezért itt kifejezetten kizárjuk a
            // document_type = cancellation sorokat (nem elég az
            // effective_status-ra hagyatkozni, mert annak "issued" értéke a
            // sztornó saját, változatlan állapota is lehet).
            'unpaid_total' => (clone $base)
                ->where('institution_invoices.document_type', '!=', InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION)
                ->whereRaw("{$effectiveStatusSql} in (?, ?, ?, ?)", [
                    InstitutionInvoice::STATUS_DRAFT,
                    InstitutionInvoice::STATUS_PENDING,
                    InstitutionInvoice::STATUS_ISSUED,
                    InstitutionInvoice::STATUS_OVERDUE,
                ])
                ->sum('institution_invoices.gross_amount'),
            'overdue_count' => (clone $base)
                ->where('institution_invoices.document_type', '!=', InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION)
                ->whereRaw("{$effectiveStatusSql} = ?", [InstitutionInvoice::STATUS_OVERDUE])
                ->count(),
        ];
    }

    public function paginate(Builder $query): LengthAwarePaginator
    {
        $paginator = $query
            ->orderByDesc('institution_invoices.issue_date')
            ->orderByDesc('institution_invoices.id')
            ->paginate(20)
            ->withQueryString();

        $items = $paginator->getCollection()->map(function (InstitutionInvoice $invoice) {
            $invoice->setAttribute('effective_status_meta', InstitutionInvoice::statusMeta($invoice->effective_status));
            $invoice->setAttribute('provider_meta', InstitutionInvoice::providerMeta($invoice->provider));
            $invoice->setAttribute('document_type_label', InstitutionInvoice::documentTypeOptions()[$invoice->document_type] ?? $invoice->document_type);

            return $invoice;
        });

        $paginator->setCollection($items);

        return $paginator;
    }

    public function settings(Institution $institution): InstitutionSetting
    {
        return InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
    }

    public function searchableStatements(Institution $institution, string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return MonthlyPaymentStatement::query()
            ->with('child')
            ->where('institution_id', $institution->id)
            ->where('total_payable', '>', 0)
            ->whereDoesntHave('invoice')
            ->where(function (Builder $query) use ($term) {
                $query->whereHas('child', function (Builder $childQuery) use ($term) {
                    $childQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('educational_identifier', 'like', "%{$term}%");
                })->orWhereRaw("CONCAT(year, '-', LPAD(month, 2, '0')) like ?", ["%{$term}%"]);
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('child_id')
            ->limit(20)
            ->get();
    }

    public function paymentInvoiceAction(Institution $institution, InstitutionPayment $payment): ?array
    {
        abort_if($payment->institution_id !== $institution->id, 403);

        $payment->loadMissing(['child', 'guardian', 'monthlyPaymentStatement.child', 'invoice']);

        if (! $this->isCashPaymentInvoiceSource($payment)) {
            return null;
        }

        $invoice = $this->existingInvoiceForStatement($institution, $payment->monthly_payment_statement_id);

        if ($invoice) {
            return [
                'label' => 'Számla megnyitása',
                'url' => route('dashboard.institution.finance.invoices.show', $invoice),
                'class' => 'btn btn-primary btn-lg',
                'icon' => 'fa-solid fa-file-invoice',
                'helper' => $invoice->status === InstitutionInvoice::STATUS_FAILED
                    ? 'Ehhez a készpénzes befizetéshez már tartozik egy sikertelen számlapróbálkozás. A meglévő számlaoldalon a jelenlegi szabályok szerint újrapróbálhatod.'
                    : 'Ehhez a készpénzes befizetéshez már tartozik számla. Új helyett a meglévő rekord nyitható meg.',
            ];
        }

        return [
            'label' => 'Számla készítése',
            'url' => route('dashboard.institution.finance.invoices.create', ['payment_id' => $payment->id]),
            'class' => 'btn btn-success btn-lg',
            'icon' => 'fa-solid fa-file-circle-plus',
            'helper' => 'A befizetés adataiból előkészített számlaűrlap nyílik meg. A számla nem készül el automatikusan.',
        ];
    }

    public function invoiceDraftFromPayment(Institution $institution, InstitutionPayment $payment, string $provider): array
    {
        abort_if($payment->institution_id !== $institution->id, 403);

        $payment->loadMissing(['child', 'guardian', 'monthlyPaymentStatement.child', 'invoice']);

        if (! $this->isCashPaymentInvoiceSource($payment)) {
            abort(422, 'Csak sikeres, havi kötelezettséghez kapcsolt készpénzes befizetésből készíthető elő számla.');
        }

        $existingInvoice = $this->existingInvoiceForStatement($institution, $payment->monthly_payment_statement_id);

        return [
            'payment' => $payment,
            'existing_invoice' => $existingInvoice,
            'preview' => $existingInvoice
                ? null
                : $this->buildPreview($institution, $payment->monthlyPaymentStatement, $provider, $payment),
        ];
    }

    public function preview(Institution $institution, MonthlyPaymentStatement $statement, string $provider): array
    {
        return $this->buildPreview($institution, $statement, $provider);
    }

    public function store(Institution $institution, User $user, array $data): InstitutionInvoice
    {
        $settings = $this->settings($institution);
        $provider = $data['provider'];

        return DB::transaction(function () use ($institution, $user, $data, $settings, $provider) {
            $sourcePayment = null;

            $statement = MonthlyPaymentStatement::query()
                ->where('institution_id', $institution->id)
                ->whereKey($data['monthly_payment_statement_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->total_payable <= 0) {
                $this->throwValidation(['monthly_payment_statement_id' => '0 Ft vagy negatív összegű kötelezettségre nem készülhet számla.']);
            }

            $existingInvoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->where('monthly_payment_statement_id', $statement->id)
                ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL)
                ->lockForUpdate()
                ->exists();

            if ($existingInvoice) {
                $this->throwValidation(['monthly_payment_statement_id' => 'Ehhez a fizetési kötelezettséghez már tartozik számla.']);
            }

            $providerState = $this->providerState($institution, $settings, $provider);
            if (! $providerState['ready']) {
                $this->throwValidation(['provider' => implode(' ', $providerState['messages'])]);
            }

            $billing = $this->billingSnapshot($institution, $statement);
            if (! empty($billing['missing_fields'])) {
                $this->throwValidation([
                    'monthly_payment_statement_id' => 'A számlázási adatok hiányosak: '.implode(', ', $billing['missing_fields']).'.',
                ]);
            }

            if (! empty($data['source_payment_id'])) {
                $sourcePayment = InstitutionPayment::query()
                    ->where('institution_id', $institution->id)
                    ->whereKey($data['source_payment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $this->isCashPaymentInvoiceSource($sourcePayment)) {
                    $this->throwValidation([
                        'source_payment_id' => 'Csak teljesült, havi kötelezettséghez kapcsolt készpénzes befizetésből készíthető elő számla.',
                    ]);
                }

                if ((int) $sourcePayment->monthly_payment_statement_id !== (int) $statement->id) {
                    $this->throwValidation([
                        'source_payment_id' => 'A kiválasztott befizetés nem ehhez a havi kötelezettséghez tartozik.',
                    ]);
                }
            }

            $grossAmount = (int) $statement->total_payable;
            $invoice = new InstitutionInvoice;
            $invoice->fill([
                'child_id' => $statement->child_id,
                'guardian_id' => $billing['guardian_id'],
                'monthly_payment_statement_id' => $statement->id,
                'institution_payment_id' => $sourcePayment?->id,
                'provider' => $provider,
                'document_type' => InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL,
                'status' => InstitutionInvoice::STATUS_PENDING,
                'issue_date' => null,
                'due_date' => Carbon::parse($data['due_date'])->toDateString(),
                'fulfillment_date' => Carbon::parse($data['fulfillment_date'])->toDateString(),
                'net_amount' => $grossAmount,
                'vat_amount' => 0,
                'gross_amount' => $grossAmount,
                'currency' => 'HUF',
                'payment_method' => $sourcePayment ? InstitutionPayment::METHOD_CASH : $data['payment_method'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?: null,
                'customer_tax_number' => $data['customer_tax_number'] ?: null,
                'billing_postcode' => $data['billing_postcode'],
                'billing_city' => $data['billing_city'],
                'billing_address' => $data['billing_address'],
                'note' => $data['note'] ?: null,
            ]);
            $invoice->institution_id = $institution->id;
            $invoice->created_by = $user->id;

            $result = $this->provider($provider)->createInvoice(
                new InvoiceProviderPayload($institution, $statement, $invoice)
            );

            $invoice->status = $result->status;
            $invoice->provider_invoice_id = $result->providerInvoiceId;
            $invoice->invoice_number = $result->invoiceNumber;
            $invoice->issue_date = $result->issueDate?->toDateString();
            $invoice->fulfillment_date = $result->fulfillmentDate?->toDateString() ?? $invoice->fulfillment_date;
            $invoice->invoice_url = $result->invoiceUrl;
            $invoice->invoice_pdf_path = $result->invoicePdfPath;
            $invoice->pdf_disk = $result->invoicePdfPath ? 'local' : null;
            $invoice->pdf_downloaded_at = $result->invoicePdfPath ? now() : null;
            $invoice->last_synced_at = now();
            $invoice->sync_error_message = null;
            $invoice->error_message = $result->errorMessage;
            $invoice->save();

            return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'institutionPayment']);
        });
    }

    public function issueAutomaticInvoiceForPayment(Institution $institution, User $user, InstitutionPayment $payment): ?InstitutionInvoice
    {
        abort_if($payment->institution_id !== $institution->id, 403);

        $settings = $this->settings($institution);

        if (! $settings->invoicing_enabled || $settings->invoicing_provider !== InstitutionSetting::INVOICING_PROVIDER_BILLINGO) {
            return null;
        }

        $payment->loadMissing(['monthlyPaymentStatement.child']);

        $preview = $this->invoiceDraftFromPayment(
            institution: $institution,
            payment: $payment,
            provider: InstitutionInvoice::PROVIDER_BILLINGO,
        );

        if (($preview['existing_invoice'] ?? null) instanceof InstitutionInvoice) {
            return $preview['existing_invoice'];
        }

        $statementPreview = $preview['preview']['statement'] ?? null;

        if (! is_array($statementPreview)) {
            return null;
        }

        return $this->store($institution, $user, [
            'monthly_payment_statement_id' => $payment->monthly_payment_statement_id,
            'source_payment_id' => $payment->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'due_date' => $statementPreview['due_date'],
            'fulfillment_date' => $statementPreview['fulfillment_date'],
            'payment_method' => $statementPreview['payment_method'],
            'customer_name' => $statementPreview['customer_name'],
            'customer_email' => $statementPreview['customer_email'],
            'customer_tax_number' => $statementPreview['customer_tax_number'],
            'billing_postcode' => $statementPreview['billing_postcode'],
            'billing_city' => $statementPreview['billing_city'],
            'billing_address' => $statementPreview['billing_address'],
            'note' => 'Automatikus számlakiállítás sikeres CIB bankkártyás fizetés után.',
        ]);
    }

    /**
     * Kiállított számla sztornózása. Csak "issued" (a szolgáltatónál is
     * ténylegesen kiállított) bizonylat sztornózható - draft/pending/failed
     * állapotú "számlához" nincs mit sztornózni a szolgáltatónál, egy már
     * cancelled/voided számla pedig már sztornózva van. A sztornózás a
     * szolgáltatónál kiállított sztornó bizonylat adatait a
     * cancellation_* mezőkbe menti, az eredeti bizonylat adatait nem
     * írja felül (ld. InvoiceProviderInterface::cancelInvoice()).
     */
    public function cancel(Institution $institution, User $user, InstitutionInvoice $invoice, ?string $reason = null): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        return DB::transaction(function () use ($institution, $user, $invoice, $reason) {
            $invoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->isCancellable()) {
                $this->throwValidation(['invoice' => 'Csak kiállított számla sztornózható.']);
            }

            $statement = $invoice->monthlyPaymentStatement()->first();
            if (! $statement) {
                $this->throwValidation(['invoice' => 'A számlához nem található fizetési kötelezettség, sztornózás nem lehetséges.']);
            }

            $result = $this->provider($invoice->provider)->cancelInvoice(
                new InvoiceProviderPayload($institution, $statement, $invoice),
                $reason
            );

            if ($result->status !== InstitutionInvoice::STATUS_VOIDED) {
                $this->throwValidation(['invoice' => $result->errorMessage ?: 'A számla sztornózása sikertelen.']);
            }

            $invoice->status = InstitutionInvoice::STATUS_VOIDED;
            $invoice->cancelled_at = now();
            $invoice->cancelled_by = $user->id;
            $invoice->cancellation_reason = $reason ?: null;
            $invoice->cancellation_provider_document_id = $result->providerInvoiceId;
            $invoice->cancellation_invoice_number = $result->invoiceNumber;
            $invoice->cancellation_pdf_path = $result->invoicePdfPath;
            $invoice->save();

            $this->reverseLinkedPaymentAfterCancellation($invoice);

            $cancellationInvoice = InstitutionInvoice::query()->firstOrNew([
                'institution_id' => $institution->id,
                'provider' => $invoice->provider,
                'provider_invoice_id' => $result->providerInvoiceId,
            ]);

            $cancellationInvoice->fill([
                'child_id' => $invoice->child_id,
                'guardian_id' => $invoice->guardian_id,
                'monthly_payment_statement_id' => $invoice->monthly_payment_statement_id,
                'original_invoice_id' => $invoice->id,
                'institution_payment_id' => null,
                'provider' => $invoice->provider,
                'document_type' => InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
                'provider_invoice_id' => $result->providerInvoiceId,
                'provider_original_invoice_id' => $invoice->provider_invoice_id,
                'invoice_number' => $result->invoiceNumber,
                'status' => InstitutionInvoice::STATUS_ISSUED,
                'issue_date' => $result->issueDate?->toDateString() ?? now()->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'fulfillment_date' => $invoice->fulfillment_date?->toDateString(),
                // A sztornó (jóváíró) számla az eredeti számla összegeinek
                // NEGATÍVJÁT rögzíti, hogy az összes olyan összesítés, ami
                // gross_amount-ot (vagy net/vat_amount-ot) összegez az
                // intézmény/időszak számláin (pl. summary(), a szülői
                // portál éves összesítője, a pénzügyi export), a sztornózott
                // számlát automatikusan nullára nettósítsa az eredeti
                // számlával szemben, ahelyett hogy tévesen duplán,
                // pozitívként adná hozzá az összeghez.
                'net_amount' => -$invoice->net_amount,
                'vat_amount' => -$invoice->vat_amount,
                'gross_amount' => -$invoice->gross_amount,
                'currency' => $invoice->currency,
                'payment_method' => $invoice->payment_method,
                'customer_name' => $invoice->customer_name,
                'customer_email' => $invoice->customer_email,
                'customer_tax_number' => $invoice->customer_tax_number,
                'billing_postcode' => $invoice->billing_postcode,
                'billing_city' => $invoice->billing_city,
                'billing_address' => $invoice->billing_address,
                'invoice_url' => $result->invoiceUrl,
                'invoice_pdf_path' => $result->invoicePdfPath,
                'pdf_disk' => $result->invoicePdfPath ? 'local' : null,
                'pdf_downloaded_at' => $result->invoicePdfPath ? now() : null,
                'note' => $reason ?: null,
                'sync_error_message' => null,
                'last_synced_at' => now(),
            ]);
            $cancellationInvoice->created_by = $cancellationInvoice->created_by ?: $user->id;
            $cancellationInvoice->save();

            return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'cancelledBy']);
        });
    }

    public function reloadInvoicePdf(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        return $this->reloadBillingoPdf($institution, $invoice);
    }

    public function reloadCancellationPdf(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        $cancellationInvoice = $invoice->isCancellationDocument()
            ? $invoice
            : ($invoice->cancellationInvoice()->first() ?? $this->materializeLegacyCancellationInvoice($invoice));

        if (! $cancellationInvoice) {
            $this->throwValidation(['invoice' => 'Ehhez a számlához nem tartozik külön sztornó bizonylatrekord.']);
        }

        return $this->reloadBillingoPdf($institution, $cancellationInvoice);
    }

    /**
     * Egy sikertelen ("failed") számlapróbálkozás helyi törlése. Mivel a
     * szolgáltatónál ilyenkor sosem jött létre valódi bizonylat, nincs mit
     * sztornózni - a törlés csak a helyi rekordot szünteti meg, hogy a
     * monthly_payment_statement_id oszlopon lévő unique megkötés ne
     * akadályozza tovább az adott fizetési kötelezettséghez tartozó új
     * számlázási próbálkozást.
     */
    public function delete(Institution $institution, InstitutionInvoice $invoice): void
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        DB::transaction(function () use ($institution, $invoice) {
            $invoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invoice->isDeletable()) {
                $this->throwValidation(['invoice' => 'Csak sikertelen próbálkozás törölhető helyben - kiállított számlát sztornózni kell.']);
            }

            $invoice->delete();
        });
    }

    public function findForInstitution(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        $invoice->load([
            'child',
            'guardian',
            'creator',
            'cancelledBy',
            'institutionPayment.recordedBy',
            'monthlyPaymentStatement.child',
            'originalInvoice',
            'cancellationInvoice',
        ]);
        $invoice->setAttribute('provider_meta', InstitutionInvoice::providerMeta($invoice->provider));
        $invoice->setAttribute('effective_status', $this->effectiveStatusForInvoice($invoice));
        $invoice->setAttribute('effective_status_meta', InstitutionInvoice::statusMeta($invoice->effective_status));
        $invoice->setAttribute('completed_payments_total', $this->completedPaymentsTotal($institution, $invoice));

        return $invoice;
    }

    public function relatedPayments(Institution $institution, InstitutionInvoice $invoice): Collection
    {
        return InstitutionPayment::query()
            ->with(['guardian', 'recordedBy'])
            ->where('institution_id', $institution->id)
            ->where('monthly_payment_statement_id', $invoice->monthly_payment_statement_id)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();
    }

    public function hasUsableInvoicePdf(InstitutionInvoice $invoice): bool
    {
        return $this->hasUsableLocalPdfPath($invoice->invoice_pdf_path);
    }

    public function hasUsableCancellationPdf(InstitutionInvoice $invoice): bool
    {
        $cancellationInvoice = $invoice->isCancellationDocument()
            ? $invoice
            : ($invoice->cancellationInvoice()->first() ?? $this->materializeLegacyCancellationInvoice($invoice, false));

        return $cancellationInvoice
            ? $this->hasUsableLocalPdfPath($cancellationInvoice->invoice_pdf_path)
            : $this->hasUsableLocalPdfPath($invoice->cancellation_pdf_path);
    }

    private function provider(string $provider): InvoiceProviderInterface
    {
        return match ($provider) {
            InstitutionInvoice::PROVIDER_BILLINGO => app(BillingoInvoiceProvider::class),
            InstitutionInvoice::PROVIDER_SZAMLAZZ_HU => app(SzamlazzHuInvoiceProvider::class),
            InstitutionInvoice::PROVIDER_MANUAL => app(ManualInvoiceProvider::class),
            default => throw new \InvalidArgumentException('Unsupported invoice provider: '.$provider),
        };
    }

    private function reloadBillingoPdf(Institution $institution, InstitutionInvoice $invoice): InstitutionInvoice
    {
        abort_if($invoice->institution_id !== $institution->id, 403);

        return DB::transaction(function () use ($institution, $invoice) {
            $invoice = InstitutionInvoice::query()
                ->where('institution_id', $institution->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $statement = $invoice->monthlyPaymentStatement()->first();
            if (! $statement) {
                $this->throwValidation(['invoice' => 'A számlához nem található fizetési kötelezettség, ezért a PDF nem tölthető újra.']);
            }

            if ($invoice->provider !== InstitutionInvoice::PROVIDER_BILLINGO) {
                $this->throwValidation(['invoice' => 'PDF újratöltés jelenleg csak Billingo-s bizonylatnál használható.']);
            }

            if (! in_array($invoice->status, [InstitutionInvoice::STATUS_ISSUED, InstitutionInvoice::STATUS_VOIDED], true)) {
                $this->throwValidation(['invoice' => 'A számla PDF-je csak már kiállított Billingo-s bizonylatnál tölthető újra.']);
            }

            if (! filled($invoice->provider_invoice_id)) {
                $this->throwValidation(['invoice' => 'A számlához nem tartozik Billingo bizonylatazonosító, ezért a PDF nem tölthető újra.']);
            }

            if ($this->hasUsableInvoicePdf($invoice)) {
                return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'cancelledBy']);
            }

            $result = app(BillingoInvoiceProvider::class)->downloadExistingDocumentPdf(
                new InvoiceProviderPayload($institution, $statement, $invoice)
            );

            if (! filled($result->invoicePdfPath)) {
                $this->throwValidation(['invoice' => $result->errorMessage ?: 'A számla PDF-je nem tölthető újra a Billingótól.']);
            }

            $invoice->invoice_pdf_path = $result->invoicePdfPath;
            $invoice->pdf_disk = 'local';
            $invoice->pdf_downloaded_at = now();
            $invoice->last_synced_at = now();
            $invoice->sync_error_message = null;
            $invoice->save();

            return $invoice->fresh(['child', 'guardian', 'monthlyPaymentStatement', 'creator', 'cancelledBy']);
        });
    }

    private function providerState(Institution $institution, InstitutionSetting $settings, string $provider): array
    {
        if (! $settings->invoicing_enabled) {
            return [
                'ready' => false,
                'messages' => ['A számlázás nincs engedélyezve ennél az intézménynél.'],
            ];
        }

        $missing = [];

        if ($provider === InstitutionInvoice::PROVIDER_BILLINGO) {
            if (! $settings->hasBillingoApiKey()) {
                $missing[] = 'hiányzik a Billingo API-kulcs';
            }

            if (! filled($settings->billingo_document_block_id)) {
                $missing[] = 'hiányzik a Billingo bizonylattömb azonosító';
            }
        }

        if ($provider === InstitutionInvoice::PROVIDER_SZAMLAZZ_HU) {
            if (! $settings->hasSzamlazzHuAgentKey()) {
                $missing[] = 'hiányzik a Számlázz.hu agent kulcs';
            }

            if (! filled($settings->szamlazz_hu_invoice_prefix)) {
                $missing[] = 'hiányzik a Számlázz.hu számla előtag';
            }
        }

        return [
            'ready' => empty($missing),
            'messages' => empty($missing) ? [] : ['A kiválasztott szolgáltató beállítása hiányos: '.implode(', ', $missing).'.'],
        ];
    }

    private function billingSnapshot(Institution $institution, MonthlyPaymentStatement $statement): array
    {
        $profile = BillingProfile::query()
            ->select('billing_profiles.*')
            ->join('billing_profile_child', 'billing_profile_child.billing_profile_id', '=', 'billing_profiles.id')
            ->where('billing_profiles.institution_id', $institution->id)
            ->where('billing_profile_child.child_id', $statement->child_id)
            ->where('billing_profiles.active', true)
            ->orderByDesc('billing_profile_child.is_primary')
            ->orderByDesc('billing_profiles.id')
            ->first();

        $guardian = null;
        if ($profile?->guardian_id) {
            $guardian = $profile->guardian()->first();
        }

        if (! $guardian) {
            $guardian = $statement->child()
                ->first()
                ?->guardians()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->first();
        }

        $customerName = trim((string) ($profile->billing_name ?? $guardian?->full_name ?? ''));
        $customerEmail = trim((string) ($profile->email ?? $guardian?->email ?? ''));
        $customerTaxNumber = trim((string) ($profile->tax_number ?? ''));
        $postcode = trim((string) ($profile->postal_code ?? ''));
        $city = trim((string) ($profile->city ?? ''));
        $address = trim((string) ($profile->address ?? ''));

        $missingFields = [];
        if ($customerName === '') {
            $missingFields[] = 'vevő neve';
        }
        if ($postcode === '') {
            $missingFields[] = 'irányítószám';
        }
        if ($city === '') {
            $missingFields[] = 'város';
        }
        if ($address === '') {
            $missingFields[] = 'cím';
        }

        return [
            'guardian_id' => $guardian?->id,
            'guardian_name' => $guardian?->full_name ?: 'Nincs kapcsolt gondviselő',
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_tax_number' => $customerTaxNumber,
            'billing_postcode' => $postcode,
            'billing_city' => $city,
            'billing_address' => $address,
            'missing_fields' => $missingFields,
        ];
    }

    private function defaultDueDate(
        Institution $institution,
        InstitutionSetting $settings,
        string $provider,
        MonthlyPaymentStatement $statement,
        ?InstitutionPayment $sourcePayment = null
    ): Carbon {
        if ($sourcePayment && $this->isCashPaymentInvoiceSource($sourcePayment)) {
            return $sourcePayment->paid_at?->copy()->startOfDay()
                ?? now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay();
        }

        $today = now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay();
        $providerDueDays = match ($provider) {
            InstitutionInvoice::PROVIDER_BILLINGO => (int) $settings->billingo_due_days,
            InstitutionInvoice::PROVIDER_SZAMLAZZ_HU => (int) $settings->szamlazz_hu_due_days,
            default => 0,
        };

        if ($providerDueDays > 0) {
            return $today->copy()->addDays($providerDueDays);
        }

        $settingDueDay = (int) $settings->payment_due_day;
        if ($settingDueDay > 0) {
            $statementDue = Carbon::create($statement->year, $statement->month, min(28, $settingDueDay), 0, 0, 0, $today->timezone);

            return $statementDue->lt($today) ? $today : $statementDue;
        }

        $institutionDueDays = (int) $institution->billing_payment_due_days;
        if ($institutionDueDays > 0) {
            return $today->copy()->addDays($institutionDueDays);
        }

        return $today->copy()->addDays(8);
    }

    private function defaultFulfillmentDate(MonthlyPaymentStatement $statement): Carbon
    {
        return Carbon::create($statement->year, $statement->month, 1, 0, 0, 0, config('digifood.business_timezone', 'Europe/Budapest'))
            ->endOfMonth()
            ->startOfDay();
    }

    private function buildPreview(
        Institution $institution,
        MonthlyPaymentStatement $statement,
        string $provider,
        ?InstitutionPayment $sourcePayment = null
    ): array {
        abort_if($statement->institution_id !== $institution->id, 403);

        $statement->loadMissing('child');
        $settings = $this->settings($institution);
        $providerState = $this->providerState($institution, $settings, $provider);
        $billing = $this->billingSnapshot($institution, $statement);
        $existingInvoice = $this->existingInvoiceForStatement($institution, $statement->id);
        $periods = $this->periodHelper->fromStatement($statement);
        $dueDate = $this->defaultDueDate($institution, $settings, $provider, $statement, $sourcePayment);
        $fulfillmentDate = $sourcePayment?->paid_at?->copy()->startOfDay() ?? $this->defaultFulfillmentDate($statement);

        $errors = [];

        if ($statement->total_payable <= 0) {
            $errors[] = '0 Ft vagy negatív összegű kötelezettségre nem készülhet számla.';
        }

        if ($existingInvoice) {
            $errors[] = 'Ehhez a fizetési kötelezettséghez már tartozik számla.';
        }

        if (! $providerState['ready']) {
            $errors = array_merge($errors, $providerState['messages']);
        }

        if (! empty($billing['missing_fields'])) {
            $errors[] = 'A számlázási adatok hiányosak: '.implode(', ', $billing['missing_fields']).'.';
        }

        return [
            'available' => empty($errors),
            'errors' => $errors,
            'settings_url' => route('dashboard.institution.settings.invoicing.edit'),
            'statement' => [
                ...$periods,
                'id' => $statement->id,
                'child_name' => $statement->child?->name,
                'guardian_name' => $billing['guardian_name'],
                'month_label' => $periods['payment_period_label'],
                'gross_amount' => (int) $statement->total_payable,
                'due_date' => $dueDate->toDateString(),
                'fulfillment_date' => $fulfillmentDate->toDateString(),
                'issue_date' => now(config('digifood.business_timezone', 'Europe/Budapest'))->toDateString(),
                'currency' => 'HUF',
                'item_name' => 'Étkezési térítési díj',
                'customer_name' => $billing['customer_name'],
                'customer_email' => $billing['customer_email'],
                'customer_tax_number' => $billing['customer_tax_number'],
                'billing_postcode' => $billing['billing_postcode'],
                'billing_city' => $billing['billing_city'],
                'billing_address' => $billing['billing_address'],
                'payment_method' => $sourcePayment ? InstitutionPayment::METHOD_CASH : $this->defaultPaymentMethod($settings, $provider),
                'guardian_id' => $billing['guardian_id'],
                'source_payment_id' => $sourcePayment?->id,
                'source_payment_reference' => $sourcePayment?->reference,
                'source_payment_amount' => $sourcePayment?->amount,
                'source_payment_paid_at' => $sourcePayment?->paid_at?->format('Y.m.d. H:i'),
            ],
        ];
    }

    private function existingInvoiceForStatement(Institution $institution, ?int $statementId): ?InstitutionInvoice
    {
        if (! $statementId) {
            return null;
        }

        return InstitutionInvoice::query()
            ->where('institution_id', $institution->id)
            ->where('monthly_payment_statement_id', $statementId)
            ->where('document_type', InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL)
            ->first();
    }

    private function isCashPaymentInvoiceSource(InstitutionPayment $payment): bool
    {
        $statement = $payment->relationLoaded('monthlyPaymentStatement')
            ? $payment->monthlyPaymentStatement
            : $payment->monthlyPaymentStatement()->first();

        return $payment->status === InstitutionPayment::STATUS_COMPLETED
            && $payment->payment_method === InstitutionPayment::METHOD_CASH
            && filled($payment->monthly_payment_statement_id)
            && $statement !== null;
    }

    private function hasUsableLocalPdfPath(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return false;
        }

        $content = (string) $disk->get($path);

        return $content !== '' && str_starts_with($content, '%PDF-');
    }

    private function defaultPaymentMethod(InstitutionSetting $settings, string $provider): ?string
    {
        $method = match ($provider) {
            InstitutionInvoice::PROVIDER_BILLINGO => $settings->billingo_default_payment_method,
            InstitutionInvoice::PROVIDER_SZAMLAZZ_HU => $settings->szamlazz_hu_default_payment_method,
            default => null,
        };

        return filled($method) ? (string) $method : null;
    }

    private function effectiveStatusSql(string $today): string
    {
        // A sztornó (jóváíró) számla saját, negatív gross_amount-tal
        // rendelkezik, ezért a befizetés-alapú "paid"/"overdue" számítás
        // rá nem értelmezhető (a COALESCE(...) >= negatív_összeg
        // feltétel gyakorlatilag mindig igaz lenne) - a sztornó dokumentum
        // mindig a saját (tárolt) állapotát mutassa.
        return "CASE
            WHEN institution_invoices.document_type = '".InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION."' THEN institution_invoices.status
            WHEN institution_invoices.status IN ('draft', 'pending', 'failed', 'cancelled', 'voided') THEN institution_invoices.status
            WHEN COALESCE(completed_payments.completed_payments_total, 0) >= institution_invoices.gross_amount THEN 'paid'
            WHEN institution_invoices.due_date < '{$today}' THEN 'overdue'
            ELSE 'issued'
        END";
    }

    private function effectiveStatusForInvoice(InstitutionInvoice $invoice): string
    {
        // Lásd az effectiveStatusSql() megjegyzését: a sztornó dokumentum
        // negatív összege miatt a befizetés-alapú "paid"/"overdue" számítás
        // rá nem alkalmazható, mindig a saját állapotát mutatjuk.
        if ($invoice->isCancellationDocument()) {
            return $invoice->status;
        }

        $completedTotal = $this->completedPaymentsTotal($invoice->institution, $invoice);

        if (in_array($invoice->status, [
            InstitutionInvoice::STATUS_DRAFT,
            InstitutionInvoice::STATUS_PENDING,
            InstitutionInvoice::STATUS_FAILED,
            InstitutionInvoice::STATUS_CANCELLED,
            InstitutionInvoice::STATUS_VOIDED,
        ], true)) {
            return $invoice->status;
        }

        if ($completedTotal >= $invoice->gross_amount) {
            return InstitutionInvoice::STATUS_PAID;
        }

        if ($invoice->due_date && $invoice->due_date->lt(now(config('digifood.business_timezone', 'Europe/Budapest'))->startOfDay())) {
            return InstitutionInvoice::STATUS_OVERDUE;
        }

        return InstitutionInvoice::STATUS_ISSUED;
    }

    private function completedPaymentsTotal(Institution $institution, InstitutionInvoice $invoice): int
    {
        return (int) InstitutionPayment::query()
            ->where('institution_id', $institution->id)
            ->where('monthly_payment_statement_id', $invoice->monthly_payment_statement_id)
            ->where('status', InstitutionPayment::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Egy kiállított számla sztornózásakor a hozzá tartozó, már teljesült
     * (completed) befizetést "törölve" (cancelled) állapotba állítjuk át.
     * Enélkül a szülői havi elszámolás (ld.
     * ParentMonthlySettlementService::buildChildCard() - fennmaradó összeg =
     * total_payable - SUM(completed InstitutionPayment)) a sztornózás UTÁN
     * is "kifizetettként" látná a hónapot, hiszen a korábbi befizetés
     * összege továbbra is levonásra kerülne - a szülő emiatt nem tudna újra
     * fizetni, miközben az admin oldalon a hónap ténylegesen újra
     * fizetendőnek számít.
     *
     * Csak akkor nyúlunk automatikusan a befizetéshez, ha az EGYÉRTELMŰEN
     * beazonosítható:
     *  - elsődlegesen a számla institution_payment_id mezőjén keresztül,
     *  - ha az nincs kitöltve (a mező a gyakorlatban nem mindig van
     *    kitöltve minden számlánál), csak akkor, ha a kimutatáshoz
     *    (monthly_payment_statement_id) PONTOSAN EGY teljesült befizetés
     *    tartozik.
     * Minden más esetben (nincs egyértelmű találat, vagy több teljesült
     * befizetés is van ugyanahhoz a kimutatáshoz) a befizetést szándékosan
     * NEM módosítjuk automatikusan - ilyenkor az intézmény
     * adminisztrátorának kézzel, a Befizetések felületen kell rendeznie,
     * hogy elkerüljük egy rossz befizetés véletlen "törlését".
     */
    public function reverseLinkedPaymentAfterCancellation(InstitutionInvoice $invoice): void
    {
        $payment = $invoice->institution_payment_id
            ? InstitutionPayment::query()
                ->where('id', $invoice->institution_payment_id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->first()
            : null;

        if (! $payment && $invoice->monthly_payment_statement_id) {
            $completedPayments = InstitutionPayment::query()
                ->where('monthly_payment_statement_id', $invoice->monthly_payment_statement_id)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->get();

            if ($completedPayments->count() === 1) {
                $payment = $completedPayments->first();
            }
        }

        if (! $payment) {
            return;
        }

        $payment->status = InstitutionPayment::STATUS_CANCELLED;
        $payment->note = trim(($payment->note ? $payment->note."\n" : '').sprintf(
            'Automatikusan törölve a(z) %s számla sztornózása miatt (%s).',
            $invoice->invoice_number ?: '#'.$invoice->id,
            now()->format('Y.m.d. H:i')
        ));
        $payment->save();
    }

    private function throwValidation(array $errors): void
    {
        throw new HttpResponseException(
            back()
                ->withInput()
                ->withErrors($errors)
        );
    }

    private function materializeLegacyCancellationInvoice(
        InstitutionInvoice $invoice,
        bool $persist = true
    ): ?InstitutionInvoice {
        if (! $invoice->cancellation_provider_document_id || ! $invoice->cancellation_invoice_number) {
            return null;
        }

        if (! $persist) {
            $legacy = $invoice->replicate([
                'provider_invoice_id',
                'invoice_number',
                'invoice_pdf_path',
                'status',
                'document_type',
            ]);
            $legacy->provider_invoice_id = $invoice->cancellation_provider_document_id;
            $legacy->invoice_number = $invoice->cancellation_invoice_number;
            $legacy->invoice_pdf_path = $invoice->cancellation_pdf_path;
            $legacy->document_type = InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION;

            return $legacy;
        }

        return DB::transaction(function () use ($invoice) {
            $existing = InstitutionInvoice::query()
                ->where('institution_id', $invoice->institution_id)
                ->where('provider', $invoice->provider)
                ->where('provider_invoice_id', $invoice->cancellation_provider_document_id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $created = new InstitutionInvoice;
            $created->fill([
                'institution_id' => $invoice->institution_id,
                'child_id' => $invoice->child_id,
                'guardian_id' => $invoice->guardian_id,
                'monthly_payment_statement_id' => $invoice->monthly_payment_statement_id,
                'original_invoice_id' => $invoice->id,
                'institution_payment_id' => null,
                'provider' => $invoice->provider,
                'document_type' => InstitutionInvoice::DOCUMENT_TYPE_CANCELLATION,
                'provider_invoice_id' => $invoice->cancellation_provider_document_id,
                'provider_original_invoice_id' => $invoice->provider_invoice_id,
                'invoice_number' => $invoice->cancellation_invoice_number,
                'status' => InstitutionInvoice::STATUS_ISSUED,
                'issue_date' => $invoice->cancelled_at?->toDateString() ?: $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'fulfillment_date' => $invoice->fulfillment_date?->toDateString(),
                // Lásd a fenti megjegyzést a cancel() metódusban: a
                // (legacy, utólag pótolt) sztornó-rekord összegeit is
                // negatívan kell rögzíteni, hogy az összesítésekben
                // nullázza az eredeti számlát.
                'net_amount' => -$invoice->net_amount,
                'vat_amount' => -$invoice->vat_amount,
                'gross_amount' => -$invoice->gross_amount,
                'currency' => $invoice->currency,
                'payment_method' => $invoice->payment_method,
                'customer_name' => $invoice->customer_name,
                'customer_email' => $invoice->customer_email,
                'customer_tax_number' => $invoice->customer_tax_number,
                'billing_postcode' => $invoice->billing_postcode,
                'billing_city' => $invoice->billing_city,
                'billing_address' => $invoice->billing_address,
                'invoice_pdf_path' => $invoice->cancellation_pdf_path,
                'pdf_disk' => $invoice->cancellation_pdf_path ? 'local' : null,
                'pdf_downloaded_at' => $invoice->cancellation_pdf_path ? now() : null,
                'note' => $invoice->cancellation_reason,
            ]);
            $created->created_by = $invoice->cancelled_by ?: $invoice->created_by;
            $created->save();

            return $created;
        });
    }
}
