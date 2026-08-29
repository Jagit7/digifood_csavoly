<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\PaymentObligation;

use App\Models\PaymentObligation\MonthlyPaymentDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualPaymentDayUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                MonthlyPaymentDay::STATUS_PAY,
                MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE,
                MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING,
                MonthlyPaymentDay::STATUS_SCHOOL_BREAK,
                MonthlyPaymentDay::STATUS_CLASS_CANCELLATION,
                MonthlyPaymentDay::STATUS_WEEKEND,
                MonthlyPaymentDay::STATUS_WORKING_SATURDAY,
                MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL,
                MonthlyPaymentDay::STATUS_NO_VALID_PRICE,
                MonthlyPaymentDay::STATUS_FREE_MEAL,
                MonthlyPaymentDay::STATUS_MANUALLY_MODIFIED,
            ])],
            'payable_amount' => ['required', 'integer', 'min:0'],
            'modification_reason' => ['required', 'string', 'max:191'],
        ];
    }
}
