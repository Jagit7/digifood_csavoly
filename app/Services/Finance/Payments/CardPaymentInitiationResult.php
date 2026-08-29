<?php

namespace App\Services\Finance\Payments;

class CardPaymentInitiationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $errorMessage = null,
        public readonly array $meta = [],
    ) {
    }
}
