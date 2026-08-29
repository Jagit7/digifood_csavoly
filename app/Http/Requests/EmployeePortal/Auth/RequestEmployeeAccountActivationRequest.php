<?php

namespace App\Http\Requests\EmployeePortal\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RequestEmployeeAccountActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Az e-mail cím megadása kötelező.',
            'email.email' => 'Kérjük, adjon meg egy érvényes e-mail címet.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
