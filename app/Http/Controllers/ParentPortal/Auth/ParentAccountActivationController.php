<?php

namespace App\Http\Controllers\ParentPortal\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\Auth\CompleteParentAccountActivationRequest;
use App\Http\Requests\ParentPortal\Auth\RequestParentAccountActivationRequest;
use App\Services\ParentPortal\ParentAccountActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParentAccountActivationController extends Controller
{
    public function __construct(
        private readonly ParentAccountActivationService $service
    ) {}

    public function create(): View
    {
        return view('parent.auth.activation-request');
    }

    public function send(RequestParentAccountActivationRequest $request): RedirectResponse
    {
        $result = $this->service->requestActivation($request->validated('email'));

        return match ($result['status']) {
            'guardian_not_found' => back()
                ->withErrors(['email' => 'A megadott e-mail címhez nem található aktiválható szülői hozzáférés.'])
                ->withInput(),
            'non_parent_user_exists' => back()
                ->withErrors(['email' => 'Ehhez az e-mail címhez már más típusú felhasználói fiók tartozik. Kérjük, vegye fel a kapcsolatot az intézménnyel.'])
                ->withInput(),
            'already_active_parent' => back()
                ->with('existing_account_message', 'Ehhez az e-mail címhez már tartozik szülői fiók. Kérjük, jelentkezzen be.')
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
                ->to(route('parent.activation.create', [], false))
                ->withErrors(['email' => 'Az aktiváló link érvénytelen vagy lejárt.']);
        }

        return view('parent.auth.activation-complete', $data + [
            'token' => $token,
        ]);
    }

    public function store(CompleteParentAccountActivationRequest $request, string $token): RedirectResponse
    {
        try {
            $user = $this->service->activate($token, $request->validated('password'));
        } catch (ValidationException $exception) {
            return redirect()
                ->to(route('parent.activation.show', ['token' => $token], false))
                ->withErrors($exception->errors())
                ->withInput();
        }

        $this->service->loginActivatedParent($user);
        $request->session()->regenerate();

        return redirect()
            ->to(route('parent.dashboard', [], false))
            ->with('success', 'A szülői fiók aktiválása sikeres volt.');
    }

    /**
     * A debug-link megjelenítés eldöntése kizárólag a service dolga (ld.
     * ParentAccountActivationService::isDebugLinkMode()) - itt csak
     * megjelenítjük, amit onnan kaptunk. Korábban ez a controller egy
     * saját, a service-től független feltétellel döntötte el ugyanezt,
     * ami két, egymástól eltérő "igazságforráshoz" vezetett.
     */
    private function activationRequestedResponse(string $activationUrl, bool $debugLinkAvailable): RedirectResponse
    {
        $redirect = redirect()
            ->to(route('parent.activation.create', [], false))
            ->with('status', 'Ha a megadott e-mail címhez tartozik aktiválható szülői hozzáférés, a rendszer előkészítette az aktiválási folyamatot.');

        if ($debugLinkAvailable) {
            $redirect->with('activation_debug_url', $activationUrl);
        }

        return $redirect;
    }
}
