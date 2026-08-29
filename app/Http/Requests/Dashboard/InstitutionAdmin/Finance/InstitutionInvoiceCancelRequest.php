<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Finance;

use Illuminate\Foundation\Http\FormRequest;

class InstitutionInvoiceCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
