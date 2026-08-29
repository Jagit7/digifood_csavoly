<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentPeriodNotificationJob;
use App\Models\Institution;
use App\Models\PaymentPeriodNotificationLog;
use App\Services\InstitutionCalendarService;
use App\Services\PaymentNotifications\PaymentPeriodNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchPaymentPeriodNotificationsCommand extends Command
{
    protected $signature = 'digifood:payment-period-notifications:dispatch';

    protected $description = 'Dispatches due payment period notification emails for institutions.';

    public function handle(
        PaymentPeriodNotificationService $notifications,
        InstitutionCalendarService $calendar
    ): int {
        $now = $calendar->now();

        Institution::query()
            ->where('active', true)
            ->whereHas('setting', fn ($query) => $query->where('payment_notification_enabled', true))
            ->with('setting')
            ->orderBy('id')
            ->chunkById(100, function ($institutions) use ($notifications, $now) {
                foreach ($institutions as $institution) {
                    $context = $notifications->scheduledNotificationContext($institution, $now);

                    if ($context === null || ! $context['is_due']) {
                        continue;
                    }

                    $recipients = $notifications->notificationRecipientsForInstitution(
                        $institution,
                        $context['payment_period']
                    );

                    foreach ($recipients as $recipient) {
                        DB::transaction(function () use ($institution, $recipient, $context, $now) {
                            $log = PaymentPeriodNotificationLog::query()
                                ->where('institution_id', $institution->id)
                                ->where('user_id', $recipient['user']->id)
                                ->where('year', $context['payment_period']->year)
                                ->where('month', $context['payment_period']->month)
                                ->first();

                            if ($log !== null && in_array($log->status, [
                                PaymentPeriodNotificationLog::STATUS_QUEUED,
                                PaymentPeriodNotificationLog::STATUS_SENDING,
                                PaymentPeriodNotificationLog::STATUS_SENT,
                            ], true)) {
                                return;
                            }

                            if ($log === null) {
                                $log = PaymentPeriodNotificationLog::query()->create([
                                    'institution_id' => $institution->id,
                                    'user_id' => $recipient['user']->id,
                                    'guardian_id' => $recipient['guardian']?->id,
                                    'year' => $context['payment_period']->year,
                                    'month' => $context['payment_period']->month,
                                    'recipient_email' => $recipient['recipient_email'],
                                    'status' => PaymentPeriodNotificationLog::STATUS_QUEUED,
                                    'subject' => $recipient['subject'],
                                    'queued_at' => $now,
                                ]);
                            } else {
                                $log->forceFill([
                                    'guardian_id' => $recipient['guardian']?->id,
                                    'recipient_email' => $recipient['recipient_email'],
                                    'status' => PaymentPeriodNotificationLog::STATUS_QUEUED,
                                    'subject' => $recipient['subject'],
                                    'queued_at' => $now,
                                    'failed_at' => null,
                                    'error_message' => null,
                                ])->save();
                            }

                            DB::afterCommit(fn () => dispatch(new SendPaymentPeriodNotificationJob($log->id)));
                        });
                    }
                }
            });

        return self::SUCCESS;
    }
}
