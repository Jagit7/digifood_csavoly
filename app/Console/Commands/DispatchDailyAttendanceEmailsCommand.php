<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyAttendanceEmailJob;
use App\Models\DailyAttendanceEmailLog;
use App\Models\Institution;
use App\Services\DailyAttendance\DailyAttendanceEmailService;
use App\Services\InstitutionCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DispatchDailyAttendanceEmailsCommand extends Command
{
    protected $signature = 'digifood:daily-attendance-email:dispatch';

    protected $description = 'Dispatches the due daily kindergarten attendance sheet emails, one per group.';

    public function handle(
        DailyAttendanceEmailService $service,
        InstitutionCalendarService $calendar
    ): int {
        $now = $calendar->now();
        $today = $now->startOfDay();

        Institution::query()
            ->where('active', true)
            ->where('type', Institution::TYPE_KINDERGARTEN)
            ->whereHas('setting', fn ($query) => $query->where('daily_attendance_email_enabled', true))
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
        DailyAttendanceEmailService $service,
        InstitutionCalendarService $calendar,
        CarbonImmutable $now,
        CarbonImmutable $today
    ): void {
        if (! $this->isDue($institution->setting?->daily_attendance_email_send_time, $now)) {
            return;
        }

        // A jelenléti ívnek csak akkor van értelme, ha az adott nap
        // ténylegesen szolgáltatási nap (nem hétvége/szünet/ünnepnap) -
        // ugyanaz a naptárlogika dönt erről, mint a Napi működés modulban.
        if (! $calendar->isServiceDay($institution->id, $today)) {
            return;
        }

        $groupRecipients = $service->recipientsByGroup($institution->id);

        if ($groupRecipients->isEmpty()) {
            return;
        }

        foreach ($groupRecipients as $groupName => $emails) {
            if ($emails->isEmpty()) {
                // "Ha egy csoportnak nincs címzettje, arra a csoportra ne
                // menjen e-mail."
                continue;
            }

            $this->dispatchForGroup($institution, (string) $groupName, $emails->all(), $today, $now);
        }
    }

    /**
     * Percre pontos küldési idő ellenőrzése - a parancs percenként fut (ld.
     * bootstrap/app.php withSchedule()), így az intézményenként egyedileg
     * beállítható küldési idő is pontosan tartható, a kitchen-notifications
     * parancshoz hasonló mintával.
     */
    private function isDue(?string $sendTime, CarbonImmutable $now): bool
    {
        if (! $sendTime) {
            return false;
        }

        if (! preg_match('/^(\d{2}):(\d{2})/', $sendTime, $matches)) {
            return false;
        }

        return $now->format('H:i') === $matches[1].':'.$matches[2];
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

            $log = DailyAttendanceEmailLog::query()
                ->where('institution_id', $institution->id)
                ->where('group_name', $groupName)
                ->whereDate('attendance_date', $dateString)
                ->first();

            // Egy QUEUED/SENDING/SENT állapotú korábbi napló azt jelenti,
            // hogy a mai jelenléti ív erre a csoportra már folyamatban van
            // vagy megtörtént - ezt nem küldjük ki újra (idempotens
            // védelem ismétlődő scheduler-futás esetén). Egy FAILED (vagy
            // korábbi, ismeretlen) állapotú naplót viszont újra kell
            // próbálni.
            if ($log !== null && in_array($log->status, [
                DailyAttendanceEmailLog::STATUS_QUEUED,
                DailyAttendanceEmailLog::STATUS_SENDING,
                DailyAttendanceEmailLog::STATUS_SENT,
            ], true)) {
                return;
            }

            if ($log === null) {
                $log = DailyAttendanceEmailLog::query()->create([
                    'institution_id' => $institution->id,
                    'group_name' => $groupName,
                    'attendance_date' => $dateString,
                    'status' => DailyAttendanceEmailLog::STATUS_QUEUED,
                    'recipient_emails' => $emails,
                    'scheduled_at' => $now,
                ]);
            } else {
                $log->forceFill([
                    'status' => DailyAttendanceEmailLog::STATUS_QUEUED,
                    'recipient_emails' => $emails,
                    'scheduled_at' => $now,
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
            }

            DB::afterCommit(fn () => dispatch(new SendDailyAttendanceEmailJob($log->id)));
        });
    }
}
