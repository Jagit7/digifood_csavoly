<?php

namespace App\Console\Commands;

use App\Jobs\SendKitchenDailySummaryJob;
use App\Models\Institution;
use App\Models\KitchenNotificationLog;
use App\Services\InstitutionCalendarService;
use App\Services\Kitchen\KitchenDailySummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchKitchenNotificationsCommand extends Command
{
    protected $signature = 'digifood:kitchen-notifications:dispatch';

    protected $description = 'Dispatches due kitchen daily summary emails for institutions.';

    public function handle(
        KitchenDailySummaryService $summaryService,
        InstitutionCalendarService $calendar
    ): int {
        $now = $calendar->now();

        Institution::query()
            ->where('active', true)
            ->whereHas('setting', fn ($query) => $query->where('send_kitchen_email', true))
            ->with(['setting', 'mealSetting'])
            ->orderBy('id')
            ->chunkById(100, function ($institutions) use ($summaryService, $now) {
                foreach ($institutions as $institution) {
                    $recipients = $summaryService->recipientEmails($institution);

                    if ($recipients === []) {
                        continue;
                    }

                    $context = $summaryService->scheduledNotificationContext($institution, $now);

                    if ($context === null || ! $context['is_due']) {
                        continue;
                    }

                    DB::transaction(function () use ($institution, $context, $recipients, $now) {
                        $serviceDate = $context['service_date']->toDateString();
                        $log = KitchenNotificationLog::query()
                            ->where('institution_id', $institution->id)
                            ->whereDate('target_service_date', $serviceDate)
                            ->first();

                        // A DispatchPaymentPeriodNotificationsCommand-hoz
                        // hasonlóan: egy QUEUED/SENDING/SENT állapotú
                        // korábbi napló azt jelenti, hogy már folyamatban
                        // van vagy megtörtént a küldés, ezt nem duplikáljuk.
                        // Egy FAILED (vagy egyéb, korábbi) állapotú naplót
                        // viszont ÚJRA KELL küldeni - eddig ez a parancs
                        // bármilyen meglévő naplónál (FAILED esetén is)
                        // egyszerűen kihagyta az intézményt, így egy
                        // sikertelen konyhai értesítés örökre elveszett,
                        // amíg valaki kézzel nem törölte a naplórekordot.
                        if ($log !== null && in_array($log->status, [
                            KitchenNotificationLog::STATUS_QUEUED,
                            KitchenNotificationLog::STATUS_SENDING,
                            KitchenNotificationLog::STATUS_SENT,
                        ], true)) {
                            return;
                        }

                        if ($log === null) {
                            $log = KitchenNotificationLog::query()->create([
                                'institution_id' => $institution->id,
                                'target_service_date' => $serviceDate,
                                'status' => KitchenNotificationLog::STATUS_QUEUED,
                                'recipient_emails' => $recipients,
                                'scheduled_at' => $now,
                            ]);
                        } else {
                            $log->forceFill([
                                'status' => KitchenNotificationLog::STATUS_QUEUED,
                                'recipient_emails' => $recipients,
                                'scheduled_at' => $now,
                                'failed_at' => null,
                                'error_message' => null,
                            ])->save();
                        }

                        DB::afterCommit(fn () => dispatch(new SendKitchenDailySummaryJob($log->id)));
                    });
                }
            });

        return self::SUCCESS;
    }
}
