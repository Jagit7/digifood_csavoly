<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Services\Finance\CardPaymentService;
use App\Services\ParentPortal\ParentMonthlySettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParentMonthlySettlementController extends Controller
{
    public function __construct(
        private readonly ParentMonthlySettlementService $service,
        private readonly CardPaymentService $cardPaymentService,
    ) {
    }

    public function index(Request $request): View
    {
        $month = $this->service->resolveMonth($request->query('month'));

        return view('parent.monthly-settlements.index', array_merge(
            $this->service->buildPageData($request->user(), $month),
            [
                'payment_result' => $this->service->paymentResult($request->user(), $request->query('payment')),
            ],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'payment_intent_key' => ['required', 'string', 'max:191'],
            'data_processing_consent' => ['accepted'],
        ], [
            'data_processing_consent.accepted' => 'A bankkártyás fizetés indításához el kell fogadnia az adatkezelési tájékoztatót.',
        ]);

        $month = $this->service->resolveMonth($validated['month']);
        $payment = $this->service->createPaymentIntent(
            $request->user(),
            $month,
            $validated['payment_intent_key']
        );

        $child = $payment->items->first()?->child;
        $institution = $child?->institution;

        if ($institution) {
            $result = $this->cardPaymentService->initiate(
                $payment,
                $institution,
                route('parent.monthly-settlements.index', ['month' => $month->format('Y-m')])
            );

            if ($result->redirectUrl) {
                return redirect()->away($result->redirectUrl);
            }

            if (! $result->success) {
                return redirect()
                    ->route('parent.monthly-settlements.index', ['month' => $month->format('Y-m')])
                    ->withErrors(['payment' => $result->errorMessage ?: 'A bankkártyás fizetés indítása jelenleg nem lehetséges.']);
            }
        }

        return redirect()
            ->route('parent.monthly-settlements.index', ['month' => $month->format('Y-m')])
            ->with('success', 'A közös fizetés előkészítve. Fizetési azonosító: '.$payment->reference);
    }
}
