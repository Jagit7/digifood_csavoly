<?php

namespace App\Http\Requests\ParentPortal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParentBillingDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // A számlázási nevet és az adószámot a szülő a szülői felületen nem
        // módosíthatja, illetve nem is adhatja meg - ezért ezeket a mezőket
        // szándékosan nem fogadjuk el innen. Mindkettőt csak az intézmény
        // adminisztrátora kezelheti (ld. Dashboard\InstitutionAdmin\ParentController).
        return [
            'billing_same_as_address' => ['nullable', 'boolean'],
            'billing_postal_code' => ['nullable', 'string', 'max:10'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_same_as_address' => $this->boolean('billing_same_as_address'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('billing_same_as_address')) {
                $postalCode = trim((string) $this->user()?->guardians()->where('active', true)->orderBy('id')->first()?->postal_code);
                $city = trim((string) $this->user()?->guardians()->where('active', true)->orderBy('id')->first()?->city);
                $address = trim(implode(' ', array_filter([
                    trim((string) $this->user()?->guardians()->where('active', true)->orderBy('id')->first()?->street_name),
                    trim((string) $this->user()?->guardians()->where('active', true)->orderBy('id')->first()?->street_type),
                    trim((string) $this->user()?->guardians()->where('active', true)->orderBy('id')->first()?->house_number),
                    trim((string) $this->user()?->guardians()->where('active', true)->orderBy('id')->first()?->floor),
                    trim((string) $this->user()?->guardians()->where('active', true)->orderBy('id')->first()?->door),
                ])));

                if ($postalCode === '') {
                    $validator->errors()->add('billing_postal_code', 'A lakcím irányítószáma hiányzik, ezért nem másolható át a számlázási címre.');
                }

                if ($city === '') {
                    $validator->errors()->add('billing_city', 'A lakcím települése hiányzik, ezért nem másolható át a számlázási címre.');
                }

                if ($address === '') {
                    $validator->errors()->add('billing_address', 'A lakcím címsora hiányzik, ezért nem másolható át a számlázási címre.');
                }

                return;
            }

            foreach ([
                'billing_postal_code' => 'A számlázási irányítószám megadása kötelező.',
                'billing_city' => 'A számlázási település megadása kötelező.',
                'billing_address' => 'A számlázási cím megadása kötelező.',
            ] as $field => $message) {
                if (trim((string) $this->input($field)) === '') {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        return route('parent.account').'#billing-card';
    }
}
