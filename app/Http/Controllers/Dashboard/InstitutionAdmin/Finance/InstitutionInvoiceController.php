<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceCancelRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceIndexRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceStoreRequest;
use App\Jobs\Finance\RunBillingoInvoiceSyncJob;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\Finance\BillingoInvoiceSyncService;
use App\Services\Finance\InstitutionInvoiceService;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionInvoiceController extends Controller
{
    public function __construct(
        private readonly InstitutionInvoiceService $invoiceService,
        private readonly BillingoInvoiceSyncService $billingoSyncService,
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper
    ) {}

    public function index(InstitutionInvoiceIndexRequest $request): View
    {
        $institution = $this->institution();
        $filters = validator($request->all(), $request->rules())->validate();
        $query = $this->invoiceService->query($institution, $filters);

        return view('dashboard.institution_admin.finance.invoices.index', [
            'institution' => $institution,
            'setting' => $this->invoiceService->settings($institution),
            'invoices' => $this->invoiceService->paginate($query),
            'summary' => $this->invoiceService->summary($query),
            'providerOptions' => InstitutionInvoice::providerOptions(),
            'documentTypeOptions' => InstitutionInvoice::documentTypeOptions(),
            'statusOptions' => InstitutionInvoice::statusOptions(),
            'paymentMethodOptions' => InstitutionPayment::paymentMethodOptions(),
            'syncMeta' => $this->billingoSyncService->latestSyncMeta($institution),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $institution = $this->institution();
        $lock = Cache::lock("billingo-invoice-sync:institution:{$institution->id}", 900);

        if (! $lock->get()) {
            return back()->with('error', 'Ehhez az intézményhez már fut egy Billingo-szinkronizálás.');
        }

        $lock->release();

        RunBillingoInvoiceSyncJob::dispatch(
            $institution->id,
            $request->user()?->id,
            \App\Models\InstitutionInvoiceSyncRun::MODE_MANUAL,
            false
        );

        return back()->with('success', 'A Billingo-szinkronizálás elindult. Az eredmény a lista tetején és a naplóban követhető.');
    }

    public function create(): View|RedirectResponse
    {
        $institution = $this->institution();
        $setting = $this->invoiceService->settings($institution);
        $selectedProvider = $this->resolveProvider($setting);

        $preview = null;
        $sourcePayment = null;
        $statementId = request('statement_id');
        $paymentId = request('payment_id');

        if ($paymentId) {
            $payment = InstitutionPayment::query()
                ->where('institution_id', $institution->id)
                ->findOrFail($paymentId);

            $draft = $this->invoiceService->invoiceDraftFromPayment($institution, $payment, $selectedProvider);

            if ($draft['existing_invoice']) {
                $message = $draft['existing_invoice']->status === InstitutionInvoice::STATUS_FAILED
                    ? 'Ehhez a készpénzes befizetéshez már tartozik egy sikertelen számlapróbálkozás. A meglévő számlaoldalon a jelenlegi szabályok szerint újrapróbálhatod.'
                    : 'Ehhez a készpénzes befizetéshez már létezik számla, ezért új helyett a meglévő rekord nyílt meg.';

                abort_if($draft['existing_invoice']->institution_id !== $institution->id, 403);

                return redirect()
                    ->route('dashboard.institution.finance.invoices.show', $draft['existing_invoice'])
                    ->with('warning', $message);
            }

            $sourcePayment = $draft['payment'];
            $preview = $draft['preview'];
            $statementId = $sourcePayment->monthly_payment_statement_id;
        }

        if ($statementId) {
            $statement = MonthlyPaymentStatement::query()
                ->where('institution_id', $institution->id)
                ->find($statementId);

            if ($statement && ! $preview) {
                $preview = $this->invoiceService->preview($institution, $statement, $selectedProvider);
            }
        }

        return view('dashboard.institution_admin.finance.invoices.create', [
            'institution' => $institution,
            'setting' => $setting,
            'preview' => $preview,
            'sourcePayment' => $sourcePayment,
            'selectedProvider' => $selectedProvider,
            'providerOptions' => InstitutionInvoice::providerOptions(),
            'paymentMethodOptions' => InstitutionPayment::paymentMethodOptions(),
        ]);
    }

    public function searchStatements(): JsonResponse
    {
        $institution = $this->institution();
        $statements = $this->invoiceService->searchableStatements($institution, (string) request('q', ''));

        return response()->json([
            'results' => $statements->map(function (MonthlyPaymentStatement $statement) {
                $child = $statement->child;

                return [
                    'id' => $statement->id,
                    'text' => collect([
                        $child?->name,
                        $this->periodHelper->fromStatement($statement)['payment_period_label'].'i fizetési hónap',
                        'Étkezés: '.$this->periodHelper->fromStatement($statement)['meal_period_label'],
                        'Jóváírás: '.$this->periodHelper->fromStatement($statement)['credit_period_label'],
                        $child?->educational_identifier,
                        // A ténylegesen számlázható (aktuális havi) összeg -
                        // ld. InstitutionInvoiceService::store() kommentjét:
                        // a total_payable a korábbi egyenleget is
                        // tartalmazná, ami a most kiállítandó számlának NEM
                        // része.
                        number_format($statement->invoiceable_amount, 0, ',', ' ').' Ft',
                    ])->filter()->implode(' – '),
                ];
            })->values(),
        ]);
    }

    public function previewStatement(MonthlyPaymentStatement $statement): JsonResponse
    {
        $institution = $this->institution();
        abort_if($statement->institution_id !== $institution->id, 403);
        $setting = $this->invoiceService->settings($institution);
        $provider = $this->resolveProvider($setting);

        return response()->json(
            $this->invoiceService->preview($institution, $statement, $provider)
        );
    }

    public function store(InstitutionInvoiceStoreRequest $request): RedirectResponse
    {
        $institution = $this->institution();
        $invoice = $this->invoiceService->store($institution, $request->user(), $request->validated());

        $message = match ($invoice->status) {
            InstitutionInvoice::STATUS_FAILED => 'A számla rekord létrejött, de a szolgáltatói integráció még nincs aktiválva.',
            InstitutionInvoice::STATUS_DRAFT => 'A számla előkészítése sikeresen létrejött.',
            default => 'A számla sikeresen létrejött.',
        };

        return redirect()
            ->route('dashboard.institution.finance.invoices.show', $invoice)
            ->with('success', $message);
    }

    public function show(InstitutionInvoice $invoice): View
    {
        $institution = $this->institution();
        $invoice = $this->invoiceService->findForInstitution($institution, $invoice);
        $hasLocalInvoicePdf = $this->invoiceService->hasUsableInvoicePdf($invoice);
        $hasLocalCancellationPdf = $this->invoiceService->hasUsableCancellationPdf($invoice);

        return view('dashboard.institution_admin.finance.invoices.show', [
            'institution' => $institution,
            'invoice' => $invoice,
            'payments' => $this->invoiceService->relatedPayments($institution, $invoice),
            'hasLocalInvoicePdf' => $hasLocalInvoicePdf,
            'hasLocalCancellationPdf' => $hasLocalCancellationPdf,
        ]);
    }

    public function cancel(InstitutionInvoiceCancelRequest $request, InstitutionInvoice $invoice): RedirectResponse
    {
        $institution = $this->institution();
        $invoice = $this->invoiceService->cancel($institution, $request->user(), $invoice, $request->validated('reason'));

        return redirect()
            ->route('dashboard.institution.finance.invoices.show', $invoice)
            ->with('success', 'A számla sikeresen sztornózásra került.');
    }

    public function reloadPdf(InstitutionInvoice $invoice): RedirectResponse
    {
        $institution = $this->institution();
        $invoice = $this->invoiceService->reloadInvoicePdf($institution, $invoice);

        return redirect()
            ->route('dashboard.institution.finance.invoices.show', $invoice)
            ->with('success', 'A számla PDF-je elérhető a helyi tárolóban.');
    }

    public function reloadCancellationPdf(InstitutionInvoice $invoice): RedirectResponse
    {
        $institution = $this->institution();
        $redirectInvoice = $invoice->isCancellationDocument() && $invoice->originalInvoice
            ? $invoice->originalInvoice
            : $invoice;
        $this->invoiceService->reloadCancellationPdf($institution, $invoice);

        return redirect()
            ->route('dashboard.institution.finance.invoices.show', $redirectInvoice)
            ->with('success', 'A sztornó bizonylat PDF-je elérhető a helyi tárolóban.');
    }

    /**
     * A bizonylat PDF-jét a BillingoInvoiceProvider/SzamlazzHuInvoiceProvider
     * a "local" (privát) storage lemezre menti - szándékosan, hiszen egy
     * számla-PDF a szülő nevét, címét, adószámát is tartalmazza, ezért nem
     * lehet egy kitalálható, publikus /storage/... URL-en keresztül
     * bárkinek elérhető. Emiatt NEM Storage::url()-lel adjuk ki a linket
     * (az a "public" lemezt feltételezné, 404-et adna), hanem ezen a
     * jogosultság-ellenőrzött végponton streameljük vissza a fájlt.
     */
    public function download(InstitutionInvoice $invoice): StreamedResponse
    {
        $institution = $this->institution();
        $this->authorizeInvoice($invoice, $institution);

        abort_unless(
            $this->invoiceService->hasUsableInvoicePdf($invoice),
            404,
            'A számla PDF-je nem található.'
        );

        return Storage::disk('local')->download(
            $invoice->invoice_pdf_path,
            ($invoice->invoice_number ?: 'szamla').'.pdf'
        );
    }

    public function downloadCancellation(InstitutionInvoice $invoice): StreamedResponse
    {
        $institution = $this->institution();
        $this->authorizeInvoice($invoice, $institution);
        $cancellationInvoice = $invoice->isCancellationDocument()
            ? $invoice
            : $invoice->cancellationInvoice()->first();

        abort_unless($cancellationInvoice, 404, 'A sztornó bizonylat nem található.');

        abort_unless(
            $this->invoiceService->hasUsableCancellationPdf($invoice),
            404,
            'A sztornó bizonylat PDF-je nem található.'
        );

        return Storage::disk('local')->download(
            $cancellationInvoice->invoice_pdf_path,
            ($cancellationInvoice->invoice_number ?: 'sztorno').'.pdf'
        );
    }

    public function destroy(InstitutionInvoice $invoice): RedirectResponse
    {
        $institution = $this->institution();
        $this->invoiceService->delete($institution, $invoice);

        return redirect()
            ->route('dashboard.institution.finance.invoices')
            ->with('success', 'A sikertelen számlapróbálkozás törölve lett, a fizetési kötelezettséghez újra kiállítható számla.');
    }

    private function authorizeInvoice(InstitutionInvoice $invoice, Institution $institution): void
    {
        abort_if($invoice->institution_id !== $institution->id, 403);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }

    /**
     * A számla létrehozásához használt szolgáltató kizárólag az intézmény
     * beállításaiban rögzített szolgáltató lehet - a felhasználó a
     * számla-létrehozási űrlapon ezt nem választhatja meg szabadon, csak
     * tájékoztató jelleggel jelenik meg (ld. InstitutionSetting::invoicing_provider).
     */
    private function resolveProvider(InstitutionSetting $setting): string
    {
        $provider = $setting->invoicing_provider;

        return in_array($provider, array_keys(InstitutionInvoice::providerOptions()), true)
            ? $provider
            : InstitutionInvoice::PROVIDER_MANUAL;
    }
}
