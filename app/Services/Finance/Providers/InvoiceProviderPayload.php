<?php

namespace App\Services\Finance\Providers;

use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\PaymentObligation\MonthlyPaymentStatement;

class InvoiceProviderPayload
{
    public function __construct(
        public readonly Institution $institution,
        public readonly MonthlyPaymentStatement $statement,
        public readonly InstitutionInvoice $invoice
    ) {
    }
}
