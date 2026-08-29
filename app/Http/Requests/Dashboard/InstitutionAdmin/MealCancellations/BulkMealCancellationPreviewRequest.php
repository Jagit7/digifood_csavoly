<?php

namespace App\Http\Requests\Dashboard\InstitutionAdmin\MealCancellations;

use App\Models\BulkMealCancellationBatch;
use App\Support\AdminInstitutionContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkMealCancellationPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $institutionId = app(AdminInstitutionContext::class)->currentInstitution($this->user())?->id;

        return [
            'selected_child_ids' => ['required', 'array', 'min:1'],
            'selected_child_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('children', 'id')->where(fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->where('active', true)),
            ],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'meal_scope' => ['required', Rule::in([BulkMealCancellationBatch::MEAL_SCOPE_ALL_CONFIGURED])],
            'event_name' => ['required', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:191'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('date_from') || ! $this->filled('date_to')) {
                return;
            }

            $start = CarbonImmutable::parse((string) $this->input('date_from'))->startOfDay();
            $end = CarbonImmutable::parse((string) $this->input('date_to'))->startOfDay();
            $maximum = (int) config('digifood.maximum_bulk_cancellation_days', 366);

            if ($start->diffInDays($end) + 1 > $maximum) {
                $validator->errors()->add('date_to', "Egyszerre legfeljebb {$maximum} napos időszak rögzíthető.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'selected_child_ids.required' => 'Válassz ki legalább egy gyermeket az előnézethez.',
            'selected_child_ids.array' => 'A kijelölt gyermekek listája érvénytelen.',
            'selected_child_ids.min' => 'Válassz ki legalább egy gyermeket az előnézethez.',
            'selected_child_ids.*.integer' => 'A kijelölt gyermek azonosítója érvénytelen.',
            'selected_child_ids.*.distinct' => 'Ugyanaz a gyermek csak egyszer választható ki.',
            'selected_child_ids.*.exists' => 'Csak a saját intézményed aktív gyermekei választhatók ki.',
            'date_from.required' => 'Add meg a lemondás kezdetét.',
            'date_from.date' => 'A lemondás kezdete érvényes dátum legyen.',
            'date_to.required' => 'Add meg a lemondás végét.',
            'date_to.date' => 'A lemondás vége érvényes dátum legyen.',
            'date_to.after_or_equal' => 'A lemondás vége nem lehet korábbi, mint a kezdőnap.',
            'meal_scope.required' => 'Válaszd ki a lemondandó étkezést.',
            'meal_scope.in' => 'A kiválasztott lemondási mód nem támogatott.',
            'event_name.required' => 'Add meg az esemény vagy csoport nevét.',
            'event_name.string' => 'Az esemény neve szöveg legyen.',
            'event_name.max' => 'Az esemény neve legfeljebb 191 karakter lehet.',
            'reason.string' => 'A megjegyzés szöveg legyen.',
            'reason.max' => 'A megjegyzés legfeljebb 191 karakter lehet.',
        ];
    }
}
