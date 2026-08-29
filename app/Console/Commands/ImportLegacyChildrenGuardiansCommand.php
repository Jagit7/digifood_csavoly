<?php

namespace App\Console\Commands;

use App\Models\BillingProfile;
use App\Models\Child;
use App\Models\Guardian;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportLegacyChildrenGuardiansCommand extends Command
{
    protected $signature = 'digifood:import-legacy-children-guardians
        {file : Az importálandó CSV fájl}
        {--institution-id= : Cél intézmény azonosító}
        {--dry-run : Csak ellenőrzés, adatbázis-módosítás nélkül}';

    protected $description = 'Egyszeri import régi rendszerből származó gyermek-szülő-számlázási CSV-hez.';

    private const SOURCE_TYPE = 'legacy_children_guardians_import';

    /**
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'diakid' => 'child_legacy_id',
        'diaknev' => 'child_name',
        'diakcsoport' => 'child_group_name',
        'kapcsolatiszulotoken' => 'guardian_token',
        'azon' => 'guardian_primary_token',
        'nev' => 'guardian_name',
        'mel' => 'guardian_email',
        'tel' => 'guardian_phone',
        'szamlanev' => 'billing_name',
        'szamlairanyito' => 'billing_postal_code',
        'szamlavaros' => 'billing_city',
        'szamlacim' => 'billing_address',
    ];

    /**
     * @var array<int, int>
     */
    private array $guardianIdsBySourceIndex = [];

    /**
     * @var array<string, int>
     */
    private array $guardianIdsByToken = [];

    /**
     * @var array<int, string>
     */
    private array $tokensByGuardianId = [];

    /**
     * @var array<string, array<string, ?string>>
     */
    private array $canonicalSourceGuardianDataByToken = [];

    public function handle(): int
    {
        $institutionId = (int) $this->option('institution-id');
        $dryRun = (bool) $this->option('dry-run');
        $file = $this->resolveFilePath((string) $this->argument('file'));

        if ($institutionId <= 0) {
            $this->error('Az --institution-id paraméter kötelező.');

            return self::FAILURE;
        }

        if (! is_file($file)) {
            $this->error('A fájl nem található: '.$file);

            return self::FAILURE;
        }

        try {
            [$rows, $delimiter] = $this->readCsv($file);
        } catch (Throwable $exception) {
            $this->error('A CSV nem olvasható: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (count($rows) < 2) {
            $this->error('A CSV nem tartalmaz feldolgozható adatokat.');

            return self::FAILURE;
        }

        try {
            [$headerMap, $missingHeaders] = $this->resolveHeaderMap($rows[0]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($missingHeaders !== []) {
            $this->error('Hiányzó kötelező CSV oszlopok: '.implode(', ', $missingHeaders));

            return self::FAILURE;
        }

        $stats = [
            'csv_rows' => 0,
            'unique_children' => 0,
            'new_children' => 0,
            'existing_children' => 0,
            'new_guardians' => 0,
            'reused_guardians' => 0,
            'parentless_children' => 0,
            'new_child_guardian_links' => 0,
            'new_billing_profiles' => 0,
            'reused_billing_profiles' => 0,
            'new_billing_profile_child_links' => 0,
            'conflicts' => 0,
            'errors' => 0,
        ];

        $conflicts = [];
        $errors = [];
        $notices = [];
        $groupedRows = $this->groupRowsByChildLegacyId(array_slice($rows, 1), $headerMap, $stats, $errors);

        $this->info('Fájl: '.$file);
        $this->info('Intézmény ID: '.$institutionId);
        $this->info('Elválasztó: '.$this->printableDelimiter($delimiter));
        $this->info('Mód: '.($dryRun ? 'DRY-RUN' : 'ÉLES IMPORT'));
        $this->newLine();

        if ($groupedRows->isEmpty()) {
            $stats['errors'] += count($errors);
            $this->renderSummary($stats, $conflicts, $errors, $notices, $dryRun, committed: false);

            return self::FAILURE;
        }

        $stats['unique_children'] = $groupedRows->count();
        $committed = false;

        try {
            DB::beginTransaction();

            foreach ($groupedRows as $childLegacyId => $childRows) {
                $rowNumbers = $childRows->pluck('row_number')->all();
                [$childName, $childGroupName, $childConflictMessages] = $this->resolveChildIdentity($childLegacyId, $childRows);

                if ($childConflictMessages !== []) {
                    foreach ($childConflictMessages as $message) {
                        $conflicts[] = $this->formatIssue($rowNumbers, $message);
                    }

                    continue;
                }

                [$child, $childState, $childConflict] = $this->findExistingChild(
                    $institutionId,
                    $childName,
                    $childGroupName
                );

                if ($childConflict !== null) {
                    $conflicts[] = $this->formatIssue($rowNumbers, $childConflict);

                    continue;
                }

                if ($child === null) {
                    $child = new Child([
                        'institution_id' => $institutionId,
                        'source_type' => self::SOURCE_TYPE,
                    ]);

                    $childState = 'new';
                }

                $child->fill([
                    'name' => $childName,
                    'group_name' => $childGroupName,
                    'active' => true,
                ]);

                if (! $child->exists) {
                    $child->educational_identifier = null;
                    $child->school_year = null;
                }

                $child->save();

                if ($childState === 'new') {
                    $stats['new_children']++;
                } else {
                    $stats['existing_children']++;
                }

                $parentRows = $childRows
                    ->filter(fn (array $row) => $this->rowContainsGuardianSourceData($row))
                    ->values();

                if ($parentRows->isEmpty()) {
                    $stats['parentless_children']++;
                    $notices[] = $this->formatIssue($rowNumbers, 'A gyermekhez nem tartozik importálható szülői rekord, ezért csak a gyermek jön létre.');

                    continue;
                }

                foreach ($parentRows as $row) {
                    $rowNumber = [(int) $row['row_number']];

                    try {
                        [$guardian, $guardianCreated, $guardianReused, $guardianIssues] = $this->resolveGuardian(
                            $institutionId,
                            $row
                        );

                        foreach ($guardianIssues as $issue) {
                            $conflicts[] = $this->formatIssue($rowNumber, $issue);
                        }

                        if ($guardian === null) {
                            continue;
                        }

                        if ($guardianCreated) {
                            $stats['new_guardians']++;
                        } elseif ($guardianReused) {
                            $stats['reused_guardians']++;
                        }

                        if (! $this->childGuardianLinkExists($child->id, $guardian->id)) {
                            $child->guardians()->attach($guardian->id);
                            $stats['new_child_guardian_links']++;
                        }

                        [$billingProfile, $billingProfileCreated] = $this->resolveBillingProfile(
                            $institutionId,
                            $guardian,
                            $row
                        );

                        if ($billingProfileCreated) {
                            $stats['new_billing_profiles']++;
                        } else {
                            $stats['reused_billing_profiles']++;
                        }

                        if (! $this->billingProfileChildLinkExists($billingProfile->id, $child->id)) {
                            DB::table('billing_profile_child')->insert([
                                'billing_profile_id' => $billingProfile->id,
                                'child_id' => $child->id,
                                'is_primary' => ! $this->childHasAnyBillingProfile($child->id),
                                'valid_from' => now()->toDateString(),
                                'valid_to' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $stats['new_billing_profile_child_links']++;
                        }
                    } catch (Throwable $exception) {
                        $errors[] = $this->formatIssue($rowNumber, $exception->getMessage());
                    }
                }
            }

            $stats['conflicts'] = count($conflicts);
            $stats['errors'] += count($errors);

            if ($dryRun || $stats['conflicts'] > 0 || $stats['errors'] > 0) {
                DB::rollBack();
            } else {
                DB::commit();
                $committed = true;
            }
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderSummary($stats, $conflicts, $errors, $notices, $dryRun, $committed);

        if (! $dryRun && ! $committed) {
            $this->error('Az import konfliktus vagy hiba miatt rollbackelve lett, ezért semmi nem íródott az adatbázisba.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int, array<int, string|null>>, 1: string}
     */
    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('A fájl nem nyitható meg.');
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                throw new \RuntimeException('A CSV üres.');
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $rows = [];

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = array_map(function ($value) {
                    if ($value === null) {
                        return null;
                    }

                    $value = (string) $value;
                    $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;

                    $trimmed = trim($value);

                    return $trimmed === '' ? null : $trimmed;
                }, $data);
            }

            return [$rows, $delimiter];
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $firstLine): string
    {
        $delimiters = [';', ',', "\t"];
        $bestDelimiter = ';';
        $bestCount = -1;

        foreach ($delimiters as $delimiter) {
            $count = count(str_getcsv($firstLine, $delimiter));

            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function printableDelimiter(string $delimiter): string
    {
        return match ($delimiter) {
            "\t" => 'TAB',
            ';' => ';',
            ',' => ',',
            default => $delimiter,
        };
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @return array{0: array<string, int>, 1: array<int, string>}
     */
    private function resolveHeaderMap(array $headerRow): array
    {
        $headerMap = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($normalized === '') {
                continue;
            }

            $canonical = self::HEADER_ALIASES[$normalized] ?? null;

            if ($canonical === null) {
                continue;
            }

            $headerMap[$canonical] = $index;
        }

        $requiredHeaders = [
            'child_legacy_id' => 'diakId',
            'child_name' => 'diakNev',
            'child_group_name' => 'diakCsoport',
            'guardian_name' => 'reg.nev',
            'guardian_email' => 'reg.mel',
            'guardian_phone' => 'reg.tel',
            'billing_name' => 'reg.szamlaNev',
            'billing_postal_code' => 'reg.szamlaIranyito',
            'billing_city' => 'reg.szamlaVaros',
            'billing_address' => 'reg.szamlaCim',
        ];

        $missing = [];

        foreach ($requiredHeaders as $key => $label) {
            if (! array_key_exists($key, $headerMap)) {
                $missing[] = $label;
            }
        }

        if (! array_key_exists('guardian_primary_token', $headerMap) && ! array_key_exists('guardian_token', $headerMap)) {
            $missing[] = 'azon vagy kapcsolati_szulo_token';
        }

        return [$headerMap, $missing];
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/u', '', $header) ?? $header;
        $header = mb_strtolower(trim($header));

        return preg_replace('/[^a-z0-9]+/u', '', $header) ?? '';
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @param  array<string, int>  $headerMap
     * @param  array<string, int>  $stats
     * @param  array<int, string>  $errors
     * @return Collection<string, Collection<int, array<string, string|null|int>>>
     */
    private function groupRowsByChildLegacyId(array $rows, array $headerMap, array &$stats, array &$errors): Collection
    {
        $grouped = collect();

        foreach ($rows as $offset => $row) {
            $stats['csv_rows']++;
            $mappedRow = $this->mapRow($row, $headerMap, $offset + 2);
            $childLegacyId = $mappedRow['child_legacy_id'];

            if (! filled($childLegacyId)) {
                $errors[] = $this->formatIssue([$mappedRow['row_number']], 'Hiányzik a diakId, a sor nem dolgozható fel.');

                continue;
            }

            $existing = $grouped->get($childLegacyId, collect());
            $existing->push($mappedRow);
            $grouped->put($childLegacyId, $existing);
        }

        return $grouped;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $headerMap
     * @return array<string, string|null|int>
     */
    private function mapRow(array $row, array $headerMap, int $rowNumber): array
    {
        $value = function (string $key) use ($row, $headerMap) {
            $index = $headerMap[$key] ?? null;

            if ($index === null) {
                return null;
            }

            return $this->nullIfEmpty($row[$index] ?? null);
        };

        return [
            'row_number' => $rowNumber,
            'child_legacy_id' => $value('child_legacy_id'),
            'child_name' => $value('child_name'),
            'child_group_name' => $value('child_group_name'),
            'guardian_primary_token' => $value('guardian_primary_token'),
            'guardian_token' => $value('guardian_token'),
            'guardian_name' => $value('guardian_name'),
            'guardian_email' => $this->normalizeEmail($value('guardian_email')),
            'guardian_phone' => $this->normalizePhone($value('guardian_phone')),
            'billing_name' => $value('billing_name'),
            'billing_postal_code' => $value('billing_postal_code'),
            'billing_city' => $value('billing_city'),
            'billing_address' => $value('billing_address'),
        ];
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = trim((string) ($email ?? ''));

        return $email === '' ? null : mb_strtolower($email);
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) ($phone ?? ''));

        if ($phone === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', '', $phone);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  Collection<int, array<string, string|null|int>>  $childRows
     * @return array{0: string, 1: ?string, 2: array<int, string>}
     */
    private function resolveChildIdentity(string $childLegacyId, Collection $childRows): array
    {
        $names = $childRows
            ->pluck('child_name')
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values();

        $groups = $childRows
            ->pluck('child_group_name')
            ->map(fn ($value) => $value === null ? null : trim((string) $value))
            ->unique(fn ($value) => $value === null ? '__NULL__' : $value)
            ->values();

        $conflicts = [];

        if ($names->count() !== 1) {
            $conflicts[] = "A {$childLegacyId} diakId több eltérő diakNev értékkel szerepel a CSV-ben.";
        }

        if ($groups->count() > 1) {
            $conflicts[] = "A {$childLegacyId} diakId több eltérő diakCsoport értékkel szerepel a CSV-ben.";
        }

        if ($names->isEmpty()) {
            $conflicts[] = "A {$childLegacyId} diakId-hez nem tartozik használható diakNev.";
        }

        return [
            (string) $names->first(),
            $groups->first() === '__NULL__' ? null : $groups->first(),
            $conflicts,
        ];
    }

    /**
     * @return array{0: ?Child, 1: string, 2: ?string}
     */
    private function findExistingChild(int $institutionId, string $name, ?string $groupName): array
    {
        $query = Child::query()
            ->where('institution_id', $institutionId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($groupName === null) {
            $query->whereNull('group_name');
        } else {
            $query->whereRaw('LOWER(group_name) = ?', [mb_strtolower($groupName)]);
        }

        $matches = $query->get();

        if ($matches->count() > 1) {
            return [null, 'conflict', 'A gyermek meglévő rekordjai nem egyértelműek (azonos név és csoport több rekordban).'];
        }

        if ($matches->count() === 1) {
            return [$matches->first(), 'existing', null];
        }

        return [null, 'new', null];
    }

    /**
     * @param  array<string, string|null|int>  $row
     * @return array{0: bool, 1: int, 2: array<int, string>}
     */
    private function resolveSourceGuardianIndex(array $row): array
    {
        $token = $this->nullIfEmpty((string) ($row['guardian_token'] ?? null));

        if ($token === null) {
            return [false, -1, ['A szülői rekordhoz hiányzik a kapcsolati_szulo_token / reg.azon, ezért nem kapcsolható biztonságosan.']];
        }

        $sourceData = [
            'guardian_name' => $this->nullIfEmpty($row['guardian_name'] ?? null),
            'guardian_email' => $this->normalizeEmail($row['guardian_email'] ?? null),
            'guardian_phone' => $this->normalizePhone($row['guardian_phone'] ?? null),
            'billing_name' => $this->nullIfEmpty($row['billing_name'] ?? null),
            'billing_postal_code' => $this->nullIfEmpty($row['billing_postal_code'] ?? null),
            'billing_city' => $this->nullIfEmpty($row['billing_city'] ?? null),
            'billing_address' => $this->nullIfEmpty($row['billing_address'] ?? null),
        ];

        $issues = [];

        if (! array_key_exists($token, $this->canonicalSourceGuardianDataByToken)) {
            $index = count($this->canonicalSourceGuardianDataByToken);
            $this->canonicalSourceGuardianDataByToken[$token] = $sourceData;
            $this->guardianIdsBySourceIndex[$index] = 0;

            return [true, $index, $issues];
        }

        $canonical = $this->canonicalSourceGuardianDataByToken[$token];

        foreach ($sourceData as $field => $value) {
            $canonicalValue = $canonical[$field] ?? null;

            if ($canonicalValue !== null && $value !== null && $canonicalValue !== $value) {
                $issues[] = "A {$token} szülői tokenhez eltérő {$field} értékek tartoznak a CSV-ben.";
                continue;
            }

            if ($canonicalValue === null && $value !== null) {
                $canonical[$field] = $value;
            }
        }

        $this->canonicalSourceGuardianDataByToken[$token] = $canonical;
        $index = array_search($token, array_keys($this->canonicalSourceGuardianDataByToken), true);

        return [false, (int) $index, $issues];
    }

    /**
     * @param  array<string, string|null|int>  $row
     * @return array{0: ?Guardian, 1: bool, 2: bool, 3: array<int, string>}
     */
    private function resolveGuardian(int $institutionId, array $row): array
    {
        [$newTokenSeen, $sourceIndex, $issues] = $this->resolveSourceGuardianIndex($row);

        if ($sourceIndex < 0) {
            return [null, false, false, $issues];
        }

        $token = $this->nullIfEmpty((string) ($row['guardian_token'] ?? null));
        $sourceData = $this->canonicalSourceGuardianDataByToken[$token];

        if (isset($this->guardianIdsByToken[$token])) {
            $guardianId = $this->guardianIdsByToken[$token];
            $guardian = Guardian::query()->find($guardianId);

            if ($guardian !== null) {
                return [$guardian, false, true, $issues];
            }
        }

        $guardian = null;
        $created = false;

        if ($sourceData['guardian_email'] !== null) {
            $matches = Guardian::query()
                ->where('institution_id', $institutionId)
                ->whereRaw('LOWER(email) = ?', [$sourceData['guardian_email']])
                ->get();

            if ($matches->count() > 1) {
                $issues[] = 'Az e-mail cím alapján több meglévő gondviselő is egyezik, ezért nincs automatikus összevonás.';

                return [null, false, false, $issues];
            }

            $guardian = $matches->first();
        } elseif ($sourceData['guardian_name'] !== null && $sourceData['guardian_phone'] !== null) {
            [$lastName, $firstName] = $this->splitHungarianName($sourceData['guardian_name']);

            $matches = Guardian::query()
                ->where('institution_id', $institutionId)
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)])
                ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                ->where('phone', $sourceData['guardian_phone'])
                ->get();

            if ($matches->count() > 1) {
                $issues[] = 'A név + telefonszám alapján több meglévő gondviselő is egyezik, ezért nincs automatikus összevonás.';

                return [null, false, false, $issues];
            }

            $guardian = $matches->first();
        }

        if ($guardian !== null) {
            $existingToken = $this->tokensByGuardianId[$guardian->id] ?? null;

            if ($existingToken !== null && $existingToken !== $token) {
                $issues[] = "A megtalált meglévő gondviselőt már egy másik legacy tokenhez ({$existingToken}) kötötte ez az importfutás, ezért nincs automatikus összevonás.";

                return [null, false, false, $issues];
            }

            $this->applyGuardianDataConservatively($guardian, $sourceData, $issues);
            $guardian->save();
        } else {
            if ($sourceData['guardian_name'] === null) {
                $issues[] = 'Hiányzik a gondviselő neve, ezért nem hozható létre Guardian rekord.';

                return [null, false, false, $issues];
            }

            [$lastName, $firstName] = $this->splitHungarianName($sourceData['guardian_name']);

            $guardian = Guardian::query()->create([
                'institution_id' => $institutionId,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'email' => $sourceData['guardian_email'],
                'phone' => $sourceData['guardian_phone'],
                'source_type' => self::SOURCE_TYPE,
                'active' => true,
            ]);

            $created = true;
        }

        $this->guardianIdsByToken[$token] = (int) $guardian->id;
        $this->tokensByGuardianId[(int) $guardian->id] = $token;
        $this->guardianIdsBySourceIndex[$sourceIndex] = (int) $guardian->id;

        return [$guardian, $created, ! $created || ! $newTokenSeen, $issues];
    }

    /**
     * @param  array<string, ?string>  $sourceData
     * @param  array<int, string>  $issues
     */
    private function applyGuardianDataConservatively(Guardian $guardian, array $sourceData, array &$issues): void
    {
        [$lastName, $firstName] = $sourceData['guardian_name'] !== null
            ? $this->splitHungarianName($sourceData['guardian_name'])
            : [$guardian->last_name, $guardian->first_name];

        $fields = [
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => $sourceData['guardian_email'],
            'phone' => $sourceData['guardian_phone'],
        ];

        foreach ($fields as $field => $incomingValue) {
            if ($incomingValue === null) {
                continue;
            }

            $currentValue = $this->nullIfEmpty($guardian->{$field});

            if ($currentValue !== null && $currentValue !== $incomingValue) {
                $issues[] = "A meglévő gondviselő {$field} mezője eltér a CSV-től, ezért az import ezt nem írja felül.";

                continue;
            }

            if ($currentValue === null) {
                $guardian->{$field} = $incomingValue;
            }
        }
    }

    /**
     * @param  array<string, string|null|int>  $row
     * @return array{0: BillingProfile, 1: bool}
     */
    private function resolveBillingProfile(int $institutionId, Guardian $guardian, array $row): array
    {
        $billingName = $this->nullIfEmpty($row['billing_name'] ?? null) ?: $guardian->full_name;
        $billingPostalCode = $this->nullIfEmpty($row['billing_postal_code'] ?? null);
        $billingCity = $this->nullIfEmpty($row['billing_city'] ?? null);
        $billingAddress = $this->nullIfEmpty($row['billing_address'] ?? null);
        $billingEmail = $this->normalizeEmail($row['guardian_email'] ?? null);

        $query = BillingProfile::query()
            ->where('institution_id', $institutionId)
            ->where('guardian_id', $guardian->id)
            ->where('payer_type', 'guardian')
            ->where('billing_name', $billingName)
            ->where('active', true);

        foreach ([
            'postal_code' => $billingPostalCode,
            'city' => $billingCity,
            'address' => $billingAddress,
            'email' => $billingEmail,
        ] as $field => $value) {
            if ($value === null) {
                $query->whereNull($field);
            } else {
                $query->where($field, $value);
            }
        }

        $profile = $query->first();

        if ($profile !== null) {
            return [$profile, false];
        }

        return [
            BillingProfile::query()->create([
                'institution_id' => $institutionId,
                'guardian_id' => $guardian->id,
                'payer_type' => 'guardian',
                'billing_name' => $billingName,
                'postal_code' => $billingPostalCode,
                'city' => $billingCity,
                'address' => $billingAddress,
                'email' => $billingEmail,
                'active' => true,
            ]),
            true,
        ];
    }

    private function childGuardianLinkExists(int $childId, int $guardianId): bool
    {
        return DB::table('child_guardian')
            ->where('child_id', $childId)
            ->where('guardian_id', $guardianId)
            ->exists();
    }

    private function billingProfileChildLinkExists(int $billingProfileId, int $childId): bool
    {
        return DB::table('billing_profile_child')
            ->where('billing_profile_id', $billingProfileId)
            ->where('child_id', $childId)
            ->exists();
    }

    private function childHasAnyBillingProfile(int $childId): bool
    {
        return DB::table('billing_profile_child')
            ->where('child_id', $childId)
            ->exists();
    }

    /**
     * @param  array<string, string|null|int>  $row
     */
    private function rowContainsGuardianSourceData(array $row): bool
    {
        return collect([
            $row['guardian_token'] ?? null,
            $row['guardian_name'] ?? null,
            $row['guardian_email'] ?? null,
            $row['guardian_phone'] ?? null,
            $row['billing_name'] ?? null,
            $row['billing_postal_code'] ?? null,
            $row['billing_city'] ?? null,
            $row['billing_address'] ?? null,
        ])->contains(fn ($value) => filled($value));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitHungarianName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            $only = $parts[0] ?? $fullName;

            return [$only, $only];
        }

        $firstName = array_pop($parts);
        $lastName = implode(' ', $parts);

        return [$lastName, $firstName];
    }

    /**
     * @param  array<int, int>  $rows
     */
    private function formatIssue(array $rows, string $message): string
    {
        $rowLabel = collect($rows)
            ->map(fn (int $row) => (string) $row)
            ->unique()
            ->implode(', ');

        return sprintf('sor(ok): %s - %s', $rowLabel, $message);
    }

    /**
     * @param  array<string, int>  $stats
     * @param  array<int, string>  $conflicts
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $notices
     */
    private function renderSummary(array $stats, array $conflicts, array $errors, array $notices, bool $dryRun, bool $committed): void
    {
        $this->newLine();
        $this->table(
            ['Összesítés', 'Érték'],
            [
                ['CSV sorok', $stats['csv_rows']],
                ['Egyedi gyermekek', $stats['unique_children']],
                ['Új Child', $stats['new_children']],
                ['Meglévő Child', $stats['existing_children']],
                ['Új Guardian', $stats['new_guardians']],
                ['Újrahasznált Guardian', $stats['reused_guardians']],
                ['Szülő nélküli gyermek', $stats['parentless_children']],
                ['Új child_guardian kapcsolat', $stats['new_child_guardian_links']],
                ['Új BillingProfile', $stats['new_billing_profiles']],
                ['Újrahasznált BillingProfile', $stats['reused_billing_profiles']],
                ['Új billing_profile_child kapcsolat', $stats['new_billing_profile_child_links']],
                ['Konfliktusok', $stats['conflicts']],
                ['Hibák', $stats['errors']],
            ]
        );

        if ($conflicts !== []) {
            $this->newLine();
            $this->warn('Konfliktusok:');

            foreach (array_slice($conflicts, 0, 50) as $conflict) {
                $this->line(' - '.$conflict);
            }

            if (count($conflicts) > 50) {
                $this->line('... további '.(count($conflicts) - 50).' konfliktus.');
            }
        }

        if ($errors !== []) {
            $this->newLine();
            $this->warn('Hibák:');

            foreach (array_slice($errors, 0, 50) as $error) {
                $this->line(' - '.$error);
            }

            if (count($errors) > 50) {
                $this->line('... további '.(count($errors) - 50).' bejegyzés.');
            }
        }

        if ($notices !== []) {
            $this->newLine();
            $this->warn('Jelzések:');

            foreach (array_slice($notices, 0, 50) as $notice) {
                $this->line(' - '.$notice);
            }

            if (count($notices) > 50) {
                $this->line('... további '.(count($notices) - 50).' jelzés.');
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY-RUN volt, az adatbázis nem módosult.');

            return;
        }

        if ($committed) {
            $this->info('Az import sikeresen befejeződött.');

            return;
        }

        $this->warn('Az import nem került commitálásra.');
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
}
