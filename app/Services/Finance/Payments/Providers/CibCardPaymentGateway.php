<?php

namespace App\Services\Finance\Payments\Providers;

use App\Services\Finance\Payments\CardPaymentCallbackResult;
use App\Services\Finance\Payments\CardPaymentGatewayInterface;
use App\Services\Finance\Payments\CardPaymentInitiationPayload;
use App\Services\Finance\Payments\CardPaymentInitiationResult;
use App\Support\CibMessage;
use App\Support\CibMessageCrypto;
use App\Support\CibSecretKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CibCardPaymentGateway implements CardPaymentGatewayInterface
{
    public function __construct(
        private readonly CibMessageCrypto $crypto,
    ) {}

    public function initiate(CardPaymentInitiationPayload $payload): CardPaymentInitiationResult
    {
        $pid = $payload->transaction->pid;
        $secret = $this->resolveSecret((string) $payload->settings->cib_secret_key);

        if ($pid === '' || $secret === '') {
            return new CardPaymentInitiationResult(
                success: false,
                errorMessage: 'A CIB Bank kártyás fizetés beállítása hiányos (PID vagy titkos kulcs hiányzik).'
            );
        }

        $plainMessage = CibMessage::build([
            'PID' => $pid,
            'TRID' => $payload->transaction->trid,
            'MSGT' => '10',
            'UID' => str_pad((string) $payload->payment->user_id, 11, '0', STR_PAD_LEFT),
            'AMO' => (string) $payload->amount,
            'CUR' => $payload->currency,
            'TS' => now()->format('YmdHis'),
            'AUTH' => '0',
            'LANG' => 'HU',
            'URL' => $payload->callbackUrl,
            'EXTRA01' => $payload->orderRef,
            'CEMAIL' => $payload->payment->user?->email,
            'CNAME' => $payload->payment->guardian?->full_name,
        ]);

        try {
            $decrypted = $this->sendMerchantMessage(
                merchantUrl: $payload->transaction->merchant_url,
                pid: $pid,
                secret: $secret,
                fields: CibMessage::parse($plainMessage),
            );
        } catch (RuntimeException $exception) {
            return new CardPaymentInitiationResult(success: false, errorMessage: $exception->getMessage());
        } catch (\Throwable) {
            return new CardPaymentInitiationResult(
                success: false,
                errorMessage: 'A CIB fizetési szerver jelenleg nem érhető el. Kérjük, próbálja újra később.'
            );
        }

        $rc = (string) ($decrypted['RC'] ?? '');
        $redirectPlain = CibMessage::build([
            'PID' => $pid,
            'TRID' => $payload->transaction->trid,
            'MSGT' => '20',
        ]);

        return new CardPaymentInitiationResult(
            success: $rc === '00',
            redirectUrl: $rc === '00'
                ? $payload->transaction->customer_url.'?'.$this->crypto->encrypt($redirectPlain, $pid, $secret)
                : null,
            errorMessage: $rc === '00'
                ? null
                : ((string) ($decrypted['RT'] ?? 'A CIB az inicializálást elutasította.')),
            meta: [
                'request' => $this->maskSensitive($plainMessage),
                'response' => $decrypted,
            ],
        );
    }

    public function handleCallback(Request $request): CardPaymentCallbackResult
    {
        return new CardPaymentCallbackResult(
            success: false,
            orderRef: '',
            errorMessage: 'A CIB integráció a böngészős visszatérést használja, külön callback-üzenetet nem küld.'
        );
    }

    public function handleReturn(Request $request): CardPaymentCallbackResult
    {
        $query = $request->query();
        $messageType = (string) ($query['MSGT'] ?? '');
        $trid = (string) ($query['TRID'] ?? '');
        $pid = (string) ($query['PID'] ?? '');

        if ($messageType !== '21' || $trid === '' || $pid === '') {
            return new CardPaymentCallbackResult(
                success: false,
                orderRef: '',
                errorMessage: 'A CIB visszatérési üzenet hiányos vagy érvénytelen.'
            );
        }

        return new CardPaymentCallbackResult(
            success: true,
            orderRef: '',
            trid: $trid,
            pid: $pid,
            messageType: $messageType,
            rawResponse: [
                'MSGT' => $messageType,
                'TRID' => $trid,
                'PID' => $pid,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function queryStatus(string $merchantUrl, string $pid, string $trid, int $amount, string $secret): array
    {
        return $this->sendMerchantMessage($merchantUrl, $pid, $secret, [
            'PID' => $pid,
            'TRID' => $trid,
            'MSGT' => '33',
            'AMO' => (string) $amount,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function closeStatus(string $merchantUrl, string $pid, string $trid, int $amount, string $secret): array
    {
        return $this->sendMerchantMessage($merchantUrl, $pid, $secret, [
            'PID' => $pid,
            'TRID' => $trid,
            'MSGT' => '32',
            'AMO' => (string) $amount,
        ]);
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function sendMerchantMessage(string $merchantUrl, string $pid, string $secret, array $fields): array
    {
        $plain = CibMessage::build($fields);
        $encrypted = $this->crypto->encrypt($plain, $pid, $secret);
        $response = Http::timeout(config('cib.request_timeout_seconds', 20))
            ->retry(2, 250)
            ->withHeaders(['Accept' => 'text/plain'])
            ->get($merchantUrl.'?'.$encrypted);

        $body = trim((string) $response->body());

        if ($body === '') {
            throw new RuntimeException('A CIB üres választ adott vissza.');
        }

        if (str_starts_with($body, 'RC=')) {
            parse_str($body, $plainResponse);

            return collect($plainResponse)
                ->mapWithKeys(fn ($value, $key) => [$key => (string) $value])
                ->all();
        }

        return $this->crypto->decrypt($body, $secret);
    }

    /**
     * @return array<string, string>
     */
    private function maskSensitive(string $message): array
    {
        $parsed = CibMessage::parse($message);

        if (isset($parsed['CEMAIL'])) {
            $parsed['CEMAIL'] = '***';
        }

        if (isset($parsed['CNAME'])) {
            $parsed['CNAME'] = '***';
        }

        return $parsed;
    }

    private function resolveSecret(string $institutionSecret): string
    {
        $secret = trim($institutionSecret);

        if ($secret !== '') {
            return $secret;
        }

        $defaultSecret = trim((string) config('cib.default_secret_key_base64'));

        if ($defaultSecret !== '') {
            return $defaultSecret;
        }

        $filePath = trim((string) config('cib.default_secret_key_file'));

        if ($filePath === '') {
            return '';
        }

        return CibSecretKey::toBase64FromFile($filePath);
    }
}
