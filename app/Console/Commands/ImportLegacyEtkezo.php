<?php

namespace App\Console\Commands;

use App\Models\Child;
use App\Models\ChildDiscountPeriod;
use App\Models\ClassGroup;
use App\Models\DietaryRestriction;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\InstitutionMealType;
use App\Models\SchoolYear;
use App\Models\StudentMealSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use App\Models\InstitutionEmployee;

class ImportLegacyEtkezo extends Command
{
    protected $signature = 'digifood:import-legacy-etkezo
        {file : Az importálandó XLS/XLSX fájl}
        {--institution= : Intézmény azonosító}
        {--school-year=2026/2027 : Tanév}
        {--dry-run : Csak ellenőrzés, adatbázis-módosítás nélkül}';

    protected $description = 'Egyszeri import a régi Digifood étkező Excelből.';

    private const SOURCE_TYPE = 'legacy_meal_import';

    public function handle(): int
    {
        $institutionId = (int) $this->option('institution');
        $schoolYearName = trim((string) $this->option('school-year'));
        $dryRun = (bool) $this->option('dry-run');

        if ($institutionId <= 0) {
            $this->error('Az --institution paraméter kötelező.');

            return self::FAILURE;
        }

        $file = $this->resolveFilePath((string) $this->argument('file'));

        if (!is_file($file)) {
            $this->error('A fájl nem található: '.$file);

            return self::FAILURE;
        }

        $this->info('Fájl: '.$file);
        $this->info('Intézmény ID: '.$institutionId);
        $this->info('Tanév: '.$schoolYearName);
        $this->info('Mód: '.($dryRun ? 'DRY-RUN' : 'ÉLES IMPORT'));
        $this->newLine();

        try {
            $spreadsheet = IOFactory::load($file);
        } catch (Throwable $e) {
            $this->error('Az Excel nem olvasható: '.$e->getMessage());

            return self::FAILURE;
        }

        $sheet = $spreadsheet->getSheetByName('Data');

        if (!$sheet) {
            $this->error('A "Data" munkalap nem található.');

            return self::FAILURE;
        }

        $rows = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            $this->error('A Data munkalap nem tartalmaz adatokat.');

            return self::FAILURE;
        }

        $indexes = [
            'Etk_Nev' => 1,
            'Etk_TFK' => 2,
            'Etk_Oszt' => 3,
            'EtkezesiCsoport' => 4,
            'Etk_Fajta' => 5,
            'UtaloEMail' => 6,
            'AlapitoOkirat' => 7,
            'Foglalkozas' => 8,
            'BizonyitvanySzama' => 9,
            'DietaTipus' => 10,
            'Etk_Kedv_1' => 11,
            'Etk_Kedv_1_Tol' => 12,
            'Etk_Kedv_1_Ig' => 13,
            'Etk_Kedv_2' => 14,
            'Etk_Kedv_2_Tol' => 15,
            'Etk_Kedv_2_Ig' => 16,
            'Etk_Kedv_3' => 17,
            'Etk_Kedv_3_Tol' => 18,
            'Etk_Kedv_3_Ig' => 19,
        ];

        $stats = [
            'processed' => 0,
            'students' => 0,
            'created' => 0,
            'updated' => 0,
            'staff_skipped' => 0,
            'other_skipped' => 0,
            'no_class_skipped' => 0,
            'guardian_created' => 0,
            'guardian_updated' => 0,
            'dietary' => 0,
            'discounts' => 0,
            'meal_settings' => 0,
            'errors' => 0,
            'staff' => 0,
            'staff_created' => 0,
            'staff_updated' => 0,
            'staff_dietary' => 0,
            'staff_discounted' => 0,
        ];

        $warnings = [];
        $unknownDiscounts = [];
        $unknownMealTypes = [];

