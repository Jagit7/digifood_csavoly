<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation;

use Illuminate\Foundation\Http\FormRequest;

class FinancialAdjustmentReverseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reversal_reason' => ['required', 'string', 'max:191'],
        ];
    }
}
