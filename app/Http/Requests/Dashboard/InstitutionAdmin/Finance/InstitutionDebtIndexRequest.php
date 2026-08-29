<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionDebtIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'child_search' => ['nullable', 'string', 'max:191'],
            'guardian_search' => ['nullable', 'string', 'max:191'],
            'month' => ['nullable', 'date_format:Y-m'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['before_due', 'due_today', 'overdue', 'partial_paid'])],
            'overdue_only' => ['nullable', 'boolean'],
        ];
    }
}
