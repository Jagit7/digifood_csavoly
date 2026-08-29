<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation;

use Illuminate\Foundation\Http\FormRequest;

class ManualInvoiceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'invoice_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
