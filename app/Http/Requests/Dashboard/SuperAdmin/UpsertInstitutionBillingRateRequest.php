<?php

namespace App\Http\Requests\Dashboard\SuperAdmin;

use App\Models\Institution;
use App\Models\InstitutionBillingRate;
use Illuminate\Foundation\Http\FormRequest;

class UpsertInstitutionBillingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'price_per_child' => ['nullable', 'numeric', 'min:0'],
            'fixed_monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'minimum_monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hasPricePerChild = $this->filled('price_per_child');
            $hasFixedMonthlyFee = $this->filled('fixed_monthly_fee');

            if ($hasPricePerChild === $hasFixedMonthlyFee) {
                $message = 'Pontosan az egyik díjtípust kell megadni: vagy a havi díjat gyermekenként, vagy a fix havi díjat.';
                $validator->errors()->add('price_per_child', $message);
                $validator->errors()->add('fixed_monthly_fee', $message);

                return;
            }

            $institution = $this->route('institution');

            if (! $institution instanceof Institution) {
                return;
            }

            $validFrom = (string) $this->input('valid_from');
            $validTo = $this->filled('valid_to') ? (string) $this->input('valid_to') : null;
            $billingRate = $this->route('billingRate');
            $billingRateId = $billingRate instanceof InstitutionBillingRate ? $billingRate->id : null;

            $overlapQuery = InstitutionBillingRate::query()
                ->where('institution_id', $institution->id)
                ->when($billingRateId, fn ($query) => $query->where('id', '!=', $billingRateId));

            if ($validTo !== null) {
                $overlapQuery->whereDate('valid_from', '<=', $validTo);
            }

            $hasOverlap = $overlapQuery
                ->where(function ($query) use ($validFrom) {
                    $query->whereNull('valid_to')
                        ->orWhereDate('valid_to', '>=', $validFrom);
                })
                ->exists();

            if ($hasOverlap) {
                $validator->errors()->add('valid_from', 'A megadott időszak átfed egy már létező díjszabással.');
            }
        });
    }
}
