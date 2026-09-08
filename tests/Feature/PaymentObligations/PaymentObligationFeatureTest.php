<?php

namespace Tests\Feature\PaymentObligations;

use App\Http\Controllers\Dashboard\InstitutionAdmin\FinanceController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\PaymentObligationController;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\PaymentObligationIndexRequest;
use App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation\ReopenMonthRequest;
use App\Models\Child;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionMealPackageItem;
use App\Models\InstitutionMealPrice;
use App\Models\InstitutionMealType;
use App\Models\MealType;
use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\StudentMealSetting;
use App\Models\User;
use App\Services\PaymentObligation\MonthlyPaymentStatementExportService;
use App\Services\PaymentObligation\MonthlyPaymentSummaryExportService;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use App\Support\PaymentObligation\MonthlyPaymentStatementDetailPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PaymentObligationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_institution_statement_is_forbidden(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        [, $otherUser] = $this->seedUserWithInstitution('MASIK01');

        $response = $this->actingAs($otherUser)->get(route('dashboard.institution.payment-obligations.show', ['statement' => $statement->id]));

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_closed_month_day_cannot_be_modified(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $statement->update(['status' => MonthlyPaymentStatement::STATUS_CLOSED]);
        $day = $statement->days()->firstOrFail();

        $response = $this->actingAs($user)->put(route('dashboard.institution.payment-obligations.days.update', [
            'statement' => $statement->id,
            'day' => $day->id,
        ]), [
            'status' => 'PAY',
            'payable_amount' => 900,
            'modification_reason' => 'Teszt modositas',
        ]);

        $this->assertContains($response->getStatusCode(), [404, 422]);
    }

    public function test_detail_presenter_uses_human_friendly_status_labels(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        InstitutionMealPrice::query()->update(['valid_to' => '2026-07-03']);
        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = $statement->fresh([
            'child.discountType',
            'mealPackage',
            'days.cancellation',
            'days.schoolBreak',
            'days.classCancellation',
            'days.workingDay',
            'days.modifiedBy',
        ]);

        $presenter = app(MonthlyPaymentStatementDetailPresenter::class);
        $rows = $presenter->rows($statement);
        $missingPriceRow = $rows->firstWhere('final_status_label', 'Nincs érvényes ár');

        $this->assertNotNull($missingPriceRow);
        $this->assertSame('Nincs érvényes ár', $missingPriceRow['final_status_label']);
        $this->assertStringContainsString('érvényes ár', $missingPriceRow['note_export']);
    }

    public function test_export_service_downloads_xlsx_with_human_labels(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        $statement->load([
            'child.discountType',
            'mealPackage',
            'days.cancellation',
            'days.schoolBreak',
            'days.classCancellation',
            'days.workingDay',
            'days.modifiedBy',
        ]);

        $response = app(MonthlyPaymentStatementExportService::class)->export($statement);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        $this->assertStringContainsString('feature-gyermek-havi-reszletezo-2026-07.xlsx', (string) $response->headers->get('content-disposition'));

        $filePath = $response->getFile()->getPathname();
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame(1200, $sheet->getCell('E4')->getValue());
        $this->assertSame('# ##0 "Ft"', $sheet->getStyle('E4')->getNumberFormat()->getFormatCode());
    }

    public function test_export_route_uses_child_year_and_month_parameters(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        $url = route('dashboard.institution.payment-obligations.export', [
            'child' => $statement->child_id,
            'year' => $statement->year,
            'month' => $statement->month,
        ]);

        $this->assertStringContainsString('/dashboard/institution-admin/payment-obligations/'.$statement->child_id.'/'.$statement->year.'/'.$statement->month.'/export', $url);
    }

    public function test_monthly_summary_export_route_uses_year_and_month_parameters(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        $url = route('dashboard.institution.payment-obligations.monthly-summary.export', [
            'year' => $statement->year,
            'month' => $statement->month,
        ]);

        $this->assertStringContainsString('/dashboard/institution-admin/payment-obligations/monthly-summary/'.$statement->year.'/'.$statement->month.'/export', $url);
    }

    public function test_monthly_summary_export_service_downloads_xlsx(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $statement->load(['child.billingProfiles', 'days']);

        $response = app(MonthlyPaymentSummaryExportService::class)->export(
            collect([$statement]),
            Carbon::create($statement->year, $statement->month, 1)
        );

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        $this->assertStringContainsString('Digifood_teljes_havi_osszesito_2026_07.xlsx', (string) $response->headers->get('content-disposition'));

        $filePath = $response->getFile()->getPathname();
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Gyermek neve', $sheet->getCell('B1')->getValue());
        $this->assertSame('Következő havi étkezések', $sheet->getCell('AK1')->getValue());
        $this->assertSame(1200, $sheet->getCell('F2')->getValue());
        $this->assertSame('# ##0 "Ft"', $sheet->getStyle('AN2')->getNumberFormat()->getFormatCode());
    }

    public function test_monthly_summary_export_controller_downloads_xlsx_for_authenticated_institution(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        $this->actingAs($user);
        $response = app(PaymentObligationController::class)->exportMonthlySummary($statement->year, $statement->month);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        $this->assertStringContainsString('Digifood_teljes_havi_osszesito_2026_07.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_index_page_contains_routes_period_header_and_reopen_ui(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/institution_admin/payment_obligations/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("route('dashboard.institution.payment-obligations.recalculate')", $view);
        $this->assertStringContainsString("route('dashboard.institution.payment-obligations.close')", $view);
        $this->assertStringContainsString("route('dashboard.institution.payment-obligations.reopen')", $view);
        $this->assertStringContainsString("route('dashboard.institution.payment-obligations.monthly-summary.export'", $view);
        $this->assertStringContainsString('payment-period-hero', $view);
        $this->assertStringContainsString('Részben lezárt hónap', $view);
        $this->assertStringContainsString('Nyitott hónap', $view);
        $this->assertStringContainsString('Hónap újranyitása', $view);
        $this->assertStringContainsString('Fizetési hónap:', $view);
        $this->assertStringContainsString('Étkezési időszak:', $view);
        $this->assertStringContainsString('Jóváírási időszak:', $view);
        $this->assertStringContainsString('Havi előírás', $view);
        $this->assertStringContainsString('Tényleges fizetendő', $view);
    }

    public function test_show_page_contains_period_labels_and_next_month_wording(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/institution_admin/payment_obligations/show.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('Fizetési hónap:', $view);
        $this->assertStringContainsString('Étkezési időszak:', $view);
        $this->assertStringContainsString('Jóváírási időszak:', $view);
        $this->assertStringContainsString('Havi előírás', $view);
        $this->assertStringContainsString('A napi részletező sorai az étkezési időszak napjaihoz tartoznak', $view);
    }

    public function test_index_status_flags_mark_open_month(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $this->actingAs($user);

        $view = app(PaymentObligationController::class)->index($this->makeIndexRequest($user, '2026-07'));
        $data = $view->getData();

        $this->assertTrue($data['isOpen']);
        $this->assertFalse($data['isClosed']);
        $this->assertFalse($data['isPartiallyClosed']);
    }

    public function test_index_status_flags_mark_closed_month(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        MonthlyPaymentStatement::query()->update(['status' => MonthlyPaymentStatement::STATUS_CLOSED]);
        $this->actingAs($user);

        $view = app(PaymentObligationController::class)->index($this->makeIndexRequest($user, '2026-07'));
        $data = $view->getData();

        $this->assertTrue($data['isClosed']);
        $this->assertFalse($data['isOpen']);
        $this->assertFalse($data['isPartiallyClosed']);
    }

    public function test_index_status_flags_mark_partially_closed_month(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();

        $secondChild = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $statement->child->discount_type_id,
            'name' => 'Masodik Gyermek',
            'educational_identifier' => 'FGY002',
            'group_name' => '2.B',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        StudentMealSetting::create([
            'student_id' => $secondChild->id,
            'institution_id' => $institution->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));
        MonthlyPaymentStatement::query()->where('child_id', $statement->child_id)->update(['status' => MonthlyPaymentStatement::STATUS_CLOSED]);
        $this->actingAs($user);

        $view = app(PaymentObligationController::class)->index($this->makeIndexRequest($user, '2026-07'));
        $data = $view->getData();

        $this->assertTrue($data['isPartiallyClosed']);
        $this->assertFalse($data['isClosed']);
        $this->assertFalse($data['isOpen']);
    }

    public function test_recalculate_rejects_closed_month(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $statement->update(['status' => MonthlyPaymentStatement::STATUS_CLOSED]);
        $this->actingAs($user);

        $request = Request::create(route('dashboard.institution.payment-obligations.recalculate'), 'POST', [
            'month' => '2026-07',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(PaymentObligationController::class)->recalculate($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.payment-obligations.index', ['month' => '2026-07']), $response->getTargetUrl());
        $this->assertNotNull($response->getSession()->get('error'));
    }

    public function test_close_rejects_already_closed_month(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $statement->update(['status' => MonthlyPaymentStatement::STATUS_CLOSED]);
        $this->actingAs($user);

        $request = Request::create(route('dashboard.institution.payment-obligations.close'), 'POST', [
            'month' => '2026-07',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(PaymentObligationController::class)->close($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.payment-obligations.index', ['month' => '2026-07']), $response->getTargetUrl());
        $this->assertNotNull($response->getSession()->get('error'));
    }

    public function test_reopen_restores_closed_statements_to_draft_and_saves_audit_fields(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        MonthlyPaymentStatement::query()->update([
            'status' => MonthlyPaymentStatement::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);
        $this->actingAs($user);

        $request = $this->makeReopenRequest($user, '2026-07', 'Teszt ujranyitas');

        $response = app(PaymentObligationController::class)->reopen($request);
        $statement = $statement->fresh();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(MonthlyPaymentStatement::STATUS_DRAFT, $statement->status);
        $this->assertNull($statement->closed_at);
        $this->assertNull($statement->closed_by);
        $this->assertNotNull($statement->reopened_at);
        $this->assertSame($user->id, $statement->reopened_by);
        $this->assertSame('Teszt ujranyitas', $statement->reopen_reason);
    }

    public function test_reopen_rejects_month_without_closed_statements(): void
    {
        [$institution, $user, $statement] = $this->seedStatement();
        $this->actingAs($user);

        $request = $this->makeReopenRequest($user, '2026-07', 'Teszt ujranyitas');

        $response = app(PaymentObligationController::class)->reopen($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertNotNull($response->getSession()->get('error'));
    }

    public function test_confirm_form_script_checks_validity_and_uses_native_submit(): void
    {
        $script = file_get_contents(resource_path('views/layouts/partials/scripts.blade.php'));

        $this->assertIsString($script);
        $this->assertStringContainsString('form.checkValidity()', $script);
        $this->assertStringContainsString('form.reportValidity()', $script);
        $this->assertStringContainsString("form.dataset.confirmed = 'true';", $script);
        $this->assertStringContainsString('HTMLFormElement.prototype.submit.call(form);', $script);
    }

    public function test_finance_exports_controller_redirects_to_payment_obligations(): void
    {
        $response = app(FinanceController::class)->exports();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('dashboard.institution.payment-obligations.index'), $response->getTargetUrl());
    }

    /**
     * Regresszió teszt arra a hibára, hogy a havi elszámolás az étkezési
     * hónap utolsó napja UTÁN egy plusz napot (a következő hónap 1-jét)
     * is beleszámolt az étkezési napok közé - lásd
     * PaymentObligationCalculatorService::calculateChildMonth() korábbi
     * ->addDay() hívását. A 2026 szeptemberi fizetési hónaphoz (=
     * szeptemberi étkezési időszak, mivel a javítás után a fizetési és az
     * étkezési hónap megegyezik) tartozó kimutatásnak pontosan a
     * szeptemberi 30 naptári napot kell tartalmaznia, október 1-jét nem.
     */
    public function test_recalculate_month_does_not_include_first_day_of_next_month(): void
    {
        [$institution, $user] = $this->seedStatement();

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 9, 1));

        $statement = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', 2026)
            ->where('month', 9)
            ->firstOrFail();
        $statement->load('days');

        $dates = $statement->days->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->sort()
            ->values();

        $this->assertCount(30, $dates);
        $this->assertSame('2026-09-01', $dates->first());
        $this->assertSame('2026-09-30', $dates->last());
        $this->assertNotContains('2026-10-01', $dates->all());
    }

    /**
     * Regressziós teszt arra az esetre, amikor egy korábbi (a
     * daysUntil()-os hiba miatt hibás záró dátummal futtatott) számítás
     * már létrehozott egy, a következő hónap 1-jéhez tartozó napi
     * rekordot: az újraszámolásnak ezt a "kívülrekedt" napot törölnie
     * kell, különben örökre torzítja az étkezési napok összesítését, még
     * úgy is, hogy a napi bontás táblázat (ami a hónap tényleges
     * napjaihoz van kötve) már helyesen nem jeleníti meg.
     */
    public function test_recalculate_month_removes_stale_day_left_over_from_previous_wider_range(): void
    {
        [$institution, $user] = $this->seedStatement();

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 9, 1));

        $statement = MonthlyPaymentStatement::query()
            ->where('institution_id', $institution->id)
            ->where('year', 2026)
            ->where('month', 9)
            ->firstOrFail();

        $countBeforeStaleDay = $statement->days()->where('payable_amount', '>', 0)->count();

        // Szimuláljuk a korábbi hibás állapotot: egy 2026-10-01-i napi
        // rekord, ami a régi (->addDay()-es) számításból maradt ott.
        MonthlyPaymentDay::create([
            'monthly_payment_statement_id' => $statement->id,
            'date' => '2026-10-01',
            'status' => MonthlyPaymentDay::STATUS_PAY,
            'original_daily_price' => 1200,
            'discount_percent' => 0,
            'payable_amount' => 1200,
        ]);

        $statement->refresh();
        $this->assertSame($countBeforeStaleDay + 1, $statement->days()->where('payable_amount', '>', 0)->count());

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 9, 1));

        $statement->refresh();
        $dates = $statement->days()->get()->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->sort()
            ->values();

        $this->assertNotContains('2026-10-01', $dates->all());
        $this->assertSame($countBeforeStaleDay, $statement->days()->where('payable_amount', '>', 0)->count());
    }

    private function seedStatement(): array
    {
        [$institution, $user] = $this->seedUserWithInstitution('TESZT02');

        $discount = DiscountType::create([
            'institution_id' => $institution->id,
            'name' => 'Alap',
            'percentage' => 0,
            'active' => true,
            'sort_order' => 1,
        ]);

        $child = Child::create([
            'institution_id' => $institution->id,
            'discount_type_id' => $discount->id,
            'name' => 'Feature Gyermek',
            'educational_identifier' => 'FGY001',
            'group_name' => '2.B',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => true,
        ]);

        $mealType = MealType::create([
            'code' => 'lunch-2',
            'name' => 'Ebed 2',
            'default_order' => 1,
        ]);

        $institutionMealType = InstitutionMealType::create([
            'institution_id' => $institution->id,
            'meal_type_id' => $mealType->id,
            'is_active' => true,
            'is_required' => true,
            'display_order' => 1,
        ]);

        InstitutionMealPrice::create([
            'institution_meal_type_id' => $institutionMealType->id,
            'price' => 1200,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        $package = InstitutionMealPackage::create([
            'institution_id' => $institution->id,
            'name' => 'Feature csomag',
            'is_active' => true,
            'is_default' => true,
            'display_order' => 1,
            'pricing_mode' => 'component_sum',
            'created_by' => $user->id,
        ]);

        InstitutionMealPackageItem::create([
            'institution_meal_package_id' => $package->id,
            'institution_meal_type_id' => $institutionMealType->id,
            'display_order' => 1,
        ]);

        StudentMealSetting::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT,
            'valid_from' => '2026-01-01',
            'created_by' => $user->id,
        ]);

        app(PaymentObligationCalculatorService::class)->recalculateMonth($institution, Carbon::create(2026, 7, 1));

        $statement = MonthlyPaymentStatement::query()->firstOrFail();

        return [$institution, $user, $statement];
    }

    private function seedUserWithInstitution(string $code): array
    {
        $institution = Institution::create([
            'name' => 'Feature Intezmeny '.$code,
            'institution_code' => $code,
            'type' => 'iskola',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ]);

        DB::table('institution_user')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'scope_role' => 'institution_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institution, $user];
    }

    private function makeIndexRequest(User $user, string $month): PaymentObligationIndexRequest
    {
        $request = PaymentObligationIndexRequest::create(
            route('dashboard.institution.payment-obligations.index'),
            'GET',
            ['month' => $month]
        );
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $validator = $this->app['validator']->make($request->all(), $request->rules());
        $request->setValidator($validator);

        return $request;
    }

    private function makeReopenRequest(User $user, string $month, string $reason): ReopenMonthRequest
    {
        $request = ReopenMonthRequest::create(
            route('dashboard.institution.payment-obligations.reopen'),
            'POST',
            [
                'month' => $month,
                'reopen_reason' => $reason,
            ]
        );
        $request->setUserResolver(fn () => $user);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $validator = $this->app['validator']->make($request->all(), $request->rules());
        $request->setValidator($validator);

        return $request;
    }
}
