<?php

namespace App\Http\Requests\ParentPortal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParentAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'street_type' => ['nullable', 'string', 'max:50'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:20'],
            'door' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('parent.account').'#address-card';
    }
}
