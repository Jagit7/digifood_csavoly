<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\PaymentObligation\PaymentObligationCalculatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Egyszeri javító parancs a "meal_period = payment_period + 1 hónap" hiba
 * kijavítása (PaymentObligationCalculatorService::resolvePeriods()) UTÁNI
 * takarításhoz.
 *
 * A probléma: a régi (hibás) logikával számolt, majd LEZÁRT hónapokat a
 * sima "Újraszámítás" gomb NEM érinti - a recalculateMonth() szándékosan
 * kihagyja a már lezárt statementeket (ld. a kódban: "if ($statement->exists
 * && $statement->isClosed()) { ... continue; }"). Emiatt egy ilyen lezárt
 * hónapnál a napi bontás már a helyes (a fizetési hónap saját napjait
 * mutató) dátumokat próbálja kikeresni, de nem talál hozzájuk tartozó
 * napi rekordot (mert azok még a régi, eltolt hónapra vannak elmentve) -
 * ezért csupa "-" jelenik meg -, miközben az összesítő mezők
 * (meal_amount, invoiceable_amount, total_payable) még mindig a RÉGI,
 * soha újra nem számolt összeget mutatják.
 *
 * Ez a parancs minden LEZÁRT (status = closed) fizetési hónapot
 * megkeres (intézmény+év+hónap bontásban), és sorban: újranyitja
 * (reopenMonth), majd azonnal újraszámolja (recalculateMonth) a javított
 * logikával. A frissített hónapok szándékosan TERVEZET (draft) állapotban
 * maradnak a futás után - nem zárja le automatikusan újra őket -, hogy az
 * intézményi admin át tudja nézni az új számokat, mielőtt véglegesíti.
 */
class RefixMealPeriodOffsetCommand extends Command
{
    protected $signature = 'digifood:payment-obligations:refix-meal-period
        {--institution= : Csak ennél az institution_id-nél (ha üres: minden intézmény)}
        {--from= : Legkorábbi érintett fizetési hónap, formátum ÉÉÉÉ-HH (ha üres: minden lezárt hónap)}
        {--to= : Legkésőbbi érintett fizetési hónap, formátum ÉÉÉÉ-HH (ha üres: minden lezárt hónap)}
        {--dry-run : Csak kilistázza, mit nyitna újra és számolna újra, tényleges módosítás nélkül}';

    protected $description = 'Az étkezési hónap eltolási hiba javítása után újranyitja és újraszámolja a már LEZÁRT (és emiatt automatikusan újra nem számolt) havi elszámolásokat.';

    public function handle(PaymentObligationCalculatorService $calculator): int
    {
        $institutionId = $this->option('institution');
        $from = $this->option('from') ? Carbon::createFromFormat('Y-m', $this->option('from'))->startOfMonth() : null;
        $to = $this->option('to') ? Carbon::createFromFormat('Y-m', $this->option('to'))->startOfMonth() : null;
        $dryRun = (bool) $this->option('dry-run');

        $groups = MonthlyPaymentStatement::query()
            ->select('institution_id', 'year', 'month')
            ->where('status', MonthlyPaymentStatement::STATUS_CLOSED)
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->when($from, fn ($query) => $query->where(function ($query) use ($from) {
                $query->where('year', '>', $from->year)
                    ->orWhere(function ($query) use ($from) {
                        $query->where('year', $from->year)->where('month', '>=', $from->month);
                    });
            }))
            ->when($to, fn ($query) => $query->where(function ($query) use ($to) {
                $query->where('year', '<', $to->year)
                    ->orWhere(function ($query) use ($to) {
                        $query->where('year', $to->year)->where('month', '<=', $to->month);
                    });
            }))
            ->distinct()
            ->orderBy('institution_id')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('Nincs lezárt fizetési hónap a megadott szűrésnek megfelelően - nincs mit javítani.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d db lezárt intézmény+hónap kombináció érintett.', $groups->count()));

        $skipped = [];
        $processed = 0;

        foreach ($groups as $group) {
            $institution = Institution::find($group->institution_id);

            if (!$institution) {
                continue;
            }

            $month = Carbon::create((int) $group->year, (int) $group->month, 1);
            $label = sprintf('#%d %s – %04d.%02d.', $institution->id, $institution->name, $group->year, $group->month);

            $systemUser = User::query()
                ->where(function ($query) use ($institution) {
                    $query->where('institution_id', $institution->id)
                        ->orWhereHas('institutions', fn ($q) => $q->where('institutions.id', $institution->id));
                })
                ->where('role', User::ROLE_INSTITUTION_ADMIN)
                ->orderBy('id')
                ->first();

            if (!$systemUser) {
                $skipped[] = $label;
                $this->warn("Nincs institution_admin felhasználó ehhez az intézményhez, kihagyva: {$label}");

                continue;
            }

            if ($dryRun) {
                $this->line("[DRY RUN] Újranyitás + újraszámítás: {$label}");

                continue;
            }

            DB::transaction(function () use ($calculator, $institution, $month, $systemUser) {
                $calculator->reopenMonth(
                    $institution,
                    $month,
                    $systemUser,
                    'Automatikus javítás: étkezési hónap eltolási hiba korrekciója (meal_period = payment_period).'
                );
                $calculator->recalculateMonth($institution, $month);
            });

            $processed++;
            $this->info("Frissítve (TERVEZET állapotban maradt, ellenőrizd és zárd le újra): {$label}");
        }

        if ($dryRun) {
            $this->warn('Dry-run mód: nem történt adatbázis-írás.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Kész: %d hónap újranyitva és újraszámolva.%s',
            $processed,
            $skipped ? ' Kihagyva (nincs institution_admin): '.implode('; ', $skipped) : ''
        ));

        return self::SUCCESS;
    }
}
