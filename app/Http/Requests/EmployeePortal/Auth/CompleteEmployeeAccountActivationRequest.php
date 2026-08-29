<?php

namespace App\Http\Requests\EmployeePortal\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CompleteEmployeeAccountActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'Az új jelszó megadása kötelező.',
            'password.confirmed' => 'A jelszó megerősítése nem egyezik.',
        ];
    }
}
