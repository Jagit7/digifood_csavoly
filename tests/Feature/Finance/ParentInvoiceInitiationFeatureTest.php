<?php

namespace Tests\Feature\Finance;

use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\InstitutionInvoice;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\Finance\InstitutionInvoiceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 2. FÁZIS - Szülő által önállóan indított számlázás (Számlázz.hu/Billingo),
 * egyedi fizetési közlemény, banki adatok megjelenítése és a bővített
 * sztornó-jogosultság rendszer tesztjei.
 *
 * Ez a fájl SZÁNDÉKOSAN külön class a meglévő, nagy
 * tests/Feature/Finance/InstitutionInvoiceFeatureTest.php és
 * tests/Feature/Parent/ParentPortalFeatureTest.php mellett - egyik meglévő
 * fájlt sem módosítja, kizárólag azok már bevált fixture-mintáit
 * (intézmény/gyermek/gondviselő/BillingProfile/MonthlyPaymentStatement
 * közvetlen létrehozása, Http::fake() a Billingo API-ra) veszi át, hogy a
 * meglévő két regressziós csomag (InstitutionInvoiceFeatureTest,
 * ParentPortalFeatureTest) továbbra is önmagában, változatlanul futtatható
 * maradjon.
 */
class ParentInvoiceInitiationFeatureTest extends TestCase
{
    use RefreshDatabase;

    // ----------------------------------------------------------------
    // 1) Szülő saját statementre indíthat számlázást
    // ----------------------------------------------------------------
    public function test_parent_can_initiate_invoicing_for_own_statement(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV01');
        $this->enableBillingoInvoicing($ctx['institution']);
        $this->fakeSuccessfulBillingoIssuance(90001, 'ETK-P-0001');

        $response = $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $response->assertRedirect(route('parent.monthly-settlements.index', [
            'month' => sprintf('%04d-%02d', $ctx['statement']->year, $ctx['statement']->month),
        ]));
        $response->assertSessionHas('success');

        $invoice = InstitutionInvoice::query()
            ->where('monthly_payment_statement_id', $ctx['statement']->id)
            ->firstOrFail();

        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame((int) $ctx['statement']->invoiceable_amount, $invoice->gross_amount);
        $this->assertSame($ctx['parent']->id, $invoice->created_by);
        $this->assertSame(InstitutionInvoice::PROVIDER_BILLINGO, $invoice->provider);
    }

    // ----------------------------------------------------------------
    // 2) Másik szülő gyermekének/statementjének számlázása tiltott
    // ----------------------------------------------------------------
    public function test_parent_cannot_initiate_invoicing_for_foreign_statement(): void
    {
        $owner = $this->seedParentInvoicingContext('PINV02A');
        $foreign = $this->seedParentInvoicingContext('PINV02B');
        $this->enableBillingoInvoicing($foreign['institution']);

        $response = $this->actingAs($owner['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $foreign['statement']));

        $response->assertForbidden();
        $this->assertSame(0, InstitutionInvoice::query()->count());
    }

    // ----------------------------------------------------------------
    // 3) 0 Ft-os (vagy negatív) aktuális havi kötelezettségre tilos számlázni
    // ----------------------------------------------------------------
    public function test_zero_amount_statement_blocks_invoice_creation(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV03', [
            'invoiceable_amount' => 0,
            'total_payable' => 0,
        ]);
        $this->enableBillingoInvoicing($ctx['institution']);

        $response = $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $response->assertSessionHasErrors();
        $this->assertSame(0, InstitutionInvoice::query()->count());
    }

