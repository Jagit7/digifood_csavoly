<?php

namespace App\Services;

use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\ClassGroup;
use App\Models\DataImport;
use App\Models\Guardian;
use App\Models\SchoolYear;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Óvodai Excel import: "Gyermek neve / Anyja neve / Apja neve / Lakóhely /
 * Csoport neve / Email" fejlécű, szabad (nem külső rendszerből exportált,
 * kötött sablonú) Excel feldolgozása.
 *
 * A "Lakóhely" mező mindig ir. szám + település + cím együtt (pl.
 * "1025 Budapest, Szilfa u. 4."), ezt bontjuk szét ir.szám/település
 * részre (parseResidenceAddress()) - a maradék (utca, házszám stb.) egyben
 * kerül a gondviselő "street_name" / a számlázási profil "address" mezőjébe,
 * mivel a forrás szöveg nem strukturált eléggé egy finomabb szétbontáshoz.
 *
 * Soronként EGY gyermek és 1-2 szülő szerepel (anya és/vagy apa neve).
 * Az első megadott szülő (anya, ha szerepel, különben apa) lesz az "1.
 * gondviselő": ő kapja a sorból kiolvasott lakcímet és e-mail címet, és
 * az ő adataiból jön létre (ensureDefaultBillingProfile() mintájára) a
 * gyermek alapértelmezett számlázási profilja is. A másik szülő (ha
 * szerepel) szintén létrejön gondviselőként, de cím/e-mail/számlázás
 * nélkül - csak a neve kerül be, hogy az adat ne vesszen el.
 *
 * Nincs "oktatási azonosító" jellegű oszlop ezen a sablonon, ezért a
 * gyermek azonosítása (új vs. meglévő) intézményen belül a NÉV alapján
 * történik - két azonos nevű gyermek importja emiatt egy rekordba
 * összeolvad. Ezt a korlátot az import feltöltő felülete is jelzi.
 */
class KindergartenImportService
{
    private const HEADERS = [
        'Gyermek neve',
        'Anyja neve',
        'Apja neve',
        'Lakóhely',
        'Csoport neve',
        'Email',
    ];

    public function __construct(private readonly XlsxReader $reader)
    {
    }