        try {
            DB::beginTransaction();

            [$schoolYear] = $this->resolveSchoolYear(
                $institutionId,
                $schoolYearName
            );

            foreach (array_slice($rows, 1) as $offset => $row) {
                $excelRow = $offset + 2;
                $stats['processed']++;

                $value = function (string $header) use ($row, $indexes) {
                    return $this->clean(
                        $row[$indexes[$header]] ?? null
                    );
                };

                $name = $value('Etk_Nev');
                $personType = mb_strtoupper((string) $value('Etk_TFK'));
                $groupName = $value('Etk_Oszt');

                if (!$name) {
                    continue;
                }

                /*
                 * F = alkalmazott.
                 * Majd a külön alkalmazotti modul importálja.
                 */
                if ($personType === 'F') {
                    $stats['staff']++;

                    try {
                        $this->importEmployee(
                            $institutionId,
                            $name,
                            [
                                'email' => $value('UtaloEMail'),
                                'billing_name' => $value('AlapitoOkirat'),
                                'address' => $value('Foglalkozas'),
                                'bank_account' => $value('BizonyitvanySzama'),
                                'dietary' => $value('DietaTipus'),
                                'discount_1' => $value('Etk_Kedv_1'),
                                'discount_2' => $value('Etk_Kedv_2'),
                                'discount_3' => $value('Etk_Kedv_3'),
                            ],
                            $stats
                        );
                    } catch (Throwable $e) {
                        $stats['errors']++;

                        $warnings[] = sprintf(
                            '%d. sor – dolgozó – %s: %s',
                            $excelRow,
                            $name,
                            $e->getMessage()
                        );
                    }

                    continue;
                }

                /*
                 * Csak tanulókat veszünk át.
                 */
                if ($personType !== 'T') {
                    $stats['other_skipped']++;

                    continue;
                }

                /*
                 * Régi/inaktív tanulók osztály nélkül nem kerülnek be.
                 */
                if (!$groupName) {
                    $stats['no_class_skipped']++;

                    continue;
                }

                $stats['students']++;

                try {
                    $classGroup = $this->resolveClassGroup(
                        $institutionId,
                        $schoolYear,
                        $groupName
                    );

                    /*
                     * Mivel nincs OM azonosító, elsődlegesen:
                     *
                     * intézmény + név + legacy forrás
                     *
                     * alapján keressük.
                     *
                     * Így a parancs újrafuttatható anélkül,
                     * hogy újra létrehozná ugyanazt a gyermeket.
                     */
                    $child = Child::query()
                        ->where('institution_id', $institutionId)
                        ->where('name', $name)
                        ->where('source_type', self::SOURCE_TYPE)
                        ->first();

                    $wasExisting = $child !== null;

                    if (!$child) {
                        $child = new Child();
                        $child->institution_id = $institutionId;
                    }

                    $child->fill([
                        'name' => $name,
                        'educational_identifier' => null,
                        'group_name' => $groupName,
                        'school_year' => $schoolYearName,
                        'source_type' => self::SOURCE_TYPE,
                        'active' => true,
                    ]);

                    $child->save();

                    if ($wasExisting) {
                        $stats['updated']++;
                    } else {
                        $stats['created']++;
                    }

                    $this->syncClassMembership(
                        $child,
                        $schoolYear,
                        $classGroup
                    );

                    $guardian = $this->importGuardian(
                        $child,
                        $institutionId,
                        [
                            'email' => $value('UtaloEMail'),
                            'name' => $value('AlapitoOkirat'),
                            'address' => $value('Foglalkozas'),
                            'bank_account' => $value('BizonyitvanySzama'),
                        ],
                        $stats
                    );

                    $this->importDietaryRestrictions(
                        $child,
                        $institutionId,
                        $value('DietaTipus'),
                        $stats
                    );

                    for ($i = 1; $i <= 3; $i++) {
                        $discountName = $value('Etk_Kedv_'.$i);

                        if (!$discountName) {
                            continue;
                        }

                        $imported = $this->importDiscount(
                            $child,
                            $institutionId,
                            $discountName,
                            $value('Etk_Kedv_'.$i.'_Tol'),
                            $value('Etk_Kedv_'.$i.'_Ig'),
                            $unknownDiscounts
                        );

                        if ($imported) {
                            $stats['discounts']++;
                        }
                    }

                    $mealTypeValue = $value('Etk_Fajta');

                    if ($mealTypeValue) {
                        $importedMealTypeCount = $this->importMealSetting(
                            $child,
                            $institutionId,
                            $mealTypeValue,
                            $schoolYear,
                            $unknownMealTypes
                        );

                        if ($importedMealTypeCount > 0) {
                            $stats['meal_settings']++;
                        }
                    }

                } catch (Throwable $e) {
                    $stats['errors']++;

                    $warnings[] = sprintf(
                        '%d. sor – %s: %s',
                        $excelRow,
                        $name,
                        $e->getMessage()
                    );
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                /*
                 * Ismeretlen kedvezményt nem akarunk vakon százalékhoz
                 * kötni. Ha van ilyen, inkább álljon meg az import.
                 */
                if ($unknownDiscounts !== []) {
                    DB::rollBack();

                    $this->error(
                        'Az import NEM történt meg, mert vannak ismeretlen kedvezménytípusok.'
                    );

                    $this->showUnknownValues(
                        'Ismeretlen kedvezmények',
                        $unknownDiscounts
                    );

                    return self::FAILURE;
                }

                if ($unknownMealTypes !== []) {
                    DB::rollBack();

                    $this->error(
                        'Az import NEM történt meg, mert vannak nem párosítható étkezési típusok.'
                    );

                    $this->showUnknownValues(
                        'Ismeretlen étkezési típusok',
                        $unknownMealTypes
                    );

                    return self::FAILURE;
                }

                DB::commit();
            }
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        $this->table(
            ['Ellenőrzés', 'Eredmény'],
            [
                ['Feldolgozott Excel sor', $stats['processed']],
                ['Importálandó tanuló', $stats['students']],
                ['Új tanuló', $stats['created']],
                ['Frissített tanuló', $stats['updated']],
                ['Importálandó dolgozó (F)', $stats['staff']],
                ['Új dolgozó', $stats['staff_created']],
                ['Frissített dolgozó', $stats['staff_updated']],
                ['Dolgozói kedvezmények', $stats['staff_discounted']],
                ['Dolgozói diéta kapcsolatok', $stats['staff_dietary']],
                ['Kihagyott egyéb típus', $stats['other_skipped']],
                ['Kihagyott osztály nélküli tanuló', $stats['no_class_skipped']],
                ['Új gondviselő', $stats['guardian_created']],
                ['Frissített gondviselő', $stats['guardian_updated']],
                ['Diéta kapcsolatok', $stats['dietary']],
                ['Kedvezmény időszakok', $stats['discounts']],
                ['Étkezési beállítások', $stats['meal_settings']],
                ['Sorhibák', $stats['errors']],
            ]
        );

        if ($unknownDiscounts !== []) {
            $this->showUnknownValues(
                'Excelben szereplő, de adatbázisban nem talált kedvezmények',
                $unknownDiscounts
            );
        }

        if ($unknownMealTypes !== []) {
            $this->showUnknownValues(
                'Nem párosítható étkezési típusok',
                $unknownMealTypes
            );
        }

        if ($warnings !== []) {
            $this->newLine();
            $this->warn('Hibás sorok:');

            foreach (array_slice($warnings, 0, 50) as $warning) {
                $this->line(' - '.$warning);
            }

            if (count($warnings) > 50) {
                $this->line(
                    '... további '.(count($warnings) - 50).' hiba.'
                );
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY-RUN volt, az adatbázis nem módosult.');
        } else {
            $this->info('Az import sikeresen befejeződött.');
        }

        return self::SUCCESS;
    }

    private function importEmployee(
        int $institutionId,
        string $name,
        array $data,
        array &$stats
    ): InstitutionEmployee {
        $employee = InstitutionEmployee::query()
            ->where('institution_id', $institutionId)
            ->where('name', $name)
            ->where('source_type', self::SOURCE_TYPE)
            ->first();

        $wasExisting = $employee !== null;

        if (!$employee) {
            $employee = new InstitutionEmployee([
                'institution_id' => $institutionId,
            ]);
        }

        $address = $this->parseAddress(
            $this->clean($data['address'] ?? null)
        );

        $employee->fill([
            'name' => $name,
            'email' => $this->clean($data['email'] ?? null),
            'address_type' => $address['address_type'],
            'country' => $address['country'],
            'postal_code' => $address['postal_code'],
            'city' => $address['city'],
            'street_name' => $address['street_name'],
            'street_type' => $address['street_type'],
            'house_number' => $address['house_number'],
            'source_type' => self::SOURCE_TYPE,
            'active' => true,
        ]);

        /*
        * A dolgozó saját maga a számlázási alany.
        * Ha a régi fájlban van külön számlázási név, megtartjuk,
        * egyébként a dolgozó saját nevét használjuk.
        */
        $employee->bank_account_holder =
            $this->clean($data['billing_name'] ?? null) ?: $name;

        $employee->bank_account_number =
            $this->clean($data['bank_account'] ?? null);

        /*
        * Kedvezmény:
        * először az Excelben szereplő kedvezményt próbáljuk meg.
        * Semmilyen százalékot nem találunk ki.
        */
        $discountNames = array_filter([
            $this->clean($data['discount_1'] ?? null),
            $this->clean($data['discount_2'] ?? null),
            $this->clean($data['discount_3'] ?? null),
        ]);

        foreach ($discountNames as $discountName) {
            $discount = DiscountType::query()
                ->where('institution_id', $institutionId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($discountName)])
                ->first();

            if ($discount) {
                $employee->discount_type_id = $discount->id;
                $stats['staff_discounted']++;

                break;
            }
        }

        $employee->save();

        if ($wasExisting) {
            $stats['staff_updated']++;
        } else {
            $stats['staff_created']++;
        }

        $this->importEmployeeDietaryRestrictions(
            $employee,
            $institutionId,
            $this->clean($data['dietary'] ?? null),
            $stats
        );

        return $employee;
    }

    private function importEmployeeDietaryRestrictions(
        InstitutionEmployee $employee,
        int $institutionId,
        ?string $value,
        array &$stats
    ): void {
        if (!$value) {
            return;
        }

        $names = preg_split(
            '/[,;+\/|]+/u',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $restriction = DietaryRestriction::firstOrCreate(
                [
                    'institution_id' => $institutionId,
                    'name' => $name,
                ],
                [
                    'type' => DietaryRestriction::TYPE_INTOLERANCE,
                    'active' => true,
                    'sort_order' => 0,
                ]
            );

            $employee->dietaryRestrictions()->syncWithoutDetaching([
                $restriction->id,
            ]);

            $stats['staff_dietary']++;
        }
    }

    private function importGuardian(
        Child $child,
        int $institutionId,
        array $data,
        array &$stats
    ): ?Guardian {
        $fullName = $this->clean($data['name'] ?? null);
        $email = $this->clean($data['email'] ?? null);

        if (!$fullName && !$email) {
            return null;
        }

        [$prefix, $lastName, $firstName] = $this->splitHungarianName(
            $fullName
        );

        $guardian = null;

        if ($email) {
            $guardian = Guardian::query()
                ->where('institution_id', $institutionId)
                ->where('email', $email)
                ->first();
        }

        if (!$guardian && $lastName && $firstName) {
            $guardian = Guardian::query()
                ->where('institution_id', $institutionId)
                ->where('last_name', $lastName)
                ->where('first_name', $firstName)
                ->first();
        }

        $wasExisting = $guardian !== null;

        if (!$guardian) {
            $guardian = new Guardian([
                'institution_id' => $institutionId,
            ]);
        }

        $address = $this->parseAddress(
            $this->clean($data['address'] ?? null)
        );

        $guardian->fill([
            'prefix' => $prefix,
            'last_name' => $lastName ?: ($fullName ?: 'Ismeretlen'),
            'first_name' => $firstName ?: '-',
            'email' => $email,
            'address_type' => $address['address_type'],
            'country' => $address['country'],
            'postal_code' => $address['postal_code'],
            'city' => $address['city'],
            'street_name' => $address['street_name'],
            'street_type' => $address['street_type'],
            'house_number' => $address['house_number'],
            'source_type' => self::SOURCE_TYPE,
            'active' => true,
        ]);

        $guardian->save();

        /*
         * Bankszámlaadatot a modellen keresztül írjuk,
         * így a history tábla is megfelelően létrejön.
         */
        $bankAccount = $this->clean(
            $data['bank_account'] ?? null
        );

        if ($bankAccount) {
            $guardian->syncBankAccountData(
                $fullName,
                $bankAccount,
                null,
                self::SOURCE_TYPE,
                'Egyszeri régi Digifood adatimport'
            );
        }

        $child->guardians()->syncWithoutDetaching([
            $guardian->id => [
                'relationship_type' => null,
                'is_legal_representative' => true,
                'has_no_custody' => false,
                'is_emergency_contact' => true,
                'receives_family_allowance' => false,
            ],
        ]);

        if ($wasExisting) {
            $stats['guardian_updated']++;
        } else {
            $stats['guardian_created']++;
        }

        return $guardian;
    }

    private function importDietaryRestrictions(
        Child $child,
        int $institutionId,
        ?string $value,
        array &$stats
    ): void {
        if (!$value) {
            return;
        }

        /*
         * Régi rendszerben több diéta egy mezőben is szerepelhet.
         */
        $names = preg_split(
            '/[,;+\/|]+/u',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            $restriction = DietaryRestriction::firstOrCreate(
                [
                    'institution_id' => $institutionId,
                    'name' => $name,
                ],
                [
                    'type' => DietaryRestriction::TYPE_INTOLERANCE,
                    'active' => true,
                    'sort_order' => 0,
                ]
            );

            $child->dietaryRestrictions()->syncWithoutDetaching(
                [$restriction->id]
            );

            $stats['dietary']++;
        }
    }

    private function importDiscount(
        Child $child,
        int $institutionId,
        string $name,
        mixed $validFrom,
        mixed $validTo,
        array &$unknownDiscounts
    ): bool {
        $name = trim($name);

        $discount = DiscountType::query()
            ->where('institution_id', $institutionId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        /*
         * Kedvezmény százalékát nem találjuk ki.
         * Dry-runban kiírjuk, mit kell párosítani.
         */
        if (!$discount) {
            $unknownDiscounts[$name] = true;

            return false;
        }

        $from = $this->parseExcelDate($validFrom);
        $to = $this->parseExcelDate($validTo);

        /*
         * Ha nincs kezdőnap, a tanév kezdete legyen.
         */
        $from ??= Carbon::create(
            (int) substr((string) $child->school_year, 0, 4),
            9,
            1
        )->startOfDay();

        ChildDiscountPeriod::updateOrCreate(
            [
                'child_id' => $child->id,
                'discount_type_id' => $discount->id,
                'valid_from' => $from->toDateString(),
            ],
            [
                'valid_to' => $to?->toDateString(),
                'source_type' => self::SOURCE_TYPE,
                'note' => 'Importálva a régi étkező rendszerből',
            ]
        );

        /*
         * A jelenlegi discount_type_id legyen az aktuális/
         * legutolsó importált kedvezmény.
         */
        $today = now()->toDateString();

        if (
            $from->toDateString() <= $today
            && ($to === null || $to->toDateString() >= $today)
        ) {
            $child->discount_type_id = $discount->id;
            $child->save();
        }

        return true;
    }

    private function importMealSetting(
        Child $child,
        int $institutionId,
        string $value,
        SchoolYear $schoolYear,
        array &$unknownMealTypes
    ): int {
        $requestedNames = $this->mealNamesFromLegacyValue($value);

        if ($requestedNames === []) {
            return 0;
        }

        $mealTypeIds = [];

        foreach ($requestedNames as $mealName) {
            $institutionMealType = InstitutionMealType::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->whereHas('mealType', function ($query) use ($mealName) {
                $query->whereRaw(
                    'LOWER(name) = ?',
                    [mb_strtolower($mealName)]
                );
            })
            ->first();

            if (!$institutionMealType) {
                $unknownMealTypes[$mealName] = true;

                continue;
            }

            $mealTypeIds[] = $institutionMealType->id;
        }

        if (count($mealTypeIds) !== count($requestedNames)) {
            return 0;
        }

        $setting = StudentMealSetting::updateOrCreate(
            [
                'student_id' => $child->id,
                'institution_id' => $institutionId,
                'valid_from' => $schoolYear->starts_on->toDateString(),
            ],
            [
                'institution_meal_package_id' => null,
                'mode' => StudentMealSetting::MODE_CUSTOM,
                'valid_to' => $schoolYear->ends_on->toDateString(),
                'created_by' => null,
                'note' => 'Importálva a régi étkező rendszerből',
            ]
        );

        $sync = [];

        foreach ($mealTypeIds as $order => $mealTypeId) {
            $sync[$mealTypeId] = [
                'display_order' => $order + 1,
            ];
        }

        $setting->mealTypes()->sync($sync);

        return count($mealTypeIds);
    }

    private function mealNamesFromLegacyValue(string $value): array
    {
        $normalized = mb_strtolower(trim($value));

        /*
         * Az Excelben látott fő értékek.
         */
        if ($normalized === 'ebéd' || $normalized === 'ebed') {
            return ['Ebéd'];
        }

        if (
            $normalized === 'reggeli-ebéd-uzsonna'
            || $normalized === 'reggeli-ebed-uzsonna'
        ) {
            return [
                'Reggeli',
                'Ebéd',
                'Uzsonna',
            ];
        }

        /*
         * Ha más kombináció jön, próbáljuk kötőjel szerint.
         */
        return collect(
            preg_split('/\s*-\s*/u', trim($value))
        )
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveSchoolYear(
        int $institutionId,
        string $name
    ): array {
        if (
            !preg_match(
                '/^(\d{4})\/(\d{4})$/',
                $name,
                $matches
            )
        ) {
            throw new \RuntimeException(
                'Hibás tanév formátum: '.$name
            );
        }

        $startsOn = $matches[1].'-09-01';
        $endsOn = $matches[2].'-08-31';

        $schoolYear = SchoolYear::updateOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => $name,
            ],
            [
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'active',
                'is_current' => now()->toDateString() >= $startsOn
                    && now()->toDateString() <= $endsOn,
            ]
        );

        return [$schoolYear];
    }

    private function resolveClassGroup(
        int $institutionId,
        SchoolYear $schoolYear,
        string $groupName
    ): ClassGroup {
        $groupName = trim($groupName);

        preg_match(
            '/^(\d{1,2})\.\s*(.+)$/u',
            $groupName,
            $parts
        );

        return ClassGroup::updateOrCreate(
            [
                'school_year_id' => $schoolYear->id,
                'name' => $groupName,
            ],
            [
                'institution_id' => $institutionId,
                'grade_level' => isset($parts[1])
                    ? (int) $parts[1]
                    : null,
                'section' => isset($parts[2])
                    ? trim($parts[2])
                    : null,
                'group_type' => 'school_class',
                'active' => true,
            ]
        );
    }

    private function syncClassMembership(
        Child $child,
        SchoolYear $schoolYear,
        ClassGroup $classGroup
    ): void {
        DB::table('class_group_memberships')
            ->join(
                'class_groups',
                'class_groups.id',
                '=',
                'class_group_memberships.class_group_id'
            )
            ->where(
                'class_group_memberships.child_id',
                $child->id
            )
            ->where(
                'class_groups.school_year_id',
                $schoolYear->id
            )
            ->where(
                'class_groups.id',
                '!=',
                $classGroup->id
            )
            ->update([
                'class_group_memberships.status' => 'transferred',
                'class_group_memberships.left_on' => now()->toDateString(),
                'class_group_memberships.updated_at' => now(),
            ]);

        $membership = DB::table('class_group_memberships')
            ->where('class_group_id', $classGroup->id)
            ->where('child_id', $child->id)
            ->first();

        if ($membership) {
            DB::table('class_group_memberships')
                ->where('id', $membership->id)
                ->update([
                    'status' => 'active',
                    'left_on' => null,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('class_group_memberships')->insert([
            'class_group_id' => $classGroup->id,
            'child_id' => $child->id,
            'status' => 'active',
            'joined_on' => $schoolYear->starts_on->toDateString(),
            'left_on' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function splitHungarianName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return [null, null, null];
        }

        $parts = preg_split('/\s+/u', $fullName);

        $prefix = null;

        $prefixes = [
            'dr.',
            'dr',
            'ifj.',
            'ifj',
            'id.',
            'id',
            'özv.',
            'özv',
        ];

        if (
            isset($parts[0])
            && in_array(mb_strtolower($parts[0]), $prefixes, true)
        ) {
            $prefix = array_shift($parts);
        }

        if (count($parts) === 1) {
            return [$prefix, $parts[0], '-'];
        }

        /*
         * Magyar névsorrend:
         * első szó = vezetéknév,
         * többi = keresztnév.
         */
        $lastName = array_shift($parts);
        $firstName = implode(' ', $parts);

        return [$prefix, $lastName, $firstName];
    }

    private function parseAddress(?string $address): array
    {
        $result = [
            'address_type' => null,
            'country' => 'Magyarország',
            'postal_code' => null,
            'city' => null,
            'street_name' => null,
            'street_type' => null,
            'house_number' => null,
        ];

        $address = trim((string) $address);

        if ($address === '') {
            return $result;
        }

        /*
         * Példa:
         * 1234 Budapest, Kossuth Lajos utca 12.
         */
        if (
            preg_match(
                '/^(\d{4})\s+([^,]+),?\s*(.*)$/u',
                $address,
                $matches
            )
        ) {
            $result['postal_code'] = $matches[1];
            $result['city'] = trim($matches[2]);

            $street = trim($matches[3]);

            if ($street !== '') {
                if (
                    preg_match(
                        '/^(.*)\s+(utca|út|útja|tér|köz|sor|körút|sétány|dűlő)\s+(.+)$/ui',
                        $street,
                        $streetParts
                    )
                ) {
                    $result['street_name'] = trim($streetParts[1]);
                    $result['street_type'] = trim($streetParts[2]);
                    $result['house_number'] = trim($streetParts[3]);
                } else {
                    /*
                     * Ha nem tudjuk biztosan bontani,
                     * legalább az eredeti cím ne vesszen el.
                     */
                    $result['street_name'] = $street;
                }
            }

            return $result;
        }

        /*
         * Ismeretlen formátumnál az egész eredeti értéket
         * megtartjuk a street_name mezőben.
         */
        $result['street_name'] = $address;

        return $result;
    }

    private function parseExcelDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                        (float) $value
                    )
                );
            } catch (Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);

        $formats = [
            'Y-m-d',
            'Y.m.d',
            'Y.m.d.',
            'Y/m/d',
            'd.m.Y',
            'd.m.Y.',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date !== false) {
                    return $date->startOfDay();
                }
            } catch (Throwable) {
                // következő formátum
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function resolveFilePath(string $file): string
    {
        if (is_file($file)) {
            return $file;
        }

        $basePath = base_path($file);

        if (is_file($basePath)) {
            return $basePath;
        }

        $storagePath = storage_path('app/'.$file);

        if (is_file($storagePath)) {
            return $storagePath;
        }

        return $file;
    }

    private function showUnknownValues(
        string $title,
        array $values
    ): void {
        if ($values === []) {
            return;
        }

        $this->newLine();
        $this->warn($title.':');

        foreach (array_keys($values) as $value) {
            $this->line(' - '.$value);
        }
    }
}