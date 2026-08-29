<?php

namespace App\Services\Invoicing;

use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SzamlazzHuInvoiceProvider implements InvoiceProviderInterface
{
    public function issue(MonthlyPaymentStatement $statement): array
    {
        throw new HttpException(501, 'Az integráció még nincs aktiválva.');
    }
}
