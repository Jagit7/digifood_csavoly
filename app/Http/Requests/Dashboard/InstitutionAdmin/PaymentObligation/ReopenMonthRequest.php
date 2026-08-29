<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation;

use Illuminate\Foundation\Http\FormRequest;

class ReopenMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'reopen_reason' => ['required', 'string', 'max:191'],
        ];
    }
}
