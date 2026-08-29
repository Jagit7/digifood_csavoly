<?php

namespace App\Services\Finance\Providers;

use App\Models\InstitutionInvoice;

class ManualInvoiceProvider implements InvoiceProviderInterface
{
    public function createInvoice(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_DRAFT
        );
    }

    /**
     * A "kézi" számlázásnál nincs külső szolgáltató, amit értesíteni
     * kellene - a sztornózás csak a helyi státuszváltást jelenti, amit a
     * hívó fél (InstitutionInvoiceService::cancel()) állít be. Itt csupán
     * jelezzük, hogy a "sztornózás" sikeresnek tekinthető.
     */
    public function cancelInvoice(InvoiceProviderPayload $payload, ?string $reason = null): InvoiceProviderResult
    {
        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_VOIDED
        );
    }

    public function downloadExistingInvoicePdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_FAILED,
            errorMessage: 'Kézi számlázásnál nincs külső szolgáltatói PDF-letöltés.'
        );
    }

    public function downloadExistingCancellationPdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_FAILED,
            errorMessage: 'Kézi számlázásnál nincs külső szolgáltatói PDF-letöltés.'
        );
    }
}
