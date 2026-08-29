<?php

namespace App\Services\Finance\Providers;

interface InvoiceProviderInterface
{
    public function createInvoice(InvoiceProviderPayload $payload): InvoiceProviderResult;

    /**
     * Egy már kiállított bizonylat sztornózása (stornó/cancellation
     * bizonylat kiállítása a szolgáltatónál, ha a szolgáltató ezt
     * támogatja). A visszaadott InvoiceProviderResult::$status
     * InstitutionInvoice::STATUS_VOIDED siker esetén, egyébként
     * InstitutionInvoice::STATUS_FAILED (ld. $errorMessage).
     *
     * A visszaadott providerInvoiceId/invoiceNumber/invoicePdfPath a
     * LÉTREJÖTT SZTORNÓ bizonylatra vonatkozik, nem az eredetire - ezeket a
     * hívó fél nem az eredeti számla mezőibe, hanem a hozzá tartozó
     * cancellation_* mezőkbe menti el (ld. InstitutionInvoiceService::cancel()).
     */
    public function cancelInvoice(InvoiceProviderPayload $payload, ?string $reason = null): InvoiceProviderResult;

    /**
     * Egy már kiállított EREDETI bizonylat PDF-jének újbóli letöltése a
     * szolgáltatótól, kizárólag a már meglévő szolgáltatói
     * bizonylatazonosító alapján. Ez NEM állíthat ki új számlát.
     */
    public function downloadExistingInvoicePdf(InvoiceProviderPayload $payload): InvoiceProviderResult;

    /**
     * Egy már kiállított SZTORNÓ bizonylat PDF-jének újbóli letöltése a
     * szolgáltatótól, kizárólag a már meglévő szolgáltatói
     * bizonylatazonosító alapján. Ez NEM állíthat ki új sztornót.
     */
    public function downloadExistingCancellationPdf(InvoiceProviderPayload $payload): InvoiceProviderResult;
}
