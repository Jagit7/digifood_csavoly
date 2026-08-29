<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BillingPartner;
use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use App\Models\PartnerMonthlyBilling;
use App\Models\PartnerMonthlyBillingItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingPartnerDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_can_have_multiple_institutions(): void
    {
        $partner = BillingPartner::create([
            'name' => 'Fenntarto 01',
        ]);

        $firstInstitution = $this->createInstitution('BILL01', $partner->id);
        $secondInstitution = $this->createInstitution('BILL02', $partner->id);

        $partner->load('institutions');

        $this->assertCount(2, $partner->institutions);
        $this->assertTrue($partner->institutions->contains('id', $firstInstitution->id));
        $this->assertTrue($partner->institutions->contains('id', $secondInstitution->id));
    }

    public function test_institution_belongs_to_at_most_one_billing_partner(): void
    {
        $partner = BillingPartner::create([
            'name' => 'Fenntarto 02',
        ]);

        $institution = $this->createInstitution('BILL03', $partner->id);

        $institution->load('billingPartner');

        $this->assertInstanceOf(BillingPartner::class, $institution->billingPartner);
        $this->assertSame($partner->id, $institution->billingPartner->id);
    }

    public function test_institution_can_have_multiple_billing_rates_for_different_periods(): void
    {
        $institution = $this->createInstitution('BILL04');

        InstitutionBillingRate::create([
            'institution_id' => $institution->id,
            'price_per_child' => '1200.50',
            'fixed_monthly_fee' => '10000.00',
            'minimum_monthly_fee' => '15000.00',
            'valid_from' => '2026-01-01',
        ]);

        InstitutionBillingRate::create([
            'institution_id' => $institution->id,
            'price_per_child' => '1300.75',
            'fixed_monthly_fee' => '11000.00',
            'minimum_monthly_fee' => '16000.00',
            'valid_from' => '2026-09-01',
        ]);

        $institution->load('billingRates');

        $this->assertCount(2, $institution->billingRates);
        $this->assertSame('1200.50', $institution->billingRates[0]->price_per_child);
        $this->assertSame('1300.75', $institution->billingRates[1]->price_per_child);
    }

    public function test_monthly_billing_is_unique_per_partner_and_month(): void
    {
        $partner = BillingPartner::create([
            'name' => 'Fenntarto 03',
        ]);

        PartnerMonthlyBilling::create([
            'billing_partner_id' => $partner->id,
            'billing_month' => '2026-08-01',
        ]);

        $this->expectException(QueryException::class);

        PartnerMonthlyBilling::create([
            'billing_partner_id' => $partner->id,
            'billing_month' => '2026-08-01',
        ]);
    }

    public function test_force_deleting_partner_does_not_delete_institutions(): void
    {
        $partner = BillingPartner::create([
            'name' => 'Fenntarto 04',
        ]);

        $institution = $this->createInstitution('BILL05', $partner->id);

        $partner->forceDelete();
        $institution->refresh();

        $this->assertDatabaseHas('institutions', [
            'id' => $institution->id,
        ]);
        $this->assertNull($institution->billing_partner_id);
    }

    public function test_force_deleting_institution_does_not_delete_existing_monthly_snapshot(): void
    {
        $partner = BillingPartner::create([
            'name' => 'Fenntarto 05',
        ]);

        $institution = $this->createInstitution('BILL06', $partner->id);

        $monthlyBilling = PartnerMonthlyBilling::create([
            'billing_partner_id' => $partner->id,
            'billing_month' => '2026-08-01',
            'total_children' => 25,
            'net_amount' => '50000.00',
            'vat_amount' => '13500.00',
            'gross_amount' => '63500.00',
        ]);

        $item = PartnerMonthlyBillingItem::create([
            'partner_monthly_billing_id' => $monthlyBilling->id,
            'institution_id' => $institution->id,
            'institution_name_snapshot' => $institution->name,
            'child_count' => 25,
            'price_per_child' => '2000.00',
            'net_amount' => '50000.00',
        ]);

        $institution->forceDelete();

        $this->assertDatabaseHas('partner_monthly_billing_items', [
            'id' => $item->id,
            'partner_monthly_billing_id' => $monthlyBilling->id,
            'institution_name_snapshot' => $institution->name,
        ]);

        $this->assertNull($item->fresh()->institution_id);
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
}
