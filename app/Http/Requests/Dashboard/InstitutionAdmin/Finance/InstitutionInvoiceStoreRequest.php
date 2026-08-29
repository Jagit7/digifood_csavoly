<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Finance;

use App\Models\InstitutionInvoice;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Support\AdminInstitutionContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionInvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $institutionId = $this->institutionId();

        return [
            'monthly_payment_statement_id' => [
                'required',
                'integer',
                Rule::exists('monthly_payment_statements', 'id')->where(
                    fn ($query) => $query->where('institution_id', $institutionId)
                ),
            ],
            // Az intézmény beállításaiban rögzített szolgáltató az egyetlen
            // elfogadható érték - a felhasználó nem választhat másikat, még
            // egy manipulált kéréssel sem (ld. InstitutionInvoiceController::resolveProvider()).
            'provider' => ['required', Rule::in([$this->allowedProvider($institutionId)])],
            'payment_method' => ['required', Rule::in(array_keys(InstitutionPayment::paymentMethodOptions()))],
            'due_date' => ['required', 'date'],
            'fulfillment_date' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:191'],
            'customer_email' => ['nullable', 'email', 'max:191'],
            'customer_tax_number' => ['nullable', 'string', 'max:50'],
            'billing_postcode' => ['required', 'string', 'max:20'],
            'billing_city' => ['required', 'string', 'max:100'],
            'billing_address' => ['required', 'string', 'max:191'],
            'source_payment_id' => [
                'nullable',
                'integer',
                Rule::exists('institution_payments', 'id')->where(
                    fn ($query) => $query->where('institution_id', $institutionId)
                ),
            ],
            'note' => ['nullable', 'string'],
        ];
    }

    private function institutionId(): ?int
    {
        return app(AdminInstitutionContext::class)->currentInstitution($this->user())?->id;
    }

    private function allowedProvider(?int $institutionId): string
    {
        $provider = $institutionId
            ? InstitutionSetting::where('institution_id', $institutionId)->value('invoicing_provider')
            : null;

        return in_array($provider, array_keys(InstitutionInvoice::providerOptions()), true)
            ? $provider
            : InstitutionInvoice::PROVIDER_MANUAL;
    }
}
