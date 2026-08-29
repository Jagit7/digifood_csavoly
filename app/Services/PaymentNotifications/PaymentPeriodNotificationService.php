<?php

namespace App\Services\PaymentNotifications;

use App\Models\Guardian;
use App\Models\Institution;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Models\User;
use App\Services\InstitutionCalendarService;
use App\Services\ParentPortal\ParentMonthlySettlementService;
use App\Support\PaymentObligation\MonthlyPaymentStatementPeriodHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PaymentPeriodNotificationService
{
    public function __construct(
        private readonly ParentMonthlySettlementService $settlements,
        private readonly PaymentNotificationTemplateService $templates,
        private readonly MonthlyPaymentStatementPeriodHelper $periodHelper,
        private readonly InstitutionCalendarService $calendar
    ) {}

    public function scheduledNotificationContext(Institution $institution, ?CarbonImmutable $now = null): ?array
    {
        $now ??= $this->calendar->now();
        $day = (int) ($institution->setting?->payment_notification_day ?? 0);

        if (! $institution->setting?->payment_notification_enabled || $day < 1 || $day > 31) {
            return null;
        }

        $window = $this->calendar->cancellationWindow($institution->id, $now);
        $cutoff = $window['cutoff'] ?? null;

        return [
            'payment_period' => $now->startOfMonth(),
            'cutoff' => $cutoff,
            'next_service_day' => $window['next_service_day'] ?? null,
            'earliest_cancellable_day' => $window['earliest_cancellable_day'] ?? null,
            'is_due' => $now->day === $day
                && $window['configured'] === true
                && $cutoff instanceof CarbonImmutable
                && $now->gt($cutoff),
        ];
    }

    public function notificationRecipientsForInstitution(Institution $institution, CarbonImmutable $paymentPeriod): Collection
    {
        return $this->candidateUsers($institution)
            ->map(function (User $user) use ($institution, $paymentPeriod) {
                $settlement = $this->settlements->buildInstitutionNotificationData($user, $institution, $paymentPeriod);
                $guardian = $settlement['primary_guardian'];
                $recipientEmail = $this->recipientEmail($user, $guardian);

                if ($recipientEmail === null || ! $this->isFinalPayableSettlement($settlement)) {
                    return null;
                }

                $subjectTemplate = $this->templates->resolvedSubject($institution->setting?->payment_notification_subject);
                $bodyTemplate = $this->templates->resolvedBody($institution->setting?->payment_notification_body);
                $periods = $this->periodHelper->fromMonth($paymentPeriod->toMutable());
                $paymentUrl = route('parent.monthly-settlements.index', ['month' => $paymentPeriod->format('Y-m')]);

                return [
                    'user' => $user,
                    'guardian' => $guardian,
                    'recipient_email' => $recipientEmail,
                    'settlement' => $settlement,
                    'subject' => $subjectTemplate,
                    'custom_body_text' => $bodyTemplate,
                    'payment_url' => $paymentUrl,
                    'periods' => $periods,
                    'amount_label' => $this->formatMoney((int) ($settlement['summary']['remaining_total'] ?? 0)),
                    'due_date_label' => (string) ($settlement['summary']['due_date_label'] ?? ''),
                ];
            })
            ->filter()
            ->values();
    }

    private function candidateUsers(Institution $institution): Collection
    {
        return User::query()
            ->where('role', User::ROLE_PARENT)
            ->where('is_active', true)
            ->whereHas('guardians', function ($query) use ($institution) {
                $query->where('guardians.institution_id', $institution->id)
                    ->where('guardians.active', true)
                    ->whereHas('children', fn ($childQuery) => $childQuery
                        ->where('children.institution_id', $institution->id)
                        ->where('children.active', true));
            })
            ->orderBy('id')
            ->get();
    }

    private function isFinalPayableSettlement(array $settlement): bool
    {
        $childCards = collect($settlement['child_cards'] ?? []);

        if ($childCards->isEmpty()) {
            return false;
        }

        if ($childCards->contains(fn (array $card) => ! ($card['has_statement'] ?? false))) {
            return false;
        }

        if ($childCards->contains(fn (array $card) => ($card['statement']?->status ?? null) !== MonthlyPaymentStatement::STATUS_CLOSED)) {
            return false;
        }

        if ($childCards->contains(fn (array $card) => ! empty($card['issues'] ?? []))) {
            return false;
        }

        return (int) ($settlement['summary']['remaining_total'] ?? 0) > 0;
    }

    private function recipientEmail(User $user, ?Guardian $guardian): ?string
    {
        foreach ([$user->email, $guardian?->email] as $email) {
            $normalized = $this->normalizeEmail($email);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    private function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }
}
