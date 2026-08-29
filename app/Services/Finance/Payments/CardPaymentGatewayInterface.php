<?php

namespace App\Services\Finance\Payments;

use Illuminate\Http\Request;

interface CardPaymentGatewayInterface
{
    public function initiate(CardPaymentInitiationPayload $payload): CardPaymentInitiationResult;

    public function handleCallback(Request $request): CardPaymentCallbackResult;

    public function handleReturn(Request $request): CardPaymentCallbackResult;

    /**
     * @return array<string, string>
     */
    public function queryStatus(string $merchantUrl, string $pid, string $trid, int $amount, string $secret): array;

    /**
     * @return array<string, string>
     */
    public function closeStatus(string $merchantUrl, string $pid, string $trid, int $amount, string $secret): array;
}
