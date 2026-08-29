<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation;

use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\FinancialAdjustment;
use App\Support\AdminInstitutionContext;
use App\Support\Finance\PaymentComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinancialAdjustmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                FinancialAdjustment::TYPE_OPENING_DEBT,
                FinancialAdjustment::TYPE_OPENING_CREDIT,
                FinancialAdjustment::TYPE_DEBT,
                FinancialAdjustment::TYPE_CREDIT,
                FinancialAdjustment::TYPE_BILLING_CORRECTION,
                FinancialAdjustment::TYPE_OTHER,
            ])],
            'amount' => ['required', 'integer', 'min:1'],
            'affects_invoice' => ['nullable', 'boolean'],
            'reference_year' => ['nullable', 'integer', 'between:2000,2100'],
            'reference_month' => ['nullable', 'integer', 'between:1,12'],
            'payment_component' => ['nullable', Rule::in(PaymentComponent::all())],
            'entry_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:191'],
            'document_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $institution = app(AdminInstitutionContext::class)->currentInstitution($this->user());

            if (! $institution) {
                return;
            }

            $setting = InstitutionSetting::firstOrCreate(
                ['institution_id' => $institution->id],
                InstitutionSetting::defaults()
            );

            if ((bool) $setting->split_manual_transfer_enabled && ! filled($this->input('payment_component'))) {
                $validator->errors()->add('payment_component', 'A kétbankszámlás modellnél kötelező megadni a pénzügyi komponenst.');
            }
        });
    }
}
