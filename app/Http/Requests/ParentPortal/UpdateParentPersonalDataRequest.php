<?php

namespace App\Http\Requests\ParentPortal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParentPersonalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'string',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-()\/]{7,50}$/'],
            // A bankszámla-adatokat a szülő a szülői felületen nem módosíthatja,
            // ezért ezek a mezők itt nincsenek felvéve a validálandó adatok közé -
            // lásd ParentAccountService::updatePersonalData().
            'bank_account_holder' => ['prohibited'],
            'bank_account_number' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Ez az e-mail-cím már használatban van.',
            'phone.regex' => 'A telefonszám formátuma nem megfelelő.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return route('parent.account').'#personal-card';
    }
}
