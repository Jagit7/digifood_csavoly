<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\UpdateParentAddressRequest;
use App\Http\Requests\ParentPortal\UpdateParentBillingProfileRequest;
use App\Http\Requests\ParentPortal\UpdateParentPasswordRequest;
use App\Http\Requests\ParentPortal\UpdateParentPersonalDataRequest;
use App\Services\ParentPortal\ParentAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ParentAccountController extends Controller
{
    public function __construct(
        private readonly ParentAccountService $service
    ) {}

    public function edit(Request $request): View
    {
        return view('parent.account.edit', $this->service->buildPageData($request->user()));
    }

    public function updateLegacy(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-()\/]{7,50}$/'],
            // A bankszámla-adatokat a szülő a szülői felületen nem
            // módosíthatja, csak megtekintheti - lásd ParentAccountService.
            'bank_account_holder' => ['prohibited'],
            'bank_account_number' => ['prohibited'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'street_name' => ['nullable', 'string', 'max:255'],
            'street_type' => ['nullable', 'string', 'max:50'],
            'house_number' => ['nullable', 'string', 'max:30'],
            'floor' => ['nullable', 'string', 'max:20'],
            'door' => ['nullable', 'string', 'max:20'],
            'billing_same_as_contact' => ['nullable', 'boolean'],
            // A számlázási nevet és az adószámot a szülő a szülői felületen
            // nem módosíthatja, illetve nem is adhatja meg - ezért ezeket a
            // mezőket szándékosan nem fogadjuk el innen. Mindkettőt csak az
            // intézmény adminisztrátora kezelheti (ld.
            // Dashboard\InstitutionAdmin\ParentController).
            'billing_postal_code' => ['nullable', 'string', 'max:10'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $billingAddress = trim((string) $request->input('billing_address'));
            $billingCity = trim((string) $request->input('billing_city'));

            if ($billingAddress === '' && $billingCity === '') {
                return;
            }

            if ((bool) $request->boolean('billing_same_as_contact')) {
                return;
            }

            foreach ([
                'billing_city' => 'A számlázási település megadása kötelező.',
                'billing_address' => 'A számlázási cím megadása kötelező.',
            ] as $field => $message) {
                if (trim((string) $request->input($field)) === '') {
                    $validator->errors()->add($field, $message);
                }
            }
        });

        if ($validator->fails()) {
            return redirect()
                ->to(route('parent.account'))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $this->service->updatePersonalData($request->user(), [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        $this->service->updateAddress($request->user(), [
            'country' => $validated['country'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'city' => $validated['city'] ?? null,
            'street_name' => $validated['street_name'] ?? null,
            'street_type' => $validated['street_type'] ?? null,
            'house_number' => $validated['house_number'] ?? null,
            'floor' => $validated['floor'] ?? null,
            'door' => $validated['door'] ?? null,
        ]);

        $billingSameAsContact = (bool) ($validated['billing_same_as_contact'] ?? false);
        $hasBillingAddressInput = trim((string) ($validated['billing_city'] ?? '')) !== ''
            || trim((string) ($validated['billing_address'] ?? '')) !== '';

        if ($billingSameAsContact || $hasBillingAddressInput) {
            $this->service->updateBillingData($request->user(), [
                'billing_same_as_address' => $billingSameAsContact,
                'billing_postal_code' => $validated['billing_postal_code'] ?? null,
                'billing_city' => $validated['billing_city'] ?? null,
                'billing_address' => $validated['billing_address'] ?? null,
            ]);
        }

        return redirect()
            ->route('parent.account')
            ->with('success', 'A személyes adatok frissítése sikeres volt.')
            ->with('success_modal', 'A módosítások mentése sikeres volt.');
    }

    public function updatePersonal(UpdateParentPersonalDataRequest $request): RedirectResponse
    {
        $this->service->updatePersonalData($request->user(), $request->validated());

        return redirect()
            ->route('parent.account')
            ->with('success', 'A személyes adatok frissítése sikeres volt.')
            ->withFragment('personal-card');
    }

    public function updateAddress(UpdateParentAddressRequest $request): RedirectResponse
    {
        $this->service->updateAddress($request->user(), $request->validated());

        return redirect()
            ->route('parent.account')
            ->with('success', 'A lakcím frissítése sikeres volt.')
            ->withFragment('address-card');
    }

    public function updateBilling(UpdateParentBillingProfileRequest $request): RedirectResponse
    {
        $this->service->updateBillingData($request->user(), $request->validated());

        return redirect()
            ->route('parent.account')
            ->with('success', 'A számlázási adatok frissítése sikeres volt.')
            ->withFragment('billing-card');
    }

    public function updatePassword(UpdateParentPasswordRequest $request): RedirectResponse
    {
        $this->service->updatePassword($request->user(), $request->validated()['password']);

        return redirect()
            ->route('parent.account')
            ->with('success', 'A jelszó módosítása sikeres volt.')
            ->withFragment('security-card');
    }
}
