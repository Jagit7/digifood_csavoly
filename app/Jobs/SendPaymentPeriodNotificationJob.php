<?php

namespace App\Jobs;

use App\Mail\PaymentPeriodNotificationMail;
use App\Models\PaymentPeriodNotificationLog;
use App\Services\PaymentNotifications\PaymentPeriodNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendPaymentPeriodNotificationJob implements ShouldQueue
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

    public function handle(PaymentPeriodNotificationService $notifications): void
    {
        $log = DB::transaction(function () {
            $log = PaymentPeriodNotificationLog::query()
                ->with(['institution.setting', 'user', 'guardian'])
                ->lockForUpdate()
                ->find($this->logId);

            if ($log === null || ! in_array($log->status, [
                PaymentPeriodNotificationLog::STATUS_QUEUED,
                PaymentPeriodNotificationLog::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $log->forceFill([
                'status' => PaymentPeriodNotificationLog::STATUS_SENDING,
                'failed_at' => null,
            ])->save();

            return $log;
        });

        if ($log === null) {
            return;
        }

        try {
            $paymentPeriod = CarbonImmutable::create(
                $log->year,
                $log->month,
                1,
                0,
                0,
                0,
                config('app.timezone')
            )->startOfMonth();

            $recipient = $notifications->notificationRecipientsForInstitution($log->institution, $paymentPeriod)
                ->first(fn (array $item) => (int) $item['user']->id === (int) $log->user_id);

            if ($recipient === null) {
                throw new RuntimeException('Nem található elküldhető fizetési értesítési címzett ehhez a naplóbejegyzéshez.');
            }

            $payload = [
                'institution' => $log->institution,
                'subject' => $recipient['subject'],
                'custom_body_text' => $recipient['custom_body_text'],
                'payment_period_label' => $recipient['periods']['payment_period_label'],
                'meal_period_label' => $recipient['periods']['meal_period_label'],
                'credit_period_label' => $recipient['periods']['credit_period_label'],
                'amount_label' => $recipient['amount_label'],
                'due_date_label' => $recipient['due_date_label'],
                'payment_url' => $recipient['payment_url'],
            ];

            Mail::to($log->recipient_email)->send(new PaymentPeriodNotificationMail($payload));

            DB::transaction(function () use ($log, $payload) {
                $freshLog = PaymentPeriodNotificationLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => PaymentPeriodNotificationLog::STATUS_SENT,
                    'subject' => $payload['subject'],
                    'sent_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            Log::error('Payment period notification sending failed.', [
                'log_id' => $log->id,
                'institution_id' => $log->institution_id,
                'user_id' => $log->user_id,
                'payment_period' => sprintf('%04d-%02d', $log->year, $log->month),
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            DB::transaction(function () use ($log, $exception) {
                $freshLog = PaymentPeriodNotificationLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => $this->attempts() >= $this->tries
                        ? PaymentPeriodNotificationLog::STATUS_FAILED
                        : PaymentPeriodNotificationLog::STATUS_QUEUED,
                    'failed_at' => $this->attempts() >= $this->tries ? now() : null,
                    'error_message' => mb_substr($exception->getMessage(), 0, 65535),
                ])->save();
            });

            throw $exception;
        }
    }
}
