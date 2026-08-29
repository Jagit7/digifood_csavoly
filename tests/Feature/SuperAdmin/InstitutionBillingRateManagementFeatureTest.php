<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\InstitutionBillingRateController;
use App\Http\Middleware\CheckRole;
use App\Http\Requests\Dashboard\SuperAdmin\UpsertInstitutionBillingRateRequest;
use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstitutionBillingRateManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_billing_rate_index(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE001');
        $institution->billingRates()->create([
            'price_per_child' => '1250.00',
            'valid_from' => '2026-08-01',
        ]);

        $this->actingAs($superAdmin);
        $request = Request::create('/dashboard/institutions/' . $institution->id . '/billing-rates', 'GET');
        $view = app(CheckRole::class)->handle(
            $request,
            fn () => app(InstitutionBillingRateController::class)->index($institution),
            User::ROLE_SUPER_ADMIN
        );

        view()->share('errors', new ViewErrorBag());
        $response = response()->view($view->name(), $view->getData());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Intézményi díjszabások', $response->getContent());
        $this->assertStringContainsString($institution->name, $response->getContent());
        $this->assertStringContainsString('1 250 Ft', $response->getContent());
    }

    public function test_non_superadmin_cannot_view_billing_rate_index(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'is_active' => true,
        ]);
        $institution = $this->createInstitution('RATE002');

        $this->actingAs($user);
        $request = Request::create('/dashboard/institutions/' . $institution->id . '/billing-rates', 'GET');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Nincs jogosultsága az oldal megtekintéséhez.');

        app(CheckRole::class)->handle(
            $request,
            fn () => app(InstitutionBillingRateController::class)->index($institution),
            User::ROLE_SUPER_ADMIN
        );
    }

    public function test_per_child_billing_rate_can_be_created(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE003');
        $request = $this->makeBillingRateRequest($superAdmin, $institution, [
            'price_per_child' => '1400',
            'valid_from' => '2026-08-01',
        ]);

        $response = app(InstitutionBillingRateController::class)->store($request, $institution);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institutions.billing-rates.index', $institution), $response->getTargetUrl());
        $this->assertDatabaseHas('institution_billing_rates', [
            'institution_id' => $institution->id,
            'price_per_child' => 1400,
            'fixed_monthly_fee' => null,
            'valid_from' => '2026-08-01 00:00:00',
        ]);
    }

    public function test_fixed_monthly_billing_rate_can_be_created(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE004');
        $request = $this->makeBillingRateRequest($superAdmin, $institution, [
            'fixed_monthly_fee' => '25000',
            'valid_from' => '2026-08-01',
        ]);

        $response = app(InstitutionBillingRateController::class)->store($request, $institution);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institutions.billing-rates.index', $institution), $response->getTargetUrl());
        $this->assertDatabaseHas('institution_billing_rates', [
            'institution_id' => $institution->id,
            'price_per_child' => null,
            'fixed_monthly_fee' => '25000.00',
        ]);
    }

    public function test_minimum_monthly_fee_can_be_saved(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE005');
        $request = $this->makeBillingRateRequest($superAdmin, $institution, [
            'price_per_child' => '1800',
            'minimum_monthly_fee' => '12000',
            'valid_from' => '2026-08-01',
        ]);

        app(InstitutionBillingRateController::class)->store($request, $institution);

        $this->assertDatabaseHas('institution_billing_rates', [
            'institution_id' => $institution->id,
            'minimum_monthly_fee' => '12000.00',
        ]);
    }

    public function test_error_when_neither_per_child_nor_fixed_fee_is_provided(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE006');
        $exception = $this->captureValidationException($superAdmin, $institution, [
            'valid_from' => '2026-08-01',
        ]);

        $this->assertArrayHasKey('price_per_child', $exception->errors());
        $this->assertArrayHasKey('fixed_monthly_fee', $exception->errors());
    }

    public function test_error_when_both_fee_types_are_provided(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE007');
        $exception = $this->captureValidationException($superAdmin, $institution, [
            'price_per_child' => '1000',
            'fixed_monthly_fee' => '20000',
            'valid_from' => '2026-08-01',
        ]);

        $this->assertArrayHasKey('price_per_child', $exception->errors());
        $this->assertArrayHasKey('fixed_monthly_fee', $exception->errors());
    }

    public function test_negative_amount_cannot_be_saved(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE008');
        $exception = $this->captureValidationException($superAdmin, $institution, [
            'price_per_child' => '-1',
            'valid_from' => '2026-08-01',
        ]);

        $this->assertArrayHasKey('price_per_child', $exception->errors());
    }

    public function test_end_date_cannot_be_earlier_than_start_date(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE009');
        $exception = $this->captureValidationException($superAdmin, $institution, [
            'price_per_child' => '1500',
            'valid_from' => '2026-08-10',
            'valid_to' => '2026-08-09',
        ]);

        $this->assertArrayHasKey('valid_to', $exception->errors());
    }

    public function test_overlapping_period_cannot_be_saved(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE010');
        $institution->billingRates()->create([
            'price_per_child' => '1200.00',
            'valid_from' => '2026-08-01',
            'valid_to' => '2026-08-31',
        ]);

        $exception = $this->captureValidationException($superAdmin, $institution, [
            'price_per_child' => '1300',
            'valid_from' => '2026-08-15',
            'valid_to' => '2026-09-10',
        ]);

        $this->assertSame(
            'A megadott időszak átfed egy már létező díjszabással.',
            $exception->errors()['valid_from'][0]
        );
    }

    public function test_record_overlapping_open_ended_period_cannot_be_saved(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE011');
        $institution->billingRates()->create([
            'fixed_monthly_fee' => '35000.00',
            'valid_from' => '2026-08-01',
            'valid_to' => null,
        ]);

        $exception = $this->captureValidationException($superAdmin, $institution, [
            'fixed_monthly_fee' => '37000',
            'valid_from' => '2026-09-01',
            'valid_to' => '2026-09-30',
        ]);

        $this->assertArrayHasKey('valid_from', $exception->errors());
    }

    public function test_consecutive_non_overlapping_periods_can_be_saved(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE012');
        $institution->billingRates()->create([
            'price_per_child' => '1200.00',
            'valid_from' => '2026-08-01',
            'valid_to' => '2026-08-31',
        ]);

        $request = $this->makeBillingRateRequest($superAdmin, $institution, [
            'price_per_child' => '1300',
            'valid_from' => '2026-09-01',
            'valid_to' => '2026-09-30',
        ]);

        $response = app(InstitutionBillingRateController::class)->store($request, $institution);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(2, $institution->billingRates()->count());
    }

    public function test_billing_rate_can_be_updated(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE013');
        $billingRate = $institution->billingRates()->create([
            'price_per_child' => '1200.00',
            'valid_from' => '2026-08-01',
            'valid_to' => '2026-08-31',
        ]);

        $request = $this->makeBillingRateRequest($superAdmin, $institution, [
            'fixed_monthly_fee' => '28000',
            'valid_from' => '2026-08-01',
            'valid_to' => '2026-08-31',
            'note' => 'Frissített rekord',
        ], $billingRate);

        $response = app(InstitutionBillingRateController::class)->update($request, $institution, $billingRate);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseHas('institution_billing_rates', [
            'id' => $billingRate->id,
            'price_per_child' => null,
            'fixed_monthly_fee' => 28000,
            'note' => 'Frissített rekord',
        ]);
    }

    public function test_billing_rate_cannot_be_edited_through_modified_url_of_other_institution(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $institution = $this->createInstitution('RATE014');
        $otherInstitution = $this->createInstitution('RATE015');
        $billingRate = $institution->billingRates()->create([
            'price_per_child' => '1200.00',
            'valid_from' => '2026-08-01',
        ]);

        $this->actingAs($superAdmin);

        try {
            app(InstitutionBillingRateController::class)->edit($otherInstitution, $billingRate);
            $this->fail('404-es kivételre számítottunk.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::create([
            'name' => 'Intezmeny ' . $code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);
    }

    private function captureValidationException(User $user, Institution $institution, array $payload): ValidationException
    {
        try {
            $this->makeBillingRateRequest($user, $institution, $payload);
        } catch (ValidationException $exception) {
            return $exception;
        }

        $this->fail('ValidationException kivételre számítottunk.');
    }

    private function makeBillingRateRequest(
        User $user,
        Institution $institution,
        array $payload,
        ?InstitutionBillingRate $billingRate = null
    ): UpsertInstitutionBillingRateRequest {
        $request = UpsertInstitutionBillingRateRequest::create(
            route(
                $billingRate
                    ? 'dashboard.institutions.billing-rates.update'
                    : 'dashboard.institutions.billing-rates.store',
                $billingRate ? [$institution, $billingRate] : $institution
            ),
            $billingRate ? 'PUT' : 'POST',
            $payload
        );

        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setRouteResolver(function () use ($institution, $billingRate, $request) {
            $route = new \Illuminate\Routing\Route(
                $billingRate ? 'PUT' : 'POST',
                '/dashboard/institutions/' . $institution->id . '/billing-rates',
                []
            );

            $route->bind($request);
            $route->setParameter('institution', $institution);

            if ($billingRate) {
                $route->setParameter('billingRate', $billingRate);
            }

            return $route;
        });

        $validator = $this->app['validator']->make($request->all(), $request->rules());
        $request->withValidator($validator);
        $validator->validate();
        $request->setValidator($validator);

        return $request;
    }
}
