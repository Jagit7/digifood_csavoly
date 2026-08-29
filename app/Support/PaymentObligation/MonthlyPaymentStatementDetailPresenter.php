<?php

namespace App\Support\PaymentObligation;

use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Illuminate\Support\Collection;

class MonthlyPaymentStatementDetailPresenter
{
    public function rows(MonthlyPaymentStatement $statement): Collection
    {
        return $statement->days->map(function (MonthlyPaymentDay $day) {
            $originalStatusMeta = $day->originalStatusMeta();
            $finalStatusMeta = $day->statusMeta();
            $discountAmount = (int) round($day->original_daily_price * ($day->discount_percent / 100));
            $kindergartenDiscountAmount = (int) ($day->kindergarten_discount_amount ?? 0);

            return [
                'day' => $day,
                'date' => $day->date->toDateString(),
                'date_display' => $day->date->format('Y.m.d.'),
                'day_name' => mb_strtolower($day->date->translatedFormat('l')),
                'original_status_label' => $originalStatusMeta['label'],
                'original_status_badge_class' => $originalStatusMeta['badge_class'],
                'final_status_label' => $finalStatusMeta['label'],
                'final_status_badge_class' => $finalStatusMeta['badge_class'],
                'list_price_amount' => (int) $day->original_daily_price,
                'discount_percent' => (int) $day->discount_percent,
                'discount_amount' => $discountAmount,
                'discount_display' => $this->discountDisplay($day, $discountAmount),
                'foundation_daily_fee' => (int) ($day->foundation_daily_fee ?? 0),
                'foundation_payable_amount' => (int) ($day->foundation_payable_amount ?? 0),
                'kindergarten_daily_fee' => (int) ($day->kindergarten_daily_fee ?? 0),
                'kindergarten_discount_percent' => (int) ($day->kindergarten_discount_percent ?? $day->discount_percent),
                'kindergarten_discount_amount' => $kindergartenDiscountAmount,
                'kindergarten_payable_amount' => (int) ($day->kindergarten_payable_amount ?? 0),
                'payable_amount' => (int) $day->payable_amount,
                'has_missing_price' => $day->status === MonthlyPaymentDay::STATUS_NO_VALID_PRICE,
                'note_lines' => $this->noteLines($day, $finalStatusMeta['description']),
                'note_export' => implode(' | ', $this->noteLines($day, $finalStatusMeta['description'])),
            ];
        });
    }

    public function statusOptions(): array
    {
        return collect([
            MonthlyPaymentDay::STATUS_PAY,
            MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE,
            MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING,
            MonthlyPaymentDay::STATUS_SCHOOL_BREAK,
            MonthlyPaymentDay::STATUS_CLASS_CANCELLATION,
            MonthlyPaymentDay::STATUS_WEEKEND,
            MonthlyPaymentDay::STATUS_WORKING_SATURDAY,
            MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL,
            MonthlyPaymentDay::STATUS_NO_VALID_PRICE,
            MonthlyPaymentDay::STATUS_FREE_MEAL,
            MonthlyPaymentDay::STATUS_MANUALLY_MODIFIED,
        ])->map(fn (string $status) => [
            'value' => $status,
            'label' => MonthlyPaymentDayStatus::meta($status)['label'],
        ])->all();
    }

    private function discountDisplay(MonthlyPaymentDay $day, int $discountAmount): string
    {
        if ($day->discount_percent === 0) {
            return 'Nincs';
        }

        if ($day->original_daily_price > 0) {
            return '-'.$day->discount_percent.'%';
        }

        return '-'.$this->formatForint($discountAmount);
    }

    private function noteLines(MonthlyPaymentDay $day, string $statusDescription): array
    {
        $lines = [$statusDescription];

        if ($day->modification_reason) {
            $lines[] = 'Indok: '.$day->modification_reason;
        }

        if ($day->cancellation) {
            $lines[] = 'Lemondás dátuma: '.$day->cancellation->service_date->format('Y.m.d.');
        }

        if ($day->schoolBreak) {
            $lines[] = 'Szünet oka: '.$day->schoolBreak->title;
        }

        if ($day->classCancellation) {
            $lines[] = 'Csoportszintű lemondás: '.$day->classCancellation->reason;
        }

        if ($day->modifiedBy) {
            $lines[] = 'Módosította: '.$day->modifiedBy->name;
        }

        return $lines;
    }

    private function formatForint(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }
}
