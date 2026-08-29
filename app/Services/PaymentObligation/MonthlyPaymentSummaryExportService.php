<?php

namespace App\Services\PaymentObligation;

use App\Models\PaymentObligation\MonthlyPaymentDay;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonthlyPaymentSummaryExportService
{
    public function export(Collection $statements, Carbon $period): BinaryFileResponse
    {
        $paymentPeriod = $period->copy()->startOfMonth();
        $mealPeriod = $paymentPeriod->copy()->addMonth();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Teljes havi lista');

        $headers = [
            'Sorszám',
            'Gyermek neve',
            'Osztály',
        ];

        foreach (range(1, $mealPeriod->daysInMonth) as $dayNumber) {
            $headers[] = sprintf('%02d.', $dayNumber);
        }

        $headers = array_merge($headers, [
            'Étkezési napok',
            'Korábbi egyenleg',
            'Következő havi alap',
            'Előző havi jóváírás',
            'Zsárica fizetendő',
            'Óvodai fizetendő',
            'Teljes fizetendő',
            'Név',
        ]);

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValueExplicit("{$column}1", $header, DataType::TYPE_STRING);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('D2');

        $rowNumber = 2;

        foreach ($statements->values() as $index => $statement) {
            /** @var MonthlyPaymentStatement $statement */
            $daysByDate = $statement->days->keyBy(fn (MonthlyPaymentDay $day) => $day->date->toDateString());

            $sheet->setCellValue("A{$rowNumber}", $index + 1);
            $sheet->setCellValueExplicit("B{$rowNumber}", (string) $statement->child->name, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowNumber}", (string) ($statement->child->group_name ?: '-'), DataType::TYPE_STRING);

            $columnIndex = 4;

            foreach (range(1, $mealPeriod->daysInMonth) as $dayNumber) {
                $date = $mealPeriod->copy()->day($dayNumber)->toDateString();
                $day = $daysByDate->get($date);
                $cell = Coordinate::stringFromColumnIndex($columnIndex).$rowNumber;

                if ($day === null) {
                    $sheet->setCellValueExplicit($cell, '-', DataType::TYPE_STRING);
                    $columnIndex++;
                    continue;
                }

                if ((int) $day->payable_amount > 0) {
                    $sheet->setCellValue($cell, (int) $day->payable_amount);
                } else {
                    $sheet->setCellValueExplicit($cell, $this->dayDisplayValue($day), DataType::TYPE_STRING);
                }

                $columnIndex++;
            }

            $mealCountColumn = Coordinate::stringFromColumnIndex($columnIndex);
            $balanceColumn = Coordinate::stringFromColumnIndex($columnIndex + 1);
            $mealAmountColumn = Coordinate::stringFromColumnIndex($columnIndex + 2);
            $cancellationColumn = Coordinate::stringFromColumnIndex($columnIndex + 3);
            $foundationColumn = Coordinate::stringFromColumnIndex($columnIndex + 4);
            $kindergartenColumn = Coordinate::stringFromColumnIndex($columnIndex + 5);
            $totalPayableColumn = Coordinate::stringFromColumnIndex($columnIndex + 6);
            $nameColumn = Coordinate::stringFromColumnIndex($columnIndex + 7);

            $sheet->setCellValue("{$mealCountColumn}{$rowNumber}", (int) $statement->planned_meal_days);
            $sheet->setCellValue("{$balanceColumn}{$rowNumber}", (int) $statement->previous_balance);
            $sheet->setCellValue("{$mealAmountColumn}{$rowNumber}", (int) $statement->meal_amount);
            $sheet->setCellValue("{$cancellationColumn}{$rowNumber}", (int) $statement->previous_cancellation_credit);
            $sheet->setCellValue("{$foundationColumn}{$rowNumber}", (int) $statement->foundation_total_payable);
            $sheet->setCellValue("{$kindergartenColumn}{$rowNumber}", (int) $statement->kindergarten_total_payable);
            $sheet->setCellValue("{$totalPayableColumn}{$rowNumber}", (int) $statement->total_payable);
            $sheet->setCellValueExplicit("{$nameColumn}{$rowNumber}", $this->payerName($statement), DataType::TYPE_STRING);

            $rowNumber++;
        }

        $lastDataRow = max(1, $rowNumber - 1);
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastDataRow}");

        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $financialColumns = [
            Coordinate::stringFromColumnIndex($mealPeriod->daysInMonth + 5),
            Coordinate::stringFromColumnIndex($mealPeriod->daysInMonth + 6),
            Coordinate::stringFromColumnIndex($mealPeriod->daysInMonth + 7),
            Coordinate::stringFromColumnIndex($mealPeriod->daysInMonth + 8),
            Coordinate::stringFromColumnIndex($mealPeriod->daysInMonth + 9),
            Coordinate::stringFromColumnIndex($mealPeriod->daysInMonth + 10),
        ];

        foreach ($financialColumns as $column) {
            $sheet->getStyle("{$column}2:{$column}{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode('# ##0 "Ft"');
        }

        $filePath = tempnam(sys_get_temp_dir(), 'digifood_summary_');
        (new Xlsx($spreadsheet))->save($filePath);

        return response()->download($filePath, $this->fileName($paymentPeriod))->deleteFileAfterSend(true);
    }

    private function dayDisplayValue(MonthlyPaymentDay $day): string
    {
        return match ($day->status) {
            MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL => 'Nincs étk.',
            MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE => 'Lemondva',
            MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING => 'Késői lem.',
            MonthlyPaymentDay::STATUS_SCHOOL_BREAK => 'Szünet',
            MonthlyPaymentDay::STATUS_CLASS_CANCELLATION => 'Csoport',
            MonthlyPaymentDay::STATUS_WEEKEND => 'Hétvége',
            MonthlyPaymentDay::STATUS_WORKING_SATURDAY => 'Tan. szombat',
            MonthlyPaymentDay::STATUS_ABSENCE => 'Hiányzás',
            MonthlyPaymentDay::STATUS_NO_VALID_PRICE => 'Nincs ár',
            MonthlyPaymentDay::STATUS_FREE_MEAL => '0 Ft',
            MonthlyPaymentDay::STATUS_MANUALLY_MODIFIED => 'Kézi',
            default => '0',
        };
    }

    private function payerName(MonthlyPaymentStatement $statement): string
    {
        $billingProfiles = $statement->child->billingProfiles ?? collect();
        $primaryProfile = $billingProfiles->first(fn ($profile) => (bool) ($profile->pivot->is_primary ?? false));
        $profile = $primaryProfile ?: $billingProfiles->first();

        return (string) ($profile?->billing_name ?: $statement->child->name);
    }

    private function fileName(Carbon $period): string
    {
        return sprintf('Digifood_teljes_havi_osszesito_%04d_%02d.xlsx', $period->year, $period->month);
    }
}
