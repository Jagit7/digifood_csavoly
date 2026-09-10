<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Dashboard\SuperAdmin\SaasBillingSummaryController;
use App\Mail\SaasBillingSummaryMail;
use App\Models\BillingPartner;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use App\Models\InstitutionEmployee;
use App\Models\SaasBillingSummaryRun;
use App\Models\StudentMealSetting;
use App\Services\Billing\DigifoodMonthlyFeeOverviewService;
use App\Services\Billing\SaasBillingSummaryService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class SaasBillingSummaryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-05 08:05:00', 'Europe/Budapest'));
        Mail::fake();
    }

    private function month(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-01', 'Europe/Budapest');
    }

    private function institution(string $name, string $rate = '85.00'): Institution
    {
        $institution = Institution::create(['name' => $name, 'institution_code' => 'S'.uniqid(), 'type' => 'iskola', 'active' => true, 'saas_fee_per_active_eater' => 999]);
        InstitutionBillingRate::create(['institution_id' => $institution->id, 'price_per_child' => $rate, 'valid_from' => '2026-09-01', 'valid_to' => '2026-09-30']);
        InstitutionBillingRate::create(['institution_id' => $institution->id, 'price_per_child' => 999, 'valid_from' => '2026-10-01']);

        return $institution;
    }

    private function child(Institution $institution, ?string $from = '2026-09-01', ?string $to = null): Child
    {
        $child = Child::create(['institution_id' => $institution->id, 'name' => 'Gyermek '.uniqid(), 'educational_identifier' => substr(uniqid(), -11), 'group_name' => '1.A', 'school_year' => '2026/2027', 'source_type' => 'manual', 'active' => true]);
        if ($from !== null) {
            $this->setting($child, $institution, $from, $to);
        }

        return $child;
    }

    private function setting(Child $child, Institution $institution, string $from, ?string $to): void
    {
        StudentMealSetting::create(['student_id' => $child->id, 'institution_id' => $institution->id, 'mode' => StudentMealSetting::MODE_INSTITUTION_DEFAULT, 'valid_from' => $from, 'valid_to' => $to]);
    }

    public function test_three_institutions_produce_one_immutable_aggregated_email_and_matching_web_snapshot(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_SUPER_ADMIN, 'is_active' => true]));
        $institutions = collect([$this->institution('Óvoda A'), $this->institution('Óvoda B', '90.50'), $this->institution('Csávoly', '100.00')]);
        foreach ($institutions as $institution) {
            $this->child($institution);
        }
        $service = app(SaasBillingSummaryService::class);
        $run = $service->send($this->month(), 'schedule');
        Mail::assertSentCount(1);
        Mail::assertSent(SaasBillingSummaryMail::class, function ($mail) use ($institutions, $run) {
            $this->assertSame('275.50', $mail->data['totalAmount']);
            $this->assertCount(3, $mail->data['rows']);
            $this->assertEquals(275.50, collect($mail->data['rows'])->sum('total_amount'));
            $this->assertSame($run->snapshot_payload, $mail->data);
            $html = $mail->render();
            foreach ($institutions as $institution) {
                $this->assertStringContainsString($institution->name, $html);
            }
            $this->assertStringContainsString('ÖSSZESEN FIZETENDŐ: 275,50 Ft', $html);
            $this->assertStringContainsString('1 fő × 90,50 Ft = 90,50 Ft', $html);

            return true;
        });
        $before = $run->snapshot_payload;
        $institutions->first()->update(['name' => 'Más név', 'active' => false]);
        Child::query()->update(['active' => false]);
        InstitutionBillingRate::query()->update(['price_per_child' => 1000]);
        $run->items()->update(['status' => 'paid']);
        $view = app(SaasBillingSummaryController::class)->index(Request::create('/', 'GET', ['month' => '2026-09']));
        $this->assertSame($before['totalAmount'], $view->getData()['totalAmount']);
        $this->assertSame($before['rows'], $view->getData()['rows']->all());
        $this->assertStringContainsString('275,50 Ft', $view->with('errors', new \Illuminate\Support\ViewErrorBag)->render());
        $this->assertSame('275.50', app(DigifoodMonthlyFeeOverviewService::class)->calculate()['total']);
        $this->assertSame('275.50', app(DigifoodMonthlyFeeOverviewService::class)->calculate('2026-09-05')['total']);
        $defaultView = app(SaasBillingSummaryController::class)->index(Request::create('/', 'GET'));
        $this->assertSame('2026-09', $defaultView->getData()['selectedMonth']);
        $this->assertSame('2026-09', $defaultView->getData()['maximumMonth']);
        $this->assertSame('275.50', $defaultView->getData()['totalAmount']);
        $service->send($this->month(), 'manual');
        Mail::assertSentCount(1);
        $this->assertSame($before, $run->fresh()->snapshot_payload);
        $this->assertDatabaseCount('saas_billing_summary_runs', 1);
        $this->assertDatabaseCount('saas_billing_summary_items', 3);
    }

    public function test_monthly_overlap_counts_distinct_historical_children_and_excludes_employees(): void
    {
        $institution = $this->institution('Időszakos');
        $this->child($institution, null); // no meal setting
        $this->child($institution, '2026-10-01'); // next month
        $this->child($institution, '2026-08-01', '2026-08-31'); // previous month
        $this->child($institution, '2026-09-15'); // mid-month start
        $this->child($institution, '2026-08-01', '2026-09-15'); // mid-month end
        $this->child($institution, '2026-09-30', '2026-09-30'); // last day
        $this->child($institution, '2026-08-01', '2026-09-01'); // first day
        $archived = $this->child($institution, '2026-09-01', '2026-09-10');
        $this->setting($archived, $institution, '2026-09-11', '2026-09-20');
        $archived->update(['active' => false]);
        $employee = InstitutionEmployee::create(['institution_id' => $institution->id, 'name' => 'Dolgozó', 'active' => true]);
        StudentMealSetting::create(['institution_id' => $institution->id, 'eater_type' => 'institution_employee', 'eater_id' => $employee->id, 'mode' => 'institution_default', 'valid_from' => '2026-09-01']);
        $row = app(SaasBillingSummaryService::class)->summary($this->month())['rows'][0];
        $this->assertSame(5, $row['children_count']);
        $this->assertSame(5, $row['eaters_count']);
        $this->assertSame(0, $row['employees_count']);
        $this->assertSame('425.00', $row['total_amount']);
    }

    public function test_dispatch_uses_current_month_and_repeated_dispatch_does_not_send_again(): void
    {
        $this->child($this->institution('Iskola'));
        $this->artisan('digifood:saas-billing-summary:dispatch')->assertSuccessful();
        $this->artisan('digifood:saas-billing-summary:dispatch')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('saas_billing_summary_runs', ['year' => 2026, 'month' => 9, 'status' => 'sent']);
        Mail::assertSent(SaasBillingSummaryMail::class, fn ($mail) => $mail->data['monthLabel'] === '2026. szeptember');
    }

    public function test_october_and_november_dispatch_each_bill_their_own_month(): void
    {
        $institution = $this->institution('Havi váltás');
        $this->child($institution, '2026-09-01', '2026-09-30');
        $this->child($institution, '2026-10-15', '2026-10-31');
        $this->child($institution, '2026-11-01', '2026-11-30');
        $this->child($institution, '2026-11-20', '2026-11-30');

        foreach ([10 => 1, 11 => 2] as $month => $count) {
            $this->travelTo(CarbonImmutable::create(2026, $month, 5, 8, 5, 0, 'Europe/Budapest'));
            $this->artisan('digifood:saas-billing-summary:dispatch')->assertSuccessful();
            $this->artisan('digifood:saas-billing-summary:dispatch')->assertSuccessful();
            $run = SaasBillingSummaryRun::where('year', 2026)->where('month', $month)->firstOrFail();
            $this->assertSame($count, $run->snapshot_payload['totalEaters']);
            $this->assertSame(number_format($count * 999, 2, '.', ''), $run->total_amount);
            $this->assertSame($run->total_amount, app(DigifoodMonthlyFeeOverviewService::class)->calculate()['total']);
        }
        Mail::assertSentCount(2);
        Mail::assertSent(SaasBillingSummaryMail::class, fn ($mail) => $mail->data['monthLabel'] === '2026. október');
        Mail::assertSent(SaasBillingSummaryMail::class, fn ($mail) => $mail->data['monthLabel'] === '2026. november');
        $this->assertDatabaseMissing('saas_billing_summary_runs', ['year' => 2026, 'month' => 9]);
    }

    public function test_mail_failure_is_not_sent_and_is_not_blindly_retried(): void
    {
        $this->child($this->institution('Hiba'));
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Transport failure'));
        $this->artisan('digifood:saas-billing-summary:dispatch')->assertFailed();
        $this->artisan('digifood:saas-billing-summary:dispatch')->assertFailed();
        $run = SaasBillingSummaryRun::firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertNull($run->sent_at);
        $this->assertNotNull($run->snapshot_payload);
    }

    public function test_second_caller_during_transport_cannot_send_again(): void
    {
        $this->child($this->institution('Párhuzamos'));
        $service = app(SaasBillingSummaryService::class);
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andReturnUsing(function () use ($service) {
            $run = SaasBillingSummaryRun::firstOrFail();
            $this->assertSame('sending', $run->status);
            $this->assertNull($run->sent_at);
            $this->assertSame('sending', $service->send($this->month(), 'manual')->status);
        });
        $this->assertSame('sent', $service->send($this->month(), 'schedule')->status);
    }

    public function test_fixed_and_minimum_fees_are_explained_and_snapshotted(): void
    {
        $fixed = $this->institution('Fix');
        $minimum = $this->institution('Minimum');
        $this->child($fixed);
        $this->child($minimum);
        InstitutionBillingRate::where('institution_id', $fixed->id)->whereDate('valid_from', '2026-09-01')->update(['price_per_child' => null, 'fixed_monthly_fee' => 500, 'minimum_monthly_fee' => 600]);
        InstitutionBillingRate::where('institution_id', $minimum->id)->whereDate('valid_from', '2026-09-01')->update(['minimum_monthly_fee' => 200]);
        $run = app(SaasBillingSummaryService::class)->send($this->month(), 'schedule');
        $this->assertSame('800.00', $run->total_amount);
        $rows = collect($run->snapshot_payload['rows'])->keyBy('institution_id');
        $this->assertNull($rows[$fixed->id]['rate']);
        $this->assertSame('Fix havi díj: 500,00 Ft, alkalmazott minimumdíj: 600,00 Ft', $rows[$fixed->id]['calculation_description']);
        $this->assertSame('1 fő × 85,00 Ft = 85,00 Ft, alkalmazott minimumdíj: 200,00 Ft', $rows[$minimum->id]['calculation_description']);
    }

    public function test_missing_historical_rate_blocks_partial_summary_without_snapshot(): void
    {
        $institution = $this->institution('Hiányzó');
        InstitutionBillingRate::where('institution_id', $institution->id)->whereDate('valid_from', '2026-09-01')->delete();
        $this->artisan('digifood:saas-billing-summary:dispatch')->assertFailed();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('saas_billing_summary_runs', 0);
    }

    public function test_legacy_snapshots_are_not_overwritten_even_if_failed(): void
    {
        $institution = $this->institution('Régi');
        $run = SaasBillingSummaryRun::create(['year' => 2026, 'month' => 9, 'total_amount' => 123, 'institution_count' => 1, 'status' => 'failed']);
        $item = $run->items()->create(['institution_id' => $institution->id, 'institution_name_snapshot' => 'Eredeti név', 'children_count' => 1, 'employees_count' => 1, 'eaters_count' => 2, 'rate' => '61.50', 'amount' => 123]);
        $before = $item->fresh()->getAttributes();
        app(SaasBillingSummaryService::class)->send($this->month(), 'manual');
        $data = app(SaasBillingSummaryService::class)->summary($this->month());
        $this->assertSame('123.00', $data['totalAmount']);
        $this->assertSame('Eredeti név', $data['rows'][0]['institution_name']);
        $this->assertSame($before, $item->fresh()->getAttributes());
        $this->assertNull($run->fresh()->snapshot_payload);
        Mail::assertNothingSent();
    }

    public function test_partner_institutions_do_not_create_duplicate_direct_obligations(): void
    {
        $direct = $this->institution('Közvetlen');
        $partner = $this->institution('Partneri');
        $partner->update(['billing_partner_id' => BillingPartner::create(['name' => 'Partner', 'active' => true])->id]);
        $data = app(SaasBillingSummaryService::class)->summary($this->month());
        $this->assertSame([$direct->id], array_column($data['rows'], 'institution_id'));
        $this->assertDatabaseCount('partner_monthly_billings', 0);
    }

    public function test_future_month_cannot_be_sent(): void
    {
        $this->expectException(DomainException::class);
        app(SaasBillingSummaryService::class)->send($this->month()->addMonth(), 'manual');
    }
}
