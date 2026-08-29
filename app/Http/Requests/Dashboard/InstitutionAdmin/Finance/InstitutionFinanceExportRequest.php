<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionFinanceExportRequest extends FormRequest
{
    public const TYPE_PAYMENT_OBLIGATIONS = 'payment_obligations';

    public const TYPE_PAYMENTS = 'payments';

    public const TYPE_DEBTS = 'debts';

    public const TYPE_INVOICES = 'invoices';

    public const TYPE_MONTHLY_SUMMARY = 'monthly_summary';

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'export_type' => ['required', Rule::in(array_keys(self::exportTypeOptions()))],
            'format' => ['required', Rule::in(['xlsx', 'csv'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'month' => ['nullable', 'date_format:Y-m'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }

    public static function exportTypeOptions(): array
    {
        return [
            self::TYPE_PAYMENT_OBLIGATIONS => 'Fizetési kötelezettségek',
            self::TYPE_PAYMENTS => 'Befizetések',
            self::TYPE_DEBTS => 'Tartozások',
            self::TYPE_INVOICES => 'Számlák',
            self::TYPE_MONTHLY_SUMMARY => 'Havi pénzügyi összesítő',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'statement_draft' => 'Kötelezettség: Piszkozat',
            'statement_reviewed' => 'Kötelezettség: Ellenőrzött',
            'statement_closed' => 'Kötelezettség: Lezárt',
            'statement_payable' => 'Kötelezettség: Fizetendő',
            'statement_zero' => 'Kötelezettség: 0 Ft vagy kisebb',
            'payment_pending' => 'Befizetés: Függőben',
            'payment_completed' => 'Befizetés: Teljesült',
            'payment_failed' => 'Befizetés: Sikertelen',
            'payment_refunded' => 'Befizetés: Visszatérítve',
            'payment_cancelled' => 'Befizetés: Törölve',
            'debt_before_due' => 'Tartozás: Határidő előtt',
            'debt_due_today' => 'Tartozás: Ma esedékes',
            'debt_overdue' => 'Tartozás: Késedelmes',
            'debt_partial_paid' => 'Tartozás: Részben fizetve',
            'invoice_draft' => 'Számla: Piszkozat',
            'invoice_pending' => 'Számla: Folyamatban',
            'invoice_issued' => 'Számla: Kiállítva',
            'invoice_paid' => 'Számla: Kifizetve',
            'invoice_overdue' => 'Számla: Lejárt',
            'invoice_cancelled' => 'Számla: Törölve',
            'invoice_voided' => 'Számla: Sztornózva',
            'invoice_failed' => 'Számla: Sikertelen',
        ];
    }
}
