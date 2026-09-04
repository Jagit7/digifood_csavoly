<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyHeadcountEmailJob;
use App\Models\DailyHeadcountEmailLog;
use App\Models\Institution;
use App\Services\DailyHeadcount\DailyHeadcountEmailService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Percenként lefutó scheduler parancs (ld. bootstrap/app.php withSchedule())
 * az osztályonkénti/csoportonkénti "Napi létszám e-mailek" funkcióhoz.
 *
 * Nincs osztályonkénti/csoportonkénti külön cron bejegyzés: egyetlen
 * parancs dolgozza fel az összes intézmény összes aktív osztályát/
 * csoportját, az intézmény közös küldési időpontja alapján.
 */
class DispatchDailyHeadcountEmailsCommand extends Command
{
    protected $signature = 'digifood:daily-headcount-email:dispatch';

    protected $description = 'Dispatches the due per-class/group daily meal headcount emails, one per class/group.';

    public function handle(
        DailyHeadcountEmailService $service,
        InstitutionCalendarService $calendar
    ): int {
        $now = $calendar->now();
        $today = $now->startOfDay();

        Institution::query()
            ->where('active', true)
            ->whereHas('setting', fn ($query) => $query->where('daily_headcount_email_enabled', true))
            ->with('setting')
            ->orderBy('id')
            ->chunkById(100, function (Collection $institutions) use ($service, $calendar, $now, $today) {
                foreach ($institutions as $institution) {
                    $this->handleInstitution($institution, $service, $calendar, $now, $today);
                }
            });

        return self::SUCCESS;
    }

    private function handleInstitution(
        Institution $institution,
        DailyHeadcountEmailService $service,
        InstitutionCalendarService $calendar,
        CarbonImmutable $now,
        CarbonImmutable $today
    ): void {
        if (! $this->isDue($institution->setting?->daily_headcount_email_send_time, $now)) {
            return;
        }

        // A napi létszámnak csak akkor van értelme, ha az adott nap
        // ténylegesen szolgáltatási nap (nem hétvége/szünet/ünnepnap) -
        // ugyanaz a naptárlogika dönt erről, mint a Napi működés modulban.
        if (! $calendar->isServiceDay($institution->id, $today)) {
            return;
        }

        $groupRecipients = $service->enabledGroupsWithRecipients($institution->id);

        if ($groupRecipients->isEmpty()) {
            return;
        }

        foreach ($groupRecipients as $groupName => $emails) {
            $this->dispatchForGroup($institution, (string) $groupName, $emails, $today, $now);
        }
    }

    /**
     * Szándékosan NEM percre pontos egyezést vizsgál (current_time ==
     * send_time), hanem azt, hogy a mai küldési időpont már elérkezett-e.
     * Ez biztosítja, hogy egy néhány perccel később lefutó scheduler
     * (pl. 07:33 a beállított 07:30 helyett) még kiküldje a mai e-mailt -
     * az alábbi dispatchForGroup()-beli napi egyedi napló (unique index)
     * pedig gondoskodik róla, hogy emiatt ne menjen ki kétszer. Mivel a
     * vizsgálat mindig a mai napra vonatkozik, egy elmaradt (tegnapi)
     * küldés utólag, másnap sem megy ki.
     */
    private function isDue(?string $sendTime, CarbonImmutable $now): bool
    {
        if (! $sendTime) {
            return false;
        }

        if (! preg_match('/^(\d{2}):(\d{2})/', $sendTime, $matches)) {
            return false;
        }

        return $now->format('H:i') >= $matches[1].':'.$matches[2];
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function dispatchForGroup(
        Institution $institution,
        string $groupName,
        array $emails,
        CarbonImmutable $today,
        CarbonImmutable $now
    ): void {
        DB::transaction(function () use ($institution, $groupName, $emails, $today, $now) {
            $dateString = $today->toDateString();

            $log = DailyHeadcountEmailLog::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', $groupName)
                ->whereDate('headcount_date', $dateString)
                ->first();

            // Egy QUEUED/SENDING/SENT állapotú korábbi napló azt jelenti,
            // hogy a mai e-mail erre az osztályra/csoportra már folyamatban
            // van vagy megtörtént - ezt nem küldjük ki újra (idempotens
            // védelem ismétlődő scheduler-futás esetén). Egy FAILED (vagy
            // korábbi, ismeretlen) állapotú naplót viszont újra kell
            // próbálni.
            if ($log !== null && in_array($log->status, [
                DailyHeadcountEmailLog::STATUS_QUEUED,
                DailyHeadcountEmailLog::STATUS_SENDING,
                DailyHeadcountEmailLog::STATUS_SENT,
            ], true)) {
                return;
            }

            if ($log === null) {
                $log = DailyHeadcountEmailLog::query()->create([
                    'institution_id' => $institution->id,
                    'group_name' => $groupName,
                    'headcount_date' => $dateString,
                    'status' => DailyHeadcountEmailLog::STATUS_QUEUED,
                    'recipient_emails' => $emails,
                    'scheduled_at' => $now,
                ]);
            } else {
                $log->forceFill([
                    'status' => DailyHeadcountEmailLog::STATUS_QUEUED,
                    'recipient_emails' => $emails,
                    'scheduled_at' => $now,
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
            }

            DB::afterCommit(fn () => dispatch(new SendDailyHeadcountEmailJob($log->id)));
        });
    }
}
