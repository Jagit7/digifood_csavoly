<?php

namespace App\Http\Requests\EmployeePortal;

use Illuminate\Foundation\Http\FormRequest;

class RestoreEmployeeMealCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()?->isEmployee();
    }

    public function rules(): array
    {
        return [
            'service_date' => ['required', 'date'],
            'employee_id' => ['required', 'integer'],
        ];
    }
}
