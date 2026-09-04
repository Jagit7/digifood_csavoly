<?php

namespace App\Services\Finance\Providers;

use App\Models\InstitutionInvoice;
use App\Models\InstitutionSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
 *
 * TESZT/ÉLES MÓD (2026-09-es vizsgálat eredménye, dokumentálva, NEM
 * implementálva): a Számlázz.hu hivatalos "Számla Agent" XML dokumentációja
 * (https://docs.szamlazz.hu/agent/generating_invoice/xml és
 * https://docs.szamlazz.hu/agent/basics/details) szerint a <beallitasok>
 * blokkban NINCS önálló "teszt mód" / sandbox XML mező - a dokumentáció csak
 * annyit ír, hogy a teszteléshez óránként maximum 100 számla állítható ki. A
 * Számlázz.hu-nál a "teszt üzemmód" gyakorlatban egy KÜLÖN, a felhasználó
 * felületén regisztrált teszt fiók/agent-kulcs használatát jelenti, nem egy
 * kérés-paramétert - emiatt az itt beállítható `szamlazz_hu_test_mode`
 * mezőt ez a provider SZÁNDÉKOSAN nem használja fel: nincs olyan valódi API
 * mező, amit ráköthetnénk, és egy kitalált mező hozzáadása hamis működést
 * sugallna. (Megjegyzés: a `billingo_test_mode` mező a BillingoInvoiceProvider-ben
 * SZINTÉN nincs felhasználva - tehát ez nem Számlázz.hu-specifikus hiányosság,
 * hanem mindkét szolgáltatónál egyaránt fennálló, már a mostani felmérés
 * előtt is létező állapot.) Ha valódi teszt/éles elkülönítés szükséges, az
 * intézménynek két különböző Számlázz.hu agent-kulcsot (egy teszt- és egy
 * éles fiókét) kellene tudnia tárolni és a kettő között választani - ez
 * séma- és UI-módosítást (üzleti döntést) igényelne, ezért ebben a fázisban
 * nem valósítottuk meg.
 */
class SzamlazzHuInvoiceProvider implements InvoiceProviderInterface
{
    private const ENDPOINT = 'https://www.szamlazz.hu/szamla/';

    /**
     * Ld. a buildRequestXml() 'afakulcs' mezőjénél lévő kommentet: a
     * jelenlegi rendszer minden Számlázz.hu-s tételt ÁFA-mentesként (AAM)
     * számláz - ez könyvelői/jogi döntést igényel, mielőtt megváltozna.
     */
    private const DEFAULT_VAT_CODE = 'AAM';

    /**
     * A meglévő bizonylat PDF-jének újralekérdezéséhez használt HTTP
     * multipart mezőnév (ld. https://docs.szamlazz.hu/hu/agent/querying_pdf/request
     * és https://docs.szamlazz.hu/hu/agent/querying_pdf/xml - ellenőrizve a
     * Számlázz.hu hivatalos dokumentációjában, 2026-09).
     */
    private const PDF_QUERY_FIELD = 'action-szamlazz_agent_pdf';

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
        } catch (ConnectionException $exception) {
            return $this->handleConnectionException($exception, $invoice, 'számlakiállítási');
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
        } catch (ConnectionException $exception) {
            return $this->handleConnectionException($exception, $invoice, 'sztornózási');
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

    /**
     * Egy már kiállított EREDETI bizonylat PDF-jének újralekérdezése.
     *
     * 2026-09-es bővítés: korábban ez a metódus mindig STATUS_FAILED-et adott
     * vissza ("nem támogatott"). A Számlázz.hu hivatalos "Számla PDF lekérés"
     * XML API-ja (https://docs.szamlazz.hu/hu/agent/querying_pdf/*, ellenőrizve
     * 2026-09-ben) valójában TÁMOGATJA egy meglévő bizonylat PDF-jének
     * lekérdezését a számlaszám alapján ("action-szamlazz_agent_pdf" mezőben
     * elküldött <xmlszamlapdf> dokumentummal) - ez teljesen külön akció a
     * kiállítástól/sztornózástól, NEM állít ki új bizonylatot. A válasz
     * formátuma (valaszVerzio=2 esetén) megegyezik a kiállítás/sztornózás
     * válaszával (sikeres/hibakod/hibauzenet/pdf mezők), ezért ugyanazt a
     * mintát követi, mint parseResponse().
     */
    public function downloadExistingInvoicePdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return $this->queryExistingPdf($payload, $payload->invoice, 'szamlazz_hu');
    }

    /**
     * Egy már kiállított SZTORNÓ bizonylat PDF-jének újralekérdezése.
     *
     * Ld. downloadExistingInvoicePdf() kommentjét - ugyanazt az API-t
     * használja, csak a sztornó bizonylat SAJÁT számlaszámával (a Számlázz.hu
     * a sztornó bizonylatot is önálló, saját számlaszámmal rendelkező
     * dokumentumként tartja nyilván, ld. cancelInvoice() dokblokkja).
     */
    public function downloadExistingCancellationPdf(InvoiceProviderPayload $payload): InvoiceProviderResult
    {
        return $this->queryExistingPdf($payload, $payload->invoice, 'szamlazz_hu_storno');
    }

    private function queryExistingPdf(InvoiceProviderPayload $payload, InstitutionInvoice $invoice, string $pdfSubdir): InvoiceProviderResult
    {
        $institution = $payload->institution;
        $settings = $institution->setting;

        if (! $settings || ! $settings->hasSzamlazzHuAgentKey()) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'Hiányzik a Számlázz.hu agent kulcs az intézmény beállításaiban.'
            );
        }

        // A Számlázz.hu-nál a "provider_invoice_id"-t a rendszer mindig a
        // ténylegesen kapott számlaszámmal (szamlaszam) tölti fel (ld.
        // parseResponse() -> providerInvoiceId: $invoiceNumber), ezért a kettő
        // ezen a szolgáltatón belül mindig megegyezik - a lekérdezéshez az
        // invoice_number mezőt használjuk, ami a hivatalos "szamlaszam"
        // azonosító.
        if (! filled($invoice->invoice_number)) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A bizonylathoz nem tartozik Számlázz.hu számlaszám, a PDF nem kérdezhető le újra.'
            );
        }

        try {
            $xml = $this->buildPdfQueryXml($settings, $invoice);
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu PDF-lekérdezési XML összeállítási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A PDF-lekérdezés adatai nem állíthatók össze: '.$exception->getMessage()
            );
        }

        try {
            $response = Http::timeout(30)
                ->attach(self::PDF_QUERY_FIELD, $xml, 'szamla_pdf_lekeres.xml', ['Content-Type' => 'text/xml'])
                ->post(self::ENDPOINT);
        } catch (ConnectionException $exception) {
            return $this->handleConnectionException($exception, $invoice, 'PDF-lekérdezési');
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu PDF-lekérdezési API hívási hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu szolgáltatás jelenleg nem érhető el: '.$exception->getMessage()
            );
        }

        return $this->parsePdfQueryResponse($response, $invoice, $pdfSubdir);
    }

    /**
     * Ld. https://docs.szamlazz.hu/hu/agent/querying_pdf/xml (ellenőrizve
     * 2026-09-ben) - a mezők sorrendje a Számlázz.hu XSD-je szerint kötött:
     * szamlaagentkulcs, szamlaszam, valaszVerzio, szamlaKulsoAzon.
     */
    private function buildPdfQueryXml(InstitutionSetting $settings, InstitutionInvoice $invoice): string
    {
        $root = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<xmlszamlapdf xmlns="http://www.szamlazz.hu/xmlszamlapdf" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            .'xsi:schemaLocation="http://www.szamlazz.hu/xmlszamlapdf https://www.szamlazz.hu/szamla/docs/xsds/agentpdf/xmlszamlapdf.xsd">'
            .'</xmlszamlapdf>'
        );

        $root->addChild('szamlaagentkulcs', $settings->szamlazz_hu_agent_key);
        $root->addChild('szamlaszam', $invoice->invoice_number);
        $root->addChild('valaszVerzio', '2');

        return (string) $root->asXML();
    }

    private function parsePdfQueryResponse(
        \Illuminate\Http\Client\Response $response,
        InstitutionInvoice $invoice,
        string $pdfSubdir
    ): InvoiceProviderResult {
        if (! $response->successful()) {
            Log::error('Számlázz.hu PDF-lekérdezési HTTP hiba', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => $this->safeResponseExcerpt($response->body()),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu HTTP hibát adott vissza ('.$response->status().').'
            );
        }

        try {
            $xml = new SimpleXMLElement($response->body());
        } catch (Throwable $exception) {
            Log::error('Számlázz.hu PDF-lekérdezési válasz értelmezési hiba', [
                'invoice_id' => $invoice->id,
                'exception' => $exception->getMessage(),
                'body' => $this->safeResponseExcerpt($response->body()),
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu válasza nem értelmezhető. A részletek a szerver naplójában megtalálhatók.'
            );
        }

        $success = ((string) ($xml->sikeres ?? '')) === 'true';

        if (! $success) {
            $errorMessage = trim((string) ($xml->hibauzenet ?? '')) ?: 'Ismeretlen hiba a Számlázz.hu PDF-lekérdezési válaszában.';

            Log::error('Számlázz.hu PDF-lekérdezés sikertelen', [
                'invoice_id' => $invoice->id,
                'hibakod' => (string) ($xml->hibakod ?? ''),
                'hibauzenet' => $errorMessage,
            ]);

            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: $errorMessage
            );
        }

        if (! isset($xml->pdf) || ! filled((string) $xml->pdf)) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu válasza nem tartalmazott PDF-tartalmat.'
            );
        }

        $pdfBinary = base64_decode((string) $xml->pdf, true);

        if ($pdfBinary === false) {
            return new InvoiceProviderResult(
                status: InstitutionInvoice::STATUS_FAILED,
                errorMessage: 'A Számlázz.hu PDF-válasza nem dekódolható.'
            );
        }

        $safeFileNamePart = $this->sanitizeForFilePath($invoice->invoice_number) ?? (string) $invoice->id;
        $pdfPath = 'invoices/'.$pdfSubdir.'/'.$invoice->institution_id.'/'.$safeFileNamePart.'.pdf';
        Storage::disk('local')->put($pdfPath, $pdfBinary);

        return new InvoiceProviderResult(
            status: $invoice->status,
            providerInvoiceId: $invoice->provider_invoice_id,
            invoiceNumber: $invoice->invoice_number,
            issueDate: $invoice->issue_date,
            fulfillmentDate: $invoice->fulfillment_date,
            invoiceUrl: $invoice->invoice_url,
            invoicePdfPath: $pdfPath,
        );
    }

    /**
     * Az intézményi boolean beállításokat ('true'/'false' string) a
     * Számlázz.hu XML API az irodalmi "true"/"false" szöveges értékként
     * várja (nem "1"/"0"-ként) - ld. eszamla mező mindkét XML-építőben.
     */
    private function boolToXmlString(?bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Kapcsolati hiba (timeout, DNS, kapcsolat megszakadt) esetén - a Laravel
     * HTTP kliens ConnectionException-t dob, amikor a kérés NEM jutott el
     * egyértelmű HTTP válaszig. Ez a legkockázatosabb hibatípus: elképzelhető,
     * hogy a Számlázz.hu ténylegesen KIÁLLÍTOTTA a bizonylatot, csak a válasz
     * nem érkezett meg időben - ilyenkor a helyi rekord "failed" állapotban
     * marad, de emiatt NEM szabad automatikusan/gondolkodás nélkül újra
     * megpróbálni a kiállítást (ami duplikált, valós bizonylatot
     * eredményezhetne a Számlázz.hu rendszerében). A hibaüzenet ezért
     * kifejezetten figyelmeztet erre, hogy az adminisztrátor a Számlázz.hu
     * felületén ELLENŐRIZZE, készült-e mégis bizonylat, mielőtt törli a helyi
     * "failed" rekordot és újrapróbálja (ld. InstitutionInvoiceService::delete()
     * dokblokkja, ami jelenleg feltételezi, hogy "failed" esetén sosem jött
     * létre valódi bizonylat a szolgáltatónál - ez a feltételezés pontosan
     * EBBEN az esetben lehet hamis).
     */
    private function handleConnectionException(ConnectionException $exception, InstitutionInvoice $invoice, string $muveletLabel): InvoiceProviderResult
    {
        Log::error('Számlázz.hu kapcsolati hiba (időtúllépés/elérhetetlen szolgáltatás) - a bizonylat státusza a szolgáltatónál BIZONYTALAN', [
            'invoice_id' => $invoice->id,
            'muvelet' => $muveletLabel,
            'exception' => $exception->getMessage(),
        ]);

        return new InvoiceProviderResult(
            status: InstitutionInvoice::STATUS_FAILED,
            errorMessage: 'A Számlázz.hu szolgáltatás nem válaszolt időben a '.$muveletLabel.' kérésre. '
                .'FONTOS: ez esetben ELŐFORDULHAT, hogy a bizonylat mégis elkészült a Számlázz.hu rendszerében, csak a '
                .'válasz nem érkezett meg. Az újrapróbálkozás vagy a próbálkozás törlése ELŐTT kérjük, ellenőrizze a '
                .'Számlázz.hu felületén (szamlazz.hu), hogy nem készült-e már bizonylat ehhez a kötelezettséghez, nehogy '
                .'duplikált számla jöjjön létre.'
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
        // Az intézményi beállítást (szamlazz_hu_e_invoice_enabled) használjuk,
        // NEM hardcode-olt 'true'-t - ld. a fájl elején lévő osztály-szintű
        // kommentet a korábbi hibáról.
        $beallitasok->addChild('eszamla', $this->boolToXmlString($settings->szamlazz_hu_e_invoice_enabled));
        $beallitasok->addChild('szamlaLetoltes', 'true');
        $beallitasok->addChild('valaszVerzio', '2');

        $fejlec = $root->addChild('fejlec');
        $fejlec->addChild('szamlaszam', $invoice->invoice_number);
        $fejlec->addChild('keltDatum', now()->toDateString());
        if (filled($reason)) {
            // REGRESSZIÓ-JAVÍTÁS (2026-09, második kör): az előző javítás
            // (ami a nyers $reason értéket adta át) TÉVES feltevésen alapult.
            // Ténylegesen leellenőrizve (php -r ... SimpleXMLElement teszt):
            // a SimpleXMLElement::addChild() NEM escape-eli automatikusan a
            // benne lévő "&" karaktert - egy nyers, önmagában álló "&" esetén
            // "unterminated entity reference" PHP warningot dob, és az elem
            // TARTALMA CSENDBEN ELVESZIK (üres <tipus/> jön létre a
            // megadott indoklás helyett) - tehát a korábbi "javítás" valójában
            // egy ÚJ, súlyosabb hibát okozott (elveszett sztornó-indoklás),
            // nem csak dupla escape-elést. A helyes megoldás - amit egy
            // különálló PHP teszttel is megerősítettem - az érték előzetes,
            // egyszeres htmlspecialchars(...) escape-elése ENT_XML1-gyel: így
            // az addChild() a már escape-elt "&amp;"-et szó szerinti
            // szövegként veszi át (nem próbálja entitásként értelmezni, mert
            // nincs benne nyers "&"), a végeredmény XML-ben pontosan egyszer
            // escape-elt "&amp;" jelenik meg, dupla escape-elés (& "&amp;amp;")
            // nélkül.
            $fejlec->addChild('tipus', htmlspecialchars($reason, ENT_QUOTES | ENT_XML1, 'UTF-8'));
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
        // Az intézményi beállítást (szamlazz_hu_e_invoice_enabled) használjuk,
        // NEM hardcode-olt 'true'-t - ld. a fájl elején lévő osztály-szintű
        // kommentet a korábbi hibáról.
        $beallitasok->addChild('eszamla', $this->boolToXmlString($settings->szamlazz_hu_e_invoice_enabled));
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
        // kiszámlázásra (vat_amount mindig 0) - ez KÖNYVELŐI/JOGI DÖNTÉST
        // igényel, mielőtt ÁFA-köteles intézménynél élesben módosítható
        // lenne, ezért ezt a fázist NEM változtatjuk meg. A self::DEFAULT_VAT_CODE
        // konstansba emelése tisztán technikai refaktor (a viselkedés
        // változatlan), hogy egy jövőbeli, könyvelői jóváhagyással
        // rendelkező módosítás egyetlen helyen legyen paraméterezhető. Ld.
        // ugyanez a minta és ugyanez a korlátozás:
        // App\Services\Finance\Providers\BillingoInvoiceProvider (kb. 97.
        // sor, 'vat' => 'AAM' tétel-szinten) - azt a fájlt ebben a fázisban
        // szándékosan NEM módosítottuk, hogy a működő Billingo-integrációt
        // ne érintse semmilyen kockázat.
        $tetel->addChild('afakulcs', self::DEFAULT_VAT_CODE);
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
                'body' => $this->safeResponseExcerpt($response->body()),
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
                'body' => $this->safeResponseExcerpt($body),
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

    /**
     * A Számlázz.hu válaszait (hibás/nem értelmezhető válasz esetén) a
     * naplóba írjuk a hibaelemzéshez - ez a válasz azonban tartalmazhat a
     * kérésben elküldött vevőadatokat (név, cím, e-mail, adószám) is, ha a
     * Számlázz.hu visszatükrözi őket egy validációs hibaüzenetben. Ugyanazt
     * a védekező redaktálási mintát alkalmazzuk, mint a
     * BillingoInvoiceProvider::safeResponseExcerpt() - személyes adatok és
     * esetleges kulcs/token-szerű értékek NE kerüljenek olvasható formában a
     * szerver naplójába.
     */
    private function safeResponseExcerpt(string $body): string
    {
        $excerpt = Str::of(substr($body, 0, 500))
            ->replaceMatches('/<[^>]*>/u', ' ')
            ->replaceMatches(
                '/("?(?:name|first_name|last_name|full_name|company_name|customer_name|customer_email|customer_tax_number|tax_number|taxcode|address|billing_address|street|street_address|city|postal_code|postcode|zip|nev|cim|email|adoszam|irsz|telepules)"?\s*[:=]\s*"?)[^",}<\r\n]+/iu',
                '$1[redacted]'
            )
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ')
            ->replaceMatches('/[\r\n\t]+/u', ' ')
            ->replaceMatches('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[redacted-email]')
            ->replaceMatches('/\b\d{8,}\b/u', '[redacted-number]')
            ->replaceMatches('/("?(agentkulcs|szamlaagentkulcs|api[_-]?key|token|authorization|password)"?\s*[:=]\s*"?)[^",\s<]+/iu', '$1[redacted]')
            ->squish();

        return Str::limit((string) $excerpt, 300);
    }
}
