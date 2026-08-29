<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\Children;

use App\Support\AdminInstitutionContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChildBarcodeSelectionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('children') && ! $this->has('child_ids')) {
            $this->merge([
                'child_ids' => $this->input('children'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isInstitutionAdmin() ?? false;
    }

    public function rules(): array
    {
        $institutionId = app(AdminInstitutionContext::class)->currentInstitution($this->user())?->id;

        return [
            'child_ids' => ['required', 'array', 'min:1', 'max:200'],
            'child_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('children', 'id')
                    ->where(fn ($query) => $query->where('institution_id', $institutionId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'child_ids.required' => 'Válassz ki legalább egy gyermeket.',
            'child_ids.array' => 'A kijelölt gyermekek listája érvénytelen.',
            'child_ids.min' => 'Válassz ki legalább egy gyermeket.',
            'child_ids.max' => 'Egyszerre legfeljebb 200 gyermek kezelhető.',
            'child_ids.*.exists' => 'Csak a saját intézményedhez tartozó gyermekek választhatók.',
        ];
    }
}
