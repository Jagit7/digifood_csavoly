<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Finance;

use App\Models\InstitutionPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionPaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', Rule::in(array_keys(InstitutionPayment::statusOptions()))],
            'payment_method' => ['nullable', Rule::in(array_keys(InstitutionPayment::paymentMethodOptions()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
