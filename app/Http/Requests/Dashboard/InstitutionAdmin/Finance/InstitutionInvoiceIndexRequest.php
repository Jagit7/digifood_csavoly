<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Finance;

use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionInvoiceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:191'],
            'month' => ['nullable', 'date_format:Y-m'],
            'provider' => ['nullable', Rule::in(array_keys(InstitutionInvoice::providerOptions()))],
            'document_type' => ['nullable', Rule::in(array_keys(InstitutionInvoice::documentTypeOptions()))],
            'status' => ['nullable', Rule::in(array_keys(InstitutionInvoice::statusOptions()))],
            'payment_method' => ['nullable', Rule::in(array_keys(InstitutionPayment::paymentMethodOptions()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'pdf_state' => ['nullable', Rule::in(['missing', 'error'])],
        ];
    }
}
