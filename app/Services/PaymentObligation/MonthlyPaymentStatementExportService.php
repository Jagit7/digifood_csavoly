<?php

namespace App\Services\PaymentObligation;

use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Support\PaymentObligation\MonthlyPaymentStatementDetailPresenter;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MonthlyPaymentStatementExportService
{
    public function __construct(
        private readonly MonthlyPaymentStatementDetailPresenter $presenter
    ) {
    }

    public function export(MonthlyPaymentStatement $statement): BinaryFileResponse
    {
        $rows = $this->presenter->rows($statement);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Havi részletező');

        $headers = [
            'Dátum',
            'Nap',
            'Eredeti státusz',
            'Végleges státusz',
            'Listaár',
            'Kedvezmény',
            'Fizetendő összeg',
            'Megjegyzés',
        ];

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue("{$column}1", $header);
        }

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $rowNumber = 2;

        foreach ($rows as $row) {
            $sheet->setCellValueExplicit("A{$rowNumber}", $row['date_display'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$rowNumber}", $row['day_name'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowNumber}", $row['original_status_label'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$rowNumber}", $row['final_status_label'], DataType::TYPE_STRING);
            $sheet->setCellValue("E{$rowNumber}", $row['list_price_amount']);
            $sheet->setCellValue("F{$rowNumber}", $row['discount_amount']);
            $sheet->setCellValue("G{$rowNumber}", $row['payable_amount']);
            $sheet->setCellValueExplicit("H{$rowNumber}", $row['note_export'], DataType::TYPE_STRING);

            $rowNumber++;
        }

        $lastDataRow = max(1, $rowNumber - 1);
        $sheet->setAutoFilter("A1:H{$lastDataRow}");
        $sheet->freezePane('A2');
        $sheet->getStyle("E2:G{$lastDataRow}")
            ->getNumberFormat()
            ->setFormatCode('# ##0 "Ft"');

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filePath = tempnam(sys_get_temp_dir(), 'digifood_statement_');
        (new Xlsx($spreadsheet))->save($filePath);

        return response()->download($filePath, $this->fileName($statement))->deleteFileAfterSend(true);
    }

    private function fileName(MonthlyPaymentStatement $statement): string
    {
        $childName = $this->normalizeFileNamePart($statement->child->name ?? 'gyermek');
        $month = sprintf('%04d-%02d', $statement->year, $statement->month);

        return "{$childName}-havi-reszletezo-{$month}.xlsx";
    }

    private function normalizeFileNamePart(string $value): string
    {
        $normalized = Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();

        return $normalized !== '' ? $normalized : 'gyermek';
    }
}
