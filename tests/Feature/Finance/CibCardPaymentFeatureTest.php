<?php

namespace Tests\Feature\Finance;

use App\Models\Child;
use App\Models\CibTransaction;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\ParentMonthlySettlementPayment;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\InstitutionInvoiceService;
use App\Support\CibMessage;
use App\Support\CibMessageCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class CibCardPaymentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cib_payment_initiation_builds_and_sends_msgt10_then_redirects_to_customer_page(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();
        $settings = $this->createCibSettings($institution);
        $this->createOpenStatement($institution, $guardian, 12500);

        $captured = [];
        $this->fakeMerchantResponses(
            secret: $settings->cib_secret_key,
            pid: $settings->cib_terminal_id,
            responsesByMessageType: [
                '10' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => '__from_request__',
                    'MSGT' => '11',
                    'RC' => '00',
                    'RT' => 'OK',
                ],
            ],
            captured: $captured,
        );

        $response = $this->postPaymentIntent($user, '2026-07');

        $response->assertRedirect();
        $this->assertStringStartsWith(config('cib.customer_url'), $response->headers->get('Location'));

        $transaction = CibTransaction::query()->firstOrFail();
        $payment = ParentMonthlySettlementPayment::query()->firstOrFail();

        $this->assertSame(CibTransaction::STATUS_PENDING, $transaction->status);
        $this->assertSame('00', $transaction->init_rc);
        $this->assertSame(16, strlen($transaction->trid));
        $this->assertTrue(ctype_digit($transaction->trid));
        $this->assertSame(ParentMonthlySettlementPayment::STATUS_PENDING, $payment->status);
        $this->assertCount(1, $captured);
        $this->assertSame('10', $captured[0]['MSGT']);
        $this->assertSame('12500', $captured[0]['AMO']);
        $this->assertSame($transaction->trid, $captured[0]['TRID']);
        $this->assertSame('***', data_get($transaction->request_log, '0.payload.CEMAIL'));
        $this->assertSame('***', data_get($transaction->request_log, '0.payload.CNAME'));
    }

    public function test_cib_payment_initiation_handles_rejected_msgt11_response(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();
        $settings = $this->createCibSettings($institution);
        $this->createOpenStatement($institution, $guardian, 7500);

        $this->fakeMerchantResponses(
            secret: $settings->cib_secret_key,
            pid: $settings->cib_terminal_id,
            responsesByMessageType: [
                '10' => 'RC=01&RT=Elutasitott%20inicializalas',
            ],
        );

        $response = $this->postPaymentIntent($user, '2026-07');

        $response->assertRedirect(route('parent.monthly-settlements.index', ['month' => '2026-07']));
        $response->assertSessionHasErrors(['payment']);

        $transaction = CibTransaction::query()->firstOrFail();
        $payment = ParentMonthlySettlementPayment::query()->firstOrFail();

        $this->assertSame(CibTransaction::STATUS_FAILED, $transaction->status);
        $this->assertSame('01', $transaction->init_rc);
        $this->assertSame(ParentMonthlySettlementPayment::STATUS_FAILED, $payment->status);
    }

    public function test_cib_return_flow_queries_and_closes_transaction_once_then_books_payment_idempotently(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();
        $settings = $this->createCibSettings($institution);
        $statement = $this->createOpenStatement($institution, $guardian, 9800);
        $payment = $this->createPendingPayment($user, $guardian, $statement, 9800);
        $transaction = $this->createPendingTransaction($institution, $payment, 9800, $settings->cib_terminal_id);

        $captured = [];
        $this->fakeMerchantResponses(
            secret: $settings->cib_secret_key,
            pid: $settings->cib_terminal_id,
            responsesByMessageType: [
                '33' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => $transaction->trid,
                    'MSGT' => '31',
                    'AMO' => '9800',
                    'RC' => '00',
                    'RT' => 'Status ok',
                    'ANUM' => 'AUTH123456',
                    'CNUM' => '1234********5678',
                ],
                '32' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => $transaction->trid,
                    'MSGT' => '31',
                    'AMO' => '9800',
                    'RC' => '00',
                    'RT' => 'Lezarva',
                    'ANUM' => 'AUTH123456',
                ],
            ],
            captured: $captured,
        );

        $response = $this->actingAs($user)->get($this->paymentReturnUrl([
            'PID' => $transaction->pid,
            'TRID' => $transaction->trid,
            'MSGT' => '21',
        ]));

        $response->assertRedirect(route('parent.monthly-settlements.index', [
            'month' => '2026-07',
            'payment' => $payment->reference,
        ]));
        $response->assertSessionHas('success');

        $payment->refresh();
        $transaction->refresh();
        $statement->refresh();
        $paymentItem = $payment->items()->firstOrFail();

        $this->assertSame(ParentMonthlySettlementPayment::STATUS_COMPLETED, $payment->status);
        $this->assertSame($transaction->trid, $payment->transaction_reference);
        $this->assertSame(CibTransaction::STATUS_SUCCESSFUL, $transaction->status);
        $this->assertSame('00', $transaction->final_rc);
        $this->assertSame('AUTH123456', $transaction->anum);
        $this->assertSame(9800, $paymentItem->paid_amount);
        $this->assertDatabaseCount('institution_payments', 1);
        $this->assertSame('AUTH123456', data_get($payment->metadata, 'cib.anum'));
        $this->assertCount(2, $captured);
        $this->assertSame(['33', '32'], array_column($captured, 'MSGT'));

        Http::fake(fn () => throw new \RuntimeException('A második visszatérés nem hívhatja meg újra a CIB-et.'));

        $repeat = $this->actingAs($user)->get($this->paymentReturnUrl([
            'PID' => $transaction->pid,
            'TRID' => $transaction->trid,
            'MSGT' => '21',
        ]));

        $repeat->assertRedirect(route('parent.monthly-settlements.index', [
            'month' => '2026-07',
            'payment' => $payment->reference,
        ]));
        $this->assertDatabaseCount('institution_payments', 1);
    }

    public function test_cib_return_flow_marks_pending_when_status_query_returns_pr(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();
        $settings = $this->createCibSettings($institution);
        $statement = $this->createOpenStatement($institution, $guardian, 6100);
        $payment = $this->createPendingPayment($user, $guardian, $statement, 6100);
        $transaction = $this->createPendingTransaction($institution, $payment, 6100, $settings->cib_terminal_id);

        $captured = [];
        $this->fakeMerchantResponses(
            secret: $settings->cib_secret_key,
            pid: $settings->cib_terminal_id,
            responsesByMessageType: [
                '33' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => $transaction->trid,
                    'MSGT' => '31',
                    'AMO' => '6100',
                    'RC' => 'PR',
                    'RT' => 'Feldolgozas alatt',
                ],
            ],
            captured: $captured,
        );

        $response = $this->actingAs($user)->get($this->paymentReturnUrl([
            'PID' => $transaction->pid,
            'TRID' => $transaction->trid,
            'MSGT' => '21',
        ]));

        $response->assertRedirect(route('parent.monthly-settlements.index', [
            'month' => '2026-07',
            'payment' => $payment->reference,
        ]));
        $response->assertSessionHas('info');

        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(ParentMonthlySettlementPayment::STATUS_PENDING, $payment->status);
        $this->assertSame(CibTransaction::STATUS_UNCERTAIN, $transaction->status);
        $this->assertSame('PR', $transaction->final_rc);
        $this->assertDatabaseCount('institution_payments', 0);
        $this->assertCount(1, $captured);
        $this->assertSame('33', $captured[0]['MSGT']);
    }

    public function test_cib_return_flow_marks_failed_when_status_query_returns_to(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();
        $settings = $this->createCibSettings($institution);
        $statement = $this->createOpenStatement($institution, $guardian, 4200);
        $payment = $this->createPendingPayment($user, $guardian, $statement, 4200);
        $transaction = $this->createPendingTransaction($institution, $payment, 4200, $settings->cib_terminal_id);

        $this->fakeMerchantResponses(
            secret: $settings->cib_secret_key,
            pid: $settings->cib_terminal_id,
            responsesByMessageType: [
                '33' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => $transaction->trid,
                    'MSGT' => '31',
                    'AMO' => '4200',
                    'RC' => 'TO',
                    'RT' => 'Idotullepes',
                ],
            ],
        );

        $response = $this->actingAs($user)->get($this->paymentReturnUrl([
            'PID' => $transaction->pid,
            'TRID' => $transaction->trid,
            'MSGT' => '21',
        ]));

        $response->assertRedirect(route('parent.monthly-settlements.index', [
            'month' => '2026-07',
            'payment' => $payment->reference,
        ]));
        $response->assertSessionHasErrors(['payment']);

        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(ParentMonthlySettlementPayment::STATUS_FAILED, $payment->status);
        $this->assertSame(CibTransaction::STATUS_FAILED, $transaction->status);
        $this->assertSame('TO', $transaction->final_rc);
        $this->assertDatabaseCount('institution_payments', 0);
    }

    public function test_cib_return_flow_marks_transaction_uncertain_when_bank_response_amount_is_invalid(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();
        $settings = $this->createCibSettings($institution);
        $statement = $this->createOpenStatement($institution, $guardian, 5600);
        $payment = $this->createPendingPayment($user, $guardian, $statement, 5600);
        $transaction = $this->createPendingTransaction($institution, $payment, 5600, $settings->cib_terminal_id);

        $this->fakeMerchantResponses(
            secret: $settings->cib_secret_key,
            pid: $settings->cib_terminal_id,
            responsesByMessageType: [
                '33' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => $transaction->trid,
                    'MSGT' => '31',
                    'AMO' => '9999',
                    'RC' => '00',
                    'RT' => 'Osszeg hiba',
                    'ANUM' => 'AUTH999',
                ],
            ],
        );

        $response = $this->actingAs($user)->get($this->paymentReturnUrl([
            'PID' => $transaction->pid,
            'TRID' => $transaction->trid,
            'MSGT' => '21',
        ]));

        $response->assertRedirect(route('parent.monthly-settlements.index', [
            'month' => '2026-07',
            'payment' => $payment->reference,
        ]));
        $response->assertSessionHasErrors(['payment']);

        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(ParentMonthlySettlementPayment::STATUS_FAILED, $payment->status);
        $this->assertSame(CibTransaction::STATUS_UNCERTAIN, $transaction->status);
        $this->assertSame('A CIB válasz összege nem egyezik a nyilvántartott összeggel.', $payment->note);
        $this->assertDatabaseCount('institution_payments', 0);
    }

    public function test_successful_cib_payment_remains_successful_when_invoice_creation_fails(): void
    {
        [$user, $institution, $guardian] = $this->createParentContext();
        $settings = $this->createCibSettings($institution, [
            'invoicing_enabled' => true,
            'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
        ]);
        Log::spy();
        $statement = $this->createOpenStatement($institution, $guardian, 8300);
        $payment = $this->createPendingPayment($user, $guardian, $statement, 8300);
        $transaction = $this->createPendingTransaction($institution, $payment, 8300, $settings->cib_terminal_id);

        $invoiceService = Mockery::mock(InstitutionInvoiceService::class);
        $invoiceService->shouldReceive('issueAutomaticInvoiceForPayment')
            ->once()
            ->andThrow(new \RuntimeException('Billingo atmeneti hiba'));
        $this->app->instance(InstitutionInvoiceService::class, $invoiceService);

        $this->fakeMerchantResponses(
            secret: $settings->cib_secret_key,
            pid: $settings->cib_terminal_id,
            responsesByMessageType: [
                '33' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => $transaction->trid,
                    'MSGT' => '31',
                    'AMO' => '8300',
                    'RC' => '00',
                    'RT' => 'Status ok',
                    'ANUM' => 'AUTH8300',
                ],
                '32' => [
                    'PID' => $settings->cib_terminal_id,
                    'TRID' => $transaction->trid,
                    'MSGT' => '31',
                    'AMO' => '8300',
                    'RC' => '00',
                    'RT' => 'Lezarva',
                    'ANUM' => 'AUTH8300',
                ],
            ],
        );

        $response = $this->actingAs($user)->get($this->paymentReturnUrl([
            'PID' => $transaction->pid,
            'TRID' => $transaction->trid,
            'MSGT' => '21',
        ]));

        $response->assertRedirect(route('parent.monthly-settlements.index', [
            'month' => '2026-07',
            'payment' => $payment->reference,
        ]));

        $payment->refresh();
        $transaction->refresh();

        $this->assertSame(ParentMonthlySettlementPayment::STATUS_COMPLETED, $payment->status);
        $this->assertSame(CibTransaction::STATUS_SUCCESSFUL, $transaction->status);
        $this->assertSame('A fizetés sikeres, de a számlázás újrapróbálást igényel.', $payment->note);
        $this->assertSame(['Billingo atmeneti hiba'], data_get($payment->metadata, 'cib.invoice_errors'));
        $this->assertDatabaseCount('institution_payments', 1);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_cib_keyfile_to_base64_command_outputs_last_24_bytes_only(): void
    {
        $path = storage_path('framework/testing/cib-test.des');
        $contents = str_repeat('H', 14).'12345678ABCDEFGH87654321';

        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $contents);

        $this->artisan('cib:keyfile-to-base64', ['path' => $path])
            ->expectsOutput(base64_encode('12345678ABCDEFGH87654321'))
            ->assertExitCode(0);

        @unlink($path);
    }

    public function test_cib_keyfile_to_base64_command_rejects_too_short_file(): void
    {
        $path = storage_path('framework/testing/cib-short.des');
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, str_repeat('A', 23));

        $this->artisan('cib:keyfile-to-base64', ['path' => $path])
            ->expectsOutputToContain('24')
            ->assertExitCode(1);

        @unlink($path);
    }

    private function postPaymentIntent(User $user, string $month): \Illuminate\Testing\TestResponse
    {
        $page = $this->actingAs($user)->get($this->parentUrl('/havi-elszamolasok?month='.$month));
        preg_match('/name="payment_intent_key" value="([^"]+)"/', $page->getContent(), $matches);

        return $this->actingAs($user)->post($this->parentUrl('/havi-elszamolasok/fizetes'), [
            'month' => $month,
            'payment_intent_key' => $matches[1] ?? '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCibSettings(Institution $institution, array $attributes = []): InstitutionSetting
    {
        $settings = InstitutionSetting::create(array_merge([
            'institution_id' => $institution->id,
            'payment_due_day' => 10,
            'card_payment_enabled' => true,
            'card_payment_provider' => InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB,
            'card_payment_test_mode' => true,
            'cib_terminal_id' => 'SNL0001',
            'cib_secret_key' => $this->cibSecret(),
            'invoicing_enabled' => false,
            'invoicing_provider' => null,
        ], $attributes));

        config()->set('cib.default_pid', 'SNL0001');
        config()->set('cib.default_secret_key_base64', $this->cibSecret());
        config()->set('cib.merchant_url', 'https://ekit.cib.hu/market.saki');
        config()->set('cib.customer_url', 'https://ekit.cib.hu/customer.saki');

        return $settings->fresh();
    }

    private function createOpenStatement(Institution $institution, Guardian $guardian, int $amount): MonthlyPaymentStatement
    {
        $child = $this->createChild($institution->id, 'CIB Fizeto Gyermek');
        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        return MonthlyPaymentStatement::create([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 7,
            'meal_amount' => $amount,
            'invoiceable_amount' => $amount,
            'previous_balance' => 0,
            'total_payable' => $amount,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    private function createPendingPayment(User $user, Guardian $guardian, MonthlyPaymentStatement $statement, int $amount): ParentMonthlySettlementPayment
    {
        $payment = ParentMonthlySettlementPayment::create([
            'user_id' => $user->id,
            'guardian_id' => $guardian->id,
            'year' => 2026,
            'month' => 7,
            'reference' => 'CSAL-202607-CIB01',
            'idempotency_key' => 'cib-test-'.$amount.'-'.$statement->id,
            'payment_method' => InstitutionPayment::METHOD_ONLINE,
            'status' => ParentMonthlySettlementPayment::STATUS_PENDING,
            'total_amount' => $amount,
            'metadata' => [],
        ]);

        $payment->items()->create([
            'child_id' => $statement->child_id,
            'monthly_payment_statement_id' => $statement->id,
            'amount' => $amount,
            'paid_amount' => 0,
        ]);

        return $payment->fresh(['items.child']);
    }

    private function createPendingTransaction(Institution $institution, ParentMonthlySettlementPayment $payment, int $amount, string $pid): CibTransaction
    {
        return CibTransaction::create([
            'institution_id' => $institution->id,
            'parent_monthly_settlement_payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'guardian_id' => $payment->guardian_id,
            'pid' => $pid,
            'trid' => '1234567890123456',
            'order_ref' => $payment->reference,
            'amount' => $amount,
            'currency' => 'HUF',
            'status' => CibTransaction::STATUS_PENDING,
            'merchant_url' => (string) config('cib.merchant_url'),
            'customer_url' => (string) config('cib.customer_url'),
            'return_url' => route('parent.monthly-settlements.index', ['month' => '2026-07']),
            'init_requested_at' => now(),
            'init_completed_at' => now(),
            'init_rc' => '00',
            'init_rt' => 'OK',
        ]);
    }

    /**
     * @param  array<string, array<string, string>|string>  $responsesByMessageType
     * @param  array<int, array<string, string>>  $captured
     */
    private function fakeMerchantResponses(string $secret, string $pid, array $responsesByMessageType, array &$captured = []): void
    {
        Http::fake(function (Request $request) use ($secret, $pid, $responsesByMessageType, &$captured) {
            $query = parse_url($request->url(), PHP_URL_QUERY) ?: '';
            $payload = app(CibMessageCrypto::class)->decrypt($query, $secret);
            $captured[] = $payload;

            $messageType = $payload['MSGT'] ?? '';
            $response = $responsesByMessageType[$messageType] ?? 'RC=01&RT=Unexpected%20message';

            if (is_string($response)) {
                return Http::response($response, 200, ['Content-Type' => 'text/plain']);
            }

            $normalized = array_map(
                fn ($value) => $value === '__from_request__' ? ($payload['TRID'] ?? '') : $value,
                $response
            );

            return Http::response(
                $this->encryptedResponse($normalized, $pid, $secret),
                200,
                ['Content-Type' => 'text/plain']
            );
        });
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function encryptedResponse(array $fields, string $pid, string $secret): string
    {
        return app(CibMessageCrypto::class)->encrypt(CibMessage::build($fields), $pid, $secret);
    }

    private function cibSecret(): string
    {
        return base64_encode(str_repeat('H', 14).'12345678ABCDEFGH87654321');
    }

    private function createParentContext(): array
    {
        $institution = Institution::create([
            'name' => 'Szuloi intezmeny',
            'institution_code' => 'PAR100',
            'type' => 'iskola',
            'address_zip' => '1024',
            'address_city' => 'Budapest',
            'address_line' => 'Fo utca 1.',
            'email' => 'intezmeny@example.com',
            'phone' => '+36 1 999 0000',
            'billing_name' => 'Szuloi Intezmeny Fenntarto',
            'billing_tax_number' => '12345678-2-41',
            'billing_zip' => '1024',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Teszt Szulo',
            'email' => 'szulo@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'last_name' => 'Teszt',
            'first_name' => 'Szulo',
            'email' => $user->email,
            'source_type' => 'manual',
            'active' => true,
        ]);

        return [$user, $institution, $guardian];
    }

    private function parentUrl(string $path): string
    {
        return (parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'http').'://'.(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost').((parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '')).'/szulo'.$path;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function paymentReturnUrl(array $query): string
    {
        return (parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'http').'://'.(parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost').((parse_url(config('app.url'), PHP_URL_PORT) ? ':'.parse_url(config('app.url'), PHP_URL_PORT) : '')).'/payments/card/return?'.http_build_query($query);
    }

    private function createChild(int $institutionId, string $name): Child
    {
        $discount = DiscountType::firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => 'Kedvezmeny nelkul',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return Child::create([
            'institution_id' => $institutionId,
            'discount_type_id' => $discount->id,
            'name' => $name,
            'educational_identifier' => substr(md5($name.$institutionId), 0, 10),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);
    }
}
