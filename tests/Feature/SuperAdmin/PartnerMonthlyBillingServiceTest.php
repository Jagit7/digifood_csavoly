<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\BillingPartner;
use App\Models\Child;
use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use App\Models\PartnerMonthlyBilling;
use App\Services\Billing\PartnerMonthlyBillingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PartnerMonthlyBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_partner_snapshot_with_aggregated_children_and_mixed_pricing_rules(): void
    {
        $partner = $this->createPartner('Fenntartó 01', '27.00');
        $perChildInstitution = $this->createInstitution('Számolt Iskola', 'BILL101', $partner->id);
        $minimumInstitution = $this->createInstitution('Minimum Iskola', 'BILL102', $partner->id);
        $fixedInstitution = $this->createInstitution('Fix Díjas Intézmény', 'BILL103', $partner->id, false);
        $outsideInstitution = $this->createInstitution('Külső Intézmény', 'BILL104');

        $this->createChild($perChildInstitution->id, 'Aktív Anna', true);
        $this->createChild($perChildInstitution->id, 'Aktív Béla', true);
        $this->createChild($perChildInstitution->id, 'Inaktív Cili', false);
        $this->createChild($minimumInstitution->id, 'Minimum Dani', true);
        $this->createChild($fixedInstitution->id, 'Fix Emma', true);
        $this->createChild($outsideInstitution->id, 'Külső Feri', true);

        $this->createRate($perChildInstitution->id, [
            'price_per_child' => '300.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);
        $this->createRate($minimumInstitution->id, [
            'price_per_child' => '300.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => '1000.00',
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);
        $this->createRate($fixedInstitution->id, [
            'price_per_child' => null,
            'fixed_monthly_fee' => '5000.00',
            'minimum_monthly_fee' => '6000.00',
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $snapshot = app(PartnerMonthlyBillingService::class)->createSnapshot($partner, '2026-08');

        $this->assertSame('2026-08-01', $snapshot->billing_month->toDateString());
        $this->assertSame(4, $snapshot->total_children);
        $this->assertSame('7600.00', $snapshot->net_amount);
        $this->assertSame('2052.00', $snapshot->vat_amount);
        $this->assertSame('9652.00', $snapshot->gross_amount);
        $this->assertSame('draft', $snapshot->status);
        $this->assertCount(3, $snapshot->items);

        $perChildItem = $snapshot->items->firstWhere('institution_id', $perChildInstitution->id);
        $minimumItem = $snapshot->items->firstWhere('institution_id', $minimumInstitution->id);
        $fixedItem = $snapshot->items->firstWhere('institution_id', $fixedInstitution->id);

        $this->assertNotNull($perChildItem);
        $this->assertNotNull($minimumItem);
        $this->assertNotNull($fixedItem);

        $this->assertSame('Számolt Iskola', $perChildItem->institution_name_snapshot);
        $this->assertSame(2, $perChildItem->child_count);
        $this->assertSame('300.00', $perChildItem->price_per_child);
        $this->assertNull($perChildItem->fixed_monthly_fee);
        $this->assertNull($perChildItem->minimum_monthly_fee);
        $this->assertSame('600.00', $perChildItem->net_amount);
        $this->assertSame('2 fő × 300,00 Ft = 600,00 Ft', $perChildItem->calculation_description);

        $this->assertSame('Minimum Iskola', $minimumItem->institution_name_snapshot);
        $this->assertSame(1, $minimumItem->child_count);
        $this->assertSame('300.00', $minimumItem->price_per_child);
        $this->assertSame('1000.00', $minimumItem->minimum_monthly_fee);
        $this->assertSame('1000.00', $minimumItem->net_amount);
        $this->assertSame(
            '1 fő × 300,00 Ft = 300,00 Ft, alkalmazott minimumdíj: 1 000,00 Ft',
            $minimumItem->calculation_description
        );

        $this->assertSame('Fix Díjas Intézmény', $fixedItem->institution_name_snapshot);
        $this->assertSame(1, $fixedItem->child_count);
        $this->assertNull($fixedItem->price_per_child);
        $this->assertSame('5000.00', $fixedItem->fixed_monthly_fee);
        $this->assertSame('6000.00', $fixedItem->minimum_monthly_fee);
        $this->assertSame('6000.00', $fixedItem->net_amount);
        $this->assertSame(
            'Fix havi díj: 5 000,00 Ft, alkalmazott minimumdíj: 6 000,00 Ft',
            $fixedItem->calculation_description
        );
    }

    public function test_snapshot_remains_unchanged_after_later_source_data_changes(): void
    {
        $partner = $this->createPartner('Fenntartó 02', '27.00');
        $institution = $this->createInstitution('Eredeti Iskola', 'BILL201', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createRate($institution->id, [
            'price_per_child' => '300.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $snapshot = app(PartnerMonthlyBillingService::class)->createSnapshot($partner, Carbon::create(2026, 8, 15));
        $item = $snapshot->items->first();

        $institution->update(['name' => 'Módosított Iskola']);
        $this->createChild($institution->id, 'Gyermek 02', true);
        InstitutionBillingRate::query()->whereKey($item->institution_id)->update([]);
        $rate = InstitutionBillingRate::query()->where('institution_id', $institution->id)->firstOrFail();
        $rate->update(['price_per_child' => '999.00']);

        $freshItem = $snapshot->fresh()->load('items')->items->first();

        $this->assertSame('Eredeti Iskola', $freshItem->institution_name_snapshot);
        $this->assertSame(1, $freshItem->child_count);
        $this->assertSame('300.00', $freshItem->price_per_child);
        $this->assertSame('300.00', $freshItem->net_amount);
        $this->assertSame('Gyermek 01', Child::query()->findOrFail(1)->name);
    }

    public function test_it_rejects_snapshot_creation_when_any_rate_is_missing_and_keeps_database_clean(): void
    {
        $partner = $this->createPartner('Fenntartó 03', '27.00');
        $ratedInstitution = $this->createInstitution('Árazott Iskola', 'BILL301', $partner->id);
        $missingInstitution = $this->createInstitution('Díj Nélküli Iskola', 'BILL302', $partner->id);

        $this->createChild($ratedInstitution->id, 'Gyermek 01', true);
        $this->createRate($ratedInstitution->id, [
            'price_per_child' => '300.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        try {
            app(PartnerMonthlyBillingService::class)->createSnapshot($partner, '2026-08');
            $this->fail('A hiányzó díjszabás miatt kivételt vártunk.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Díj Nélküli Iskola', $exception->getMessage());
            $this->assertStringContainsString('2026. augusztus', $exception->getMessage());
        }

        $this->assertDatabaseCount('partner_monthly_billings', 0);
        $this->assertDatabaseCount('partner_monthly_billing_items', 0);

        $service = app(PartnerMonthlyBillingService::class);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A partnerhez jelenleg nincs hozzárendelt intézmény');

        $service->createSnapshot($this->createPartner('Üres Partner', '27.00'), '2026-08');
    }

    public function test_same_partner_and_month_cannot_have_two_snapshots(): void
    {
        $partner = $this->createPartner('Fenntartó 04', '27.00');
        $institution = $this->createInstitution('Dupla Iskola', 'BILL401', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createRate($institution->id, [
            'price_per_child' => '300.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        app(PartnerMonthlyBillingService::class)->createSnapshot($partner, '2026-08');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ehhez a partnerhez erre a hónapra már létezik havi számlázási adat.');

        app(PartnerMonthlyBillingService::class)->createSnapshot($partner, '2026-08');
    }

    public function test_draft_snapshot_can_be_recalculated_with_current_child_count(): void
    {
        $partner = $this->createPartner('Fenntartó 05', '27.00');
        $institution = $this->createInstitution('Újraszámolt Iskola', 'BILL501', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createRate($institution->id, [
            'price_per_child' => '400.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $service = app(PartnerMonthlyBillingService::class);
        $snapshot = $service->createSnapshot($partner, '2026-08');
        $oldItemId = $snapshot->items->first()->id;

        $this->createChild($institution->id, 'Gyermek 02', true);

        $recalculated = $service->recalculateSnapshot($snapshot);
        $newItem = $recalculated->items->first();

        $this->assertSame($snapshot->id, $recalculated->id);
        $this->assertSame(2, $recalculated->total_children);
        $this->assertSame('800.00', $recalculated->net_amount);
        $this->assertSame('216.00', $recalculated->vat_amount);
        $this->assertSame('1016.00', $recalculated->gross_amount);
        $this->assertSame(2, $newItem->child_count);
        $this->assertSame('2 fő × 400,00 Ft = 800,00 Ft', $newItem->calculation_description);
        $this->assertNotSame($oldItemId, $newItem->id);
    }

    public function test_invoiced_and_paid_snapshots_cannot_be_recalculated(): void
    {
        $service = app(PartnerMonthlyBillingService::class);
        $partner = $this->createPartner('Fenntartó 06', '27.00');
        $institution = $this->createInstitution('Zárt Iskola', 'BILL601', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createRate($institution->id, [
            'price_per_child' => '300.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $invoiced = $service->createSnapshot($partner, '2026-08');
        $invoiced->update(['status' => 'invoiced', 'invoiced_at' => now()]);

        try {
            $service->recalculateSnapshot($invoiced->fresh());
            $this->fail('Számlázott rekordnál kivételt vártunk.');
        } catch (DomainException $exception) {
            $this->assertSame('A számlázott vagy fizetett havi adat már nem számolható újra.', $exception->getMessage());
        }

        $paidPartner = $this->createPartner('Fenntartó 07', '27.00');
        $paidInstitution = $this->createInstitution('Fizetett Iskola', 'BILL701', $paidPartner->id);
        $this->createChild($paidInstitution->id, 'Gyermek 01', true);
        $this->createRate($paidInstitution->id, [
            'price_per_child' => '300.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $paid = $service->createSnapshot($paidPartner, '2026-08');
        $paid->update(['status' => 'paid', 'paid_at' => now()]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A számlázott vagy fizetett havi adat már nem számolható újra.');

        $service->recalculateSnapshot($paid->fresh());
    }

    public function test_failed_recalculation_keeps_previous_snapshot_unchanged(): void
    {
        $partner = $this->createPartner('Fenntartó 08', '27.00');
        $institution = $this->createInstitution('Védett Iskola', 'BILL801', $partner->id);
        $this->createChild($institution->id, 'Gyermek 01', true);
        $this->createRate($institution->id, [
            'price_per_child' => '500.00',
            'fixed_monthly_fee' => null,
            'minimum_monthly_fee' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ]);

        $service = app(PartnerMonthlyBillingService::class);
        $snapshot = $service->createSnapshot($partner, '2026-08');
        $originalItem = $snapshot->items->first();

        InstitutionBillingRate::query()
            ->where('institution_id', $institution->id)
            ->update(['valid_to' => '2026-07-31']);
        $this->createChild($institution->id, 'Gyermek 02', true);

        try {
            $service->recalculateSnapshot($snapshot);
            $this->fail('A hiányzó díjszabás miatt kivételt vártunk.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Védett Iskola', $exception->getMessage());
        }

        $freshSnapshot = PartnerMonthlyBilling::query()->with('items')->findOrFail($snapshot->id);
        $freshItem = $freshSnapshot->items->first();

        $this->assertSame(1, $freshSnapshot->total_children);
        $this->assertSame('500.00', $freshSnapshot->net_amount);
        $this->assertCount(1, $freshSnapshot->items);
        $this->assertSame($originalItem->id, $freshItem->id);
        $this->assertSame(1, $freshItem->child_count);
        $this->assertSame('500.00', $freshItem->net_amount);
    }

    private function createPartner(string $name, string $vatRate): BillingPartner
    {
        return BillingPartner::query()->create([
            'name' => $name,
            'vat_rate' => $vatRate,
            'active' => true,
        ]);
    }

    private function createInstitution(string $name, string $code, ?int $billingPartnerId = null, bool $active = true): Institution
    {
        return Institution::query()->create([
            'name' => $name,
            'institution_code' => $code,
            'type' => 'iskola',
            'billing_partner_id' => $billingPartnerId,
            'active' => $active,
        ]);
    }

    private function createChild(int $institutionId, string $name, bool $active): Child
    {
        return Child::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'educational_identifier' => substr(md5($institutionId.$name.$active.microtime(true)), 0, 11),
            'group_name' => '1.A',
            'school_year' => '2026/2027',
            'source_type' => 'manual',
            'active' => $active,
        ]);
    }

    private function createRate(int $institutionId, array $attributes): InstitutionBillingRate
    {
        return InstitutionBillingRate::query()->create([
            'institution_id' => $institutionId,
            'price_per_child' => $attributes['price_per_child'],
            'fixed_monthly_fee' => $attributes['fixed_monthly_fee'],
            'minimum_monthly_fee' => $attributes['minimum_monthly_fee'],
            'valid_from' => $attributes['valid_from'],
            'valid_to' => $attributes['valid_to'],
        ]);
    }
}
