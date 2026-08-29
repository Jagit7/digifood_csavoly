<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin;

use App\Models\InstitutionSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionInvoicingSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'invoicing_enabled' => ['nullable', 'boolean'],
            'invoicing_provider' => [
                'nullable',
                'string',
                'max:30',
                Rule::in(array_keys(InstitutionSetting::invoicingProviderOptions())),
                Rule::requiredIf(fn () => $this->boolean('invoicing_enabled')),
            ],
            'card_payment_enabled' => ['nullable', 'boolean'],
            'card_payment_provider' => [
                'nullable',
                'string',
                'max:30',
                Rule::in(array_keys(InstitutionSetting::cardPaymentProviderOptions())),
                Rule::requiredIf(fn () => $this->boolean('card_payment_enabled')),
            ],
            'card_payment_test_mode' => ['nullable', 'boolean'],
            // A banki átutalási adatok a kártyás fizetéstől/számlázástól
            // teljesen függetlenül megadhatók (ld. InstitutionSetting::
            // hasBankTransferAccount()) - ezért nincs requiredIf szabály.
            'bank_transfer_account_holder' => ['nullable', 'string', 'max:191'],
            'bank_transfer_account_number' => ['nullable', 'string', 'max:64'],
            // Az ÁFA-kulcs is a kártyás fizetéstől/számlázástól függetlenül
            // menthető, hiszen a fizetési kötelezettségek (nettó/bruttó)
            // kimutatásaihoz mindig szükséges, a tényleges számlázási
            // integrációtól függetlenül.
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cib_terminal_id' => ['nullable', 'string', 'max:100'],
            'cib_secret_key' => ['nullable', 'string', 'max:2000'],
            'remove_cib_secret_key' => ['nullable', 'boolean'],
            'billingo_api_key' => ['nullable', 'string', 'max:191'],
            'remove_billingo_api_key' => ['nullable', 'boolean'],
            'billingo_document_block_id' => ['nullable', 'string', 'max:100'],
            'billingo_default_payment_method' => ['nullable', 'string', 'max:100'],
            'billingo_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'billingo_invoice_language' => ['nullable', 'string', Rule::in(['hu', 'en', 'de', 'fr', 'hr', 'it', 'ro', 'sk', 'us'])],
            'billingo_e_invoice_enabled' => ['nullable', 'boolean'],
            'billingo_test_mode' => ['nullable', 'boolean'],
            'szamlazz_hu_agent_key' => ['nullable', 'string', 'max:191'],
            'remove_szamlazz_hu_agent_key' => ['nullable', 'boolean'],
            'szamlazz_hu_invoice_prefix' => ['nullable', 'string', 'max:100'],
            'szamlazz_hu_default_payment_method' => ['nullable', 'string', 'max:100'],
            'szamlazz_hu_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'szamlazz_hu_invoice_language' => ['nullable', 'string', 'max:10'],
            'szamlazz_hu_e_invoice_enabled' => ['nullable', 'boolean'],
            'szamlazz_hu_test_mode' => ['nullable', 'boolean'],
        ];
    }
}
