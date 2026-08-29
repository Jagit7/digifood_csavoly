<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\DataImport;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\SchoolYear;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchoolStandardImportService
{
    public const SHEET_NAME = 'Szülő, törvényes képviselő';

    private const HEADERS = [
        'Tanuló neve',
        'Tanuló oktatási azonosító',
        'Szülő, törvényes képviselő előtagja',
        'Szülő, törvényes képviselő vezetékneve',
        'Szülő, törvényes képviselő keresztneve',
        'Rokonsági foka',
        'Törvényes képviselő',
        'Szülői felügyeletet nem gyakorló',
        'Értesítendő hozzátartozó',
        'Családi pótlékra jogosult személy',
        'Szülő, törvényes képviselő telefonszáma',
        'Telefon típus',
        'Szülő, törvényes képviselő értesítési e-mail cím',
        'Cím típusa',
        'Ország',
        'Irányítószám',
        'Helységnév',
        'Közterület név',
        'Közterület jelleg',
        'Házszám',
        'Emelet',
        'Ajtó',
    ];

    public function __construct(private readonly XlsxReader $reader)
    {
    }

    public function import(DataImport $import, string $filePath): DataImport
    {
        $rows = $this->reader->readSheet($filePath, self::SHEET_NAME);

        if ($rows === []) {
            throw new RuntimeException('Az Excel fő munkalapja üres.');
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_pad($rows[0], count(self::HEADERS), null));
        $missingHeaders = array_values(array_diff(self::HEADERS, $headers));

        if ($missingHeaders !== []) {
            throw new RuntimeException('Az Excel szerkezete nem megfelelő. Hiányzó oszlopok: '.implode(', ', $missingHeaders));
        }

        $headerIndexes = array_flip($headers);
        $dataRows = array_slice($rows, 1);
        [$schoolYear, $classGroup] = $this->resolveClassGroup($import);
        $defaultDiscount = DiscountType::firstOrCreate(
            [
                'institution_id' => $import->institution_id,
                'name' => 'Kedvezmény nélkül',
                'percentage' => 0,
            ],
            [
                'active' => true,
                'sort_order' => 0,
            ]
        );
        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $seenChildren = [];

        DB::transaction(function () use (
            $dataRows,
            $headerIndexes,
            $import,
            &$errors,
            &$created,
            &$updated,
            &$skipped,
            &$seenChildren,
            $schoolYear,
            $classGroup,
            $defaultDiscount
        ) {
            foreach ($dataRows as $offset => $row) {
                $excelRow = $offset + 2;
                $record = $this->mapRow($row, $headerIndexes);

                if ($this->isEmptyRow($record)) {
                    continue;
                }

                $rowErrors = $this->validateRow($record);

                if ($rowErrors !== []) {
                    $errors[] = ['row' => $excelRow, 'messages' => $rowErrors];
                    $skipped++;
                    continue;
                }

                $identifier = $record['educational_identifier'];
                $child = Child::where('institution_id', $import->institution_id)
                    ->where('educational_identifier', $identifier)
                    ->first();

                if (!isset($seenChildren[$identifier])) {
                    $child ? $updated++ : $created++;
                    $seenChildren[$identifier] = true;
                }

                $child ??= new Child([
                    'institution_id' => $import->institution_id,
                    'educational_identifier' => $identifier,
                    'discount_type_id' => $defaultDiscount->id,
                ]);

                $child->discount_type_id ??= $defaultDiscount->id;

                $child->fill([
                    'name' => $record['child_name'],
                    'group_name' => $import->group_name,
                    'school_year' => $import->school_year,
                    'source_type' => DataImport::PROFILE_SCHOOL_STANDARD,
                    'active' => true,
                ])->save();

                $this->syncClassMembership($child, $schoolYear, $classGroup);

                $guardian = $this->findGuardian($import->institution_id, $record);
                $guardian ??= new Guardian(['institution_id' => $import->institution_id]);
                $guardian->fill([
                    'prefix' => $record['guardian_prefix'],
                    'last_name' => $record['guardian_last_name'],
                    'first_name' => $record['guardian_first_name'],
                    'email' => $record['email'],
                    'phone' => $record['phone'],
                    'phone_type' => $record['phone_type'],
                    'address_type' => $record['address_type'],
                    'country' => $record['country'],
                    'postal_code' => $record['postal_code'],
                    'city' => $record['city'],
                    'street_name' => $record['street_name'],
                    'street_type' => $record['street_type'],
                    'house_number' => $record['house_number'],
                    'floor' => $record['floor'],
                    'door' => $record['door'],
                    'source_type' => DataImport::PROFILE_SCHOOL_STANDARD,
                ])->save();

                $child->guardians()->syncWithoutDetaching([
                    $guardian->id => [
                        'relationship_type' => $record['relationship_type'],
                        'is_legal_representative' => $this->isYes($record['is_legal_representative']),
                        'has_no_custody' => $this->isYes($record['has_no_custody']),
                        'is_emergency_contact' => $this->isYes($record['is_emergency_contact']),
                        'receives_family_allowance' => $this->isYes($record['receives_family_allowance']),
                    ],
                ]);
            }
        });

        $import->update([
            'status' => $errors === [] ? 'completed' : 'completed_with_errors',
            'row_count' => count($dataRows),
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'error_count' => count($errors),
            'errors' => $errors ?: null,
            'completed_at' => now(),
        ]);

        return $import->refresh();
    }

    private function mapRow(array $row, array $indexes): array
    {
        $value = fn (string $header) => $this->nullIfEmpty($row[$indexes[$header]] ?? null);

        return [
            'child_name' => $value(self::HEADERS[0]),
            'educational_identifier' => $value(self::HEADERS[1]),
            'guardian_prefix' => $value(self::HEADERS[2]),
            'guardian_last_name' => $value(self::HEADERS[3]),
            'guardian_first_name' => $value(self::HEADERS[4]),
            'relationship_type' => $value(self::HEADERS[5]),
            'is_legal_representative' => $value(self::HEADERS[6]),
            'has_no_custody' => $value(self::HEADERS[7]),
            'is_emergency_contact' => $value(self::HEADERS[8]),
            'receives_family_allowance' => $value(self::HEADERS[9]),
            'phone' => $value(self::HEADERS[10]),
            'phone_type' => $value(self::HEADERS[11]),
            'email' => $value(self::HEADERS[12]),
            'address_type' => $value(self::HEADERS[13]),
            'country' => $value(self::HEADERS[14]),
            'postal_code' => $value(self::HEADERS[15]),
            'city' => $value(self::HEADERS[16]),
            'street_name' => $value(self::HEADERS[17]),
            'street_type' => $value(self::HEADERS[18]),
            'house_number' => $value(self::HEADERS[19]),
            'floor' => $value(self::HEADERS[20]),
            'door' => $value(self::HEADERS[21]),
        ];
    }

    private function validateRow(array $record): array
    {
        $errors = [];

        if (!$record['child_name']) {
            $errors[] = 'A tanuló neve hiányzik.';
        }

        if (!$record['educational_identifier'] || !preg_match('/^\d{11}$/', $record['educational_identifier'])) {
            $errors[] = 'Az oktatási azonosítónak 11 számjegyből kell állnia.';
        }

        if (!$record['guardian_last_name'] || !$record['guardian_first_name']) {
            $errors[] = 'A szülő vagy törvényes képviselő neve hiányzik.';
        }

        if ($record['email'] && !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A kapcsolattartói e-mail-cím formátuma hibás.';
        }

        return $errors;
    }

    private function findGuardian(int $institutionId, array $record): ?Guardian
    {
        $query = Guardian::where('institution_id', $institutionId);

        if ($record['email']) {
            return $query->where('email', $record['email'])->first();
        }

        $query->where('last_name', $record['guardian_last_name'])
            ->where('first_name', $record['guardian_first_name']);

        if ($record['phone']) {
            $query->where('phone', $record['phone']);
        }

        return $query->first();
    }

    private function isEmptyRow(array $record): bool
    {
        return collect($record)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty();
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function isYes(?string $value): bool
    {
        return mb_strtolower(trim((string) $value)) === 'igen';
    }

    private function resolveClassGroup(DataImport $import): array
    {
        if (!preg_match('/^(\d{4})\/(\d{4})$/', (string) $import->school_year, $yearParts)) {
            throw new RuntimeException('A tanév formátuma nem megfelelő az osztály létrehozásához.');
        }

        $startsOn = $yearParts[1].'-09-01';
        $endsOn = $yearParts[2].'-08-31';
        $schoolYear = SchoolYear::updateOrCreate(
            [
                'institution_id' => $import->institution_id,
                'name' => $import->school_year,
            ],
            [
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'status' => 'active',
                'is_current' => now()->toDateString() >= $startsOn && now()->toDateString() <= $endsOn,
            ]
        );

        preg_match('/^(\d{1,2})\.\s*(.+)$/u', trim((string) $import->group_name), $groupParts);
        $classGroup = ClassGroup::updateOrCreate(
            [
                'school_year_id' => $schoolYear->id,
                'name' => $import->group_name,
            ],
            [
                'institution_id' => $import->institution_id,
                'grade_level' => isset($groupParts[1]) ? (int) $groupParts[1] : null,
                'section' => isset($groupParts[2]) ? trim($groupParts[2]) : null,
                'group_type' => $import->institution()->value('type') === 'ovoda'
                    ? 'kindergarten_group'
                    : 'school_class',
                'active' => true,
            ]
        );

        return [$schoolYear, $classGroup];
    }

    private function syncClassMembership(
        Child $child,
        SchoolYear $schoolYear,
        ClassGroup $classGroup
    ): void {
        DB::table('class_group_memberships')
            ->join('class_groups', 'class_groups.id', '=', 'class_group_memberships.class_group_id')
            ->where('class_group_memberships.child_id', $child->id)
            ->where('class_groups.school_year_id', $schoolYear->id)
            ->where('class_groups.id', '!=', $classGroup->id)
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
        } else {
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
    }
}
