<?php

namespace App\Services\Finance\Providers;

use App\Models\InstitutionInvoice;
use App\Models\InstitutionSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Throwable;

/**
 * Számlázz.hu "Számla Agent" XML API integráció.
 *
 * A Számlázz.hu-nak nincs klasszikus REST API-ja: egy XML dokumentumot kell
 * multipart/form-data kérésben, "action-xmlagentxmlfile" nevű mezőben
 * elküldeni a https://www.szamlazz.hu/szamla/ végpontra. A válaszverziót
 * 2-re állítva a válasz maga is XML (nem a PDF-fájl közvetlenül), így
 * egyértelműen el tudjuk dönteni, hogy sikeres volt-e a kiállítás, és a PDF-et
 * base64 kódolva kapjuk vissza benne.
 *
 * ÉLES SZERVEREN ELVÉGZENDŐ: az intézmény a saját Számlázz.hu fiókjában
 * generált "Számla Agent" kulcsot az intézményi beállításoknál kell
 * megadnia (Beállítások / Számlázás és fizetés). Ha az első éles számla
 * kiállításakor a Számlázz.hu eltérő mezőnevet vagy hibaüzenetet ad vissza
 * (pl. a fizmod/afakulcs értékek pontos elfogadott listája intézményenként
 * eltérhet), a hiba oka a naplóban (storage/logs) részletesen rögzítve lesz.
 */
class SzamlazzHuInvoiceProvider implements InvoiceProviderInterface
{
    private const ENDPOINT = 'https://www.szamlazz.hu/szamla/';

