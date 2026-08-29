<?php

namespace App\Services\Finance\Providers;

use App\Models\InstitutionInvoice;
use App\Models\InstitutionSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Billingo REST API v3 integráció (https://api.billingo.hu/v3).
 *
 * A Billingo minden bizonylathoz egy meglévő "partner" (vevő) azonosítót
 * vár, ezért kiállítás előtt mindig létrehozunk egy új partner rekordot a
 * vevő adataival (a Billingo felületén ez esetleg duplikációkat okozhat,
 * ha ugyanaz a szülő többször fizet - ez egy ismert, elfogadott
 * egyszerűsítés, később partner-keresésre/gyorsítótárazásra cserélhető).
 *
 * ÉLES SZERVEREN ELVÉGZENDŐ: az intézmény a saját Billingo fiókjában
 * generált API-kulcsot és a használni kívánt bizonylattömb azonosítóját
 * (block_id) kell megadnia az intézményi beállításoknál. Ha a Billingo
 * validációs hibát ad vissza (pl. a fizetési mód vagy ÁFA-kód elnevezése
 * eltér a itt feltételezettől), a hibaüzenet a naplóban rögzítve lesz.
 */
class BillingoInvoiceProvider implements InvoiceProviderInterface
{
    private const BASE_URL = 'https://api.billingo.hu/v3';

    private const INVALID_PDF_MESSAGE = 'A Billingo még nem adott vissza érvényes PDF-dokumentumot. Próbálja újra később.';

    public function createInvoice(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        $institution = $payload->institution;
        $invoice = $payload->invoice;
        $settings = $institution->setting;

        if (! $settings || ! $settings->hasBillingoApiKey()) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'Hiányzik a Billingo API-kulcs az intézmény beállításaiban.'
            );
        }

        if (! filled($settings->billingo_document_block_id)) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'Hiányzik a Billingo bizonylattömb azonosító az intézmény beállításaiban.'
            );
        }

        $apiKey = (string) $settings->billingo_api_key;

        try {
            $partnerId = $this->upsertPartner($apiKey, $invoice);
        } catch (Throwable $exception) {
            Log::error('Billingo vevő (partner) létrehozási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A vevő rögzítése a Billingo rendszerben sikertelen: '.$exception->getMessage()
            );
        }

        try {
            $documentPayload = array_filter([
                'partner_id' => $partnerId,
                'block_id' => (int) $settings->billingo_document_block_id,
                'type' => 'invoice',
                'fulfillment_date' => $invoice->fulfillment_date?->toDateString() ?? now()->toDateString(),
                'due_date' => $invoice->due_date?->toDateString() ?? now()->toDateString(),
                'payment_method' => $this->mapPaymentMethod($invoice->payment_method),
                'language' => $this->resolveDocumentLanguage($settings->billingo_invoice_language),
                'currency' => $invoice->currency ?: 'HUF',
                'conversion_rate' => 1,
                'electronic' => (bool) $settings->billingo_e_invoice_enabled,
                'paid' => false,
                // A Billingo a "comment"-et szigorúan string típusként validálja -
                // explicit null (üres megjegyzés esetén) "The comment must be a
                // string." hibát ad, ezért üresen inkább teljesen kihagyjuk.
                'comment' => filled($invoice->note) ? (string) $invoice->note : null,
                'items' => [[
                    'name' => 'Étkezési térítési díj',
                    'unit_price' => (float) $invoice->net_amount,
                    'unit_price_type' => 'net',
                    'quantity' => 1,
                    'unit' => 'db',
                    // ld. fenti megjegyzés: jelenleg mindig ÁFA-mentes (AAM) tétel.
                    'vat' => 'AAM',
                ]],
            ], fn ($value) => $value !== null);

            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->timeout(30)
                ->post(self::BASE_URL.'/documents', $documentPayload);
        } catch (Throwable $exception) {
            Log::error('Billingo API hívási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Billingo szolgáltatás jelenleg nem érhető el: '.$exception->getMessage()
            );
        }

        if (! $response->successful()) {
            $errorMessage = $this->formatApiError($response);

            Log::error('Billingo számlakiállítás sikertelen', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 2000),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Billingo hibát adott vissza: '.$errorMessage
            );
        }

        $data = $response->json();
        $documentId = $data['id'] ?? null;
        $invoiceNumber = $data['invoice_number'] ?? null;
        $download = $documentId ? $this->downloadPdf($apiKey, (int) $documentId, $invoice, $invoiceNumber) : ['path' => null, 'size' => null];

        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_ISSUED,
            providerInvoiceId: $documentId !== null ? (string) $documentId : null,
            invoiceNumber: $invoiceNumber,
            issueDate: now(),
            fulfillmentDate: $invoice->fulfillment_date,
            invoiceUrl: $data['public_url'] ?? null,
            invoicePdfPath: $download['path'],
        );
    }

    /**
     * Bizonylat sztornózása a Billingo API-n keresztül.
     *
     * A Billingo v3 API a POST /documents/{id}/cancel végponton keresztül
     * támogatja a sztornózást: sikeres híváskor egy új, önálló sztornó
     * bizonylatot (Document) hoz létre és ad vissza válaszban - ez NEM
     * módosítja/törli az eredeti bizonylatot a Billingo rendszerében, csak
     * egy hozzá kapcsolódó, azt érvénytelenítő bizonylatot állít ki. (Ld.
     * https://apidoc.billingo.hu/ - DocumentApi::cancelDocument.)
     */
    public function cancelInvoice(InvoiceProviderPayload $payload, ?string $reason = null): InvoiceProviderResult
    {
        $institution = $payload->institution;
        $invoice = $payload->invoice;
        $settings = $institution->setting;

        if (! $settings || ! $settings->hasBillingoApiKey()) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'Hiányzik a Billingo API-kulcs az intézmény beállításaiban.'
            );
        }

        if (! filled($invoice->provider_invoice_id)) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A számlához nem tartozik Billingo bizonylatazonosító, sztornózás nem lehetséges.'
            );
        }

        $apiKey = (string) $settings->billingo_api_key;

        try {
            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->timeout(30)
                ->post(self::BASE_URL."/documents/{$invoice->provider_invoice_id}/cancel", array_filter([
                    'comment' => $reason,
                ]));
        } catch (Throwable $exception) {
            Log::error('Billingo sztornózási API hívási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Billingo szolgáltatás jelenleg nem érhető el: '.$exception->getMessage()
            );
        }

        if (! $response->successful()) {
            $errorMessage = $this->formatApiError($response);

            Log::error('Billingo sztornózás sikertelen', [
                'invoice_id' => $invoice->id,
                'provider_invoice_id' => $invoice->provider_invoice_id,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 2000),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Billingo hibát adott vissza: '.$errorMessage
            );
        }

        $data = $response->json();
        $documentId = $data['id'] ?? null;
        $invoiceNumber = $data['invoice_number'] ?? null;
        $download = $documentId
            ? $this->downloadPdf($apiKey, (int) $documentId, $invoice, $invoiceNumber, 'billingo_storno')
            : ['path' => null, 'size' => null];

        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_VOIDED,
            providerInvoiceId: $documentId !== null ? (string) $documentId : null,
            invoiceNumber: $invoiceNumber,
            issueDate: now(),
            invoiceUrl: $data['public_url'] ?? null,
            invoicePdfPath: $download['path'],
        );
    }

    public function downloadExistingInvoicePdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return $this->downloadExistingDocumentPdf($payload);
    }

    public function downloadExistingCancellationPdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return $this->downloadExistingDocumentPdf($payload);
    }

    public function downloadExistingDocumentPdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        $invoice = $payload->invoice;

        return $this->downloadExistingDocumentPdfInternal(
            payload: $payload,
            providerDocumentId: $invoice->provider_invoice_id,
            invoiceNumber: $invoice->invoice_number,
            subdir: $invoice->isCancellationDocument() ? 'billingo_storno' : 'billingo',
            documentType: $invoice->document_type
        );
    }

    public function listDocuments(InstitutionSetting $settings, int $page, int $perPage = 50, ?Carbon $lastModifiedDate = null): array
    {
        $response = Http::withHeaders(['X-API-KEY' => (string) $settings->billingo_api_key])
            ->timeout(30)
            ->get(self::BASE_URL.'/documents', array_filter([
                'page' => $page,
                'per_page' => $perPage,
                'block_id' => $settings->billingo_document_block_id,
                'last_modified_date' => $lastModifiedDate?->toIso8601String(),
            ]));

        if (! $response->successful()) {
            throw new RuntimeException($this->formatApiError($response));
        }

        $payload = $response->json();
        $data = $payload['data'] ?? (array_is_list($payload) ? $payload : []);

        return [
            'data' => is_array($data) ? $data : [],
            'current_page' => (int) ($payload['current_page'] ?? $page),
            'last_page' => (int) ($payload['last_page'] ?? $page),
            'total' => (int) ($payload['total'] ?? count($data)),
        ];
    }

    public function getDocument(InstitutionSetting $settings, string $documentId): array
    {
        $response = Http::withHeaders(['X-API-KEY' => (string) $settings->billingo_api_key])
            ->timeout(30)
            ->get(self::BASE_URL."/documents/{$documentId}");

        if (! $response->successful()) {
            throw new RuntimeException($this->formatApiError($response));
        }

        return (array) $response->json();
    }

    /**
     * A Billingo validációja szigorúan típusos - egy opcionális szöveges
     * mezőnek explicit `null` értéket küldeni (pl. adószám nélküli
     * magánszemélynél) sok esetben ugyanolyan "Validation Failed" hibát ad
     * vissza, mint egy hibás érték, ezért az üres/hiányzó mezőket inkább
     * teljesen kihagyjuk a payloadból (array_filter), nem null-ként
     * küldjük.
     */
    private function upsertPartner(string $apiKey, InstitutionInvoice $invoice): int
    {
        $payload = array_filter([
            'name' => $invoice->customer_name,
            'emails' => $invoice->customer_email ? [$invoice->customer_email] : null,
            'taxcode' => $invoice->customer_tax_number ?: null,
            'address' => array_filter([
                'country_code' => 'HU',
                'post_code' => (string) $invoice->billing_postcode,
                'city' => (string) $invoice->billing_city,
                'address' => (string) $invoice->billing_address,
            ], fn ($value) => $value !== null && $value !== ''),
        ], fn ($value) => $value !== null && $value !== []);

        $response = Http::withHeaders(['X-API-KEY' => $apiKey])
            ->timeout(30)
            ->post(self::BASE_URL.'/partners', $payload);

        if (! $response->successful()) {
            Log::error('Billingo vevő (partner) validációs hiba', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 2000),
            ]);

            throw new RuntimeException($this->formatApiError($response));
        }

        return (int) $response->json('id');
    }

    private function downloadExistingDocumentPdfInternal(
        InvoiceProviderPayload $payload,
        ?string $providerDocumentId,
        ?string $invoiceNumber,
        string $subdir,
        string $documentType
    ): InvoiceProviderResult {
        $institution = $payload->institution;
        $invoice = $payload->invoice;
        $settings = $institution->setting;

        if (! $settings || ! $settings->hasBillingoApiKey()) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'Hiányzik a Billingo API-kulcs az intézmény beállításaiban.'
            );
        }

        if (! filled($providerDocumentId) || ! ctype_digit((string) $providerDocumentId)) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: $documentType === 'cancellation'
                    ? 'A sztornó bizonylathoz nem tartozik használható Billingo bizonylatazonosító.'
                    : 'A számlához nem tartozik használható Billingo bizonylatazonosító.'
            );
        }

        $download = $this->downloadPdf(
            apiKey: (string) $settings->billingo_api_key,
            documentId: (int) $providerDocumentId,
            invoice: $invoice,
            invoiceNumber: $invoiceNumber,
            subdir: $subdir,
            logContext: [
                'operation' => 'reload',
                'document_type' => $documentType,
            ]
        );

        if ($download['path'] === null) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: self::INVALID_PDF_MESSAGE
            );
        }

        return new InvoiceProviderResult(
            status: $invoice->status,
            providerInvoiceId: (string) $providerDocumentId,
            invoiceNumber: $invoiceNumber,
            issueDate: $invoice->issue_date,
            fulfillmentDate: $invoice->fulfillment_date,
            invoiceUrl: $invoice->invoice_url,
            invoicePdfPath: $download['path'],
        );
    }

    private function downloadPdf(
        string $apiKey,
        int $documentId,
        InstitutionInvoice $invoice,
        ?string $invoiceNumber,
        string $subdir = 'billingo',
        array $logContext = []
    ): array {
        try {
            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->timeout(30)
                ->get(self::BASE_URL."/documents/{$documentId}/download");

            if (! $this->isValidPdfResponse($response)) {
                $this->logInvalidPdfResponse($response, $invoice, $documentId, $invoiceNumber, $subdir, $logContext);

                return ['path' => null, 'size' => null];
            }

            $safeFileNamePart = $this->sanitizeForFilePath($invoiceNumber) ?? (string) $documentId;
            $fileName = $safeFileNamePart.'.pdf';
            $path = 'invoices/'.$subdir.'/'.$invoice->institution_id.'/'.$fileName;
            Storage::disk('local')->put($path, $response->body());

            return [
                'path' => $path,
                'size' => strlen($response->body()),
            ];
        } catch (Throwable $exception) {
            Log::warning('Billingo PDF letöltési hiba', [
                ...$this->pdfLogContext($invoice, $documentId, $invoiceNumber, $subdir, $logContext),
                'exception' => $exception->getMessage(),
            ]);

            return ['path' => null, 'size' => null];
        }
    }

    private function pdfLogContext(
        InstitutionInvoice $invoice,
        int $documentId,
        ?string $invoiceNumber,
        string $subdir,
        array $logContext = []
    ): array {
        return array_merge([
            'invoice_id' => $invoice->id,
            'institution_id' => $invoice->institution_id,
            'invoice_status' => $invoice->status,
            'provider' => $invoice->provider,
            'provider_invoice_id' => $invoice->provider_invoice_id,
            'cancellation_provider_document_id' => $invoice->cancellation_provider_document_id,
            'document_id' => $documentId,
            'invoice_number' => $invoiceNumber,
            'subdir' => $subdir,
        ], $logContext);
    }

    private function isValidPdfResponse(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $contentType = mb_strtolower(trim((string) $response->header('Content-Type')));
        if ($contentType === '' || ! str_contains($contentType, 'application/pdf')) {
            return false;
        }

        $body = $response->body();
        if ($body === '') {
            return false;
        }

        return str_starts_with($body, '%PDF-');
    }

    private function logInvalidPdfResponse(
        Response $response,
        InstitutionInvoice $invoice,
        int $documentId,
        ?string $invoiceNumber,
        string $subdir,
        array $logContext = []
    ): void {
        Log::warning('Billingo PDF letöltése sikertelen: érvénytelen PDF válasz', [
            ...$this->pdfLogContext($invoice, $documentId, $invoiceNumber, $subdir, $logContext),
            'status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'body_excerpt' => $this->safeResponseExcerpt($response->body()),
        ]);
    }

    private function safeResponseExcerpt(string $body): string
    {
        $excerpt = Str::of(substr($body, 0, 500))
            ->replaceMatches('/<[^>]*>/u', ' ')
            ->replaceMatches(
                '/("?(?:name|first_name|last_name|full_name|company_name|customer_name|customer_email|customer_tax_number|tax_number|taxcode|address|billing_address|street|street_address|city|postal_code|postcode|zip)"?\s*[:=]\s*"?)[^",}<\r\n]+/iu',
                '$1[redacted]'
            )
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ')
            ->replaceMatches('/[\r\n\t]+/u', ' ')
            ->replaceMatches('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[redacted-email]')
            ->replaceMatches('/\b\d{8,}\b/u', '[redacted-number]')
            ->replaceMatches('/("?(api[_-]?key|token|authorization|password)"?\s*[:=]\s*"?)[^",\s<]+/iu', '$1[redacted]')
            ->squish();

        return Str::limit((string) $excerpt, 300);
    }

    /**
     * A számlaszámot (amit a Billingo API válaszából kapunk) fájlnév
     * részeként használjuk fel a tárolási útvonalban - emiatt NEM szabad
     * megbízni benne, hogy nem tartalmaz path traversal karaktereket
     * (pl. "../"), még akkor sem, ha normál esetben egy megbízható
     * partner API-ból érkezik (védekezés egy esetleges hibás/kompromittált
     * válasz ellen). Csak a fájlnévben biztonságosan használható
     * karaktereket engedjük át. (Ld. App\Services\Finance\Providers\
     * SzamlazzHuInvoiceProvider ugyanerről a mintáról.)
     */
    private function sanitizeForFilePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value);
        $safe = trim((string) $safe, '_');

        return $safe !== '' ? $safe : null;
    }

    /**
     * A Billingo hibaválaszai jellemzően egy általános "message" mezőt
     * (pl. "Validation Failed") és egy mezőnkénti "errors" objektumot is
     * tartalmaznak a konkrét okkal - eddig csak az általános üzenetet
     * mutattuk, ami nem árulja el, melyik mező hibás. Ez a metódus mindkét
     * részt egyetlen, admin számára is értelmezhető üzenetté fűzi össze.
     */
    private function formatApiError(Response $response): string
    {
        $body = $response->json();
        $message = ($body['message'] ?? null) ?: ($body['error'] ?? null) ?: ('HTTP '.$response->status());
        $errors = $body['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            $details = collect($errors)
                ->map(function ($fieldMessages, $field) {
                    $fieldMessages = is_array($fieldMessages) ? implode(', ', $fieldMessages) : (string) $fieldMessages;

                    return is_string($field) ? "{$field}: {$fieldMessages}" : $fieldMessages;
                })
                ->implode('; ');

            $message .= ' ('.$details.')';
        }

        return $message;
    }

    /**
     * A Billingo v3 API a "language" mezőnél csak egy zárt kódlistát fogad
     * el (hu/en/de/fr/hr/it/ro/sk/us) - bármi más "The selected language is
     * invalid." validációs hibát ad. Az intézményi beállítás korábban egy
     * szabad szöveges mező volt (ld. InstitutionInvoicingSettingUpdateRequest),
     * ezért lehet benne még érvénytelen, régről megmaradt érték - ez a
     * védelem biztosítja, hogy ilyenkor is a magyar nyelvre essen vissza
     * ahelyett, hogy a teljes számlázás elhasalna emiatt.
     */
    private function resolveDocumentLanguage(?string $language): string
    {
        $allowed = ['hu', 'en', 'de', 'fr', 'hr', 'it', 'ro', 'sk', 'us'];
        $normalized = mb_strtolower(trim((string) $language));

        return in_array($normalized, $allowed, true) ? $normalized : 'hu';
    }

    private function mapPaymentMethod(?string $method): string
    {
        $normalized = mb_strtolower((string) $method);

        return match (true) {
            $normalized === '' => 'wire_transfer',
            str_contains($normalized, 'kés') => 'cash',
            str_contains($normalized, 'kártya') => 'bankcard',
            str_contains($normalized, 'utal') => 'wire_transfer',
            default => 'wire_transfer',
        };
    }
}
