<?php

namespace App\Console\Commands;

use App\Models\Guardian;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Egyszeri adatjavító parancs a "több intézményben, azonos e-mail-lel
 * szereplő szülő csak az egyik gyerekét látja" hibához (ld. doszil7@gmail.com
 * / Doszkocs Attila valós esete).
 *
 * A hiba oka: a guardian-létrehozási pontok (ChildController::attachGuardian()
 * / attachNewGuardianForEdit(), valamint az import-mechanizmusok) minden
 * intézményben egy ÖNÁLLÓ guardians-sort hoznak létre, és user_id-t soha
 * nem állítanak be rajta. A user_id kizárólag a szülő ELSŐ, token-alapú
 * fiókaktiválásakor (ParentAccountActivationService::activate()) íródik be
 * - egy MÁSODIK intézmény által (akár korábban, akár később) létrehozott,
 * ugyanazon e-mail című guardian-sor emiatt örökre "árva" (user_id NULL)
 * maradhat, ha a szülő már aktív fiókkal rendelkezik.
 *
 * A kódjavítás (ld. ParentAccountActivationService::linkOrphanedGuardiansForEmail(),
 * és ennek hívása guardian-létrehozáskor, ismételt aktiválás-kérésnél,
 * valamint minden sikeres szülői bejelentkezéskor) ezt mostantól önjavító
 * módon kezeli - ez a parancs a MÁR LÉTEZŐ, jelenleg árván maradt
 * guardian-sorokat javítja ki egyszeri lefuttatással, anélkül hogy meg
 * kellene várni az érintett szülők következő bejelentkezését.
 *
 * Biztonsági szabályok (ugyanazok, mint a kódban):
 *  - csak olyan guardian-sorokat érint, amelyeknek JELENLEG NINCS user_id-ja;
 *  - csak akkor kapcsol, ha az adott e-mailhez pontosan egy AKTÍV,
 *    ROLE_PARENT users-sor tartozik;
 *  - egy már MÁS user_id-hoz kötött guardian-sort SOHA nem módosít.
 *
 * Idempotens: többször is biztonságosan lefuttatható, egy már összekapcsolt
 * guardian-sort változatlanul hagy.
 */
class LinkOrphanedGuardiansToActiveParentsCommand extends Command
{
    protected $signature = 'digifood:guardians:link-orphaned-to-active-parents
        {--dry-run : Csak jelentést készít, nem ír adatbázist}';

    protected $description = 'A már aktív szülői fiókokhoz tartozó, de jelenleg gazdátlan (user_id NULL) guardian-rekordok egyszeri, automatikus összekapcsolása e-mail alapján.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $emails = Guardian::query()
            ->whereNull('user_id')
            ->whereNotNull('email')
            ->where('active', true)
            ->selectRaw('LOWER(email) as email')
            ->distinct()
            ->pluck('email');

        if ($emails->isEmpty()) {
            $this->info('Nincs gazdátlan (user_id nélküli) aktív guardian-rekord.');

            return self::SUCCESS;
        }

        $linkedCount = 0;
        $skippedNoActiveParent = 0;

        foreach ($emails as $email) {
            $activeParent = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('role', User::ROLE_PARENT)
                ->where('is_active', true)
                ->first();

            if ($activeParent === null) {
                $skippedNoActiveParent++;

                continue;
            }

            $orphaned = Guardian::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNull('user_id')
                ->get(['id', 'institution_id', 'first_name', 'last_name']);

            foreach ($orphaned as $guardian) {
                $this->line(sprintf(
                    '  guardian #%d (intézmény #%d, %s %s, %s) -> user #%d (%s)',
                    $guardian->id,
                    $guardian->institution_id,
                    $guardian->last_name,
                    $guardian->first_name,
                    $email,
                    $activeParent->id,
                    $activeParent->email,
                ));
            }

            $linkedCount += $orphaned->count();

            if (! $dryRun) {
                Guardian::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->whereNull('user_id')
                    ->update(['user_id' => $activeParent->id]);
            }
        }

        if ($dryRun) {
            $this->warn(sprintf(
                'Dry-run mód: %d db guardian-rekord kapcsolódna össze (%d e-mail cím kimaradt, mert nincs hozzá aktív szülői fiók). Nem történt adatbázis-írás.',
                $linkedCount,
                $skippedNoActiveParent,
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Kész: %d db guardian-rekord összekapcsolva a meglévő aktív szülői fiókjával (%d e-mail cím kimaradt, mert nincs hozzá aktív szülői fiók).',
            $linkedCount,
            $skippedNoActiveParent,
        ));

        return self::SUCCESS;
    }
}
