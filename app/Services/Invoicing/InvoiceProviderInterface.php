<?php

namespace App\Services\Invoicing;

use App\Models\PaymentObligation\MonthlyPaymentStatement;

interface InvoiceProviderInterface
{
    public function issue(MonthlyPaymentStatement $statement): array;
}
