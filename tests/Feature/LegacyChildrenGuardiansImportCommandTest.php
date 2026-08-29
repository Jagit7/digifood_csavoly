<?php

namespace Tests\Feature;

use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyChildrenGuardiansImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_children_guardians_and_reuses_records_for_siblings(): void
    {
        $institution = $this->createInstitution('IMP001');

        $csvPath = $this->createCsv([
            ['diakId', 'diakNev', 'diakCsoport', 'kapcsolati_szulo_token', 'reg.nev', 'reg.mel', 'reg.tel', 'reg.szamlaNev', 'reg.szamlaIranyito', 'reg.szamlaVaros', 'reg.szamlaCim'],
            ['D1', 'Kiss Anna', 'Maci', 'P1', 'Kiss Eva', 'eva@example.test', '0612345678', 'Kiss Eva', '1111', 'Budapest', 'Fo ut 1.'],
            ['D1', 'Kiss Anna', 'Maci', 'P2', 'Kiss Bela', 'bela@example.test', '06201234567', 'Kiss Bela', '1111', 'Budapest', 'Fo ut 2.'],
            ['D2', 'Kiss Peter', 'Maci', 'P1', 'Kiss Eva', 'eva@example.test', '0612345678', 'Kiss Eva', '1111', 'Budapest', 'Fo ut 1.'],
            ['D3', 'Arva Gyerek', 'Suni', '', '', '', '', '', '', '', ''],
        ]);

        $this->artisan('digifood:import-legacy-children-guardians', [
            'file' => $csvPath,
            '--institution-id' => $institution->id,
        ])->assertExitCode(0);

        $this->assertSame(3, Child::where('institution_id', $institution->id)->count());
        $this->assertSame(2, Guardian::where('institution_id', $institution->id)->count());
        $this->assertSame(2, BillingProfile::where('institution_id', $institution->id)->count());

        $mother = Guardian::where('institution_id', $institution->id)
            ->where('email', 'eva@example.test')
            ->firstOrFail();

        $this->assertCount(2, $mother->children);
        $this->assertSame(1, BillingProfile::where('guardian_id', $mother->id)->count());
        $this->assertDatabaseCount('child_guardian', 3);
        $this->assertDatabaseCount('billing_profile_child', 3);
    }

    public function test_dry_run_rolls_back_all_changes(): void
    {
        $institution = $this->createInstitution('IMP002');

        $csvPath = $this->createCsv([
            ['diakId', 'diakNev', 'diakCsoport', 'kapcsolati_szulo_token', 'reg.nev', 'reg.mel', 'reg.tel', 'reg.szamlaNev', 'reg.szamlaIranyito', 'reg.szamlaVaros', 'reg.szamlaCim'],
            ['D1', 'Szabo Anna', 'Maci', 'P1', 'Szabo Eva', 'szabo@example.test', '0611111111', 'Szabo Eva', '2222', 'Gyor', 'Fo ter 1.'],
        ]);

        $this->artisan('digifood:import-legacy-children-guardians', [
            'file' => $csvPath,
            '--institution-id' => $institution->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, Child::where('institution_id', $institution->id)->count());
        $this->assertSame(0, Guardian::where('institution_id', $institution->id)->count());
        $this->assertSame(0, BillingProfile::where('institution_id', $institution->id)->count());
    }

    public function test_import_reuses_existing_guardian_by_unique_email_without_creating_duplicate(): void
    {
        $institution = $this->createInstitution('IMP003');

        $guardian = Guardian::create([
            'institution_id' => $institution->id,
            'last_name' => 'Nagy',
            'first_name' => 'Eva',
            'email' => 'reuse@example.test',
            'phone' => null,
            'source_type' => 'manual',
            'active' => true,
        ]);

        $csvPath = $this->createCsv([
            ['diakId', 'diakNev', 'diakCsoport', 'kapcsolati_szulo_token', 'reg.nev', 'reg.mel', 'reg.tel', 'reg.szamlaNev', 'reg.szamlaIranyito', 'reg.szamlaVaros', 'reg.szamlaCim'],
            ['D1', 'Nagy Adam', 'Katica', 'P1', 'Nagy Eva', 'reuse@example.test', '06701234567', 'Nagy Eva', '3333', 'Pecs', 'Kert utca 3.'],
        ]);

        $this->artisan('digifood:import-legacy-children-guardians', [
            'file' => $csvPath,
            '--institution-id' => $institution->id,
        ])->assertExitCode(0);

        $this->assertSame(1, Guardian::where('institution_id', $institution->id)->count());
        $this->assertDatabaseHas('child_guardian', [
            'guardian_id' => $guardian->id,
        ]);
    }

    public function test_conflicting_child_rows_cause_rollback_and_failure(): void
    {
        $institution = $this->createInstitution('IMP004');

        $csvPath = $this->createCsv([
            ['diakId', 'diakNev', 'diakCsoport', 'kapcsolati_szulo_token', 'reg.nev', 'reg.mel', 'reg.tel', 'reg.szamlaNev', 'reg.szamlaIranyito', 'reg.szamlaVaros', 'reg.szamlaCim'],
            ['D1', 'Kiss Anna', 'Maci', 'P1', 'Kiss Eva', 'eva@example.test', '0612345678', 'Kiss Eva', '1111', 'Budapest', 'Fo ut 1.'],
            ['D1', 'Masik Anna', 'Maci', 'P1', 'Kiss Eva', 'eva@example.test', '0612345678', 'Kiss Eva', '1111', 'Budapest', 'Fo ut 1.'],
        ]);

        $this->artisan('digifood:import-legacy-children-guardians', [
            'file' => $csvPath,
            '--institution-id' => $institution->id,
        ])->expectsOutputToContain('Konfliktusok')
            ->assertExitCode(1);

        $this->assertSame(0, Child::where('institution_id', $institution->id)->count());
        $this->assertSame(0, Guardian::where('institution_id', $institution->id)->count());
        $this->assertSame(0, BillingProfile::where('institution_id', $institution->id)->count());
    }

    private function createInstitution(string $code): Institution
    {
        return Institution::create([
            'name' => 'Import Intézmény '.$code,
            'institution_code' => $code,
            'type' => 'ovoda',
            'active' => true,
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function createCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-import-');

        if ($path === false) {
            throw new \RuntimeException('Nem sikerült ideiglenes CSV fájlt létrehozni.');
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Nem sikerült megnyitni az ideiglenes CSV fájlt.');
        }

        try {
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }
}
