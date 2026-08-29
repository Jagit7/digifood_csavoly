<?php

namespace App\Http\Requests\ParentPortal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParentPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'A jelenlegi jelszó nem megfelelő.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('parent.account').'#security-card';
    }
}
