<?php

namespace App\Services\Finance\Payments;

use App\Models\CibTransaction;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Models\ParentMonthlySettlementPayment;

class CardPaymentInitiationPayload
{
    public function __construct(
        public readonly Institution $institution,
        public readonly InstitutionSetting $settings,
        public readonly ParentMonthlySettlementPayment $payment,
        public readonly CibTransaction $transaction,
        public readonly string $orderRef,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $returnUrl,
        public readonly string $callbackUrl,
    ) {
    }
}
