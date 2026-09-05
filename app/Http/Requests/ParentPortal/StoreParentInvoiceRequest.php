<?php

namespace App\Http\Requests\ParentPortal;

use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\ParentPortal\ParentInvoiceInitiationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "5. Másik szülő gyermekének elszámolására: NEM indíthat számlázást" -
 * ugyanaz a route-model-binding + authorize() minta, mint a projekt más
 * ParentPortal Request osztályainál (a nem létező statement route-model-
 * binding szinten 404-et ad, az idegen statement itt 403-at).
 */
class StoreParentInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $statement = $this->route('statement');

        if (! $statement instanceof MonthlyPaymentStatement) {
            return false;
        }

        return app(ParentInvoiceInitiationService::class)->guardianOwnsStatement($user, $statement);
    }

    public function rules(): array
    {
        return [];
    }
}