    public function import(DataImport $import, string $filePath): DataImport
    {
        $rows = $this->reader->readFirstSheet($filePath);

        if ($rows === []) {
            throw new RuntimeException('Az Excel munkalapja üres.');
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_pad($rows[0], count(self::HEADERS), null));
        $missingHeaders = array_values(array_diff(self::HEADERS, $headers));

        if ($missingHeaders !== []) {
            throw new RuntimeException('Az Excel szerkezete nem megfelelő. Hiányzó oszlopok: '.implode(', ', $missingHeaders));
        }

        $headerIndexes = array_flip($headers);
        $dataRows = array_slice($rows, 1);

        if (!preg_match('/^(\d{4})\/(\d{4})$/', (string) $import->school_year)) {
            throw new RuntimeException('A nevelési év formátuma nem megfelelő a csoportok létrehozásához.');
        }

        $schoolYear = $this->resolveSchoolYear($import);

        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $seenChildren = [];
        $classGroupCache = [];

        DB::transaction(function () use (
            $dataRows,
            $headerIndexes,
            $import,
            $schoolYear,
            &$errors,
            &$created,
            &$updated,
            &$skipped,
            &$seenChildren,
            &$classGroupCache
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

                $childKey = mb_strtolower($record['child_name']);
                $child = Child::where('institution_id', $import->institution_id)
                    ->whereRaw('LOWER(name) = ?', [$childKey])
                    ->first();

                if (!isset($seenChildren[$childKey])) {
                    $child ? $updated++ : $created++;
                    $seenChildren[$childKey] = true;
                }

                $child ??= new Child(['institution_id' => $import->institution_id]);
                $child->fill([
                    'name' => $record['child_name'],
                    'group_name' => $record['group_name'],
                    'school_year' => $import->school_year,
                    'source_type' => DataImport::PROFILE_KINDERGARTEN,
                    'active' => true,
                ])->save();

                $classGroup = $classGroupCache[$record['group_name']]
                    ??= $this->resolveClassGroup($import, $schoolYear, $record['group_name']);
                $this->syncClassMembership($child, $schoolYear, $classGroup);

                $address = $this->parseResidenceAddress($record['address_raw']);
                $this->attachGuardians($child, $import, $record, $address);
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
            'mother_name' => $value(self::HEADERS[1]),
            'father_name' => $value(self::HEADERS[2]),
            'address_raw' => $value(self::HEADERS[3]),
            'group_name' => $value(self::HEADERS[4]),
            'email' => $value(self::HEADERS[5]),
        ];
    }

    private function validateRow(array $record): array
    {
        $errors = [];

        if (!$record['child_name']) {
            $errors[] = 'A gyermek neve hiányzik.';
        }

        if (!$record['mother_name'] && !$record['father_name']) {
            $errors[] = 'Az anyja vagy az apja nevét meg kell adni.';
        }

        if (!$record['group_name']) {
            $errors[] = 'A csoport neve hiányzik.';
        }

        if ($record['email'] && !filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Az e-mail cím formátuma hibás.';
        }

        return $errors;
    }

    /**
     * Az 1-2 szülő gondviselőként való létrehozása/frissítése és a
     * gyermekhez kapcsolása. Az első megadott szülő (anya, ha van, különben
     * apa) kapja a sorból kiolvasott e-mail/cím adatokat, és az ő adataiból
     * jön létre a gyermek alapértelmezett számlázási profilja is - ld. az
     * osztály elején lévő doc-comment. A második szülő (ha van) csak névvel
     * kerül be, cím/e-mail/számlázás nélkül.
     */
    private function attachGuardians(Child $child, DataImport $import, array $record, array $address): void
    {
        $parents = [];

        if ($record['mother_name']) {
            $parents[] = ['name' => $record['mother_name'], 'relationship_type' => 'Édesanya'];
        }

        if ($record['father_name']) {
            $parents[] = ['name' => $record['father_name'], 'relationship_type' => 'Édesapa'];
        }

        foreach ($parents as $index => $parent) {
            $isPrimary = $index === 0;
            $nameParts = $this->splitHungarianName($parent['name']);

            $guardian = $this->findGuardian(
                $import->institution_id,
                $nameParts['last_name'],
                $nameParts['first_name'],
                $isPrimary ? $record['email'] : null
            );
            $guardian ??= new Guardian(['institution_id' => $import->institution_id, 'active' => true]);

            $fill = [
                'last_name' => $nameParts['last_name'],
                'first_name' => $nameParts['first_name'],
                'source_type' => DataImport::PROFILE_KINDERGARTEN,
            ];

            if ($isPrimary) {
                $fill['email'] = $record['email'];
                $fill['postal_code'] = $address['postal_code'];
                $fill['city'] = $address['city'];
                $fill['street_name'] = $address['remainder'];
            }

            $guardian->fill($fill)->save();

            $child->guardians()->syncWithoutDetaching([
                $guardian->id => [
                    'relationship_type' => $parent['relationship_type'],
                    'is_legal_representative' => true,
                ],
            ]);

            if ($isPrimary) {
                $this->ensureDefaultBillingProfile($child, $guardian, $import->institution_id, $address);
            }
        }
    }

    /**
     * ChildController::ensureDefaultBillingProfile() mintájára: csak akkor
     * hoz létre (vagy kapcsol be) számlázási profilt, ha a gyermeknek még
     * nincs érvényes elsődleges számlafogadója - egy admin által kézzel
     * beállított/módosított számlázási profilt importtal SOSEM írunk felül.
     */
    private function ensureDefaultBillingProfile(Child $child, Guardian $guardian, int $institutionId, array $address): void
    {
        $hasCurrentPrimaryBillingProfile = DB::table('billing_profile_child')
            ->where('child_id', $child->id)
            ->where('is_primary', true)
            ->where(function ($query) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', now()->toDateString());
            })
            ->exists();

        if ($hasCurrentPrimaryBillingProfile) {
            return;
        }

        $profile = $guardian->billingProfiles()->where('active', true)->latest()->first();

        if (!$profile) {
            $profile = BillingProfile::create([
                'institution_id' => $institutionId,
                'guardian_id' => $guardian->id,
                'payer_type' => 'guardian',
                'billing_name' => $guardian->full_name,
                'postal_code' => $address['postal_code'],
                'city' => $address['city'],
                'address' => $address['remainder'],
                'email' => $guardian->email,
                'active' => true,
            ]);
        }

        $existingLink = DB::table('billing_profile_child')
            ->where('billing_profile_id', $profile->id)
            ->where('child_id', $child->id)
            ->exists();

        if ($existingLink) {
            DB::table('billing_profile_child')
                ->where('billing_profile_id', $profile->id)
                ->where('child_id', $child->id)
                ->update([
                    'is_primary' => true,
                    'valid_to' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('billing_profile_child')->insert([
                'billing_profile_id' => $profile->id,
                'child_id' => $child->id,
                'is_primary' => true,
                'valid_from' => now()->toDateString(),
                'valid_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function findGuardian(int $institutionId, string $lastName, string $firstName, ?string $email): ?Guardian
    {
        if ($email) {
            $byEmail = Guardian::where('institution_id', $institutionId)->where('email', $email)->first();

            if ($byEmail) {
                return $byEmail;
            }
        }

        return Guardian::where('institution_id', $institutionId)
            ->where('last_name', $lastName)
            ->where('first_name', $firstName)
            ->first();
    }

    /**
     * A magyar névsorrend (Vezetéknév Keresztnév) alapján az utolsó szóközt
     * tekintjük a kereszt- és vezetéknév határának - a Guardian modellen a
     * last_name/first_name egyaránt kötelező (nem nullable) mező, ezért egy
     * darab szóból álló névnél (nincs mit szétbontani) mindkét mezőbe ez az
     * egy szó kerül, hogy sose maradjon üresen egyik sem.
     */
    private function splitHungarianName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            $only = $parts[0] ?? $fullName;

            return ['last_name' => $only, 'first_name' => $only];
        }

        $firstName = array_pop($parts);
        $lastName = implode(' ', $parts);

        return ['last_name' => $lastName, 'first_name' => $firstName];
    }

    /**
     * A "Lakóhely" mező mindig "ir. szám település, cím" formátumú (pl.
     * "1025 Budapest, Szilfa u. 4."). Csak az ir. számot és a települést
     * bontjuk ki külön mezőbe - a maradékot (utca, házszám stb.) egyben
     * hagyjuk, mivel a forrás szöveg szabad formátumú, egy finomabb
     * (utca/házszám/emelet/ajtó szintű) szétbontás megbízhatatlan lenne.
     * Ha a szöveg nem illeszkedik erre a mintára, nem próbáljuk erőltetni:
     * az egész szöveg változatlanul a maradék mezőbe kerül, hogy ne
     * vesszen adat.
     */
    private function parseResidenceAddress(?string $raw): array
    {
        if (!$raw) {
            return ['postal_code' => null, 'city' => null, 'remainder' => null];
        }

        if (preg_match('/^(\d{4})\s+([^,]+),?\s*(.*)$/u', $raw, $matches)) {
            return [
                'postal_code' => $matches[1],
                'city' => trim($matches[2]),
                'remainder' => $this->nullIfEmpty($matches[3] ?? null),
            ];
        }

        return ['postal_code' => null, 'city' => null, 'remainder' => $raw];
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

    private function resolveSchoolYear(DataImport $import): SchoolYear
    {
        preg_match('/^(\d{4})\/(\d{4})$/', (string) $import->school_year, $yearParts);
        $startsOn = $yearParts[1].'-09-01';
        $endsOn = $yearParts[2].'-08-31';

        return SchoolYear::updateOrCreate(
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
    }

    /**
     * Iskolai importtal ellentétben itt a csoport neve SORONKÉNT az
     * Excelből jön (nem az űrlapról egyszer az egész importhoz), ezért egy
     * fájlon belül több csoport is vegyesen szerepelhet - a hívó ezért
     * csoportnevenként cache-eli a visszatérési értéket (ld. import()).
     */
    private function resolveClassGroup(DataImport $import, SchoolYear $schoolYear, string $groupName): ClassGroup
    {
        return ClassGroup::updateOrCreate(
            [
                'school_year_id' => $schoolYear->id,
                'name' => $groupName,
            ],
            [
                'institution_id' => $import->institution_id,
                'group_type' => 'kindergarten_group',
                'active' => true,
            ]
        );
    }

    private function syncClassMembership(Child $child, SchoolYear $schoolYear, ClassGroup $classGroup): void
    {
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
