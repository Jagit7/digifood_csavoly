<?php

namespace App\Jobs;

use App\Mail\InstitutionCampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCampaignEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $recipientId
    ) {
    }

    public function handle(): void
    {
        $recipient = DB::transaction(function () {
            $recipient = EmailCampaignRecipient::query()
                ->with(['campaign.institution', 'guardian'])
                ->lockForUpdate()
                ->find($this->recipientId);

            // A SENDING státuszt is elfogadjuk újrafeldolgozásra. Ha egy
            // korábbi próbálkozás a Mail::send() közben fatálisan elszállt
            // (worker leállt, OOM, timeout - olyan hiba, amit a lenti
            // catch(Throwable) nem tud elkapni), a rekord örökre SENDING
            // állapotban ragadt volna, mert a régi szűrés (csak PENDING/
            // QUEUED) ezt a helyzetet sosem engedte volna újra feldolgozni
            // - a job a queue driver retry_after mechanizmusa miatt
            // amúgy is újra lefut ilyenkor, csak eddig néma no-op-ként
            // tért vissza, státuszváltás és hibaüzenet nélkül. A trade-off
            // tudatos: elvi esélye van, hogy egy rendkívül szűk időablakban
            // (a levél ténylegesen kiment, de a folyamat pont az azt jelző
            // DB-írás előtt szakadt meg) a címzett kétszer kapja meg
            // ugyanazt a tájékoztató e-mailt - ez elhanyagolható kockázat
            // ahhoz képest, hogy egyébként a küldés véglegesen, észrevétlen
            // elakadna.
            if ($recipient === null || ! in_array($recipient->status, [
                EmailCampaignRecipient::STATUS_PENDING,
                EmailCampaignRecipient::STATUS_QUEUED,
                EmailCampaignRecipient::STATUS_SENDING,
            ], true)) {
                return null;
            }

            $recipient->forceFill([
                'status' => EmailCampaignRecipient::STATUS_SENDING,
                'failed_at' => null,
            ])->save();

            if ($recipient->campaign->status === EmailCampaign::STATUS_QUEUED) {
                $recipient->campaign->forceFill([
                    'status' => EmailCampaign::STATUS_SENDING,
                ])->save();
            }

            return $recipient;
        });

        if ($recipient === null) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new InstitutionCampaignMail($recipient));

            DB::transaction(function () use ($recipient) {
                $freshRecipient = EmailCampaignRecipient::query()
                    ->lockForUpdate()
                    ->findOrFail($recipient->id);

                $freshRecipient->forceFill([
                    'status' => EmailCampaignRecipient::STATUS_SENT,
                    'sent_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();

                $this->syncCampaignStatus($freshRecipient->email_campaign_id);
            });
        } catch (Throwable $exception) {
            Log::error('Campaign email sending failed.', [
                'recipient_id' => $recipient->id,
                'campaign_id' => $recipient->email_campaign_id,
                'email' => $recipient->email,
                'attempt' => $this->attempts(),
                'message' => $exception->getMessage(),
            ]);

            DB::transaction(function () use ($recipient, $exception) {
                $freshRecipient = EmailCampaignRecipient::query()
                    ->lockForUpdate()
                    ->findOrFail($recipient->id);

                $freshRecipient->forceFill([
                    'status' => $this->attempts() >= $this->tries
                        ? EmailCampaignRecipient::STATUS_FAILED
                        : EmailCampaignRecipient::STATUS_QUEUED,
                    'failed_at' => $this->attempts() >= $this->tries ? now() : null,
                    'error_message' => mb_substr($exception->getMessage(), 0, 65535),
                ])->save();

                $this->syncCampaignStatus($freshRecipient->email_campaign_id);
            });

            throw $exception;
        }
    }

    private function syncCampaignStatus(int $campaignId): void
    {
        $campaign = EmailCampaign::query()
            ->lockForUpdate()
            ->findOrFail($campaignId);

        $recipientQuery = EmailCampaignRecipient::query()->where('email_campaign_id', $campaignId);
        $sentCount = (clone $recipientQuery)->where('status', EmailCampaignRecipient::STATUS_SENT)->count();
        $failedCount = (clone $recipientQuery)->where('status', EmailCampaignRecipient::STATUS_FAILED)->count();
        $sendingCount = (clone $recipientQuery)->where('status', EmailCampaignRecipient::STATUS_SENDING)->count();

        $status = $campaign->status;
        $completedAt = null;

        if (($sentCount + $failedCount) === $campaign->recipient_count) {
            $status = match (true) {
                $failedCount === 0 => EmailCampaign::STATUS_COMPLETED,
                $sentCount > 0 => EmailCampaign::STATUS_PARTIALLY_FAILED,
                default => EmailCampaign::STATUS_FAILED,
            };
            $completedAt = now();
        } elseif ($sendingCount > 0 || $sentCount > 0) {
            $status = EmailCampaign::STATUS_SENDING;
        } else {
            $status = EmailCampaign::STATUS_QUEUED;
        }

        $campaign->forceFill([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'status' => $status,
            'completed_at' => $completedAt,
        ])->save();
    }
}
