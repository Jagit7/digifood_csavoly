<?php

namespace App\Http\Requests\ParentPortal;

use Illuminate\Foundation\Http\FormRequest;

class StoreParentMealCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()?->isParent();
    }

    public function rules(): array
    {
        return [
            'service_date' => ['required', 'date'],
            'child_ids' => ['required', 'array', 'min:1'],
            'child_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
