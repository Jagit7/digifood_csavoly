<?php

namespace App\Services\Invoicing;

use App\Models\PaymentObligation\MonthlyPaymentStatement;

class ManualInvoiceProvider implements InvoiceProviderInterface
{
    public function issue(MonthlyPaymentStatement $statement): array
    {
        return [
            'status' => 'manual',
            'message' => 'A kézi számlázásnál a számlaszámot manuálisan kell rögzíteni.',
        ];
    }
}