    public function createInvoice(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        $institution = $payload->institution;
        $invoice = $payload->invoice;
        $settings = $institution->setting;

        if (! $settings || ! $settings->hasSzamlazzHuAgentKey()) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'Hiányzik a Számlázz.hu agent kulcs az intézmény beállításaiban.'
            );
        }

        try {
            $xml = $this->buildRequestXml($settings, $invoice);
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu XML összeállítási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A számla adatai nem állíthatók össze: '.$exception->getMessage()
            );
        }

        try {
            $response = Http::timeout(30)
                ->attach('action-xmlagentxmlfile', $xml, 'szamla.xml', ['Content-Type' => 'text/xml'])
                ->post(self::ENDPOINT);
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu API hívási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu szolgáltatás jelenleg nem érhető el: '.$exception->getMessage()
            );
        }

        return $this->parseResponse($response, $invoice);
    }

    /**
     * Bizonylat sztornózása a Számlázz.hu "Számla Agent" API-n keresztül.
     *
     * A Számlázz.hu-nál a sztornózás egy KÜLÖN XML dokumentumként, egy külön
     * mezőnévvel ("action-szamla_agent_st") kerül elküldésre ugyanarra a
     * https://www.szamlazz.hu/szamla/ végpontra, a normál kiállítástól
     * eltérő gyökérelemmel (<xmlszamlast>, namespace
     * http://www.szamlazz.hu/xmlszamlast). A kérés az eredeti bizonylat
     * számát (szamlaszam) hivatkozza, a válasz sikeres esetben egy új,
     * önálló sztornó bizonylatot ad vissza (saját számlaszámmal és -
     * valaszVerzio=2 esetén - saját PDF-fel), ugyanabban a formátumban,
     * mint a normál kiállítás válasza. (Ld. https://docs.szamlazz.hu/agent/
     * reversing_invoice/{request,xml,xsd}.)
     */
    public function cancelInvoice(InvoiceProviderPayload $payload, ?string $reason = null): InvoiceProviderResult
    {
        $institution = $payload->institution;
        $invoice = $payload->invoice;
        $settings = $institution->setting;

        if (! $settings || ! $settings->hasSzamlazzHuAgentKey()) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'Hiányzik a Számlázz.hu agent kulcs az intézmény beállításaiban.'
            );
        }

        if (! filled($invoice->invoice_number)) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A számlához nem tartozik Számlázz.hu számlaszám, sztornózás nem lehetséges.'
            );
        }

        try {
            $xml = $this->buildCancelRequestXml($settings, $invoice, $reason);
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu sztornó XML összeállítási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A sztornó adatai nem állíthatók össze: '.$exception->getMessage()
            );
        }

        try {
            $response = Http::timeout(30)
                ->attach('action-szamla_agent_st', $xml, 'szamla_storno.xml', ['Content-Type' => 'text/xml'])
                ->post(self::ENDPOINT);
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu sztornó API hívási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu szolgáltatás jelenleg nem érhető el: '.$exception->getMessage()
            );
        }

        return $this->parseResponse($response, $invoice, InstitutionInvoice::STATUS_VOIDED, 'szamlazz_hu_storno');
    }

    public function downloadExistingInvoicePdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_FAILED,
            errorMessage: 'A Számlázz.hu integráció jelenleg nem támogat külön PDF-újratöltést a meglévő bizonylathoz.'
        );
    }

    public function downloadExistingCancellationPdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_FAILED,
            errorMessage: 'A Számlázz.hu integráció jelenleg nem támogat külön PDF-újratöltést a meglévő sztornó bizonylathoz.'
        );
    }

    private function buildCancelRequestXml(InstitutionSetting $settings, InstitutionInvoice $invoice, ?string $reason): string
    {
        $root = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<xmlszamlast xmlns="http://www.szamlazz.hu/xmlszamlast" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            .'xsi:schemaLocation="http://www.szamlazz.hu/xmlszamlast https://www.szamlazz.hu/szamla/docs/xsds/agentst/xmlszamlast.xsd">'
            .'</xmlszamlast>'
        );

        $beallitasok = $root->addChild('beallitasok');
        $beallitasok->addChild('szamlaagentkulcs', $settings->szamlazz_hu_agent_key);
        $beallitasok->addChild('eszamla', 'true');
        $beallitasok->addChild('szamlaLetoltes', 'true');
        $beallitasok->addChild('valaszVerzio', '2');

        $fejlec = $root->addChild('fejlec');
        $fejlec->addChild('szamlaszam', $invoice->invoice_number);
        $fejlec->addChild('keltDatum', now()->toDateString());
        if (filled($reason)) {
            $fejlec->addChild('tipus', htmlspecialchars($reason, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (filled($invoice->customer_email)) {
            $vevo = $root->addChild('vevo');
            $vevo->addChild('email', $invoice->customer_email);
        }

        return (string) $root->asXML();
    }

    private function buildRequestXml(InstitutionSetting $settings, InstitutionInvoice $invoice): string
    {
        $root = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<xmlszamla xmlns="http://www.szamlazz.hu/xmlszamla" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            .'xsi:schemaLocation="http://www.szamlazz.hu/xmlszamla https://www.szamlazz.hu/szamla/docs/xsds/agent/xmlszamla.xsd">'
            .'</xmlszamla>'
        );

        $beallitasok = $root->addChild('beallitasok');
        $beallitasok->addChild('szamlaagentkulcs', $settings->szamlazz_hu_agent_key);
        $beallitasok->addChild('eszamla', 'true');
        $beallitasok->addChild('szamlaLetoltes', 'true');
        $beallitasok->addChild('valaszVerzio', '2');

        $fejlec = $root->addChild('fejlec');
        $fejlec->addChild('keltDatum', now()->toDateString());
        $fejlec->addChild('teljesitesDatum', $invoice->fulfillment_date?->toDateString() ?? now()->toDateString());
        $fejlec->addChild('fizetesiHataridoDatum', $invoice->due_date?->toDateString() ?? now()->toDateString());
        $fejlec->addChild('fizmod', $invoice->payment_method ?: 'átutalás');
        $fejlec->addChild('penznem', $invoice->currency ?: 'HUF');
        $fejlec->addChild('szamlaNyelve', $settings->szamlazz_hu_invoice_language ?: 'hu');

        if (filled($invoice->note)) {
            $fejlec->addChild('megjegyzes', $invoice->note);
        }

        if (filled($settings->szamlazz_hu_invoice_prefix)) {
            $fejlec->addChild('szamlaszamElotag', $settings->szamlazz_hu_invoice_prefix);
        }

        $vevo = $root->addChild('vevo');
        $vevo->addChild('nev', $invoice->customer_name);
        $vevo->addChild('irsz', (string) $invoice->billing_postcode);
        $vevo->addChild('telepules', (string) $invoice->billing_city);
        $vevo->addChild('cim', (string) $invoice->billing_address);

        if (filled($invoice->customer_email)) {
            $vevo->addChild('email', $invoice->customer_email);
            $vevo->addChild('sendEmail', 'true');
        } else {
            $vevo->addChild('sendEmail', 'false');
        }

        if (filled($invoice->customer_tax_number)) {
            $vevo->addChild('adoszam', $invoice->customer_tax_number);
        }

        $tetelek = $root->addChild('tetelek');
        $tetel = $tetelek->addChild('tetel');
        $tetel->addChild('megnevezes', 'Étkezési térítési díj');
        $tetel->addChild('mennyiseg', '1');
        $tetel->addChild('mennyisegiEgyseg', 'db');
        $tetel->addChild('nettoEgysegar', (string) $invoice->net_amount);
        // A jelenlegi rendszerben a térítési díj ÁFA-mentesen (AAM) kerül
        // kiszámlázásra (vat_amount mindig 0) - ha az intézmény ÁFA-köteles,
        // ezt éles üzembe állás előtt egyeztetni kell.
        $tetel->addChild('afakulcs', 'AAM');
        $tetel->addChild('netto', (string) $invoice->net_amount);
        $tetel->addChild('afa', (string) $invoice->vat_amount);
        $tetel->addChild('brutto', (string) $invoice->gross_amount);

        return (string) $root->asXML();
    }

    private function parseResponse(
        \Illuminate\Http\Client\Response $response,
        InstitutionInvoice $invoice,
        string $successStatus = InstitutionInvoice::STATUS_ISSUED,
        string $pdfSubdir = 'szamlazz_hu'
    ): InvoiceProviderResult {
        if (! $response->successful()) {
            Log::error('Számlázz.hu HTTP hiba', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 2000),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu HTTP hibát adott vissza ('.$response->status().').'
            );
        }

        $body = $response->body();

        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu válasz értelmezési hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
                'body' => substr($body, 0, 2000),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu válasza nem értelmezhető. A részletek a szerver naplójában megtalálhatók.'
            );
        }

        $success = ((string) ($xml->sikeres ?? '')) === 'true';

        if (! $success) {
            $errorMessage = trim((string) ($xml->hibauzenet ?? '')) ?: 'Ismeretlen hiba a Számlázz.hu válaszában.';

            Log::error('Számlázz.hu számlakiállítás sikertelen', [
                'invoice_id' => $invoice->id,
                'hibakod' => (string) ($xml->hibakod ?? ''),
                'hibauzenet' => $errorMessage,
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: $errorMessage
            );
        }

        $invoiceNumber = trim((string) ($xml->szamlaszam ?? '')) ?: null;
        $pdfPath = null;

        if (isset($xml->pdf) && filled((string) $xml->pdf)) {
            $pdfBinary = base64_decode((string) $xml->pdf, true);

            if ($pdfBinary !== false) {
                $safeFileNamePart = $this->sanitizeForFilePath($invoiceNumber) ?? (string) $invoice->id;
                $pdfPath = 'invoices/'.$pdfSubdir.'/'.$invoice->institution_id.'/'.$safeFileNamePart.'.pdf';
                Storage::disk('local')->put($pdfPath, $pdfBinary);
            }
        }

        return new InvoiceProviderResult(
            status: $successStatus,
            providerInvoiceId: $invoiceNumber,
            invoiceNumber: $invoiceNumber,
            issueDate: now(),
            fulfillmentDate: $invoice->fulfillment_date,
            invoiceUrl: null,
            invoicePdfPath: $pdfPath,
        );
    }

    /**
     * A számlaszámot (amit a Számlázz.hu válaszából kapunk) fájlnév részeként
     * használjuk fel a tárolási útvonalban - emiatt NEM szabad megbízni
     * benne, hogy nem tartalmaz path traversal karaktereket (pl. "../"),
     * még akkor sem, ha normál esetben egy megbízható partner API-ból
     * érkezik (védekezés egy esetleges hibás/kompromittált válasz ellen).
     * Csak a fájlnévben biztonságosan használható karaktereket engedjük át.
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
}
