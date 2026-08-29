<?php

namespace App\Http\Controllers;

use App\Services\Finance\CardPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nyilvános (nem bejelentkezéshez kötött) végpontok a kártyás fizetési
 * szolgáltatók számára: a szolgáltató szerver-szerver visszahívása
 * (callback) és a böngésző visszairányítása a fizetés után.
 */
class CardPaymentController extends Controller
{
    public function __construct(private readonly CardPaymentService $service)
    {
    }

    public function callback(Request $request, string $provider): Response
    {
        $result = $this->service->handleCallback($provider, $request);

        return response($result->httpResponseBody, 200);
    }

    public function returnFromGateway(Request $request): RedirectResponse
    {
        $redirect = $this->service->handleReturn($request);

        if ($redirect !== null) {
            return $redirect;
        }

        return redirect()
            ->route('parent.monthly-settlements.index')
            ->withErrors(['payment' => 'A bankkártyás fizetés visszatérése nem volt feldolgozható.']);
    }
}
