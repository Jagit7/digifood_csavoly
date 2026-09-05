<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\StoreParentInvoiceRequest;
use App\Models\PaymentObligation\MonthlyPaymentStatement;
use App\Services\Finance\CardPaymentService;
use App\Services\Finance\InstitutionInvoiceService;
use App\Services\ParentPortal\ParentInvoiceInitiationService;
use App\Services\ParentPortal\ParentMonthlySettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ParentMonthlySettlementController extends Controller
{
    public function __construct(
        private readonly ParentMonthlySettlementService $service,
        private readonly CardPaymentService $cardPaymentService,
        private readonly InstitutionInvoiceService $invoiceService,
        private readonly ParentInvoiceInitiationService $invoiceInitiationService,
    ) {
    }

    public function index(Request $request): View
    {
        $month = $this->service->resolveMonth($request->query('month'));
        $data = $this->service->buildPageData($request->user(), $month);
        $data['child_cards'] = $this->withInvoicingInfo($data['child_cards']);

        return view('parent.monthly-settlements.index', array_merge(
            $data,
            [
                'payment_result' => $this->service->paymentResult($request->user(), $request->query('payment')),
            ],
        ));
    }

    /**
     * 2. FÁZIS: minden, elszámolással rendelkező gyermek-kártyát kiegészít
     * egy 'invoicing' résszel - jelenjen-e meg a "Számla elkészítése" gomb,
     * vagy (ha már létrejött a számla) a hozzá tartozó banki tájékoztató
     * blokk. Szándékosan a controllerben, a MEGLÉVŐ ParentMonthlySettlementService::
     * buildPageData() eredményének utólagos kiegészítéseként történik, hogy
     * ne kelljen belenyúlni abba a már összetett, jól tesztelt metódusba.
     *
     * @param  Collection<int, array<string, mixed>>  $childCards
     * @return Collection<int, array<string, mixed>>
     */
    private function withInvoicingInfo(Collection $childCards): Collection
    {
        return $childCards->map(function (array $card) {
            if (! ($card['has_statement'] ?? false)) {
                return $card;
            }

            /** @var MonthlyPaymentStatement $statement */
            $statement = $card['statement'];
            $institution = $card['child']->institution;

            if (! $institution) {
                return $card;
            }

            $setting = $this->invoiceService->settings($institution);
            $existingInvoice = $statement->invoice;

            $card['invoicing'] = [
                'can_offer' => $existingInvoice === null
                    && $this->invoiceInitiationService->canOfferInvoicing($statement, $setting),
                'invoice' => $existingInvoice,
                'bank_payment_info' => $existingInvoice
                    ? $this->invoiceInitiationService->bankPaymentInfo($institution, $setting, $existingInvoice)
                    : null,
            ];

            return $card;
        });
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

    /**
     * 2. FÁZIS: a szülő saját maga indítja el egyetlen gyermek tárgyhavi
     * számlázási folyamatát (POST + CSRF, ld. StoreParentInvoiceRequest a
     * "másik szülő gyermeke" ellenőrzésért, InstitutionInvoiceService::
     * createForParent() a tényleges guard-okért/idempotenciáért). Ez
     * FÜGGETLEN a fenti store()-tól, ami a több gyermek összevont, kártyás
     * (CIB) közös fizetését indítja - a kettő nem keveredik.
     */
    public function storeInvoice(StoreParentInvoiceRequest $request, MonthlyPaymentStatement $statement): RedirectResponse
    {
        // A tulajdonjog-ellenőrzést már a StoreParentInvoiceRequest::authorize()
        // elvégezte (idegen statementre itt már nem jutunk el, 403-at kap
        // előbb) - a service saját institution_id-egyezés ellenőrzése (ld.
        // InstitutionInvoiceService::createForParent() abort_if()-je) egy
        // további, független védelmi réteg.
        $institution = $statement->child->institution;

        $this->invoiceService->createForParent($institution, $request->user(), $statement);

        $month = $this->service->resolveMonth(sprintf('%04d-%02d', $statement->year, $statement->month));

        return redirect()
            ->route('parent.monthly-settlements.index', ['month' => $month->format('Y-m')])
            ->with('success', 'A számlázási folyamat elindult. Az egyedi fizetési közlemény és a banki adatok alább, a hónap kártyáján jelennek meg.');
    }
}
