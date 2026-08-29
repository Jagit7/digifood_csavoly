<?php

namespace App\Http\Controllers\EmployeePortal\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeePortal\Auth\CompleteEmployeeAccountActivationRequest;
use App\Http\Requests\EmployeePortal\Auth\RequestEmployeeAccountActivationRequest;
use App\Services\EmployeePortal\EmployeeAccountActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeAccountActivationController extends Controller
{
    public function __construct(
        private readonly EmployeeAccountActivationService $service
    ) {}

    public function create(): View
    {
        return view('employee.auth.activation-request');
    }

    public function send(RequestEmployeeAccountActivationRequest $request): RedirectResponse
    {
        $result = $this->service->requestActivation($request->validated('email'));

        return match ($result['status']) {
            'employee_not_found' => back()
                ->withErrors(['email' => 'A megadott e-mail címhez nem található aktiválható dolgozói hozzáférés.'])
                ->withInput(),
            'non_employee_user_exists' => back()
                ->withErrors(['email' => 'Ehhez az e-mail címhez már más típusú felhasználói fiók tartozik. Kérjük, vegye fel a kapcsolatot az intézménnyel.'])
                ->withInput(),
            'already_active_employee' => back()
                ->with('existing_account_message', 'Ehhez az e-mail címhez már tartozik dolgozói fiók. Kérjük, jelentkezzen be.')
                ->with('existing_account_login_url', $result['login_url'])
                ->withInput(),
            default => $this->activationRequestedResponse(
                (string) $result['activation_url'],
                (bool) ($result['debug_link_available'] ?? false)
            ),
        };
    }

    public function show(string $token): View|RedirectResponse
    {
        $data = $this->service->activationViewData($token);

        if ($data === null) {
            return redirect()
                ->route('employee.activation.create')
                ->withErrors(['email' => 'Az aktiváló link érvénytelen vagy lejárt.']);
        }

        return view('employee.auth.activation-complete', $data + [
            'token' => $token,
        ]);
    }

    public function store(CompleteEmployeeAccountActivationRequest $request, string $token): RedirectResponse
    {
        try {
            $user = $this->service->activate($token, $request->validated('password'));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('employee.activation.show', ['token' => $token])
                ->withErrors($exception->errors())
                ->withInput();
        }

        $this->service->loginActivatedEmployee($user);
        $request->session()->regenerate();

        return redirect()
            ->route('employee.dashboard')
            ->with('success', 'A dolgozói fiók aktiválása sikeres volt.');
    }

    private function activationRequestedResponse(string $activationUrl, bool $debugLinkAvailable): RedirectResponse
    {
        $redirect = redirect()
            ->route('employee.activation.create')
            ->with('status', 'Ha a megadott e-mail címhez tartozik aktiválható dolgozói hozzáférés, a rendszer előkészítette az aktiválási folyamatot.');

        if ($debugLinkAvailable) {
            $redirect->with('activation_debug_url', $activationUrl);
        }

        return $redirect;
    }
}
