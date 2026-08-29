<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\InstitutionAdmin\InstitutionInvoicingSettingUpdateRequest;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class InstitutionInvoicingSettingController extends Controller
{
    public function edit(): View
    {
        $institution = $this->institution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        return view('dashboard.institution_admin.institution.invoicing-settings', [
            'institution' => $institution,
            'setting' => $setting,
            'invoiceProviders' => InstitutionSetting::invoicingProviderOptions(),
            'paymentProviders' => InstitutionSetting::cardPaymentProviderOptions(),
        ]);
    }

    public function update(InstitutionInvoicingSettingUpdateRequest $request): RedirectResponse
    {
        $institution = $this->institution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        $validated = $request->validated();
        $invoicingEnabled = $request->boolean('invoicing_enabled');
        $cardPaymentEnabled = $request->boolean('card_payment_enabled');
        $invoicingProvider = $invoicingEnabled ? ($validated['invoicing_provider'] ?? null) : null;
        $cardPaymentProvider = $cardPaymentEnabled ? ($validated['card_payment_provider'] ?? null) : null;
        $isBillingo = $invoicingProvider === InstitutionSetting::INVOICING_PROVIDER_BILLINGO;
        $isSzamlazzHu = $invoicingProvider === InstitutionSetting::INVOICING_PROVIDER_SZAMLAZZ_HU;
        $isCib = $cardPaymentProvider === InstitutionSetting::CARD_PAYMENT_PROVIDER_CIB;

        // Csak azokat a mezőket vesszük fel a payloadba, amelyeket ez a
        // művelet ténylegesen kezel. Egy meglévő InstitutionSetting rekord
        // mentése SOHA nem írhatja alapértékre/NULL-ra egy másik szolgáltató
        // (pl. a jelenleg nem kiválasztott CIB vagy Billingo) már elmentett
        // beállításait, csak azért, mert a mostani mentésben az a szekció
        // nincs aktívan szerkesztve - lásd a korábbi éles hibát, ami emiatt
        // törölte a Billingo API-kulcsot és a CIB terminálazonosítót.
        $payload = [
            'invoicing_enabled' => $invoicingEnabled,
            'invoicing_provider' => $invoicingProvider,
            'card_payment_enabled' => $cardPaymentEnabled,
            'card_payment_provider' => $cardPaymentProvider,
            'card_payment_test_mode' => $cardPaymentEnabled ? $request->boolean('card_payment_test_mode') : true,
            // Független a kártyás fizetés / számlázás be- vagy
            // kikapcsolásától - mindig menthető (ld. felhasználói kérés: ha
            // nincs kártyás fizetés, de van megadott bankszámlaszám, a
            // szülői felület banki átutalási tájékoztatót jelenít meg).
            'bank_transfer_account_holder' => trim((string) ($validated['bank_transfer_account_holder'] ?? '')) ?: null,
            'bank_transfer_account_number' => trim((string) ($validated['bank_transfer_account_number'] ?? '')) ?: null,
            // Ugyanígy független a számlázási szolgáltatótól - a fizetési
            // kötelezettségek nettó/bruttó kimutatásaihoz mindig ez az
            // aktuálisan elmentett kulcs számít (ld. InstitutionSetting::
            // grossAmount()).
            'vat_rate' => isset($validated['vat_rate']) && $validated['vat_rate'] !== ''
                ? round((float) $validated['vat_rate'], 2)
                : 0,
        ];

        if ($isCib) {
            $payload['cib_terminal_id'] = $validated['cib_terminal_id'] ?? null;
        }

        if ($isBillingo) {
            $payload['billingo_document_block_id'] = $validated['billingo_document_block_id'] ?? null;
            $payload['billingo_default_payment_method'] = $validated['billingo_default_payment_method'] ?? null;
            $payload['billingo_due_days'] = $validated['billingo_due_days'] ?? null;
            $payload['billingo_invoice_language'] = $validated['billingo_invoice_language'] ?? null;
            $payload['billingo_e_invoice_enabled'] = $request->boolean('billingo_e_invoice_enabled');
            $payload['billingo_test_mode'] = $request->boolean('billingo_test_mode');
        }

        if ($isSzamlazzHu) {
            $payload['szamlazz_hu_invoice_prefix'] = $validated['szamlazz_hu_invoice_prefix'] ?? null;
            $payload['szamlazz_hu_default_payment_method'] = $validated['szamlazz_hu_default_payment_method'] ?? null;
            $payload['szamlazz_hu_due_days'] = $validated['szamlazz_hu_due_days'] ?? null;
            $payload['szamlazz_hu_invoice_language'] = $validated['szamlazz_hu_invoice_language'] ?? null;
            $payload['szamlazz_hu_e_invoice_enabled'] = $request->boolean('szamlazz_hu_e_invoice_enabled');
            $payload['szamlazz_hu_test_mode'] = $request->boolean('szamlazz_hu_test_mode');
        }

        // Az érzékeny kulcsok külön szabályt követnek minden szolgáltatónál:
        // az üresen hagyott mező SOHA nem törli a már elmentett kulcsot,
        // törlés kizárólag az explicit "remove_..." jelölőnégyzettel történhet.
        $billingoApiKey = trim((string) ($validated['billingo_api_key'] ?? ''));
        if ($isBillingo && $billingoApiKey !== '') {
            $payload['billingo_api_key'] = $billingoApiKey;
        } elseif ($request->boolean('remove_billingo_api_key')) {
            $payload['billingo_api_key'] = null;
        }

        $szamlazzHuAgentKey = trim((string) ($validated['szamlazz_hu_agent_key'] ?? ''));
        if ($isSzamlazzHu && $szamlazzHuAgentKey !== '') {
            $payload['szamlazz_hu_agent_key'] = $szamlazzHuAgentKey;
        } elseif ($request->boolean('remove_szamlazz_hu_agent_key')) {
            $payload['szamlazz_hu_agent_key'] = null;
        }

        $cibSecretKey = trim((string) ($validated['cib_secret_key'] ?? ''));
        if ($isCib && $cibSecretKey !== '') {
            $payload['cib_secret_key'] = $cibSecretKey;
        } elseif ($request->boolean('remove_cib_secret_key')) {
            $payload['cib_secret_key'] = null;
        }

        $setting->fill($payload);
        $setting->save();

        return redirect()
            ->route('dashboard.institution.settings.invoicing.edit')
            ->with('success', 'A számlázási és fizetési beállítások frissítve lettek.');
    }

    /**
     * A már elmentett Billingo API-kulcsot szerver oldalon felhasználva
     * lekérdezi az intézmény Billingo fiókjában létrehozott
     * bizonylattömböket (id + név + prefix), hogy a felhasználó ki tudja
     * választani a helyes numerikus azonosítót a "Bizonylattömb
     * azonosító" mezőhöz. A kulcs sosem kerül vissza a válaszban.
     */
    public function billingoDocumentBlocks(): JsonResponse
    {
        $institution = $this->institution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );

        if (! $setting->hasBillingoApiKey()) {
            return response()->json([
                'success' => false,
                'message' => 'Nincs elmentve Billingo API-kulcs. Mentsd el a kulcsot, majd próbáld újra.',
            ], 422);
        }

        try {
            $response = Http::withHeaders(['X-API-KEY' => (string) $setting->billingo_api_key])
                ->timeout(15)
                ->get('https://api.billingo.hu/v3/document-blocks', ['per_page' => 100]);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'A Billingo szolgáltatás jelenleg nem érhető el: '.$exception->getMessage(),
            ], 502);
        }

        if (! $response->successful()) {
            $errorMessage = $response->json('message') ?? $response->json('error') ?? ('HTTP '.$response->status());

            return response()->json([
                'success' => false,
                'message' => 'A Billingo hibát adott vissza: '.$errorMessage,
            ], 502);
        }

        $blocks = collect($response->json('data', []))
            ->map(fn (array $block) => [
                'id' => $block['id'] ?? null,
                'name' => $block['name'] ?? null,
                'prefix' => $block['prefix'] ?? null,
                'type' => $block['type']['name'] ?? (is_string($block['type'] ?? null) ? $block['type'] : null),
            ])
            ->filter(fn (array $block) => $block['id'] !== null)
            ->values();

        if ($blocks->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nem található bizonylattömb a Billingo fiókban. Hozz létre egyet a Billingo felületén.',
            ]);
        }

        return response()->json([
            'success' => true,
            'blocks' => $blocks,
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }
}
