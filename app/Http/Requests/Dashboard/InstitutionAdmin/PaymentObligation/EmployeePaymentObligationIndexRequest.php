<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeePaymentObligationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:191'],
            'meal_status' => ['nullable', Rule::in(['participant', 'non_participant'])],
            'meal_package_id' => ['nullable', 'integer'],
            'discount_type_id' => ['nullable', 'integer'],
            'payment_status' => ['nullable', Rule::in(['draft', 'closed', 'payable', 'zero'])],
        ];
    }
}
