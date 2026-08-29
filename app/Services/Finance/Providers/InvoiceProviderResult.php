<?php

namespace App\Services\Finance\Providers;

use Illuminate\Support\Carbon;

class InvoiceProviderResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerInvoiceId = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?Carbon $issueDate = null,
        public readonly ?Carbon $fulfillmentDate = null,
        public readonly ?string $invoiceUrl = null,
        public readonly ?string $invoicePdfPath = null,
        public readonly ?string $errorMessage = null
    ) {
    }
}
