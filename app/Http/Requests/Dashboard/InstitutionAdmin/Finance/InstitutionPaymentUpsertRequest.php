<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Finance;

use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Support\AdminInstitutionContext;
use App\Support\Finance\PaymentComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstitutionPaymentUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $institutionId = $this->institutionId();

        return [
            'child_id' => [
                'required',
                'integer',
                Rule::exists('children', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)),
            ],
            'guardian_id' => [
                'nullable',
                'integer',
                Rule::exists('guardians', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)),
            ],
            'monthly_payment_statement_id' => [
                'nullable',
                'integer',
                Rule::exists('monthly_payment_statements', 'id')->where(fn ($query) => $query->where('institution_id', $institutionId)),
            ],
            'payment_component' => ['nullable', Rule::in(array_keys(InstitutionPayment::componentOptions()))],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(array_keys(InstitutionPayment::paymentMethodOptions()))],
            'status' => ['required', Rule::in(array_keys(InstitutionPayment::statusOptions()))],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $institutionId = $this->institutionId();

            if (! $institutionId || $validator->errors()->isNotEmpty()) {
                return;
            }

            $childId = (int) $this->input('child_id');
            $guardianId = $this->filled('guardian_id') ? (int) $this->input('guardian_id') : null;
            $statementId = $this->filled('monthly_payment_statement_id') ? (int) $this->input('monthly_payment_statement_id') : null;
            $component = $this->input('payment_component');
            $amount = (int) $this->input('amount');
            $setting = $institutionId
                ? InstitutionSetting::query()->where('institution_id', $institutionId)->first()
                : null;
            $splitEnabled = (bool) ($setting?->split_manual_transfer_enabled ?? false);

            if ($splitEnabled && ! in_array($component, PaymentComponent::all(), true)) {
                $validator->errors()->add('payment_component', 'A kétbankszámlás modellnél meg kell adni, melyik számlára érkezett a befizetés.');
            }

            if ($guardianId) {
                $guardianLinked = DB::table('child_guardian')
                    ->where('child_id', $childId)
                    ->where('guardian_id', $guardianId)
                    ->exists();

                if (! $guardianLinked) {
                    $validator->errors()->add('guardian_id', 'Csak a kiválasztott gyermekhez kapcsolt gondviselő rögzíthető.');
                }
            }

            if ($statementId) {
                $statement = MonthlyPaymentStatement::query()
                    ->where('institution_id', $institutionId)
                    ->find($statementId);

                if ($statement && $statement->child_id !== $childId) {
                    $validator->errors()->add('monthly_payment_statement_id', 'A kiválasztott havi kötelezettség nem ehhez a gyermekhez tartozik.');
                }

                if ($statement && ! $splitEnabled) {
                    $currentPaymentId = $this->route('payment')?->id;
                    $completedPaymentsTotal = (int) InstitutionPayment::query()
                        ->where('institution_id', $institutionId)
                        ->where('monthly_payment_statement_id', $statement->id)
                        ->where('status', InstitutionPayment::STATUS_COMPLETED)
                        ->when($currentPaymentId, fn ($query) => $query->whereKeyNot($currentPaymentId))
                        ->sum('amount');

                    $remainingAmount = max(0, (int) $statement->total_payable - $completedPaymentsTotal);

                    if ($remainingAmount <= 0) {
                        $validator->errors()->add('monthly_payment_statement_id', 'A kiválasztott havi kötelezettség teljesen rendezett, ehhez új befizetés nem rögzíthető.');
                    }

                    if ($amount > $remainingAmount) {
                        $validator->errors()->add('amount', 'A befizetés összege nem lehet nagyobb a fennmaradó tartozásnál ('.number_format($remainingAmount, 0, ',', ' ').' Ft).');
                    }
                }
            }
        });
    }

    private function institutionId(): ?int
    {
        return app(AdminInstitutionContext::class)->currentInstitution($this->user())?->id;
    }
}
