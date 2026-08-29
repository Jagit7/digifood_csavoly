<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\BillingPartnerController;
use App\Http\Controllers\InstitutionController;
use App\Http\Middleware\CheckRole;
use App\Models\BillingPartner;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BillingPartnerManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_billing_partner_index(): void
    {
        $superAdmin = $this->createSuperAdmin();

        BillingPartner::create([
            'name' => 'Alfa Partner',
            'vat_rate' => '27.00',
            'active' => true,
        ]);

        $this->actingAs($superAdmin);
        $request = Request::create('/dashboard/superadmin/billing-partners', 'GET');
        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(BillingPartnerController::class)->index(),
            User::ROLE_SUPER_ADMIN
        );
        view()->share('errors', new ViewErrorBag());
        $response = response()->view($view->name(), $view->getData());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Számlázási partnerek', $response->getContent());
        $this->assertStringContainsString('Alfa Partner', $response->getContent());
    }

    public function test_non_superadmin_cannot_view_billing_partner_index(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $request = Request::create('/dashboard/superadmin/billing-partners', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $request,
            fn () => app(BillingPartnerController::class)->index(),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_billing_partner_can_be_created(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $request = $this->makeWebRequest('POST', route('dashboard.billing-partners.store'), [
                'name' => 'Beta Partner',
                'billing_name' => 'Beta Kft.',
                'tax_number' => '12345678-1-42',
                'billing_zip' => '1111',
                'billing_city' => 'Budapest',
                'billing_address' => 'Fo utca 1.',
                'billing_email' => 'beta@example.test',
                'payment_due_days' => 15,
                'vat_rate' => '27.00',
                'invoice_note' => 'Megjegyzes',
                'active' => '1',
            ], $superAdmin);
        $response = app(BillingPartnerController::class)->store($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.billing-partners.index'), $response->getTargetUrl());

        $this->assertDatabaseHas('billing_partners', [
            'name' => 'Beta Partner',
            'billing_name' => 'Beta Kft.',
            'tax_number' => '12345678-1-42',
            'billing_email' => 'beta@example.test',
            'payment_due_days' => 15,
            'active' => true,
        ]);
    }

    public function test_billing_partner_can_be_updated(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = BillingPartner::create([
            'name' => 'Gamma Partner',
            'vat_rate' => '27.00',
            'active' => true,
        ]);

        $request = $this->makeWebRequest('PUT', route('dashboard.billing-partners.update', $partner), [
                'name' => 'Gamma Partner Modositva',
                'billing_name' => 'Gamma Holding',
                'tax_number' => '87654321-1-42',
                'billing_zip' => '2222',
                'billing_city' => 'Szeged',
                'billing_address' => 'Kossuth ter 2.',
                'billing_email' => 'gamma@example.test',
                'payment_due_days' => 30,
                'vat_rate' => '5.00',
                'invoice_note' => 'Frissitett megjegyzes',
                'active' => '0',
            ], $superAdmin);
        $response = app(BillingPartnerController::class)->update($request, $partner);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.billing-partners.index'), $response->getTargetUrl());

        $this->assertDatabaseHas('billing_partners', [
            'id' => $partner->id,
            'name' => 'Gamma Partner Modositva',
            'billing_name' => 'Gamma Holding',
            'tax_number' => '87654321-1-42',
            'billing_city' => 'Szeged',
            'payment_due_days' => 30,
            'active' => false,
        ]);
    }

    public function test_multiple_institutions_can_be_assigned_to_billing_partner(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = $this->createBillingPartner('Delta Partner');
        $firstInstitution = $this->createInstitution('DELTA01');
        $secondInstitution = $this->createInstitution('DELTA02');

        $request = $this->makeWebRequest('PUT', route('dashboard.billing-partners.update', $partner), [
                'name' => $partner->name,
                'vat_rate' => '27.00',
                'active' => '1',
                'institution_ids' => [$firstInstitution->id, $secondInstitution->id],
            ], $superAdmin);
        $response = app(BillingPartnerController::class)->update($request, $partner);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.billing-partners.index'), $response->getTargetUrl());

        $this->assertSame($partner->id, $firstInstitution->fresh()->billing_partner_id);
        $this->assertSame($partner->id, $secondInstitution->fresh()->billing_partner_id);
    }

    public function test_institution_can_be_removed_from_billing_partner(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = $this->createBillingPartner('Epszilon Partner');
        $firstInstitution = $this->createInstitution('EPSZ01', $partner->id);
        $secondInstitution = $this->createInstitution('EPSZ02', $partner->id);

        $request = $this->makeWebRequest('PUT', route('dashboard.billing-partners.update', $partner), [
                'name' => $partner->name,
                'vat_rate' => '27.00',
                'active' => '1',
                'institution_ids' => [$firstInstitution->id],
            ], $superAdmin);
        $response = app(BillingPartnerController::class)->update($request, $partner);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.billing-partners.index'), $response->getTargetUrl());

        $this->assertSame($partner->id, $firstInstitution->fresh()->billing_partner_id);
        $this->assertNull($secondInstitution->fresh()->billing_partner_id);
    }

    public function test_institution_belonging_to_other_partner_cannot_be_taken_over_silently(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $currentPartner = $this->createBillingPartner('Fo Partner');
        $otherPartner = $this->createBillingPartner('Masik Partner');
        $foreignInstitution = $this->createInstitution('FOREIGN1', $otherPartner->id);

        $request = $this->makeWebRequest('PUT', route('dashboard.billing-partners.update', $currentPartner), [
                'name' => $currentPartner->name,
                'vat_rate' => '27.00',
                'active' => '1',
                'institution_ids' => [$foreignInstitution->id],
            ], $superAdmin, route('dashboard.billing-partners.edit', $currentPartner));
        $response = app(BillingPartnerController::class)->update($request, $currentPartner);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertNotNull($request->session()->get('errors'));
        $this->assertSame($otherPartner->id, $foreignInstitution->fresh()->billing_partner_id);
    }

    public function test_billing_partner_can_be_set_directly_on_institution_edit_page(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $partner = $this->createBillingPartner('Direkt Partner');
        $institution = $this->createInstitution('DIRECT1');

        $this->actingAs($superAdmin);
        $editView = app(InstitutionController::class)->edit($institution);
        view()->share('errors', new ViewErrorBag());
        $editResponse = response()->view($editView->name(), $editView->getData());

        $this->assertSame(200, $editResponse->getStatusCode());
        $this->assertStringContainsString('Számlázási partner', $editResponse->getContent());
        $this->assertStringContainsString('Direkt Partner', $editResponse->getContent());

        $request = $this->makeWebRequest('PUT', route('dashboard.institutions.update', $institution), [
                'name' => $institution->name,
                'type' => $institution->type,
                'active' => '1',
                'billing_partner_id' => $partner->id,
                'barcode_entry_enabled' => '0',
            ], $superAdmin);
        $updateResponse = app(InstitutionController::class)->update($request, $institution);

        $this->assertInstanceOf(RedirectResponse::class, $updateResponse);
        $this->assertSame(route('dashboard.institutions.index'), $updateResponse->getTargetUrl());
        $this->assertSame($partner->id, $institution->fresh()->billing_partner_id);
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function createBillingPartner(string $name): BillingPartner
    {
        return BillingPartner::create([
            'name' => $name,
            'vat_rate' => '27.00',
            'active' => true,
        ]);
    }

    private function createInstitution(string $code, ?int $billingPartnerId = null): Institution
    {
        return Institution::create([
            'name' => 'Intezmeny ' . $code,
            'institution_code' => $code,
            'type' => 'iskola',
            'billing_partner_id' => $billingPartnerId,
            'active' => true,
        ]);
    }

    private function makeWebRequest(string $method, string $uri, array $payload, User $user, ?string $referer = null): FormRequest
    {
        $server = $referer ? ['HTTP_REFERER' => $referer] : [];
        $baseRequest = Request::create($uri, $method, $payload, [], [], $server);
        $request = FormRequest::createFromBase($baseRequest);
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->start();

        return $request;
    }
}
