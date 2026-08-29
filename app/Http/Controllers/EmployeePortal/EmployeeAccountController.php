<?php

namespace App\Http\Controllers\EmployeePortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeePortal\UpdateEmployeeAddressRequest;
use App\Http\Requests\EmployeePortal\UpdateEmployeePasswordRequest;
use App\Http\Requests\EmployeePortal\UpdateEmployeePersonalDataRequest;
use App\Services\EmployeePortal\EmployeeAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeAccountController extends Controller
{
    public function __construct(
        private readonly EmployeeAccountService $service
    ) {}

    public function edit(Request $request): View
    {
        return view('employee.account.edit', $this->service->buildPageData($request->user()));
    }

    public function updatePersonal(UpdateEmployeePersonalDataRequest $request): RedirectResponse
    {
        $this->service->updatePersonalData($request->user(), $request->validated());

        return redirect()
            ->route('employee.account')
            ->with('success', 'A személyes adatok frissítése sikeres volt.')
            ->withFragment('personal-card');
    }

    public function updateAddress(UpdateEmployeeAddressRequest $request): RedirectResponse
    {
        $this->service->updateAddress($request->user(), $request->validated());

        return redirect()
            ->route('employee.account')
            ->with('success', 'A lakcím frissítése sikeres volt.')
            ->withFragment('address-card');
    }

    public function updatePassword(UpdateEmployeePasswordRequest $request): RedirectResponse
    {
        $this->service->updatePassword($request->user(), $request->validated()['password']);

        return redirect()
            ->route('employee.account')
            ->with('success', 'A jelszó módosítása sikeres volt.')
            ->withFragment('security-card');
    }
}
