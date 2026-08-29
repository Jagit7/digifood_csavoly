<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\Institution;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DevelopmentDemoDataSeeder extends Seeder
{
    private const SOURCE_TYPE = 'development_demo';
    private const DEMO_PASSWORD = 'DemoParent123!';
    private const HIGHLIGHT_EMAIL = 'teszt.anya.iskola@digifood.local';

    public function run(): void
    {
        $this->guardEnvironment();

        $schoolYear = $this->currentSchoolYear();
        $seededInstitutions = [];

        DB::transaction(function () use (&$seededInstitutions, $schoolYear): void {
            foreach ($this->institutionDefinitions($schoolYear['name']) as $definition) {
                $institution = $this->upsertInstitution($definition['institution']);
                $schoolYearModel = $this->upsertSchoolYear($institution, $schoolYear);
                $classGroups = $this->upsertClassGroups($institution, $schoolYearModel, $definition['groups']);
                $referenceData = $this->upsertReferenceData($institution);
                $children = $this->upsertChildren(
                    $institution,
                    $definition['children'],
                    $classGroups,
                    $referenceData,
                    $schoolYearModel->name,
                    $schoolYearModel->starts_on->toDateString()
                );

                $this->upsertGuardians($institution, $definition['guardians'], $children);
                $seededInstitutions[] = $institution;
            }
        });

        $this->printSummary($seededInstitutions);
    }

    private function guardEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('A DevelopmentDemoDataSeeder production környezetben nem futtatható.');
        }

        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('A DevelopmentDemoDataSeeder csak local vagy testing környezetben futtatható.');
        }
    }

    private function currentSchoolYear(): array
    {
        $today = now();
        $startYear = $today->month >= 9 ? $today->year : $today->year - 1;
        $endYear = $startYear + 1;

        return [
            'name' => sprintf('%d/%d', $startYear, $endYear),
            'starts_on' => sprintf('%d-09-01', $startYear),
            'ends_on' => sprintf('%d-08-31', $endYear),
        ];
    }

    private function institutionDefinitions(string $schoolYear): array
    {
        return [
            $this->schoolInstitutionDefinition($schoolYear),
            $this->kindergartenInstitutionDefinition($schoolYear),
        ];
    }

    private function schoolInstitutionDefinition(string $schoolYear): array
    {
        return [
            'institution' => [
                'institution_code' => 'DFD-SCHOOL-001',
                'name' => 'DigiFood Teszt Általános Iskola',
                'type' => 'iskola',
                'address_zip' => '1117',
                'address_city' => 'Budapest',
                'address_line' => 'Teszt utca 12.',
                'om_identifier' => '910001',
                'contact_name' => 'Fejlesztő Iskola',
                'email' => 'iskola.demo@digifood.local',
                'phone' => '+3617001001',
                'billing_name' => 'DigiFood Teszt Általános Iskola',
                'billing_tax_number' => '12345678-2-43',
                'billing_zip' => '1117',
                'billing_city' => 'Budapest',
                'billing_address' => 'Teszt utca 12.',
                'billing_payment_due_days' => 8,
                'invoice_prefix' => 'DFI',
                'active' => true,
            ],
            'groups' => [
                'group_3a' => ['name' => '3.a', 'grade_level' => 3, 'section' => 'a', 'group_type' => 'school_class'],
                'group_6a' => ['name' => '6.a', 'grade_level' => 6, 'section' => 'a', 'group_type' => 'school_class'],
                'group_8b' => ['name' => '8.b', 'grade_level' => 8, 'section' => 'b', 'group_type' => 'school_class'],
            ],
            'children' => [
                ['key' => 'anna', 'identifier' => 'DFD-SCH-001', 'name' => 'Teszt Anna', 'group' => 'group_3a', 'active' => true, 'discount' => 'large_family_half'],
                ['key' => 'bence', 'identifier' => 'DFD-SCH-002', 'name' => 'Teszt Bence', 'group' => 'group_6a', 'active' => true],
                ['key' => 'csenge', 'identifier' => 'DFD-SCH-003', 'name' => 'Teszt Csenge', 'group' => 'group_8b', 'active' => true, 'dietary' => ['gluten']],
                ['key' => 'dora', 'identifier' => 'DFD-SCH-004', 'name' => 'Minta Dóra', 'group' => 'group_3a', 'active' => true],
                ['key' => 'endre', 'identifier' => 'DFD-SCH-005', 'name' => 'Minta Endre', 'group' => 'group_6a', 'active' => true, 'discount' => 'large_family_half'],
                ['key' => 'flora', 'identifier' => 'DFD-SCH-006', 'name' => 'Egyed Flóra', 'group' => 'group_3a', 'active' => true],
                ['key' => 'gergo', 'identifier' => 'DFD-SCH-007', 'name' => 'Egyed Gergő', 'group' => 'group_6a', 'active' => true],
                ['key' => 'hanna', 'identifier' => 'DFD-SCH-008', 'name' => 'Közös Hanna', 'group' => 'group_8b', 'active' => true],
                ['key' => 'imre', 'identifier' => 'DFD-SCH-009', 'name' => 'Fiók Imre', 'group' => 'group_3a', 'active' => true],
                ['key' => 'julia', 'identifier' => 'DFD-SCH-010', 'name' => 'Fiók Júlia', 'group' => 'group_6a', 'active' => true],
                ['key' => 'kornel', 'identifier' => 'DFD-SCH-011', 'name' => 'Aktív Kornél', 'group' => 'group_8b', 'active' => true],
                ['key' => 'lili', 'identifier' => 'DFD-SCH-012', 'name' => 'Aktív Lili', 'group' => 'group_3a', 'active' => true, 'dietary' => ['milk']],
                ['key' => 'marci', 'identifier' => 'DFD-SCH-013', 'name' => 'Aktív Marci', 'group' => 'group_6a', 'active' => true],
                ['key' => 'noemi', 'identifier' => 'DFD-SCH-014', 'name' => 'Inaktív Noémi', 'group' => 'group_8b', 'active' => true],
                ['key' => 'oliver', 'identifier' => 'DFD-SCH-015', 'name' => 'Inaktív Olivér', 'group' => 'group_3a', 'active' => true],
                ['key' => 'petra', 'identifier' => 'DFD-SCH-016', 'name' => 'Inaktív Petra', 'group' => 'group_6a', 'active' => false],
                ['key' => 'roland', 'identifier' => 'DFD-SCH-017', 'name' => 'Inaktív Roland', 'group' => 'group_8b', 'active' => false],
            ],
            'guardians' => [
                [
                    'key' => 'teszt_apa',
                    'last_name' => 'Teszt',
                    'first_name' => 'Apa',
                    'email' => 'teszt.apa.iskola@digifood.local',
                    'phone' => '+36201110001',
                    'user_active' => true,
                    'children' => [
                        'anna' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'bence' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'csenge' => $this->guardianPivot('Édesapa', true, false, true, false),
                    ],
                ],
                [
                    'key' => 'teszt_anya',
                    'last_name' => 'Teszt',
                    'first_name' => 'Anya',
                    'email' => self::HIGHLIGHT_EMAIL,
                    'phone' => '+36201110002',
                    'user_active' => true,
                    'children' => [
                        'anna' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'bence' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'csenge' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'minta_apa',
                    'last_name' => 'Minta',
                    'first_name' => 'Apa',
                    'email' => 'minta.apa.iskola@digifood.local',
                    'phone' => '+36201110003',
                    'user_active' => false,
                    'children' => [
                        'dora' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'endre' => $this->guardianPivot('Édesapa', true, false, false, false),
                    ],
                ],
                [
                    'key' => 'minta_anya',
                    'last_name' => 'Minta',
                    'first_name' => 'Anya',
                    'email' => 'minta.anya.iskola@digifood.local',
                    'phone' => '+36201110004',
                    'user_active' => true,
                    'children' => [
                        'dora' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'endre' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'egyedul_szulo',
                    'last_name' => 'Egyedül',
                    'first_name' => 'Szülő',
                    'email' => 'egyedul.szulo.iskola@digifood.local',
                    'phone' => '+36201110005',
                    'user_active' => true,
                    'children' => [
                        'flora' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'gergo' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'kozos_apa',
                    'last_name' => 'Közös',
                    'first_name' => 'Apa',
                    'email' => 'kozos.apa.iskola@digifood.local',
                    'phone' => '+36201110006',
                    'user_active' => false,
                    'children' => [
                        'hanna' => $this->guardianPivot('Édesapa', true, true, false, false),
                    ],
                ],
                [
                    'key' => 'kozos_anya',
                    'last_name' => 'Közös',
                    'first_name' => 'Anya',
                    'email' => 'kozos.anya.iskola@digifood.local',
                    'phone' => '+36201110007',
                    'user_active' => true,
                    'children' => [
                        'hanna' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'fiok_nelkul',
                    'last_name' => 'Fiók',
                    'first_name' => 'Nélküli',
                    'email' => 'fiok.nelkuli.iskola@digifood.local',
                    'phone' => '+36201110008',
                    'user_active' => null,
                    'children' => [
                        'imre' => $this->guardianPivot('Nagyszülő', false, false, true, false),
                        'julia' => $this->guardianPivot('Nagyszülő', false, false, true, false),
                    ],
                ],
                [
                    'key' => 'aktiv_szulo',
                    'last_name' => 'Aktív',
                    'first_name' => 'Szülő',
                    'email' => 'aktiv.szulo.iskola@digifood.local',
                    'phone' => '+36201110009',
                    'user_active' => true,
                    'children' => [
                        'kornel' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'lili' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'marci' => $this->guardianPivot('Édesanya', true, false, false, true),
                    ],
                ],
                [
                    'key' => 'inaktiv_szulo',
                    'last_name' => 'Inaktív',
                    'first_name' => 'Szülő',
                    'email' => 'inaktiv.szulo.iskola@digifood.local',
                    'phone' => '+36201110010',
                    'user_active' => false,
                    'children' => [
                        'noemi' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'oliver' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'petra' => $this->guardianPivot('Édesapa', true, false, false, false),
                        'roland' => $this->guardianPivot('Édesapa', true, false, false, false),
                    ],
                ],
            ],
        ];
    }

    private function kindergartenInstitutionDefinition(string $schoolYear): array
    {
        return [
            'institution' => [
                'institution_code' => 'DFD-KINDER-001',
                'name' => 'DigiFood Teszt Óvoda',
                'type' => 'ovoda',
                'address_zip' => '6724',
                'address_city' => 'Szeged',
                'address_line' => 'Példa köz 8.',
                'om_identifier' => '910002',
                'contact_name' => 'Fejlesztő Óvoda',
                'email' => 'ovoda.demo@digifood.local',
                'phone' => '+36627001001',
                'billing_name' => 'DigiFood Teszt Óvoda',
                'billing_tax_number' => '87654321-2-06',
                'billing_zip' => '6724',
                'billing_city' => 'Szeged',
                'billing_address' => 'Példa köz 8.',
                'billing_payment_due_days' => 8,
                'invoice_prefix' => 'DFO',
                'active' => true,
            ],
            'groups' => [
                'napocska' => ['name' => 'Napocska', 'grade_level' => null, 'section' => null, 'group_type' => 'kindergarten_group'],
                'katica' => ['name' => 'Katica', 'grade_level' => null, 'section' => null, 'group_type' => 'kindergarten_group'],
                'suni' => ['name' => 'Süni', 'grade_level' => null, 'section' => null, 'group_type' => 'kindergarten_group'],
            ],
            'children' => [
                ['key' => 'aliz', 'identifier' => 'DFD-KDG-001', 'name' => 'Teszt Aliz', 'group' => 'napocska', 'active' => true, 'discount' => 'large_family_half'],
                ['key' => 'barnabas', 'identifier' => 'DFD-KDG-002', 'name' => 'Teszt Barnabás', 'group' => 'katica', 'active' => true],
                ['key' => 'cili', 'identifier' => 'DFD-KDG-003', 'name' => 'Teszt Cili', 'group' => 'suni', 'active' => true, 'dietary' => ['gluten']],
                ['key' => 'domi', 'identifier' => 'DFD-KDG-004', 'name' => 'Minta Domi', 'group' => 'napocska', 'active' => true],
                ['key' => 'emma', 'identifier' => 'DFD-KDG-005', 'name' => 'Minta Emma', 'group' => 'katica', 'active' => true],
                ['key' => 'fanni', 'identifier' => 'DFD-KDG-006', 'name' => 'Egyed Fanni', 'group' => 'napocska', 'active' => true],
                ['key' => 'gergo', 'identifier' => 'DFD-KDG-007', 'name' => 'Egyed Gergő', 'group' => 'katica', 'active' => true],
                ['key' => 'heni', 'identifier' => 'DFD-KDG-008', 'name' => 'Közös Heni', 'group' => 'suni', 'active' => true],
                ['key' => 'ilon', 'identifier' => 'DFD-KDG-009', 'name' => 'Fiók Ilon', 'group' => 'napocska', 'active' => true],
                ['key' => 'janko', 'identifier' => 'DFD-KDG-010', 'name' => 'Fiók Jankó', 'group' => 'katica', 'active' => true],
                ['key' => 'kinga', 'identifier' => 'DFD-KDG-011', 'name' => 'Aktív Kinga', 'group' => 'suni', 'active' => true],
                ['key' => 'luca', 'identifier' => 'DFD-KDG-012', 'name' => 'Aktív Luca', 'group' => 'napocska', 'active' => true, 'dietary' => ['milk']],
                ['key' => 'mate', 'identifier' => 'DFD-KDG-013', 'name' => 'Aktív Máté', 'group' => 'katica', 'active' => true],
                ['key' => 'nora', 'identifier' => 'DFD-KDG-014', 'name' => 'Inaktív Nóra', 'group' => 'suni', 'active' => true],
                ['key' => 'olga', 'identifier' => 'DFD-KDG-015', 'name' => 'Inaktív Olga', 'group' => 'napocska', 'active' => true],
                ['key' => 'peti', 'identifier' => 'DFD-KDG-016', 'name' => 'Inaktív Peti', 'group' => 'katica', 'active' => false],
                ['key' => 'rozi', 'identifier' => 'DFD-KDG-017', 'name' => 'Inaktív Rozi', 'group' => 'suni', 'active' => false],
            ],
            'guardians' => [
                [
                    'key' => 'teszt_apa',
                    'last_name' => 'Teszt',
                    'first_name' => 'Apa Óvoda',
                    'email' => 'teszt.apa.ovoda@digifood.local',
                    'phone' => '+36202220001',
                    'user_active' => true,
                    'children' => [
                        'aliz' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'barnabas' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'cili' => $this->guardianPivot('Édesapa', true, false, true, false),
                    ],
                ],
                [
                    'key' => 'teszt_anya',
                    'last_name' => 'Teszt',
                    'first_name' => 'Anya Óvoda',
                    'email' => 'teszt.anya.ovoda@digifood.local',
                    'phone' => '+36202220002',
                    'user_active' => true,
                    'children' => [
                        'aliz' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'barnabas' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'cili' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'minta_apa',
                    'last_name' => 'Minta',
                    'first_name' => 'Apa Óvoda',
                    'email' => 'minta.apa.ovoda@digifood.local',
                    'phone' => '+36202220003',
                    'user_active' => false,
                    'children' => [
                        'domi' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'emma' => $this->guardianPivot('Édesapa', true, false, false, false),
                    ],
                ],
                [
                    'key' => 'minta_anya',
                    'last_name' => 'Minta',
                    'first_name' => 'Anya Óvoda',
                    'email' => 'minta.anya.ovoda@digifood.local',
                    'phone' => '+36202220004',
                    'user_active' => true,
                    'children' => [
                        'domi' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'emma' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'egyedul_szulo',
                    'last_name' => 'Egyedül',
                    'first_name' => 'Szülő Óvoda',
                    'email' => 'egyedul.szulo.ovoda@digifood.local',
                    'phone' => '+36202220005',
                    'user_active' => true,
                    'children' => [
                        'fanni' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'gergo' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'kozos_apa',
                    'last_name' => 'Közös',
                    'first_name' => 'Apa Óvoda',
                    'email' => 'kozos.apa.ovoda@digifood.local',
                    'phone' => '+36202220006',
                    'user_active' => false,
                    'children' => [
                        'heni' => $this->guardianPivot('Édesapa', true, true, false, false),
                    ],
                ],
                [
                    'key' => 'kozos_anya',
                    'last_name' => 'Közös',
                    'first_name' => 'Anya Óvoda',
                    'email' => 'kozos.anya.ovoda@digifood.local',
                    'phone' => '+36202220007',
                    'user_active' => true,
                    'children' => [
                        'heni' => $this->guardianPivot('Édesanya', true, false, true, true),
                    ],
                ],
                [
                    'key' => 'fiok_nelkul',
                    'last_name' => 'Fiók',
                    'first_name' => 'Nélküli Óvoda',
                    'email' => 'fiok.nelkuli.ovoda@digifood.local',
                    'phone' => '+36202220008',
                    'user_active' => null,
                    'children' => [
                        'ilon' => $this->guardianPivot('Nagyszülő', false, false, true, false),
                        'janko' => $this->guardianPivot('Nagyszülő', false, false, true, false),
                    ],
                ],
                [
                    'key' => 'aktiv_szulo',
                    'last_name' => 'Aktív',
                    'first_name' => 'Szülő Óvoda',
                    'email' => 'aktiv.szulo.ovoda@digifood.local',
                    'phone' => '+36202220009',
                    'user_active' => true,
                    'children' => [
                        'kinga' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'luca' => $this->guardianPivot('Édesanya', true, false, true, true),
                        'mate' => $this->guardianPivot('Édesanya', true, false, false, true),
                    ],
                ],
                [
                    'key' => 'inaktiv_szulo',
                    'last_name' => 'Inaktív',
                    'first_name' => 'Szülő Óvoda',
                    'email' => 'inaktiv.szulo.ovoda@digifood.local',
                    'phone' => '+36202220010',
                    'user_active' => false,
                    'children' => [
                        'nora' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'olga' => $this->guardianPivot('Édesapa', true, false, true, false),
                        'peti' => $this->guardianPivot('Édesapa', true, false, false, false),
                        'rozi' => $this->guardianPivot('Édesapa', true, false, false, false),
                    ],
                ],
            ],
        ];
    }

    private function guardianPivot(
        string $relationshipType,
        bool $isLegalRepresentative,
        bool $hasNoCustody,
        bool $isEmergencyContact,
        bool $receivesFamilyAllowance
    ): array {
        return [
            'relationship_type' => $relationshipType,
            'is_legal_representative' => $isLegalRepresentative,
            'has_no_custody' => $hasNoCustody,
            'is_emergency_contact' => $isEmergencyContact,
            'receives_family_allowance' => $receivesFamilyAllowance,
        ];
    }

    private function upsertInstitution(array $attributes): Institution
    {
        $institution = Institution::withTrashed()->firstOrNew([
            'institution_code' => $attributes['institution_code'],
        ]);

        if ($institution->trashed()) {
            $institution->restore();
        }

        $institution->fill($attributes);
        $institution->save();

        return $institution;
    }

    private function upsertSchoolYear(Institution $institution, array $schoolYear): SchoolYear
    {
        return SchoolYear::updateOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => $schoolYear['name'],
            ],
            [
                'starts_on' => $schoolYear['starts_on'],
                'ends_on' => $schoolYear['ends_on'],
                'status' => 'active',
                'is_current' => true,
            ]
        );
    }

    private function upsertClassGroups(Institution $institution, SchoolYear $schoolYear, array $definitions): array
    {
        $groups = [];

        foreach ($definitions as $key => $definition) {
            $groups[$key] = ClassGroup::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'school_year_id' => $schoolYear->id,
                    'name' => $definition['name'],
                ],
                [
                    'grade_level' => $definition['grade_level'],
                    'section' => $definition['section'],
                    'group_type' => $definition['group_type'],
                    'active' => true,
                ]
            );
        }

        return $groups;
    }

    private function upsertReferenceData(Institution $institution): array
    {
        $discounts = [
            'none' => DiscountType::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => 'Kedvezmény nélkül',
                    'percentage' => 0,
                ],
                [
                    'active' => true,
                    'sort_order' => 0,
                ]
            ),
            'large_family_half' => DiscountType::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => 'Nagycsaládos kedvezmény',
                    'percentage' => 50,
                ],
                [
                    'active' => true,
                    'sort_order' => 1,
                ]
            ),
        ];

        $restrictions = [
            'gluten' => DietaryRestriction::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => 'Glutén',
                    'type' => DietaryRestriction::TYPE_INTOLERANCE,
                ],
                [
                    'active' => true,
                    'sort_order' => 0,
                ]
            ),
            'milk' => DietaryRestriction::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => 'Tej',
                    'type' => DietaryRestriction::TYPE_INTOLERANCE,
                ],
                [
                    'active' => true,
                    'sort_order' => 1,
                ]
            ),
        ];

        return [
            'discounts' => $discounts,
            'restrictions' => $restrictions,
        ];
    }

    private function upsertChildren(
        Institution $institution,
        array $definitions,
        array $classGroups,
        array $referenceData,
        string $schoolYearName,
        string $joinedOn
    ): array {
        $children = [];
        $now = now();

        foreach ($definitions as $definition) {
            $group = $classGroups[$definition['group']];
            $discount = $referenceData['discounts'][$definition['discount'] ?? 'none'];

            $child = Child::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'educational_identifier' => $definition['identifier'],
                ],
                [
                    'discount_type_id' => $discount->id,
                    'name' => $definition['name'],
                    'group_name' => $group->name,
                    'school_year' => $schoolYearName,
                    'source_type' => self::SOURCE_TYPE,
                    'active' => $definition['active'],
                ]
            );

            DB::table('class_group_memberships')->updateOrInsert(
                [
                    'class_group_id' => $group->id,
                    'child_id' => $child->id,
                ],
                [
                    'status' => $definition['active'] ? 'active' : 'inactive',
                    'joined_on' => $joinedOn,
                    'left_on' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            foreach ($definition['dietary'] ?? [] as $restrictionKey) {
                $restriction = $referenceData['restrictions'][$restrictionKey];

                DB::table('child_dietary_restriction')->updateOrInsert(
                    [
                        'child_id' => $child->id,
                        'dietary_restriction_id' => $restriction->id,
                    ],
                    [
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            $children[$definition['key']] = $child;
        }

        return $children;
    }

    private function upsertGuardians(Institution $institution, array $definitions, array $children): void
    {
        $now = now();

        foreach ($definitions as $definition) {
            $user = $this->upsertParentUser(
                $institution,
                $definition['email'],
                trim($definition['last_name'].' '.$definition['first_name']),
                $definition['user_active']
            );

            $guardian = Guardian::firstOrNew([
                'institution_id' => $institution->id,
                'phone' => $definition['phone'],
            ]);

            $guardian->fill([
                'user_id' => $user?->id,
                'prefix' => null,
                'last_name' => $definition['last_name'],
                'first_name' => $definition['first_name'],
                'email' => $definition['email'],
                'phone' => $definition['phone'],
                'phone_type' => 'Mobil',
                'address_type' => 'lakcím',
                'country' => 'Magyarország',
                'postal_code' => $institution->address_zip,
                'city' => $institution->address_city,
                'street_name' => $institution->address_line,
                'street_type' => null,
                'house_number' => null,
                'floor' => null,
                'door' => null,
                'source_type' => self::SOURCE_TYPE,
                'active' => true,
            ]);
            $guardian->save();

            foreach ($definition['children'] as $childKey => $pivot) {
                $child = $children[$childKey];

                DB::table('child_guardian')->updateOrInsert(
                    [
                        'child_id' => $child->id,
                        'guardian_id' => $guardian->id,
                    ],
                    [
                        'relationship_type' => $pivot['relationship_type'],
                        'is_legal_representative' => $pivot['is_legal_representative'],
                        'has_no_custody' => $pivot['has_no_custody'],
                        'is_emergency_contact' => $pivot['is_emergency_contact'],
                        'receives_family_allowance' => $pivot['receives_family_allowance'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    private function upsertParentUser(Institution $institution, string $email, string $name, ?bool $isActive): ?User
    {
        if ($isActive === null) {
            return null;
        }

        $user = User::withTrashed()->firstOrNew([
            'email' => $email,
        ]);

        if (method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
        }

        $user->fill([
            'name' => $name,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'role' => User::ROLE_PARENT,
            'institution_id' => $institution->id,
            'phone' => null,
            'is_active' => $isActive,
            'email_verified_at' => $isActive ? now() : null,
        ]);
        $user->save();

        return $user;
    }

    private function printSummary(array $institutions): void
    {
        $institutionIds = collect($institutions)->pluck('id');

        $this->command?->info('DevelopmentDemoDataSeeder lefutott.');
        $this->command?->info('Létrehozott vagy frissített intézmények száma: '.count($institutions));

        foreach ($institutions as $institution) {
            $childrenCount = Child::query()
                ->where('institution_id', $institution->id)
                ->where('source_type', self::SOURCE_TYPE)
                ->count();

            $guardiansCount = Guardian::query()
                ->where('institution_id', $institution->id)
                ->where('source_type', self::SOURCE_TYPE)
                ->count();

            $this->command?->info(sprintf(
                '%s -> gyermekek: %d, gondviselők: %d',
                $institution->name,
                $childrenCount,
                $guardiansCount
            ));
        }

        $multiChildGuardians = Guardian::query()
            ->whereIn('institution_id', $institutionIds)
            ->where('source_type', self::SOURCE_TYPE)
            ->has('children', '>', 1)
            ->count();

        $withAccountGuardians = Guardian::query()
            ->whereIn('institution_id', $institutionIds)
            ->where('source_type', self::SOURCE_TYPE)
            ->whereNotNull('user_id')
            ->count();

        $withoutAccountGuardians = Guardian::query()
            ->whereIn('institution_id', $institutionIds)
            ->where('source_type', self::SOURCE_TYPE)
            ->whereNull('user_id')
            ->count();

        $this->command?->info('Többgyermekes gondviselők száma: '.$multiChildGuardians);
        $this->command?->info('Fiókkal rendelkező gondviselők száma: '.$withAccountGuardians);
        $this->command?->info('Fiók nélküli gondviselők száma: '.$withoutAccountGuardians);
        $this->command?->info('Kiemelt teszt szülői fiók e-mail-címe: '.self::HIGHLIGHT_EMAIL);
        $this->command?->info('Kiemelt teszt szülői fiók jelszava: '.self::DEMO_PASSWORD);
        $this->command?->warn('Megjegyzés: étkezési és pénzügyi demo adatok nem készültek, mert azokhoz további kötelező üzleti szabályok nem állapíthatók meg biztonságosan.');
    }
}
