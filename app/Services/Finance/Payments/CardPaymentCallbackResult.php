<?php

namespace App\Services\Finance\Payments;

class CardPaymentCallbackResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $orderRef,
        public readonly ?string $trid = null,
        public readonly ?string $pid = null,
        public readonly ?string $messageType = null,
        public readonly ?int $amount = null,
        public readonly ?string $providerTransactionId = null,
        public readonly ?string $errorMessage = null,
        public readonly array $rawResponse = [],
        public readonly string $httpResponseBody = 'OK',
    ) {
    }
}