    // ----------------------------------------------------------------
    // 4) A previous_balance SOSEM kerülhet bele a kiállított számla összegébe
    // ----------------------------------------------------------------
    public function test_previous_balance_is_not_included_in_created_invoice_amount(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV04', [
            'invoiceable_amount' => 8000,
            'previous_balance' => 15000,
            'total_payable' => 23000,
        ]);
        $this->enableBillingoInvoicing($ctx['institution']);
        $this->fakeSuccessfulBillingoIssuance(90004, 'ETK-P-0004');

        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $invoice = InstitutionInvoice::query()
            ->where('monthly_payment_statement_id', $ctx['statement']->id)
            ->firstOrFail();

        $this->assertSame(8000, $invoice->gross_amount);
        $this->assertNotSame(23000, $invoice->gross_amount);
    }

    // ----------------------------------------------------------------
    // 5) Korábbi tartozás külön, a kötelező szöveggel jelenik meg
    // ----------------------------------------------------------------
    public function test_previous_debt_is_displayed_separately_with_disclaimer(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV05', [
            'invoiceable_amount' => 6000,
            'previous_balance' => 3000,
            'total_payable' => 9000,
        ]);

        $page = $this->actingAs($ctx['parent'])->get(route('parent.monthly-settlements.index', [
            'month' => sprintf('%04d-%02d', $ctx['statement']->year, $ctx['statement']->month),
        ]));

        $page->assertOk();
        $page->assertSee('Kérjük, korábbi tartozását vagy túlfizetését személyesen rendezze az önkormányzatnál.');
        $page->assertSee('3 000 Ft');
        $page->assertSee('6 000 Ft');
    }

    // ----------------------------------------------------------------
    // 6) Korábbi túlfizetés külön, a kötelező szöveggel jelenik meg
    // ----------------------------------------------------------------
    public function test_previous_overpayment_is_displayed_separately_with_disclaimer(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV06', [
            'invoiceable_amount' => 2000,
            'previous_balance' => -5000,
            'total_payable' => -3000,
        ]);

        $page = $this->actingAs($ctx['parent'])->get(route('parent.monthly-settlements.index', [
            'month' => sprintf('%04d-%02d', $ctx['statement']->year, $ctx['statement']->month),
        ]));

        $page->assertOk();
        $page->assertSee('Kérjük, korábbi tartozását vagy túlfizetését személyesen rendezze az önkormányzatnál.');
        $page->assertSee('Fennmaradó túlfizetés');
        $page->assertSee('3 000 Ft');
    }

    // ----------------------------------------------------------------
    // 7) A payment_reference ténylegesen létrejön, DB-ben tárolva
    // ----------------------------------------------------------------
    public function test_payment_reference_is_created_on_invoice_initiation(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV07');
        $this->enableBillingoInvoicing($ctx['institution']);
        $this->fakeSuccessfulBillingoIssuance(90007, 'ETK-P-0007');

        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $statement = $ctx['statement']->fresh();
        $this->assertNotNull($statement->payment_reference);
        $this->assertMatchesRegularExpression('/^DF-\d{4}-[A-Z0-9]{5}$/', $statement->payment_reference);
    }

    // ----------------------------------------------------------------
    // 8) A payment_reference oszlopon DB-szintű unique constraint/index van
    // ----------------------------------------------------------------
    public function test_payment_reference_column_has_unique_database_constraint(): void
    {
        $ctxA = $this->seedParentInvoicingContext('PINV08A');
        $ctxB = $this->seedParentInvoicingContext('PINV08B');

        $ctxA['statement']->forceFill(['payment_reference' => 'DF-2608-AAAAA'])->save();

        $this->expectException(QueryException::class);

        $ctxB['statement']->forceFill(['payment_reference' => 'DF-2608-AAAAA'])->save();
    }

    // ----------------------------------------------------------------
    // 9) Dupla POST (dupla kattintás/frissítés) nem hoz létre második számlát
    // ----------------------------------------------------------------
    public function test_double_post_does_not_create_second_invoice(): void
    {
        [$ctx] = $this->initiateInvoicingTwice('PINV09', 90009, 'ETK-P-0009');

        $this->assertSame(
            1,
            InstitutionInvoice::query()->where('monthly_payment_statement_id', $ctx['statement']->id)->count()
        );
    }

    // ----------------------------------------------------------------
    // 10) Dupla POST nem generál új payment_reference-t
    // ----------------------------------------------------------------
    public function test_double_post_does_not_generate_new_payment_reference(): void
    {
        [$ctx, $referenceAfterFirst] = $this->initiateInvoicingTwice('PINV10', 90010, 'ETK-P-0010');

        $this->assertNotNull($referenceAfterFirst);
        $this->assertSame($referenceAfterFirst, $ctx['statement']->fresh()->payment_reference);
    }

    // ----------------------------------------------------------------
    // 11) A banki adatok az InstitutionSetting-ből jönnek, nincs hardcode
    // ----------------------------------------------------------------
    public function test_bank_transfer_details_come_from_institution_setting(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV11');
        $this->enableBillingoInvoicing($ctx['institution'], [
            'bank_transfer_account_holder' => 'Csavoly Onkormanyzat Teszt',
            'bank_transfer_account_number' => '12600016-11111111-00000000',
        ]);
        $this->fakeSuccessfulBillingoIssuance(90011, 'ETK-P-0011');

        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $page = $this->actingAs($ctx['parent'])->get(route('parent.monthly-settlements.index', [
            'month' => sprintf('%04d-%02d', $ctx['statement']->year, $ctx['statement']->month),
        ]));

        $page->assertOk();
        $page->assertSee('Csavoly Onkormanyzat Teszt');
        $page->assertSee('12600016-11111111-00000000');
    }

    // ----------------------------------------------------------------
    // 12) A szülő látja a SAJÁT fizetési közleményét
    // ----------------------------------------------------------------
    public function test_parent_sees_own_payment_reference(): void
    {
        [$ctxA, , $referenceA] = $this->initiateInvoicingForTwoParents();

        $pageA = $this->actingAs($ctxA['parent'])->get(route('parent.monthly-settlements.index', [
            'month' => sprintf('%04d-%02d', $ctxA['statement']->year, $ctxA['statement']->month),
        ]));

        $pageA->assertOk();
        $pageA->assertSee($referenceA);
    }

    // ----------------------------------------------------------------
    // 13) A szülő NEM láthatja másik szülő fizetési közleményét
    // ----------------------------------------------------------------
    public function test_parent_cannot_see_foreign_payment_reference(): void
    {
        [$ctxA, , $referenceA, $referenceB] = $this->initiateInvoicingForTwoParents();

        $pageA = $this->actingAs($ctxA['parent'])->get(route('parent.monthly-settlements.index', [
            'month' => sprintf('%04d-%02d', $ctxA['statement']->year, $ctxA['statement']->month),
        ]));

        $pageA->assertOk();
        $pageA->assertDontSee($referenceB);
        $this->assertNotSame($referenceA, $referenceB);
    }

    // ----------------------------------------------------------------
    // 14) Admin tud keresni fizetési közlemény (payment_reference) szerint
    // ----------------------------------------------------------------
    public function test_admin_can_search_invoices_by_payment_reference(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV14');
        $admin = $this->attachInstitutionAdmin($ctx['institution'], 'PINV14');
        $this->enableBillingoInvoicing($ctx['institution']);
        $this->fakeSuccessfulBillingoIssuance(90014, 'ETK-P-0014');

        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));
        $reference = $ctx['statement']->fresh()->payment_reference;

        $response = $this->actingAs($admin)->get(route('dashboard.institution.finance.invoices', [
            'search' => $reference,
        ]));

        $response->assertOk();
        $response->assertSee($reference);
        $response->assertSee('ETK-P-0014');
    }

    // ----------------------------------------------------------------
    // 15) Admin tud keresni számlaszám szerint (a bővítés ezt sem törte el)
    // ----------------------------------------------------------------
    public function test_admin_can_search_invoices_by_invoice_number(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV15');
        $admin = $this->attachInstitutionAdmin($ctx['institution'], 'PINV15');
        $this->enableBillingoInvoicing($ctx['institution']);
        $this->fakeSuccessfulBillingoIssuance(90015, 'ETK-P-0015');

        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $response = $this->actingAs($admin)->get(route('dashboard.institution.finance.invoices', [
            'search' => 'ETK-P-0015',
        ]));

        $response->assertOk();
        $response->assertSee('ETK-P-0015');
    }

    // ----------------------------------------------------------------
    // 16) institution_admin mindig tud sztornózni
    // ----------------------------------------------------------------
    public function test_institution_admin_can_cancel_invoice(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV16');
        $this->enableBillingoInvoicing($ctx['institution']);
        $admin = $this->attachInstitutionAdmin($ctx['institution'], 'PINV16');
        Storage::fake('local');
        $invoice = $this->createIssuedBillingoInvoiceRecord($ctx, $admin, '12016', 'ETK-P-0016');
        $this->fakeSuccessfulBillingoCancellation('12016', 90916, 'STORNO-P-0016');

        $response = $this->actingAs($admin)->post(route('dashboard.institution.finance.invoices.cancel', $invoice), [
            'reason' => 'Teszt admin sztorno',
        ]);

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $this->assertSame(InstitutionInvoice::STATUS_VOIDED, $invoice->fresh()->status);
    }

    // ----------------------------------------------------------------
    // 17) institution_secretary dedikált jogosultság NÉLKÜL nem sztornózhat
    // ----------------------------------------------------------------
    public function test_secretary_without_permission_cannot_cancel_invoice(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV17');
        $this->enableBillingoInvoicing($ctx['institution']);
        $admin = $this->attachInstitutionAdmin($ctx['institution'], 'PINV17A');
        $secretary = $this->attachInstitutionSecretary($ctx['institution'], 'PINV17');
        Storage::fake('local');
        $invoice = $this->createIssuedBillingoInvoiceRecord($ctx, $admin, '12017', 'ETK-P-0017');

        $response = $this->actingAs($secretary)->post(route('dashboard.institution.finance.invoices.cancel', $invoice), [
            'reason' => 'Titkari probalkozas jogosultsag nelkul',
        ]);

        $response->assertForbidden();
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->fresh()->status);
    }

    // ----------------------------------------------------------------
    // 18) institution_secretary dedikált jogosultsággal MÁR sztornózhat
    // ----------------------------------------------------------------
    public function test_secretary_with_permission_can_cancel_invoice(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV18');
        $this->enableBillingoInvoicing($ctx['institution']);
        $admin = $this->attachInstitutionAdmin($ctx['institution'], 'PINV18A');
        $secretary = $this->attachInstitutionSecretary($ctx['institution'], 'PINV18');
        $secretary->forceFill(['permissions' => [InstitutionInvoice::PERMISSION_CANCEL => true]])->save();
        Storage::fake('local');
        $invoice = $this->createIssuedBillingoInvoiceRecord($ctx, $admin, '12018', 'ETK-P-0018');
        $this->fakeSuccessfulBillingoCancellation('12018', 90918, 'STORNO-P-0018');

        $response = $this->actingAs($secretary)->post(route('dashboard.institution.finance.invoices.cancel', $invoice), [
            'reason' => 'Titkari jogosult sztorno',
        ]);

        $response->assertRedirect(route('dashboard.institution.finance.invoices.show', $invoice));
        $this->assertSame(InstitutionInvoice::STATUS_VOIDED, $invoice->fresh()->status);
    }

    // ----------------------------------------------------------------
    // 19) parent SOHA nem indíthat admin sztornót
    // ----------------------------------------------------------------
    public function test_parent_cannot_initiate_cancel(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV19');
        $this->enableBillingoInvoicing($ctx['institution']);
        $admin = $this->attachInstitutionAdmin($ctx['institution'], 'PINV19');
        Storage::fake('local');
        $invoice = $this->createIssuedBillingoInvoiceRecord($ctx, $admin, '12019', 'ETK-P-0019');

        $response = $this->actingAs($ctx['parent'])->post(route('dashboard.institution.finance.invoices.cancel', $invoice), [
            'reason' => 'Szulo probalkozas',
        ]);

        $response->assertForbidden();
        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->fresh()->status);
    }

    // ----------------------------------------------------------------
    // 20) Szolgáltatói hiba esetén nincs "issued" félkész számla, és az
    // ismételt próbálkozás nem hoz létre vakon második sort
    // ----------------------------------------------------------------
    public function test_provider_failure_leaves_no_issued_invoice(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV20');
        $this->enableBillingoInvoicing($ctx['institution']);
        $this->fakeFailingBillingoIssuance();

        $response = $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $response->assertRedirect();

        $invoice = InstitutionInvoice::query()
            ->where('monthly_payment_statement_id', $ctx['statement']->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InstitutionInvoice::STATUS_FAILED, $invoice->status);
        $this->assertNotSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);

        $this->fakeFailingBillingoIssuance();
        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']));

        $this->assertSame(
            1,
            InstitutionInvoice::query()->where('monthly_payment_statement_id', $ctx['statement']->id)->count()
        );
    }

    // ----------------------------------------------------------------
    // 21) Billingo regresszió: az admin által kézzel indított számlázás (a
    // 2. fázisban bővített InstitutionInvoiceService::query()/store() mellett
    // is) változatlanul működik. EZ NEM helyettesíti a teljes meglévő
    // InstitutionInvoiceFeatureTest csomag lefuttatását, csak egy gyors,
    // dedikált megerősítés.
    // ----------------------------------------------------------------
    public function test_billingo_admin_invoice_creation_still_works_after_phase2_changes(): void
    {
        $ctx = $this->seedParentInvoicingContext('PINV21');
        $this->enableBillingoInvoicing($ctx['institution']);
        $admin = $this->attachInstitutionAdmin($ctx['institution'], 'PINV21');
        $this->fakeSuccessfulBillingoIssuance(90021, 'ETK-P-0021');

        $invoice = app(InstitutionInvoiceService::class)->store($ctx['institution'], $admin, [
            'monthly_payment_statement_id' => $ctx['statement']->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'payment_method' => 'bank_transfer',
            'due_date' => '2026-08-20',
            'fulfillment_date' => '2026-08-31',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'customer_tax_number' => null,
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'note' => 'Phase2 regresszio teszt',
        ]);

        $this->assertSame(InstitutionInvoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame('ETK-P-0021', $invoice->invoice_number);
    }

    // ==================================================================
    // Segéd metódusok (a meglévő InstitutionInvoiceFeatureTest /
    // ParentPortalFeatureTest fájlok bevált mintáit követve)
    // ==================================================================

    /**
     * @return array{institution: Institution, parent: User, guardian: Guardian, child: Child, statement: MonthlyPaymentStatement}
     */
    private function seedParentInvoicingContext(string $code, array $statementOverrides = []): array
    {
        $institution = Institution::create([
            'name' => 'Szamlazasi Intezmeny '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $parent = User::factory()->create([
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'user_id' => $parent->id,
            'last_name' => 'Szulo',
            'first_name' => $code,
            'email' => strtolower($code).'@example.test',
            'postal_code' => '1111',
            'city' => 'Budapest',
            'street_name' => 'Fo',
            'street_type' => 'utca',
            'house_number' => '1',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => 'Alap '.$code,
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Gyermek '.$code,
            'educational_identifier' => 'ED'.$code,
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $child->guardians()->attach($guardian->id, ['created_at' => now(), 'updated_at' => now()]);

        // A billingSnapshot() (InstitutionInvoiceService) a vevő nevét/címét
        // KIZÁRÓLAG egy aktív BillingProfile-ból olvassa (a guardian saját
        // cím-mezői erre NEM elegendőek) - ld. a meglévő
        // seedStatementWithBillingProfile() mintáját.
        $billingProfile = BillingProfile::create([
            'institution_id' => $institution->id,
            'guardian_id' => $guardian->id,
            'payer_type' => 'guardian',
            'billing_name' => 'Szulo Payer '.$code,
            'tax_number' => null,
            'postal_code' => '1111',
            'city' => 'Budapest',
            'address' => 'Fo utca 1.',
            'email' => $guardian->email,
            'payment_method' => 'bank_transfer',
            'active' => true,
        ]);

        DB::table('billing_profile_child')->insert([
            'billing_profile_id' => $billingProfile->id,
            'child_id' => $child->id,
            'is_primary' => true,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statement = MonthlyPaymentStatement::create(array_merge([
            'institution_id' => $institution->id,
            'child_id' => $child->id,
            'year' => 2026,
            'month' => 8,
            'meal_amount' => 8000,
            'invoiceable_amount' => 8000,
            'previous_balance' => 0,
            'total_payable' => 8000,
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
        ], $statementOverrides));

        return compact('institution', 'parent', 'guardian', 'child', 'statement');
    }

    private function enableBillingoInvoicing(Institution $institution, array $overrides = []): void
    {
        InstitutionSetting::updateOrCreate(
            ['institution_id' => $institution->id],
            array_merge(InstitutionSetting::defaults(), [
                'invoicing_enabled' => true,
                'invoicing_provider' => InstitutionSetting::INVOICING_PROVIDER_BILLINGO,
                'billingo_api_key' => 'billingo-test-key',
                'billingo_document_block_id' => '77',
                'bank_transfer_account_holder' => 'Teszt Onkormanyzat',
                'bank_transfer_account_number' => '11111111-22222222-33333333',
            ], $overrides)
        );

        // Ld. InstitutionInvoiceFeatureTest::forgetCachedInstitutionSettingRelation()
        // kommentjét: a settings mentése UTÁN törölni kell az esetlegesen már
        // (null eredménnyel) gyorsítótárazott 'setting' relációt ugyanazon a
        // PHP-objektumon.
        $institution->unsetRelation('setting');
    }

    private function attachInstitutionAdmin(Institution $institution, string $code): User
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $admin->id,
            'scope_role' => User::ROLE_INSTITUTION_ADMIN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin;
    }

    private function attachInstitutionSecretary(Institution $institution, string $code): User
    {
        $secretary = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_SECRETARY,
            'institution_id' => $institution->id,
            'is_active' => true,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $secretary->id,
            'scope_role' => User::ROLE_INSTITUTION_SECRETARY,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $secretary;
    }

    private function fakeSuccessfulBillingoIssuance(int $providerId, string $invoiceNumber): void
    {
        Http::fake(function (ClientRequest $request) use ($providerId, $invoiceNumber) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents') {
                return Http::response([
                    'id' => $providerId,
                    'invoice_number' => $invoiceNumber,
                    'public_url' => 'https://billingo.test/invoice/'.$providerId,
                ], 201);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/partners') {
                return Http::response(['id' => 555], 201);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/'.$providerId.'/download') {
                return Http::response("%PDF-1.7\nparent invoice pdf", 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('unexpected request', 500);
        });
    }

    private function fakeFailingBillingoIssuance(): void
    {
        Http::fake(function (ClientRequest $request) {
            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/partners') {
                return Http::response(['id' => 555], 201);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://api.billingo.hu/v3/documents') {
                return Http::response(['message' => 'Teszt szolgaltatoi hiba'], 500);
            }

            return Http::response('unexpected request', 500);
        });
    }

    private function fakeSuccessfulBillingoCancellation(string $originalProviderId, int $cancellationProviderId, string $cancellationInvoiceNumber): void
    {
        Http::fake(function (ClientRequest $request) use ($originalProviderId, $cancellationProviderId, $cancellationInvoiceNumber) {
            if ($request->method() === 'POST' && $request->url() === "https://api.billingo.hu/v3/documents/{$originalProviderId}/cancel") {
                return Http::response([
                    'id' => $cancellationProviderId,
                    'invoice_number' => $cancellationInvoiceNumber,
                    'public_url' => 'https://billingo.test/invoice/'.$cancellationProviderId,
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://api.billingo.hu/v3/documents/'.$cancellationProviderId.'/download') {
                return Http::response("%PDF-1.7\nstorno pdf", 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('unexpected request', 500);
        });
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function initiateInvoicingTwice(string $code, int $providerId, string $invoiceNumber): array
    {
        $ctx = $this->seedParentInvoicingContext($code);
        $this->enableBillingoInvoicing($ctx['institution']);
        $this->fakeSuccessfulBillingoIssuance($providerId, $invoiceNumber);

        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']))
            ->assertRedirect();

        $referenceAfterFirst = $ctx['statement']->fresh()->payment_reference;

        $this->actingAs($ctx['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctx['statement']))
            ->assertRedirect();

        return [$ctx, $referenceAfterFirst];
    }

    /**
     * @return array{0: array, 1: array, 2: string|null, 3: string|null}
     */
    private function initiateInvoicingForTwoParents(): array
    {
        $ctxA = $this->seedParentInvoicingContext('PINV12A');
        $ctxB = $this->seedParentInvoicingContext('PINV12B');
        $this->enableBillingoInvoicing($ctxA['institution']);
        $this->enableBillingoInvoicing($ctxB['institution']);

        $this->fakeSuccessfulBillingoIssuance(90121, 'ETK-P-12A');
        $this->actingAs($ctxA['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctxA['statement']));

        $this->fakeSuccessfulBillingoIssuance(90122, 'ETK-P-12B');
        $this->actingAs($ctxB['parent'])
            ->post(route('parent.monthly-settlements.invoice.store', $ctxB['statement']));

        $referenceA = $ctxA['statement']->fresh()->payment_reference;
        $referenceB = $ctxB['statement']->fresh()->payment_reference;

        return [$ctxA, $ctxB, $referenceA, $referenceB];
    }

    private function createIssuedBillingoInvoiceRecord(array $ctx, User $creator, string $providerInvoiceId, string $invoiceNumber): InstitutionInvoice
    {
        $invoice = new InstitutionInvoice();
        $invoice->fill([
            'institution_id' => $ctx['institution']->id,
            'child_id' => $ctx['statement']->child_id,
            'guardian_id' => $ctx['guardian']->id,
            'monthly_payment_statement_id' => $ctx['statement']->id,
            'provider' => InstitutionInvoice::PROVIDER_BILLINGO,
            'document_type' => InstitutionInvoice::DOCUMENT_TYPE_ORIGINAL,
            'provider_invoice_id' => $providerInvoiceId,
            'invoice_number' => $invoiceNumber,
            'status' => InstitutionInvoice::STATUS_ISSUED,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-08',
            'fulfillment_date' => '2026-07-31',
            'net_amount' => (int) $ctx['statement']->invoiceable_amount,
            'vat_amount' => 0,
            'gross_amount' => (int) $ctx['statement']->invoiceable_amount,
            'currency' => 'HUF',
            'payment_method' => 'bank_transfer',
            'customer_name' => 'Szulo Payer',
            'customer_email' => 'szulo@example.test',
            'billing_postcode' => '1111',
            'billing_city' => 'Budapest',
            'billing_address' => 'Fo utca 1.',
            'invoice_url' => 'https://billingo.test/invoice/'.$providerInvoiceId,
        ]);
        $invoice->institution_id = $ctx['institution']->id;
        $invoice->created_by = $creator->id;
        $invoice->save();

        return $invoice;
    }
}
