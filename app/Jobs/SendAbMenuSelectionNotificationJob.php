<?php

namespace App\Jobs;

use App\Mail\AbMenuSelectionNotificationMail;
use App\Models\AbMenuSelectionNotificationLog;
use App\Services\Meals\AbMenuSelectionNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendAbMenuSelectionNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $logId
    ) {}

    public function handle(AbMenuSelectionNotificationService $notifications): void
    {
        $log = DB::transaction(function () {
            $log = AbMenuSelectionNotificationLog::query()
                ->with(['abMenuPlan.institution.setting', 'user.guardians', 'guardian'])
                ->lockForUpdate()
                ->find($this->logId);

            if ($log === null || ! in_array($log->status, [
                AbMenuSelectionNotificationLog::STATUS_QUEUED,
                AbMenuSelectionNotificationLog::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $log->forceFill([
                'status' => AbMenuSelectionNotificationLog::STATUS_SENDING,
                'failed_at' => null,
            ])->save();

            return $log;
        });

        if ($log === null) {
            return;
        }

        try {
            $plan = $log->abMenuPlan;

            if ($plan === null) {
                throw new RuntimeException('A menüterv, amihez az A/B menüválasztási értesítés tartozik, már nem található.');
            }

            $recipient = $notifications->notificationRecipientsForPlan($plan)
                ->first(fn (array $item) => (int) $item['user']->id === (int) $log->user_id);

            if ($recipient === null) {
                throw new RuntimeException('Nem található elküldhető A/B menüválasztási értesítési címzett ehhez a naplóbejegyzéshez.');
            }

            $payload = $notifications->buildMailPayload($plan, $recipient);

            Mail::to($log->recipient_email)->send(new AbMenuSelectionNotificationMail($payload));

            DB::transaction(function () use ($log, $payload) {
                $freshLog = AbMenuSelectionNotificationLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => AbMenuSelectionNotificationLog::STATUS_SENT,
                    'subject' => $payload['subject'],
                    'sent_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            Log::error('A/B menu selection notification sending failed.', [
                'log_id' => $log->id,
                'institution_id' => $log->institution_id,
                'ab_menu_plan_id' => $log->ab_menu_plan_id,
                'user_id' => $log->user_id,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            DB::transaction(function () use ($log, $exception) {
                $freshLog = AbMenuSelectionNotificationLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => $this->attempts() >= $this->tries
                        ? AbMenuSelectionNotificationLog::STATUS_FAILED
                        : AbMenuSelectionNotificationLog::STATUS_QUEUED,
                    'failed_at' => $this->attempts() >= $this->tries ? now() : null,
                    'error_message' => mb_substr($exception->getMessage(), 0, 65535),
                ])->save();
            });

            throw $exception;
        }
    }
}
