<?php

namespace App\Services\Finance;

use App\Mail\PaymentConfirmationMail;
use App\Models\CibTransaction;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\ParentMonthlySettlementPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\Finance\Payments\CardPaymentCallbackResult;
use App\Services\Finance\Payments\CardPaymentGatewayInterface;
use App\Services\Finance\Payments\CardPaymentInitiationPayload;
use App\Services\Finance\Payments\CardPaymentInitiationResult;
use App\Services\Finance\Payments\Providers\CibCardPaymentGateway;
use App\Support\CibSecretKey;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class CardPaymentService
{
    public function __construct(
        private readonly InstitutionInvoiceService $invoiceService,
    ) {}

    public function initiate(ParentMonthlySettlementPayment $payment, Institution $institution, string $returnUrl): CardPaymentInitiationResult
    {
        $settings = $institution->setting ?? InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        if (! $settings->card_payment_enabled || ! filled($settings->card_payment_provider)) {
            return $this->failInitiation($payment, 'A bankkártyás fizetés nincs engedélyezve ennél az intézménynél.');
        }

        if ($payment->status !== ParentMonthlySettlementPayment::STATUS_PENDING) {
            return new CardPaymentInitiationResult(
                success: false,
                errorMessage: 'Ez a fizetés már feldolgozásra került.'
            );
        }

        if ($payment->total_amount <= 0) {
            return $this->failInitiation($payment, '0 Ft-ra nem indítható bankkártyás fizetés.');
        }

        try {
            $gateway = $this->gateway($settings->card_payment_provider);
        } catch (InvalidArgumentException $exception) {
            return $this->failInitiation($payment, $exception->getMessage());
        }

        try {
            $pid = $this->resolvePid($settings);
        } catch (InvalidArgumentException $exception) {
            return $this->failInitiation($payment, $exception->getMessage());
        }

        $transaction = DB::transaction(function () use ($payment, $institution, $returnUrl, $pid, $settings) {
            $lockedPayment = ParentMonthlySettlementPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingPending = CibTransaction::query()
                ->where('parent_monthly_settlement_payment_id', $lockedPayment->id)
                ->where('status', CibTransaction::STATUS_PENDING)
                ->latest('id')
                ->first();

            if ($existingPending) {
                return $existingPending;
            }

            return CibTransaction::create([
                'institution_id' => $institution->id,
                'parent_monthly_settlement_payment_id' => $lockedPayment->id,
                'user_id' => $lockedPayment->user_id,
                'guardian_id' => $lockedPayment->guardian_id,
                'pid' => $pid,
                'trid' => $this->generateTrid(),
                'order_ref' => $lockedPayment->reference,
                'amount' => (int) $lockedPayment->total_amount,
                'currency' => 'HUF',
                'status' => CibTransaction::STATUS_PENDING,
                'merchant_url' => (string) config('cib.merchant_url'),
                'customer_url' => (string) config('cib.customer_url'),
                'return_url' => $returnUrl,
                'init_requested_at' => now(),
                'meta' => [
                    'test_mode' => (bool) $settings->card_payment_test_mode,
                ],
            ]);
        });

        $payment->loadMissing(['user', 'guardian', 'items.child']);

        $payload = new CardPaymentInitiationPayload(
            institution: $institution,
            settings: $settings,
            payment: $payment,
            transaction: $transaction,
            orderRef: $payment->reference,
            amount: (int) $payment->total_amount,
            currency: 'HUF',
            description: 'Digifood étkezési térítési díj - '.$payment->reference,
            returnUrl: $returnUrl,
            callbackUrl: route('payments.card.return'),
        );

        $result = $gateway->initiate($payload);

        DB::transaction(function () use ($payment, $transaction, $result) {
            $lockedTransaction = CibTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTransaction->fill([
                'init_completed_at' => now(),
                'last_message_type' => '11',
                'init_rc' => (string) ($result->meta['response']['RC'] ?? ''),
                'init_rt' => Arr::get($result->meta, 'response.RT'),
                'request_log' => $this->appendLogEntry(
                    $lockedTransaction->request_log,
                    'MSGT10',
                    (array) ($result->meta['request'] ?? [])
                ),
                'response_log' => $this->appendLogEntry(
                    $lockedTransaction->response_log,
                    'MSGT11',
                    (array) ($result->meta['response'] ?? [])
                ),
            ]);

            if (! $result->success) {
                $lockedTransaction->status = CibTransaction::STATUS_FAILED;
                $lockedTransaction->failed_at = now();
            }

            $lockedTransaction->save();

            if (! $result->success) {
                $payment->update([
                    'status' => ParentMonthlySettlementPayment::STATUS_FAILED,
                    'failed_at' => now(),
                    'note' => $result->errorMessage,
                    'metadata' => [
                        'cib' => [
                            'trid' => $lockedTransaction->trid,
                            'pid' => $lockedTransaction->pid,
                            'init_rc' => $lockedTransaction->init_rc,
                            'init_rt' => $lockedTransaction->init_rt,
                        ],
                    ],
                ]);
            }
        });

        return $result;
    }

    public function handleCallback(string $provider, Request $request): CardPaymentCallbackResult
    {
        try {
            $gateway = $this->gateway($provider);
        } catch (InvalidArgumentException $exception) {
            return new CardPaymentCallbackResult(success: false, orderRef: '', errorMessage: $exception->getMessage());
        }

        return $gateway->handleCallback($request);
    }

    public function handleReturn(Request $request): ?RedirectResponse
    {
        $gateway = $this->gateway(InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB);
        $returnMessage = $gateway->handleReturn($request);

        if (! $returnMessage->success || $returnMessage->trid === null || $returnMessage->pid === null) {
            return null;
        }

        $transaction = CibTransaction::query()
            ->with(['parentPayment', 'institution.setting'])
            ->where('trid', $returnMessage->trid)
            ->where('pid', $returnMessage->pid)
            ->first();

        if (! $transaction || ! $transaction->parentPayment) {
            return null;
        }

        try {
            $result = $this->finalizeCibTransaction($transaction, $gateway);
        } catch (InvalidArgumentException $exception) {
            $result = $this->handleFinalizeFailure($transaction, $exception->getMessage(), CibTransaction::STATUS_UNCERTAIN);
        } catch (\Throwable $exception) {
            Log::warning('A CIB fizetés lezárása váratlan hibára futott.', [
                'transaction_id' => $transaction->id,
                'trid' => $transaction->trid,
                'message' => $exception->getMessage(),
            ]);

            $result = $this->handleFinalizeFailure(
                $transaction,
                'A bankkártyás fizetés lezárása közben átmeneti technikai hiba történt.',
                CibTransaction::STATUS_UNCERTAIN
            );
        }

        $payment = $transaction->parentPayment;
        $month = sprintf('%04d-%02d', $payment->year, $payment->month);
        $redirect = redirect()->route('parent.monthly-settlements.index', [
            'month' => $month,
            'payment' => $payment->reference,
        ]);

        if ($result->success) {
            return $redirect->with('success', 'A bankkártyás fizetés sikeresen lezárult.');
        }

        if (($result->rawResponse['RC'] ?? null) === 'PR') {
            return $redirect->with('info', 'A tranzakció még feldolgozás alatt áll. Kérjük, frissítsd az oldalt később.');
        }

        return $redirect->withErrors([
            'payment' => $result->errorMessage ?: 'A bankkártyás fizetés nem zárult sikeresen.',
        ]);
    }

    private function finalizeCibTransaction(
        CibTransaction $transaction,
        CardPaymentGatewayInterface $gateway
    ): CardPaymentCallbackResult {
        return DB::transaction(function () use ($transaction, $gateway) {
            $transaction = CibTransaction::query()
                ->with(['parentPayment.items.child', 'parentPayment.user', 'institution.setting'])
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $transaction->parentPayment;

            if (! $payment) {
                return new CardPaymentCallbackResult(success: false, orderRef: '', errorMessage: 'A CIB tranzakcióhoz nem tartozik fizetési rekord.');
            }

            if ($payment->status === ParentMonthlySettlementPayment::STATUS_COMPLETED && $transaction->status === CibTransaction::STATUS_SUCCESSFUL) {
                return new CardPaymentCallbackResult(
                    success: true,
                    orderRef: $payment->reference,
                    trid: $transaction->trid,
                    pid: $transaction->pid,
                    messageType: '31',
                    amount: $transaction->amount,
                    providerTransactionId: $transaction->anum,
                    rawResponse: (array) data_get($payment->metadata, 'cib.final', []),
                );
            }

            $secret = $this->resolveSecret($transaction->institution?->setting);

            if ($secret === '') {
                $this->markFailedTransaction($transaction, $payment, 'Hiányzik a CIB titkos kulcs a lezáráshoz.', CibTransaction::STATUS_UNCERTAIN);

                return new CardPaymentCallbackResult(success: false, orderRef: $payment->reference, trid: $transaction->trid, pid: $transaction->pid, errorMessage: 'Hiányzik a CIB titkos kulcs a lezáráshoz.');
            }

            $statusResponse = $gateway->queryStatus(
                $transaction->merchant_url,
                $transaction->pid,
                $transaction->trid,
                (int) $transaction->amount,
                $secret,
            );

            $this->guardResponseIntegrity($transaction, $statusResponse);

            $transaction->returned_at = $transaction->returned_at ?? now();
            $transaction->last_status_polled_at = now();
            $transaction->last_message_type = '31';
            $transaction->response_log = $this->appendLogEntry($transaction->response_log, 'MSGT33/MSGT31', $statusResponse);
            $transaction->save();

            $statusRc = (string) ($statusResponse['RC'] ?? '');

            if ($statusRc === 'PR') {
                $transaction->status = CibTransaction::STATUS_UNCERTAIN;
                $transaction->final_rc = $statusRc;
                $transaction->final_rt = (string) ($statusResponse['RT'] ?? 'A tranzakció feldolgozása még folyamatban van.');
                $transaction->save();

                $payment->update([
                    'status' => ParentMonthlySettlementPayment::STATUS_PENDING,
                    'metadata' => $this->mergePaymentMetadata($payment, $transaction, [
                        'status_poll' => $statusResponse,
                    ]),
                ]);

                return new CardPaymentCallbackResult(
                    success: false,
                    orderRef: $payment->reference,
                    trid: $transaction->trid,
                    pid: $transaction->pid,
                    messageType: '31',
                    amount: (int) $transaction->amount,
                    errorMessage: 'A tranzakció feldolgozása még folyamatban van.',
                    rawResponse: $statusResponse,
                );
            }

            if ($statusRc === 'TO') {
                $this->markFailedTransaction(
                    $transaction,
                    $payment,
                    (string) ($statusResponse['RT'] ?? 'A tranzakció időtúllépés miatt meghiúsult.'),
                    CibTransaction::STATUS_FAILED,
                    $statusResponse
                );

                return new CardPaymentCallbackResult(
                    success: false,
                    orderRef: $payment->reference,
                    trid: $transaction->trid,
                    pid: $transaction->pid,
                    messageType: '31',
                    amount: (int) $transaction->amount,
                    errorMessage: (string) ($statusResponse['RT'] ?? 'A tranzakció időtúllépés miatt meghiúsult.'),
                    rawResponse: $statusResponse,
                );
            }

            $closeResponse = $gateway->closeStatus(
                $transaction->merchant_url,
                $transaction->pid,
                $transaction->trid,
                (int) $transaction->amount,
                $secret,
            );

            $this->guardResponseIntegrity($transaction, $closeResponse);

            $transaction->response_log = $this->appendLogEntry($transaction->response_log, 'MSGT32/MSGT31', $closeResponse);
            $transaction->final_rc = (string) ($closeResponse['RC'] ?? '');
            $transaction->final_rt = (string) ($closeResponse['RT'] ?? '');
            $transaction->anum = (string) ($closeResponse['ANUM'] ?? '');
            $transaction->last_message_type = '31';
            $transaction->closed_at = now();
            $transaction->save();

            if (($closeResponse['RC'] ?? null) === '00') {
                $this->bookSuccessfulPayment($transaction, $payment, $closeResponse);

                return new CardPaymentCallbackResult(
                    success: true,
                    orderRef: $payment->reference,
                    trid: $transaction->trid,
                    pid: $transaction->pid,
                    messageType: '31',
                    amount: (int) $transaction->amount,
                    providerTransactionId: (string) ($closeResponse['ANUM'] ?? ''),
                    rawResponse: $closeResponse,
                );
            }

            $this->markFailedTransaction(
                $transaction,
                $payment,
                (string) ($closeResponse['RT'] ?? 'A bankkártyás fizetés sikertelen volt.'),
                CibTransaction::STATUS_FAILED,
                $closeResponse
            );

            return new CardPaymentCallbackResult(
                success: false,
                orderRef: $payment->reference,
                trid: $transaction->trid,
                pid: $transaction->pid,
                messageType: '31',
                amount: (int) $transaction->amount,
                errorMessage: (string) ($closeResponse['RT'] ?? 'A bankkártyás fizetés sikertelen volt.'),
                rawResponse: $closeResponse,
            );
        });
    }

    /**
     * @param  array<string, string>  $closeResponse
     */
    private function bookSuccessfulPayment(CibTransaction $transaction, ParentMonthlySettlementPayment $payment, array $closeResponse): void
    {
        $invoiceUrls = [];
        $invoiceErrors = [];

        foreach ($payment->items as $item) {
            MonthlyPaymentStatement::query()
                ->whereKey($item->monthly_payment_statement_id)
                ->lockForUpdate()
                ->first();

            $existing = InstitutionPayment::query()
                ->where('monthly_payment_statement_id', $item->monthly_payment_statement_id)
                ->where('reference', $payment->reference)
                ->where('status', InstitutionPayment::STATUS_COMPLETED)
                ->first();

            if (! $existing) {
                $note = implode(' | ', array_filter([
                    'Online bankkártyás fizetés (CIB).',
                    'TRID: '.$transaction->trid,
                    'ANUM: '.((string) ($closeResponse['ANUM'] ?? '')),
                    'RC: '.((string) ($closeResponse['RC'] ?? '')),
                    'RT: '.((string) ($closeResponse['RT'] ?? '')),
                    'AMO: '.((string) ($closeResponse['AMO'] ?? $item->amount)).' HUF',
                ]));

                $existing = InstitutionPayment::create([
                    'institution_id' => $item->child?->institution_id,
                    'child_id' => $item->child_id,
                    'guardian_id' => $payment->guardian_id,
                    'monthly_payment_statement_id' => $item->monthly_payment_statement_id,
                    'amount' => $item->amount,
                    'payment_method' => InstitutionPayment::METHOD_ONLINE,
                    'status' => InstitutionPayment::STATUS_COMPLETED,
                    'reference' => $payment->reference,
                    'paid_at' => now(),
                    'note' => $note,
                    'recorded_by' => $payment->user_id,
                ]);
            }

            $item->update(['paid_amount' => $item->amount]);

            if ($item->child) {
                try {
                    $invoice = $this->invoiceService->issueAutomaticInvoiceForPayment(
                        $transaction->institution()->firstOrFail(),
                        $payment->user()->firstOrFail(),
                        $existing,
                    );

                    if ($invoice?->invoice_url) {
                        $invoiceUrls[] = $invoice->invoice_url;
                    }
                } catch (\Throwable $exception) {
                    $invoiceErrors[] = $exception->getMessage();

                    Log::warning('Sikeres CIB fizetés után az automatikus Billingo számlázás hibára futott.', [
                        'payment_id' => $existing->id,
                        'statement_id' => $item->monthly_payment_statement_id,
                        'reference' => $payment->reference,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $payment->update([
            'status' => ParentMonthlySettlementPayment::STATUS_COMPLETED,
            'transaction_reference' => $transaction->trid,
            'paid_at' => now(),
            'receipt_url' => Arr::first($invoiceUrls),
            'note' => empty($invoiceErrors)
                ? 'A bankkártyás fizetés sikeresen lezárult.'
                : 'A fizetés sikeres, de a számlázás újrapróbálást igényel.',
            'metadata' => $this->mergePaymentMetadata($payment, $transaction, [
                'final' => $closeResponse,
                'invoice_errors' => $invoiceErrors,
            ]),
        ]);

        $transaction->status = CibTransaction::STATUS_SUCCESSFUL;
        $transaction->save();

        $this->sendPaymentConfirmationEmail($payment->fresh(['user', 'items.child']), $transaction);
    }

    /**
     * A banki visszajelzés kifejezetten hiányolta a tranzakciós
     * visszaigazoló e-mailt ("A tranzakciókról egyáltalán nem érkezek
     * megerősítő e-mailek."). A kiküldés hibája nem szabad, hogy
     * meghiúsítsa a már sikeresen lezárt fizetés rögzítését, ezért csak
     * naplózzuk, ha a levél nem küldhető ki.
     */
    private function sendPaymentConfirmationEmail(ParentMonthlySettlementPayment $payment, CibTransaction $transaction): void
    {
        $recipientEmail = trim((string) ($payment->user?->email ?? ''));

        if ($recipientEmail === '') {
            Log::warning('Sikeres CIB fizetés után nem küldhető visszaigazoló e-mail: nincs e-mail cím a felhasználóhoz.', [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
            ]);

            return;
        }

        try {
            $period = CarbonImmutable::create($payment->year, $payment->month, 1, 0, 0, 0, config('app.timezone'));
            $childNames = $payment->items->map(fn ($item) => $item->child?->name)->filter()->implode(', ');

            Mail::to($recipientEmail)->send(new PaymentConfirmationMail([
                'recipientName' => trim((string) ($payment->user?->name ?: 'Kedves Szülő')),
                'institutionName' => $transaction->institution?->name ?? 'Digifood',
                'amountLabel' => number_format((int) $payment->total_amount, 0, ',', ' ').' Ft',
                'periodLabel' => $period->locale('hu')->isoFormat('YYYY. MMMM'),
                'childNamesLabel' => $childNames !== '' ? $childNames : 'Nincs megadva',
                'paidAtLabel' => $payment->paid_at?->timezone(config('app.timezone'))->locale('hu')->isoFormat('YYYY. MMMM D. HH:mm') ?? '',
                'reference' => $transaction->trid ?: $payment->reference,
                'receiptUrl' => $payment->receipt_url,
            ]));
        } catch (\Throwable $exception) {
            Log::warning('Sikeres CIB fizetés után a visszaigazoló e-mail kiküldése hibára futott.', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, string>|null  $response
     */
    private function markFailedTransaction(
        CibTransaction $transaction,
        ParentMonthlySettlementPayment $payment,
        string $errorMessage,
        string $transactionStatus,
        ?array $response = null
    ): void {
        $transaction->status = $transactionStatus;
        $transaction->failed_at = now();
        $transaction->final_rc = (string) ($response['RC'] ?? $transaction->final_rc);
        $transaction->final_rt = (string) ($response['RT'] ?? $errorMessage);
        $transaction->closed_at = $transaction->closed_at ?? now();
        $transaction->save();

        $payment->update([
            'status' => ParentMonthlySettlementPayment::STATUS_FAILED,
            'failed_at' => now(),
            'note' => $errorMessage,
            'metadata' => $this->mergePaymentMetadata($payment, $transaction, [
                'final' => $response ?? [],
            ]),
        ]);
    }

    /**
     * @param  array<string, string>  $response
     */
    private function guardResponseIntegrity(CibTransaction $transaction, array $response): void
    {
        if (($response['PID'] ?? null) !== $transaction->pid) {
            throw new InvalidArgumentException('A CIB válasz PID mezője nem egyezik.');
        }

        if (($response['TRID'] ?? null) !== $transaction->trid) {
            throw new InvalidArgumentException('A CIB válasz TRID mezője nem egyezik.');
        }

        if (isset($response['AMO']) && (int) round((float) $response['AMO']) !== (int) $transaction->amount) {
            throw new InvalidArgumentException('A CIB válasz összege nem egyezik a nyilvántartott összeggel.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $current
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function appendLogEntry(?array $current, string $messageType, array $payload): array
    {
        $entries = is_array($current) ? $current : [];
        $entries[] = [
            'timestamp' => now()->toIso8601String(),
            'message_type' => $messageType,
            'payload' => $payload,
        ];

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function mergePaymentMetadata(ParentMonthlySettlementPayment $payment, CibTransaction $transaction, array $extra = []): array
    {
        return array_replace_recursive((array) ($payment->metadata ?? []), [
            'cib' => array_merge([
                'trid' => $transaction->trid,
                'pid' => $transaction->pid,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'init_rc' => $transaction->init_rc,
                'init_rt' => $transaction->init_rt,
                'final_rc' => $transaction->final_rc,
                'final_rt' => $transaction->final_rt,
                'anum' => $transaction->anum,
            ], $extra),
        ]);
    }

    private function failInitiation(ParentMonthlySettlementPayment $payment, string $errorMessage): CardPaymentInitiationResult
    {
        $payment->update([
            'status' => ParentMonthlySettlementPayment::STATUS_FAILED,
            'failed_at' => now(),
            'note' => $errorMessage,
        ]);

        return new CardPaymentInitiationResult(success: false, errorMessage: $errorMessage);
    }

    private function handleFinalizeFailure(CibTransaction $transaction, string $errorMessage, string $transactionStatus): CardPaymentCallbackResult
    {
        return DB::transaction(function () use ($transaction, $errorMessage, $transactionStatus) {
            $transaction = CibTransaction::query()
                ->with(['parentPayment'])
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $transaction->parentPayment;

            if (! $payment) {
                return new CardPaymentCallbackResult(
                    success: false,
                    orderRef: '',
                    trid: $transaction->trid,
                    pid: $transaction->pid,
                    errorMessage: $errorMessage,
                );
            }

            $this->markFailedTransaction($transaction, $payment, $errorMessage, $transactionStatus);

            return new CardPaymentCallbackResult(
                success: false,
                orderRef: $payment->reference,
                trid: $transaction->trid,
                pid: $transaction->pid,
                errorMessage: $errorMessage,
            );
        });
    }

    private function gateway(string $provider): CardPaymentGatewayInterface
    {
        return match ($provider) {
            InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB => app(CibCardPaymentGateway::class),
            default => throw new InvalidArgumentException('Ehhez a szolgáltatóhoz még nincs implementálva a bankkártyás fizetés: '.$provider),
        };
    }

    private function resolvePid(InstitutionSetting $settings): string
    {
        $pid = trim((string) ($settings->cib_terminal_id ?: config('cib.default_pid')));

        if ($pid === '') {
            throw new InvalidArgumentException('Hiányzik a CIB PID / terminálazonosító.');
        }

        return $pid;
    }

    private function resolveSecret(?InstitutionSetting $settings): string
    {
        $institutionSecret = trim((string) ($settings?->cib_secret_key ?? ''));

        if ($institutionSecret !== '') {
            return $institutionSecret;
        }

        $defaultSecret = trim((string) config('cib.default_secret_key_base64'));

        if ($defaultSecret !== '') {
            return $defaultSecret;
        }

        $defaultSecretFile = trim((string) config('cib.default_secret_key_file'));

        if ($defaultSecretFile === '') {
            return '';
        }

        return CibSecretKey::toBase64FromFile($defaultSecretFile);
    }

    private function generateTrid(): string
    {
        do {
            $trid = '';

            for ($i = 0; $i < 16; $i++) {
                $trid .= (string) random_int(0, 9);
            }
        } while (CibTransaction::query()->where('trid', $trid)->exists());

        return $trid;
    }
}
