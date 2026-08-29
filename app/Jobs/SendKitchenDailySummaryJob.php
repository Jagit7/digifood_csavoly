<?php

namespace App\Jobs;

use App\Mail\KitchenDailySummaryMail;
use App\Models\KitchenNotificationLog;
use App\Services\Kitchen\KitchenDailySummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendKitchenDailySummaryJob implements ShouldQueue
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

    public function handle(KitchenDailySummaryService $summaryService): void
    {
        $log = DB::transaction(function () {
            $log = KitchenNotificationLog::query()
                ->with(['institution.setting'])
                ->lockForUpdate()
                ->find($this->logId);

            if ($log === null || ! in_array($log->status, [
                KitchenNotificationLog::STATUS_QUEUED,
                KitchenNotificationLog::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $log->forceFill([
                'status' => KitchenNotificationLog::STATUS_SENDING,
                'failed_at' => null,
            ])->save();

            return $log;
        });

        if ($log === null) {
            return;
        }

        try {
            $summary = $summaryService->buildSummary($log->institution, $log->target_service_date);
            $recipients = $summaryService->recipientEmails($log->institution);

            if ($recipients === []) {
                throw new RuntimeException('Nincs beállított konyhai értesítési címzett.');
            }

            Mail::to($recipients)->send(new KitchenDailySummaryMail($summary));

            DB::transaction(function () use ($log, $summary, $recipients) {
                $freshLog = KitchenNotificationLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => KitchenNotificationLog::STATUS_SENT,
                    'recipient_emails' => $recipients,
                    'subject' => $summary['subject'],
                    'sent_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            Log::error('Kitchen daily summary sending failed.', [
                'log_id' => $log->id,
                'institution_id' => $log->institution_id,
                'service_date' => $log->target_service_date?->toDateString(),
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            DB::transaction(function () use ($log, $exception) {
                $freshLog = KitchenNotificationLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => $this->attempts() >= $this->tries
                        ? KitchenNotificationLog::STATUS_FAILED
                        : KitchenNotificationLog::STATUS_QUEUED,
                    'failed_at' => $this->attempts() >= $this->tries ? now() : null,
                    'error_message' => mb_substr($exception->getMessage(), 0, 65535),
                ])->save();
            });

            throw $exception;
        }
    }
}
