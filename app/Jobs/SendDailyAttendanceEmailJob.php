<?php

namespace App\Jobs;

use App\Mail\DailyAttendanceEmailMail;
use App\Models\DailyAttendanceEmailLog;
use App\Services\DailyAttendance\DailyAttendanceEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendDailyAttendanceEmailJob implements ShouldQueue
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

    public function handle(DailyAttendanceEmailService $service): void
    {
        $log = DB::transaction(function () {
            $log = DailyAttendanceEmailLog::query()
                ->with('institution')
                ->lockForUpdate()
                ->find($this->logId);

            if ($log === null || ! in_array($log->status, [
                DailyAttendanceEmailLog::STATUS_QUEUED,
                DailyAttendanceEmailLog::STATUS_FAILED,
            ], true)) {
                return null;
            }

            $log->forceFill([
                'status' => DailyAttendanceEmailLog::STATUS_SENDING,
                'failed_at' => null,
            ])->save();

            return $log;
        });

        if ($log === null) {
            return;
        }

        try {
            $recipients = $service->recipientEmailsForGroup($log->institution_id, $log->group_name);

            if ($recipients === []) {
                throw new RuntimeException('Nincs beállított címzett ehhez a csoporthoz.');
            }

            $summary = $service->buildGroupSummary($log->institution, $log->group_name, $log->attendance_date);

            Mail::to($recipients)->send(new DailyAttendanceEmailMail($summary));

            DB::transaction(function () use ($log, $summary, $recipients) {
                $freshLog = DailyAttendanceEmailLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => DailyAttendanceEmailLog::STATUS_SENT,
                    'recipient_emails' => $recipients,
                    'subject' => $summary['subject'],
                    'sent_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            Log::error('Daily attendance email sending failed.', [
                'log_id' => $log->id,
                'institution_id' => $log->institution_id,
                'group_name' => $log->group_name,
                'attendance_date' => $log->attendance_date?->toDateString(),
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            DB::transaction(function () use ($log, $exception) {
                $freshLog = DailyAttendanceEmailLog::query()
                    ->lockForUpdate()
                    ->findOrFail($log->id);

                $freshLog->forceFill([
                    'status' => $this->attempts() >= $this->tries
                        ? DailyAttendanceEmailLog::STATUS_FAILED
                        : DailyAttendanceEmailLog::STATUS_QUEUED,
                    'failed_at' => $this->attempts() >= $this->tries ? now() : null,
                    'error_message' => mb_substr($exception->getMessage(), 0, 65535),
                ])->save();
            });

            throw $exception;
        }
    }
}
