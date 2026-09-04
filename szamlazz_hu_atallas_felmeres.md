# Számlázz.hu átállási felmérés — digifood_csavoly

**Státusz: MŰSZAKI FELMÉRÉS — még nem történt kódmódosítás, migráció vagy adatbázis-változtatás.**
Ez a dokumentum kizárólag a jelenlegi kódbázis (fájlok, modellek, migrációk, route-ok, controllerek, service-ek, blade nézetek) tényleges tartalma alapján készült. Ahol a rendelkezésre álló fájlokból egy kérdés nem volt megválaszolható, azt kifejezetten jelöltem, ahelyett hogy találgattam volna.

---

## A) Jelenlegi rendszer

### A.1 Két PÁRHUZAMOS számlázó-szolgáltató struktúra — csak az egyik él

A kódbázisban **két, egymástól teljesen független** namespace létezik, azonos névvel ellátott osztályokkal. Ez a legfontosabb, mindenre kiható felismerés — ha ezt a projekt következő fejlesztője összekeveri, rossz helyen fog "javítani".

**1. `App\Services\Invoicing\*` — HALOTT/ELÁRVULT kód, sehonnan nem hívódik:**
- `InvoiceProviderInterface.php` — triviális interfész, csak `issue(MonthlyPaymentStatement): array`.
- `InvoiceProviderFactory.php` — `match($provider)` gyár, nincs rá hivatkozás sehol.
- `BillingoInvoiceProvider.php` — `issue()` metódusa `HttpException(501, 'Az integráció még nincs aktiválva.')`-t dob.
- `SzamlazzHuInvoiceProvider.php` — ugyanígy 501-et dob.
- `ManualInvoiceProvider.php` — minimál stub.

Ez a namespace **nem használt, nem hívott sehonnan** a controllerek/service-ek felől. Fejlesztéskor ne ebből induljunk ki, és ne itt "javítsunk" semmit.

**2. `App\Services\Finance\Providers\*` + `App\Services\Finance\InstitutionInvoiceService` — a VALÓDI, aktívan bekötött rendszer:**
- `InvoiceProviderInterface.php` — a valós interfész: `createInvoice()`, `cancelInvoice(?string $reason)`, `downloadExistingInvoicePdf()`, `downloadExistingCancellationPdf()`.
- `InvoiceProviderPayload.php` — readonly DTO: `Institution $institution, MonthlyPaymentStatement $statement, InstitutionInvoice $invoice`.
- `InvoiceProviderResult.php` — readonly DTO: `status, providerInvoiceId, invoiceNumber, issueDate, fulfillmentDate, invoiceUrl, invoicePdfPath, errorMessage`.
- `ManualInvoiceProvider.php`, `BillingoInvoiceProvider.php`, `SzamlazzHuInvoiceProvider.php` — mind ezt az interfészt implementálják.
- `InstitutionInvoiceService.php` (1120 sor) — a teljes rendszer karmestere; ezt hívja a `InstitutionInvoiceController`.

A `InstitutionInvoiceService::provider()` privát metódusa a **tényleges** szolgáltató-feloldási pont (`match($provider) { ... }`), ami megerősíti, hogy az `Invoicing\InvoiceProviderFactory` valóban halott kód.

### A.2 A Számlázz.hu integráció: kódszinten KÉSZ, de több ponton hiányos/nem élesített

`app/Services/Finance/Providers/SzamlazzHuInvoiceProvider.php` (357 sor) — teljes egészében elolvasva.

- **Nincs klasszikus REST API** — a Számlázz.hu "Számla Agent" XML-alapú végpontját hívja (`https://www.szamlazz.hu/szamla/`), `multipart/form-data`-ban egy `action-xmlagentxmlfile` (kiállítás) ill. `action-szamla_agent_st` (sztornó) mezőben elküldött XML dokumentummal. `valaszVerzio=2` beállítás miatt a válasz maga is XML, a PDF-et base64-ben, a `<pdf>` tagben kapja vissza.
- `createInvoice()` — **valódi HTTP hívás** (`Http::attach()->post()`), valódi XML-építés (`SimpleXMLElement`), a választ feldolgozza, a PDF-et lementi a `local` (privát) diszkre `invoices/szamlazz_hu/{institution_id}/{invoice_number}.pdf` alá, hibát logol.
- `cancelInvoice()` — ugyanígy valódi API-hívás, külön sztornó XML-lel.
- **`downloadExistingInvoicePdf()` és `downloadExistingCancellationPdf()` — NINCS implementálva, mindig `STATUS_FAILED`-et ad vissza**, a hibaüzenet szerint "A Számlázz.hu integráció jelenleg nem támogat külön PDF-újratöltést." A Billingo-nál ez működik (ld. A.3).
- **`listDocuments()` / `getDocument()` metódus egyáltalán nincs** ezen a providernél — tehát a Billingóhoz hasonló "pull" szinkron (meglévő bizonylatok visszakérdezése) jelenleg technikailag sem lehetséges Számlázz.hu-ra ebből a fájlból kiindulva.
- Az e-számla flag **hardcode-olva `true`**, figyelmen kívül hagyja a `szamlazz_hu_e_invoice_enabled` intézményi beállítást — valódi hiányosság.
- A teszt/éles mód (`szamlazz_hu_test_mode`) beállítás **létezik az `InstitutionSetting`-ben, de ezt a providert egyáltalán nem használja fel** — a Számlázz.hu API-nak van "számlaszám tesztelése" / teszt módja, ez a kód jelenleg mindig élesben hív.
- Az ÁFA kezelése **hardcode-olva `'AAM'`** (alanyi adómentes), a kód explicit kommenttel jelzi, hogy ez könyvelői megerősítést igényel, mielőtt ÁFA-köteles intézménynél élesben használható lenne.
- Nincs automatikus retry logika hálózati hiba esetén.
- Agent-kulcs, számlaszám-prefix, fizetési mód, teljesítési dátum, fizetési határidő, megjegyzés mező, vevőadatok — mind **helyesen be vannak kötve**.

**Válasz a felhasználó eredeti kérdésére ("működőképes-e vagy csak elő van készítve"):** kódszinten **elkészült, valódi API-hívásokkal működő** implementáció, DE a saját docblockja szerint még nincs éles Számlázz.hu fiók ellen kipróbálva, hiányzik belőle a PDF-újratöltés, figyelmen kívül hagyja az e-számla és a teszt/éles mód beállítást, és az ÁFA-kezelése hardcode-olt, könyvelői jóváhagyást igénylő egyszerűsítés. Éles használat előtt ezeket a hiányosságokat pótolni/megerősíteni kell (ld. I. és J. pont).

### A.3 Összehasonlítás: `BillingoInvoiceProvider` (Finance/Providers, 578 sor, teljes egészében elolvasva)

A Billingo REST API v3-at (`https://api.billingo.hu/v3`) hívja `X-API-KEY` fejléccel. A Számlázz.hu providerhez képest **több funkciót tud**:
- `listDocuments()` és `getDocument()` — lapozható lekérdezés a Billingo felé (ezt használja a `BillingoInvoiceSyncService`, ld. A.6).
- `downloadExistingInvoicePdf()`/`downloadExistingCancellationPdf()`/`downloadExistingDocumentPdf()` — **valódi PDF-újratöltés működik**, tartalom-típus és `%PDF-` header ellenőrzéssel.
- Vevő ("partner") létrehozás minden kiállítás előtt (`upsertPartner()`) — a docblock explicit jelzi, hogy ez duplikációt okozhat ismételt vevőknél, ez egy tudatosan elfogadott egyszerűsítés.
- Ugyanúgy hardcode-olt `'vat' => 'AAM'` tétel-szinten, ugyanaz a könyvelői megerősítést igénylő pont.
- Fájlnév-sanitizálás path traversal ellen (`sanitizeForFilePath()`) — ugyanez a minta a Számlázz.hu providernél is megvan.

**Következtetés:** a hiányzó Számlázz.hu-funkciók (PDF-újratöltés, dokumentum-lista lekérdezés) NEM technikai lehetetlenség, hanem egyszerűen még nincs megírva — a Billingo oldalán már bizonyítottan működik az analóg minta.

### A.4 `InstitutionSetting` — a per-intézményi konfiguráció már ma is szimmetrikus

`app/Models/InstitutionSetting.php` — teljes egészében elolvasva. A `fillable` lista **már ma is teljesen párhuzamos** struktúrát tartalmaz mindkét szolgáltatóhoz:

| Billingo | Számlázz.hu |
|---|---|
| `billingo_api_key` (encrypted, hidden) | `szamlazz_hu_agent_key` (encrypted, hidden) |
| `billingo_document_block_id` | `szamlazz_hu_invoice_prefix` |
| `billingo_default_payment_method` | `szamlazz_hu_default_payment_method` |
| `billingo_due_days` | `szamlazz_hu_due_days` |
| `billingo_invoice_language` | `szamlazz_hu_invoice_language` |
| `billingo_e_invoice_enabled` | `szamlazz_hu_e_invoice_enabled` |
| `billingo_test_mode` | `szamlazz_hu_test_mode` |
| `billingo_last_successful_sync_at` / `billingo_sync_last_modified_at` / `billingo_last_sync_error` | *(nincs Számlázz.hu megfelelője — mert nincs sync szolgáltatás sem)* |

A tényleges szolgáltató-választás egy központi mezőben van: `invoicing_provider` (enum-szerű string: `manual` / `billingo` / `szamlazz_hu`, konstansok: `INVOICING_PROVIDER_MANUAL/BILLINGO/SZAMLAZZ_HU`), **intézményenként külön** — ez azt jelenti, hogy **az architektúra már ma is támogatja az intézményenkénti szolgáltató-választást séma-módosítás nélkül**, csak azt kell biztosítani, hogy minden kódút (különösen a leendő szülői gomb) ezt a mezőt, és sose egy hardcode-olt vagy kliens felől érkező értéket használjon.

`InstitutionSetting::hasBillingoApiKey()` / `hasSzamlazzHuAgentKey()` / `hasBankTransferAccount()` / `hasCibCredentials()` — meglévő, kész readiness-ellenőrző segédmetódusok.

### A.5 `InstitutionInvoiceService` — a karmester (1120 sor, teljes egészében elolvasva)

Kulcsmetódusok:
- **`store()`** (239-349. sor) — új számla létrehozása. **DB tranzakción belül**, `MonthlyPaymentStatement::lockForUpdate()` majd egy `InstitutionInvoice::...->lockForUpdate()->exists()` ellenőrzés zárja ki a versenyhelyzetet/duplikációt (253-266. sor): ha már van `DOCUMENT_TYPE_ORIGINAL` számla ehhez a `monthly_payment_statement_id`-hoz, kivételt dob. 0/negatív összegű kötelezettségre validációs hibát dob.
  - **KULCSFONTOSSÁGÚ TALÁLAT:** a 300. sorban `$grossAmount = (int) $statement->total_payable;` — tehát a jelenlegi kód a **teljes fizetendő összeget** (aktuális hó + korábbi tartozás/túlfizetés egyenlege) számlázza ki, **nem** a `$statement->invoiceable_amount` mezőt (ami kizárólag az aktuális havi összeget jelentené). Ugyanez ismétlődik a `buildPreview()`-ban (844. sor). **Ez direkt ütközik az új specifikáció 1. pontjával**, mely szerint a számlának KIZÁRÓLAG az aktuális havi fizetendőt szabad tartalmaznia.
- **`issueAutomaticInvoiceForPayment()`** (351-394. sor) — létezik egy automatikus számlakiállítási útvonal is: sikeres CIB bankkártyás fizetés után hívható, DE **csak akkor fut le bármit, ha `invoicing_enabled && invoicing_provider === BILLINGO`** (357. sor), Számlázz.hu-ra explicit nem működik. **Ellenőrizve: ezt a metódust a teljes kódbázisban SEHOL nem hívja semmi** (`grep -i issueAutomaticInvoice` csak ebben a fájlban talál találatot) — tehát ez jelenleg **bekötetlen, halott kód**, nincs éles automatikus számlázás sem kártyás fizetés után.
- **`cancel()`** (405. sortól) — csak `ISSUED` státuszú bizonylat sztornózható. A sztornó egy **teljesen külön `InstitutionInvoice` sort** hoz létre (`document_type = DOCUMENT_TYPE_CANCELLATION`, negált összegekkel, `original_invoice_id` kapcsolattal) — **az eredeti bizonylat sora nem módosul**. `lockForUpdate()`-et használ itt is.
- **`delete()`** — csak sikertelen ("failed") számlapróbálkozás törölhető (ld. controller `destroy()` üzenete), nem valódi kiállított bizonylat.
- **`reloadBillingoPdf()`** jellegű metódus **csak Billingóra létezik** — Számlázz.hu-ra a PDF-újratöltés funkcionálisan sincs meg (ld. A.2).
- A vevő ("customer") mindig a **gondviselő** (`Guardian`/`BillingProfile` fallback-lánc), **nem a gyermek**.

### A.6 `BillingoInvoiceSyncService` — csak Billingóra, PULL-jellegű, nem újrafelhasználható közvetlenül

A szinkron **lekérdezi** a Billingo API-tól a már ott létező bizonylatokat (`listDocuments()`, lapozva, `last_modified_date` cursor-ral az inkrementális szinkronhoz), és ezt egyezteti a helyi `InstitutionInvoice` sorokkal — ez **nem** a kiállítás (push) útvonala, az a `BillingoInvoiceProvider::createInvoice()`-ban van, teljesen külön kódúton.

- Egyezéskor frissíti: `invoice_number`, `issue_date`, `fulfillment_date`, `due_date`, `invoice_url`, és ha hiányzik a PDF, újratölti.
- Nem egyező, de a Billingo felől sztornóként azonosított dokumentumra: az eredetit `STATUS_VOIDED`-re állítja, és létrehoz egy `DOCUMENT_TYPE_CANCELLATION` sort **negált** összegekkel.
- Futásonként `InstitutionInvoiceSyncRun` sort ír (számlálókkal: fetched/created/updated/downloaded_pdfs/existing_pdfs/unmatched/error, státusz SUCCESS/PARTIAL/FAILED/SKIPPED), és frissíti az `InstitutionSetting.billingo_last_successful_sync_at` / `billingo_sync_last_modified_at` / `billingo_last_sync_error` mezőket.
- **Számlázz.hu-ra ez a service közvetlenül NEM használható újra**: konkrét `BillingoInvoiceProvider` osztályra van kötve (nem az interfészre), a Billingo `block_id` fogalmára épít, és — mivel a `SzamlazzHuInvoiceProvider`-ben nincs `listDocuments()` — a mögöttes API-hívás sincs meg hozzá. Az **orchestrációs minta** (futás-napló, dry-run, "hiányzó PDF-ek" mód, sztornó-egyeztetés eredeti dokumentum-azonosító alapján, negált összeg konvenció) viszont mintaként átvehető, ha egyszer a Számlázz.hu-oldali lekérdező API implementálásra kerül (a Számlázz.hu-nak ténylegesen van "számla lekérdezése" XML akciója, csak ez a kódbázis jelenleg nem implementálja).

### A.7 Admin pénzügyi modul (Finance)

- **`InstitutionInvoiceController`** (`app/Http/Controllers/Dashboard/InstitutionAdmin/Finance/`) — teljes egészében elolvasva. Akciók: `index`, `sync`, `create`, `searchStatements`, `previewStatement`, `store`, `show`, `cancel`, `reloadPdf`, `reloadCancellationPdf`, `download`, `downloadCancellation`, `destroy`. `resolveProvider()` **mindig az intézmény beállított `invoicing_provider` mezőjéhez zárja** a szolgáltatót — a felhasználó nem választhat mást, ezt az `InstitutionInvoiceStoreRequest` validációja is megerősíti szerver oldalon (ld. A.9). `authorizeInvoice()` csak intézmény-egyezést ellenőriz, **nincs szerepkör/permission-ellenőrzés magában a controllerben** — ez teljes egészében a route middleware-re van bízva (ld. A.8).
- **`InstitutionPaymentController`** — kézi befizetés-rögzítés (`store`/`update`/`quickPay`), minden mentés `InstitutionPaymentComponentService::syncAllocationsForPayment()`-et hív az egyenlegek frissítésére, és minden create/update/delete **audit-loggolva van** (`AuditLogger::log()`, before/after diff). Listázás/keresés: gyermek név, gondviselő vezeték-/keresztnév, státusz, fizetési mód, dátum-tartomány szerint, összesítő statisztikákkal. **Nincs automatikus bank-referencia egyeztetés** — a `reference` mező szabad szöveg, amit az admin kézzel ír be; nincs kód, ami egy beérkező utalás közleményét automatikusan egy statement-hez/számlához párosítaná.
- **`InstitutionDebtController` + `InstitutionDebtService`** — a "tartozás" itt a `total_payable - teljesült InstitutionPayment összeg` maradék, esedékesség szerint csoportosítva (`Ma esedékes` / `Késedelmes` / `Fizetési határidő előtt`, + `Részben fizetve` jelző). Ez **más fogalom**, mint a `previous_balance`/túlfizetés-nyilvántartás — a statement már eleve kombinált `total_payable`-jéből indul ki, nem bontja szét havi/korábbi részre.
- **`InstitutionPaymentComponentService`** — a FOUNDATION/KINDERGARTEN ("Zsárica Alapítvány" / "óvodai étkezés") szét-számlázás motorja. Itt **valóban explicit szét van választva** a korábbi (`sumComponentPreviousBalance()`) és a tárgyidőszaki, számlát érintő (`sumComponentInvoiceAdjustments()`) összeg, komponensenként. `syncAllocationsForPayment()` a befizetéseket a legrégebbi lezárt/kiszámlázott statementtől kezdve alkalmazza, a maradékot pedig "fel nem használt jóváírásként" (`TYPE_UNAPPLIED`) tartja nyilván, ami **automatikusan** felhasználható egy KÉSŐBBI statement egyenlegének csökkentésére — ez fontos ütközési pont az új specifikáció azon elvárásával, hogy a túlfizetés NE csökkentse automatikusan a következő havi számlát, ha a szülő személyesen szeretné rendezni (ld. J. pont, nyitott kérdés).

### A.8 Jogosultságok — jelenlegi állapot

`routes/web.php`, Pénzügyek (Finance) route-csoport: külső middleware `role:institution_admin,municipality`, ezen belül a Finance route-ok (beleértve a `invoices/{invoice}/cancel`-t is) **mind szűkítve vannak `role:institution_admin`-ra**. Ez azt jelenti:
- Az `institution_secretary` szerepkörnek **jelenleg egyáltalán nincs hozzáférése** ehhez a modulhoz (sem listázás, sem sztornó).
- Bármely `institution_admin` sztornózhat — **nincs külön "cancel-invoices" jogosultság** jelenleg.

`app/Models/User.php` — `hasPermission()`: `super_admin` és `institution_admin` esetén **feltétel nélkül `true`**-t ad vissza; csak más szerepkörnél nézi meg a `permissions` JSON tömböt. **Architekturális következmény:** egy `permissions`-alapú "cancel-invoices" jogosultság jelenlegi formájában **nem tudná korlátozni** magát az `institution_admin`-t (mert az mindig `true`-t kap), csak **kiterjeszteni** tudná a hozzáférést pl. az `institution_secretary`-re. Ez pontosan megfelel a felhasználó által javasolt modellnek (super_admin + institution_admin marad az alapértelmezett kör, `institution_secretary` csak explicit jogosultsággal kap hozzáférést) — de fontos tudatosítani, hogy ez a jelenlegi kód alapján **nem korlátozás**, hanem **bővítés** lesz.

`CheckRole` middleware (`app/Http/Middleware/CheckRole.php`) — egyszerű szerepkör-ellenőrzés, `abort(403)` eltérés esetén.

`AuditLog` modell — van `ACTION_INSTITUTION_PAYMENT_CREATED/UPDATED/DELETED`, de **nincs egyetlen számlázással kapcsolatos akció-konstans sem** (nincs `ACTION_INVOICE_ISSUED`, `ACTION_INVOICE_CANCELLED` stb.). A számla sztornó audit-nyoma jelenleg **csak magán az `InstitutionInvoice` soron** él (`cancelled_by`, `cancelled_at`, `cancellation_reason` mezők), **nem** a központi `AuditLog` táblában.

### A.9 Validációs szabályok (Form Request-ek)

- **`InstitutionInvoiceCancelRequest.php`** (teljes egészében elolvasva): `'reason' => ['nullable', 'string', 'max:500']`. **A sztornó indoklás jelenleg NEM kötelező** — ez ellentmond az új specifikáció elvárásának ("kötelező indoklás").
- **`InstitutionInvoiceStoreRequest.php`** (teljes egészében elolvasva): `monthly_payment_statement_id` az intézményre szűkítve validált (`Rule::exists(...)->where(institution_id)`); **`provider` mező értéke kizárólag az intézmény ténylegesen beállított `invoicing_provider`-je lehet** (`Rule::in([$this->allowedProvider($institutionId)])`) — tehát egy manipulált kérés sem tud más szolgáltatót kikényszeríteni, ez már ma is helyesen véd. `payment_method`, `due_date`, `fulfillment_date`, `customer_name`, `billing_postcode/city/address` kötelezők; `customer_email`, `customer_tax_number`, `source_payment_id`, `note` opcionálisak.

### A.10 Adatbázis-séma — `institution_invoices` jelenlegi állapota

Alap-migráció (`2026_07_16_150000_create_institution_invoices_table.php`) + kiegészítések (`2026_08_19_...add_cancellation_fields...`, `2026_08_23_...add_institution_payment_id...`, `2026_08_24_...split_cancellation_invoices_and_add_billingo_sync_fields...` — mindegyik elolvasva). A jelenlegi oszlopkészlet (a modell `fillable`-jével megerősítve):

`id, institution_id, child_id, guardian_id, monthly_payment_statement_id, original_invoice_id, institution_payment_id, provider, document_type, provider_invoice_id, provider_original_invoice_id, invoice_number, status, issue_date, due_date, fulfillment_date, net_amount, vat_amount, gross_amount, currency, payment_method, customer_name, customer_email, customer_tax_number, billing_postcode, billing_city, billing_address, invoice_pdf_path, pdf_disk, pdf_downloaded_at, pdf_size, invoice_url, error_message, note, last_synced_at, sync_error_message, created_by, cancelled_by, cancelled_at, cancellation_reason, timestamps`.

Indexek: `unique(monthly_payment_statement_id)` **eltávolítva** (a split cancellation migráció explicit dropolja — logikus, hiszen egy statementhez most már eredeti + sztornó sor is tartozhat), helyette `unique(institution_id, provider, provider_invoice_id)`, valamint összetett indexek `(monthly_payment_statement_id, document_type)`, `(institution_id, document_type)`, `(original_invoice_id)`.

**Nincs `payment_reference` oszlop, nincs `initiated_by_type`/`initiated_by_user_id`/`parent_generated_at` jellegű mező** — ezek ténylegesen hiányoznak (ld. E. pont).

### A.11 `MonthlyPaymentStatement` — a régi, párhuzamos mezők státusza

A `MonthlyPaymentStatement` modellen **léteznek** saját, korábbi `invoice_number`, `invoice_provider`, `invoice_status`, `invoice_url`, `invoice_pdf_path`, `invoiced_at`, `payment_status`, `paid_at`, `payment_reference` mezők. **Ellenőrizve grep-pel a teljes staged fába**: ezeket **kizárólag** a régi `payment_obligations` admin modul (`PaymentObligationController.php` + `payment_obligations/index.blade.php` + `show.blade.php`) olvassa/írja — **az új Finance modul (`InstitutionInvoiceController`/`InstitutionInvoiceService`) és a szülői oldal ezeket sehol nem használja**, kizárólag a különálló `InstitutionInvoice` modellt. Ez egy **vestigiális, párhuzamos rendszer**: nem szabad összekeverni a kettőt, és nem szabad megszüntetni anélkül, hogy megvizsgálnánk, a `payment_obligations` admin nézet még aktívan használatban van-e.

### A.12 Szülői felület — meglévő számla-lista, DE NINCS generálás

`app/Http/Controllers/ParentPortal/ParentInvoiceController.php` + `app/Services/ParentPortal/ParentInvoicePageService.php` (mindkettő teljes egészében elolvasva), route-ok `routes/parent.php`-ban:
```
GET  /szulo/szamlak                    → parent.invoices           (ParentInvoiceController::index)
GET  /szulo/szamlak/{invoice}/letoltes → parent.invoices.download  (ParentInvoiceController::download)
```
Middleware: `auth`, `parent`. **Nincs POST route, nincs "számla elkészítése" gomb, nincs generálási akció** — a teljes felület **csak olvasás**: szűrhető lista (év szerint), soronkénti "Részletek" lenyíló panel, "PDF letöltése" és "Megtekintés" linkek. A `parent/invoices/index.blade.php` nézet (teljes egészében ellenőrizve) **semmilyen formot vagy gombot nem tartalmaz** a számla-előállításhoz, és **nem különbözteti meg vizuálisan** az eredeti és a sztornó bizonylatokat (nincs `document_type` alapú jelölés a nézetben).

**Jogosultság-ellenőrzés (IDOR-védelem) — MEGLÉVŐ, jó minta:** `ParentInvoicePageService::resolveScope()` a bejelentkezett szülő **aktív** gondviselő-sorait (`$user->guardians()->where('active', true)`), majd az azokhoz kapcsolt gyermekeket (`Child::whereHas('guardians', ...)`) gyűjti össze, és `visibleInvoicesQuery()` **kizárólag** ezekre a `guardian_id`/`child_id` értékekre szűrt lekérdezést épít. A letöltés (`downloadInvoiceDocument()`) `findVisibleInvoice()`-t hív, ami ugyanezt a scope-olt lekérdezést `whereKey($invoiceId)->firstOrFail()`-lel zárja le — **ha egy másik szülő számlájának ID-jét próbálnánk meg URL-ben, 404-et kapunk**, nem 403-at, de az adat semmiképp nem szivárog. **Ez egy közvetlenül újrafelhasználható, bizonyítottan helyes minta** az új generálási flow authorizációjához is (ld. C/K pont).

**HIBA-GYANÚS FELFEDEZÉS (nem az új Számlázz.hu-témához kötődik, de érinti a szülői PDF-letöltést, ezért ide tartozik):** `ParentInvoicePageService::downloadInvoiceDocument()` a PDF-et a **`public` diszkről** (`Storage::disk('public')->exists(...)` / `->download(...)`) próbálja letölteni. Ezzel szemben **minden jelenlegi számla-előállító kód** (`InstitutionInvoiceService::store()` 340. sor, `SzamlazzHuInvoiceProvider`, `BillingoInvoiceProvider::downloadPdf()`) a PDF-et a **`local` (privát) diszkre** menti, és az admin oldali letöltés (`InstitutionInvoiceController::download()`) is helyesen a `local` diszkről szolgálja ki. **Ha ez így van élesben is, a szülői "PDF letöltése" gomb minden, a jelenlegi rendszeren keresztül kiállított számlánál 404-et fog dobni**, mert a fájl fizikailag a `local`, nem a `public` diszken van. Ezt a felmérés keretében találtam, konkrét fájl-összevetéssel (nem feltételezés) — érdemes az élesben is leellenőrizni, és ha valóban hiba, az új Számlázz.hu-s szülői letöltő flow tervezésekor mindenképp **a `local` diszket kell használni**, ne ismételjük meg ezt a hibát.

### A.13 Banki átutalás — meglévő UI, de a "közlemény" jelenleg NEM egyedi azonosító

`resources/views/parent/partials/bank-transfer-box.blade.php` (teljes egészében elolvasva) — kész, elegáns, vágólapra-másolós UI (`navigator.clipboard.writeText`, `execCommand('copy')` fallback-kel) a kedvezményezett nevére, számlaszámra, összegre és közleményre, `InstitutionSetting`-ből táplálva (nem hardcode-olt).

A tényleges "közlemény" (`reference`) forrása: `ParentMonthlySettlementService::bankTransferInfo()` (624-686. sor, teljes egészében elolvasva):
```php
'reference' => trim($childNames.' - '.$month->locale('hu')->isoFormat('YYYY. MMMM')),
```
tehát **"Gyermek neve(i) - Hónap"** formátumú, **szabad szöveg, NEM egyedi, NEM DB-ben tárolt, NEM egyértelműen visszakövethető** egyetlen számlához/statementhez. (Split-modellnél a `componentReference()` egy sablon-string, ugyanilyen jellegű.) Van egy másik metódus is, `generateReference()` (834-841. sor):
```php
return sprintf('CSAL-%s-%s', $month->format('Ym'), Str::upper(Str::random(6)));
```
ez viszont **kizárólag a CIB bankkártyás fizetési szándékhoz** (`ParentMonthlySettlementPayment::reference`) használatos, **nem** az átutalásos közleményhez, és nincs egyediségi DB-index rákényszerítve (ellenőrizendő a `parent_monthly_settlement_payments` migrációban, amit nem olvastunk el részletesen — ez nyitott pont, ld. M).

**Következtetés a 4. pont kérdésére (egyedi banki hivatkozás):** a jelenlegi átutalásos "közlemény" **semmilyen formában nem alkalmas** admin-oldali automatikus/megbízható egyeztetésre — sem egyedi, sem adatbázisban rögzített, sem egy adott számlához programozottan visszakövethető. Ez valódi, pótlandó hiány (ld. E és C pont).

---

## B) Mi használható újra, MÓDOSÍTÁS NÉLKÜL

1. **`InstitutionSetting` modell és tábla** — a Billingo/Számlázz.hu párhuzamos mezőkészlet már ma is teljes, intézményenként külön konfigurálható (`invoicing_provider`, `szamlazz_hu_*` mezők, encrypt-elt agent-kulcs). Séma-módosítás nélkül alkalmas arra, hogy egy intézmény Számlázz.hu-t, egy másik Billingót használjon egyidejűleg.
2. **`SzamlazzHuInvoiceProvider::createInvoice()` és `cancelInvoice()`** — a tényleges API-hívási logika (XML-építés, HTTP hívás, válasz-feldolgozás, PDF-mentés, hibakezelés) kódszinten kész, csak a fent (A.2) listázott hiányosságokat kell pótolni, nem újraírni.
3. **`InstitutionInvoiceService::store()`/`cancel()`/`delete()` tranzakció- és zárolás-mintája** (`DB::transaction` + `lockForUpdate()` + duplikáció-ellenőrzés) — közvetlenül átvehető minta a szülői gomb race-condition elleni védelméhez is.
4. **`ParentInvoicePageService::resolveScope()`/`visibleInvoicesQuery()`/`findVisibleInvoice()` IDOR-védelmi minta** — a "csak a saját gyermekem" hozzáférés-szűkítés bizonyítottan jól működő, közvetlenül másolható/bővíthető minta a szülői gomb és a leendő "saját statement lekérdezése" logikájához.
5. **`resources/views/parent/partials/bank-transfer-box.blade.php`** — a vágólap-másolós UI komponens gyakorlatilag változtatás nélkül újrahasználható a "Számla elkészítése" gomb utáni banki adatok megjelenítéséhez, amennyiben a `reference` értékét egy valódi egyedi azonosítóra cseréljük (ld. C/E pont).
6. **`InstitutionInvoice` modell állapotgépe** (`draft/pending/issued/paid/overdue/cancelled/voided/failed`, `document_type` original/cancellation megkülönböztetés, `originalInvoice()`/`cancellationInvoice()` relációk) — architekturálisan alkalmas az új folyamatra, nem igényel átalakítást.
7. **`InstitutionPaymentComponentService::sumComponentPreviousBalance()` / `sumComponentInvoiceAdjustments()`** — a korábbi egyenleg és a tárgyidőszaki összeg szétválasztása komponens-szinten már létezik, felhasználható annak biztosítására, hogy az új számla valóban csak az aktuális havi részt tartalmazza.
8. **`InstitutionInvoiceStoreRequest`-ben a szolgáltató szerver-oldali kikényszerítése** (`Rule::in([$this->allowedProvider($institutionId)])`) — ugyanezt a mintát kell követni a szülői oldali kérésnél is.
9. **Admin oldali `InstitutionInvoiceController::download()`/`downloadCancellation()`** — a `local` diszkről történő helyes PDF-kiszolgálás mintája (a szülői oldali hibás `public` diszk helyett ezt kell követni, ld. A.12).

---

## C) Mit kell MÓDOSÍTANI

1. **`InstitutionInvoiceService::store()` (300. sor) és `buildPreview()` (844. sor)** — `$statement->total_payable` helyett `$statement->invoiceable_amount`-ot kell számlázni, hogy a számla kizárólag az aktuális havi összeget tartalmazza (spec 1. pont). **Ez az egyetlen legfontosabb, mindenre kiható módosítási pont.** Alaposan meg kell vizsgálni minden helyet, ahol `total_payable`-t "számlázott összegként" kezelünk, nehogy csak a kiállítás pillanatában térjünk el, de valahol máshol (pl. admin lista, riport) továbbra is a kombinált összeget higgyük számlázottnak.
2. **`InstitutionInvoiceCancelRequest`** — a `reason` mezőt `nullable`-ből `required`-re kell módosítani (spec 6. pont: kötelező indoklás).
3. **A Finance route-csoport middleware-e (`routes/web.php`)** — a `role:institution_admin` szűkítés helyett/mellé egy `institution_secretary` számára is elérhető, de permission-alapú kaput kell beépíteni a sztornó (és esetleg más) végpontokhoz — ld. H pont, ez a `User::hasPermission()` jelenlegi viselkedésének (A.8) figyelembevételével tervezendő.
4. **`SzamlazzHuInvoiceProvider`** — az e-számla flag hardcode `true`-ját a `szamlazz_hu_e_invoice_enabled` beállításra kell cserélni; a teszt/éles mód beállítást ténylegesen fel kell használni az API-hívásban (a Számlázz.hu XML Agent API-ban van erre dedikált mező); az ÁFA-kezelést könyvelői egyeztetés után esetleg paraméterezni kell (jelenleg mindenhol hardcode `AAM`).
5. **`ParentInvoicePageService::downloadInvoiceDocument()`** — ha a `public`/`local` diszk-eltérés (A.12) valóban hibát okoz élesben, ezt `local`-ra kell javítani, és a fájlt nem a nyilvános URL-en, hanem streamelve (ahogy az admin oldali controller teszi) kell kiszolgálni.
6. **`ParentMonthlySettlementService::bankTransferInfo()`** — a jelenlegi, nem egyedi `reference` string helyett egy valódi, egyedi, DB-ben tárolt azonosítót kell felhasználni, amint az az új számla-generáláshoz létrejön (ld. E pont).
7. **Admin pénzügyi lista (`InstitutionInvoiceController::index()` + hozzá tartozó blade)** — bővíteni kell a spec 5. pontjában felsorolt oszlopokkal/kereséssel (aki generálta, mikor, `payment_reference` szerinti keresés) — ez alapvetően a meglévő lista bővítése, nem új képernyő.

---

## D) Mit kell ÚJONNAN LÉTREHOZNI

1. **Szülői oldali "Számla elkészítése és fizetési adatok megjelenítése" gomb + POST route + controller-akció + service-metódus.** Jelenleg **semmi ilyen nem létezik** — sem route, sem controller-metódus, sem blade gomb (A.12). Ezt a `routes/parent.php` `auth`+`parent` middleware-es csoportjába kell felvenni, a `parent.monthly-settlements.store` (CIB kártyás fizetési szándék) route mellé, azonos mintát követve (CSRF automatikusan megvan a `web` middleware-csoport miatt).
2. **Egy új, a `InstitutionInvoiceService::store()`-hoz hasonló, de szülő-kontextusú service-metódus** (pl. `InstitutionInvoiceService::storeForParent()` vagy egy külön `ParentInvoiceGenerationService`), amely:
   - `MonthlyPaymentStatement::lockForUpdate()` + duplikáció-ellenőrzés (a meglévő mintát követve),
   - explicit ellenőrzi, hogy a statement a bejelentkezett szülő saját gyermekéhez tartozik-e (a `ParentInvoicePageService::resolveScope()` mintáját követve),
   - **szerver oldalon újraszámolja/validálja** az `invoiceable_amount`-ot (sose bízzon kliens felől érkező összegben),
   - 0 Ft-os kötelezettségnél elutasít,
   - a valós Számlázz.hu (vagy az intézmény beállított) szolgáltatóval hívja a kiállítást,
   - elmenti, ki (`parent`/adott `guardian_id`) és mikor generálta.
3. **Egyedi, DB-unique banki hivatkozás / `payment_reference` generátor** — ld. E pont, a döntést (A vs. B opció) ott részletezem.
4. **Admin oldali audit-napló bővítés számla-eseményekre** — `AuditLog::ACTION_INVOICE_ISSUED`, `ACTION_INVOICE_CANCELLED` (vagy hasonló) konstansok és a megfelelő `AuditLogger::log()` hívások az `InstitutionInvoiceService::store()`/`cancel()`-ban, mert jelenleg a számla-események nem kerülnek a központi `AuditLog`-ba (A.8).
5. **Admin oldali "ki generálta" oszlop/szűrés** — jelenleg a `created_by` mező tárolja a létrehozót, de ha a szülő is generálhat, ez a mező már ma is alkalmas rá (a `created_by` egy `User`, a szülő is `User`), **viszont** vizuálisan/szűrhetően meg kell különböztetni "admin generálta" vs. "szülő generálta" eseteket — ez az E pontban tárgyalt `initiated_by_type` jellegű mező feladata lenne.
6. **Sztornó jogosultsági kapu** — egy `cancel-invoices` (vagy hasonló nevű) permission-kulcs bevezetése a `permissions` JSON-be, és a route/controller szintű ellenőrzés kiegészítése — jelenleg ilyen egyáltalán nincs (A.8, H pont).
7. **Számlázz.hu-oldali PDF-újratöltés** és **dokumentum-lekérdezés** — ha az admin oldali "re-sync az API-ból" funkciót (spec 5. pont) Számlázz.hu-ra is szeretnénk, ezt a providerben és egy hozzá tartozó sync service-ben (a `BillingoInvoiceSyncService` mintájára, de a Számlázz.hu tényleges lekérdező API-jára építve) kell megírni — ez jelenleg egyáltalán nem létezik.
8. **Teszt csomag** a spec 11. pontjában felsorolt ~18+ szcenárióra (ld. L pont) — jelenleg a `tests/Feature/Finance/InstitutionInvoiceFeatureTest.php` létezik (49 KB, a legnagyobb tesztfájl a modulban), de ennek tartalmát ebben a felmérésben nem volt módunk elolvasni (ld. M pont, nyitott elem) — a meglévő tesztek kiegészítendők/felülvizsgálandók az új szülői flow-val.

---

## E) Adatbázis-változások

**Alapelv:** csak azt vezetjük be, ami ténylegesen hiányzik — a meglévő `institution_invoices` séma (A.10) és az `InstitutionSetting` séma (A.4) a legtöbb igényt már lefedi.

### E.1 Ténylegesen hiányzó, új oszlopok az `institution_invoices` táblán

| Oszlop (javasolt név) | Cél | Megjegyzés |
|---|---|---|
| `payment_reference` | Egyedi, banki átutaláshoz használható közlemény/hivatkozás | **KÖTELEZŐEN egyedi index** (`unique`), nullable amíg nincs kiállítva, admin kereshető legyen (spec 5., 9. pont) |
| `initiated_by_type` | `admin` / `parent` — ki indította a számla-generálást | enum-szerű string, admin lista szűréséhez/megjelenítéséhez |
| `initiated_by_user_id` | A generáló `User` — **valójában ez már létezik `created_by` néven!** | Nem kell duplikálni: a `created_by` mező jelenleg is a létrehozó `User`-t tárolja, mind admin, mind (jövőbeli) szülői kiállításnál felhasználható. Csak az `initiated_by_type` az, ami ténylegesen hiányzik a "ki generálta: admin vagy a szülő maga" megkülönböztetéshez. |
| `parent_generated_at` | Mikor generálta a szülő | **Megfontolandó, hogy szükséges-e külön mező**: a meglévő `created_at` időbélyeg + az új `initiated_by_type = 'parent'` együtt már megadja ugyanezt az információt. Csak akkor indokolt külön mező, ha a "szülő generálta" időpont elvi okokból eltérhetne a sor `created_at`-jétől (pl. aszinkron feldolgozás) — a jelenlegi tervezett szinkron flow-nál ez nem áll fenn, ezért **javaslat: NEM létrehozni, hanem `created_at` + `initiated_by_type` kombinációt használni**, elkerülve a felesleges duplikációt, amit a felhasználó is kifejezetten kért elkerülni. |

Tehát ténylegesen **két új oszlop** indokolt a `institution_invoices` táblán: `payment_reference` (unique) és `initiated_by_type`. Az `initiated_by_user_id` és a `parent_generated_at` **redundáns** lenne a meglévő `created_by`/`created_at` mellett.

### E.2 A 4. pont eldöntése: A) Számlázz.hu-számlaszám vs. B) egyedi Digifood-kód

A felhasználó kifejezetten kérte, hogy csak akkor javasoljak egyedi Digifood-formátumot (pl. "DF-2609-12345"), ha az valóban jobb.

- **A Számlázz.hu-számlaszám csak a sikeres kiállítás UTÁN áll rendelkezésre.** A banki adatokat viszont a specifikáció szerint már a gomb megnyomásakor, a kiállítással egy folyamatban meg kell jeleníteni a szülőnek — technikailag ez nem jelent problémát (a számlaszám a kiállítás válaszában megjön, utána azonnal megjeleníthető), DE:
  - Ha a szolgáltató (Számlázz.hu) API-hívása bármiért lassú/időszakosan elérhetetlen, a banki adatok megjelenítése is csúszik/blokkolódik, holott elvileg független folyamat is lehetne.
  - Ha a jövőben a `manual` provider (nincs API-hívás) is engedélyezett marad valamely intézménynél, ott **nincs** szolgáltatói számlaszám, tehát a banki hivatkozás rendszere nem támaszkodhat kizárólag erre.
  - A Számlázz.hu számlaszám formátuma az intézmény saját prefix-beállításától függ (`szamlazz_hu_invoice_prefix`), ami admin oldalon bármikor módosítható — egy banki közleménynek stabilabb, a rendszer által garantáltan kontrollált formátumra van szükség.
- **Javaslat: B) opció — Digifood-generált, rövid, egyedi azonosító**, a `generateReference()`-hoz hasonló, de DB-unique-indexelt mintával (pl. `DF-{ÉÉHH}-{5 jegyű sorszám/random}`), amely:
  - a `store()` tranzakción **belül**, a `lockForUpdate()` védelem alatt jön létre (elkerülve az ütközést),
  - **függetlenül** a szolgáltatótól (Billingo/Számlázz.hu/manual) mindig létezik,
  - egyértelműen visszakövethető: `payment_reference` alapján az admin lista/keresés közvetlenül az `InstitutionInvoice` sorra (és rajta keresztül a statementre/gyermekre/gondviselőre) vezet,
  - rövid, egy bankszámlakivonat "Közlemény" mezőjében is jól olvasható/gépelhető marad.
  
  A Számlázz.hu-számlaszámot emellett **továbbra is** el kell tárolni (`invoice_number` mező, ez már ma is megvan) — csak a banki hivatkozás célja lesz külön, garantáltan egyedi mező. **Ez könyvelői/jogi szempontból is egyszerűbb**: a bizonylat hivatalos azonosítója (számlaszám) és a technikai banki egyeztetési kód (payment_reference) szándékosan két külön dolog, nem kell a kettőt egymásra kényszeríteni.

### E.3 Egyéb, ellenőrzött, NEM hiányzó dolgok

- Az `institution_invoices` és `monthly_payment_statements`/`children`/`guardians`/`institutions` közti kapcsolatok már ma is helyesen ki vannak építve (idegenkulcsok + Eloquent relációk) — nincs teendő.
- Az `institution_payment_id` kapcsolat már létezik (2026_08_23-as migráció) — készpénzes befizetésből előkészített számla esetére. Ez a jövőbeli banki-utalásos, szülő-generálta folyamatra közvetlenül nem feltétlenül releváns (ott a fizetés a számla UTÁN történik), de a mező jelenléte nem zavaró.
- `AuditLog` táblához **nem** kell séma-módosítás — csak új `action` konstans-értékek bevezetése a meglévő `action` string oszlopba (ld. D.4).

---

## F) Szülői flow, lépésről lépésre (a felmérés alapján, MÉG NEM IMPLEMENTÁLVA)

1. A szülő megnyitja a `/szulo/havi-elszamolasok` (vagy a `/szulo/befizetesek`) oldalt, ahol a `ParentMonthlySettlementService`/`SettlementAmountPresenter` már ma is **külön** mutatja: "Aktuális havi fizetendő" (`invoiceable_amount`), "Korábbi tartozás"/"Korábbi túlfizetés" (`previous_balance`), és a "Fennmaradó túlfizetés" (`overpayment_amount`) — ez a hármas bontás **már ma is létezik a megjelenítési rétegben** (A.13/F. pont — ld. az agent-kutatás F. szekcióját), csak az elnevezés/figyelmeztető szöveg pontosítása és a "Kérjük, korábbi tartozását vagy túlfizetését személyesen rendezze az önkormányzatnál." szöveg hozzáadása szükséges a `previous_balance`/túlfizetés sor mellé.
2. A szülő megnyomja az új "Számla elkészítése és fizetési adatok megjelenítése" gombot (**ÚJ elem, ld. D.1**) — ez egy CSRF-védett POST kérést küld, amely a saját (scope-olt) statementjének azonosítóját viszi.
3. A szerver (**ÚJ service-metódus, ld. D.2**) újra ellenőrzi: a statement valóban a bejelentkezett szülő gyermekéhez tartozik-e; van-e már aktív eredeti számla hozzá (ha igen, elutasít vagy a meglévőt mutatja); az `invoiceable_amount` > 0-e; zárolja a sort (`lockForUpdate`) a race-condition ellen.
4. A szerver meghívja az intézményhez beállított szolgáltatót (Számlázz.hu/Billingo/manual — `InstitutionSetting::invoicing_provider` alapján, sose a kliens által küldött értékből), létrehozza az `InstitutionInvoice` sort **kizárólag az aktuális havi összeggel**, generál egy egyedi `payment_reference`-t (E.2), elmenti ki (`created_by`) és milyen módon (`initiated_by_type = 'parent'`) generálta.
5. Siker esetén a szülő azonnal látja: a banki adatokat (kedvezményezett, számlaszám, összeg, **az új egyedi `payment_reference`**) a meglévő `bank-transfer-box.blade.php` mintáján, egy kattintással másolható formában, valamint a számla PDF letöltési lehetőségét (a `local` diszkről helyesen kiszolgálva, ld. C.5).
6. Ha a szolgáltatói API-hívás hibázik, a felhasználó érthető hibaüzenetet kap, és **nem marad félkész/duplikált állapot** — ez a meglévő `store()` tranzakciós minta (B.3) természetes velejárója, amennyiben a hibakezelést az új metódusban is ugyanígy építjük fel.

---

## G) Admin flow, lépésről lépésre (meglévő elemek + bővítendők)

1. Admin megnyitja a Pénzügyek → Számlák listát (`InstitutionInvoiceController::index()`, meglévő) — **bővítendő** a spec 5. pontja szerinti oszlopokkal: gyermek, gondviselő, hónap, aktuális havi fizetendő, számlaszám, `payment_reference`, számla-státusz, fizetési státusz, kiállítás időpontja, **ki generálta** (admin/szülő — `initiated_by_type` + `created_by`), fizetett összeg, fizetés dátuma; keresés gyermek/gondviselő név, számlaszám, `payment_reference` szerint (**ÚJ**, jelenleg a lista szűrési képességét ebben a felmérésben nem vizsgáltuk részletesen — a `InstitutionPaymentController::query()`-hez hasonló mintát érdemes követni).
2. Admin PDF-et tölt le (`download()`, meglévő), sztornóz (`cancel()`, meglévő, de **kötelező indoklással bővítendő**, ld. C.2), újratölti a PDF-et Billingónál (`reloadPdf()`, meglévő) — **Számlázz.hu-nál ez jelenleg nem működik** (A.2), ezt kell pótolni, ha a re-sync funkció Számlázz.hu-ra is kell.
3. Admin kézi befizetést rögzít (`InstitutionPaymentController::store()`/`quickPay()`, meglévő, auditált) — ez a folyamat a szülői gomb bevezetésével **változatlan** marad, csak a `reference` mező tartalmának forrása bővül (a jövőben az admin az új, egyedi `payment_reference` alapján is könnyebben be tudja azonosítani, melyik statementhez tartozik egy beérkezett utalás — bár **automatikus** egyeztetés ezután sem lesz, csak könnyebb manuális egyeztetés).
4. Sztornózáshoz a jövőben a `cancel-invoices` (vagy hasonló) jogosultság szükséges (ÚJ, ld. D.6/H pont) — az `institution_admin`/`super_admin` továbbra is korlátozás nélkül hozzáfér (A.8 architekturális ténye miatt), az `institution_secretary` csak explicit megadott jogosultsággal.

---

## H) Sztornó és jogosultságok — részletes vizsgálat

**Ki sztornózhat MA:** bármely `institution_admin` (a route middleware `role:institution_admin` miatt) és `super_admin` (globálisan, minden `role:` middleware-t megkerül — ezt nem ellenőriztem közvetlenül a middleware kódjában ebben a szegmensben, de a `User::hasPermission()` és a projekt más pontjain látott minta alapján ez a bevett konvenció). Az `institution_secretary`-nek **jelenleg semmilyen hozzáférése nincs** a teljes Finance modulhoz.

**Külön sztornó-dokumentum készül-e:** **IGEN** — `InstitutionInvoiceService::cancel()` egy teljesen új `InstitutionInvoice` sort hoz létre (`document_type = DOCUMENT_TYPE_CANCELLATION`), **negált** összegekkel, `original_invoice_id`/`cancellation_invoice_id` relációval összekötve. Ez mind a Billingo, mind a Számlázz.hu providernél így működik (a szolgáltató saját sztornó-bizonylatot állít ki, ezt tükrözi a rendszer).

**Az eredeti számla változatlan marad-e:** **IGEN** — a `cancel()` metódus nem írja felül az eredeti sor összegeit/adatait, csak a `status`-t állítja (`STATUS_VOIDED`/`STATUS_CANCELLED` — a pontos átmenetet a modell state-machine-je definiálja) és a `cancelled_by`/`cancelled_at`/`cancellation_reason` mezőket tölti ki rajta.

**A pénzügyi egyenleg helyesen helyreáll-e:** ezt közvetlenül **nem volt módunk tesztelni** (ez implementáció/futtatás, nem statikus kódolvasás kérdése), de a negált összegek konvenciója (mind a service, mind a `BillingoInvoiceSyncService` háttér-szinkronjában következetesen alkalmazva) arra utal, hogy az összesítő lekérdezések (pl. `InstitutionPaymentComponentService::buildStatementSummaries()`) helyesen nullázzák ki a sztornózott számlát, **feltéve hogy** minden összesítő valóban a `document_type`/előjel-konvenciót követi — ezt implementáció előtt egy célzott teszttel (ld. L pont) érdemes megerősíteni, nem feltételezni.

**Látja-e a szülő mindkét dokumentumot:** a szülői lista (`parent/invoices/index.blade.php`) **nem különbözteti meg** vizuálisan az eredeti és sztornó sorokat (A.12) — technikailag valószínűleg mindkettő megjelenik a listában (a `ParentInvoicePageService::visibleInvoicesQuery()` nem szűr `document_type` szerint), de ez **nem megerősített, felhasználói szemmel tesztelendő** viselkedés, és mindenképp UX-fejlesztést igényel (pl. "Sztornózva" jelölés + az eredeti/sztornó pár összekapcsolt megjelenítése).

**PDF-ek megőrzése:** mindkét dokumentumtípushoz külön `invoice_pdf_path`/`pdf_disk` mező tartozik (mert két külön `InstitutionInvoice` sor), tehát mindkét PDF elvileg megmarad — Számlázz.hu-nál azonban, mivel a PDF-újratöltés nincs implementálva (A.2), ha az eredeti letöltés/mentés valamiért sikertelen volt, **nincs mód utólagos pótlásra** ezen a szolgáltatón keresztül.

**Audit log:** ld. A.8 — **jelenleg nincs központi audit-bejegyzés** a számla-sztornóról, csak a soron tárolt `cancelled_by`/`cancelled_at`/`cancellation_reason` mező. Ez pótlandó (D.4).

**Javasolt (a felhasználó saját javaslata alapján, VÉGLEGESÍTÉSRE VÁRÓ) jogosultsági modell:**
- A szülő **soha** nem kap sztornó-hozzáférést (ma sincs neki — a Finance modul teljes egészében admin-only route-csoportban van, a szülői route-csoport `routes/parent.php` fizikailag más fájl, nincs átfedés).
- `super_admin` és `institution_admin` marad a fő kör (ez a `User::hasPermission()` jelenlegi, feltétel nélküli `true` viselkedése miatt egyszerűen **nem korlátozható** külön permission-nel — ha ezt mégis korlátozni akarnánk, az a `hasPermission()` metódus módosítását igényelné, ami messzemenő hatású, ezért **NEM javasolt** ebben a körben).
- `institution_secretary` csak egy külön, explicit megadott permission-kulccsal (pl. `cancel-invoices`) kapjon hozzáférést — ez a `permissions` JSON-alapú ág mentén **már ma is működő mechanizmus**, csak a kulcsot és a route/controller-ellenőrzést kell bevezetni.

---

## I) Számlázz.hu API hiányosságok / TODO-lista

1. **PDF-újratöltés hiányzik** — `downloadExistingInvoicePdf()`/`downloadExistingCancellationPdf()` mindig `STATUS_FAILED`. A Számlázz.hu XML Agent API-nak van erre módja (a kiállításkor visszakapott XML-ből, vagy egy külön lekérdező hívással) — implementálandó, ha a "PDF elveszett, újra kell tölteni" admin funkciót Számlázz.hu-ra is szeretnénk.
2. **Nincs dokumentum-lista/lekérdező hívás** (`listDocuments()`/`getDocument()` hiányzik) — emiatt egy Billingo-mintájú "pull" szinkron jelenleg technikailag nem építhető Számlázz.hu-ra ebből a providerből kiindulva.
3. **Idempotencia/retry:** nincs automatikus újrapróbálkozás hálózati/időtúllépési hiba esetén — ha az API hívás timeout-ol, a hívó kód felelőssége eldönteni, mi történik (jelenleg `STATUS_FAILED`-del tér vissza, új próbálkozás egy új `store()` hívással történne, ami a duplikáció-védelem miatt — ha az előző próbálkozás `FAILED` maradt — valószínűleg újra megengedett, de ezt implementáció előtt explicit tesztelni kell).
4. **Timeout:** a HTTP hívásra explicit timeout nincs dokumentálva ebben a fájlban (ellentétben a Billingo providerrel, ahol `->timeout(30)` mindenhol jelen van) — **ellenőrizendő/pótlandó**.
5. **E-számla beállítás figyelmen kívül hagyva** — hardcode `true`.
6. **Teszt/éles mód beállítás figyelmen kívül hagyva** — a `szamlazz_hu_test_mode` mező létezik, de a provider nem használja.
7. **ÁFA-kezelés hardcode-olt** (`AAM`) — könyvelői megerősítést igényel, mielőtt ÁFA-köteles intézménynél élesben bevezethető.
8. **Számlaszám-prefix kezelés:** `szamlazz_hu_invoice_prefix` beállítás létezik és be van kötve — ez rendben van, nincs teendő.
9. **Bankátutalásos fizetési mód, teljesítési dátum, fizetési határidő, megjegyzés mező, vevőadatok:** mind helyesen be vannak kötve — nincs teendő.
10. **Biztonságos agent-kulcs tárolás:** `szamlazz_hu_agent_key` `encrypted` cast + `$hidden` — helyesen védett.
11. **NAV Online Számla jelentés:** a Számlázz.hu Agent szolgáltatás jellemzően automatikusan (a csomagtól függően) beküldi a NAV felé az adatszolgáltatást, DE ez a kódból **nem** deríthető ki (nincs erre vonatkozó API-paraméter/beállítás ebben a fájlban) — **ez a projekt tényleges Számlázz.hu-fiókjának csomagfeltételeitől függ, nem a kódtól**, ezért ezt a kérdést a J. pontban jogi/adminisztratív döntési pontként jelölöm, nem technikai hiányosságként.

---

## J) Jogi és könyvelési döntési pontok — **könyvelő/jogász által véglegesítendő**

A következő kérdésekre a kódból **nem** vezethető le megbízható válasz — ezeket kifejezetten **nem** próbáltam eldönteni, mert jogi/könyvelési szaktudást igényelnek:

1. **Ki a tényleges számlakibocsátó** — az önkormányzat vagy az intézmény? A `Institution` modellen vannak saját `billing_name`/`tax_number`/`kreta_code`/`szamlazz_partner_id` mezők, ami arra utal, hogy **intézményi szinten** van beállítva a kibocsátói identitás, de hogy ez jogilag helyes-e (önkormányzati fenntartású intézménynél ki a számla szerinti "eladó") — **könyvelő/jogász által véglegesítendő**.
2. **Kinek a Számlázz.hu-fiókja állítja ki a számlát** — az `InstitutionSetting.szamlazz_hu_agent_key` intézményenként külön van tárolva, tehát technikailag **intézményenként külön Számlázz.hu-fiók** használható — de hogy ez üzleti/számviteli szempontból a helyes felállás-e (egy közös önkormányzati fiók vs. intézményenkénti fiók), **könyvelő/jogász által véglegesítendő**.
3. **Ki a "vevő" a számlán** — a kód jelenleg egyértelműen a **gondviselőt** (`Guardian`) használja vevőként (`BillingProfile`→`Guardian` fallback-lánc, `customer_name`/`customer_tax_number`/`customer_email` mind a gondviselő adataiból töltődik) — ha ez jogilag helytelen lenne (pl. a gyermek nevére kellene szólnia a számlának), az egy tervezési döntés, amit **könyvelő/jogász által véglegesítendő**.
4. **Mikor keletkezik a fizetési kötelezettség vs. mikor a "teljesítés"** — a kódban `fulfillment_date` és `due_date` külön mezők, admin/szülő oldalon szabadon állíthatók a jelenlegi validáció szerint (`InstitutionInvoiceStoreRequest`: mindkettő `required date`, tartalmi összefüggés-ellenőrzés nélkül) — a helyes üzleti szabály (pl. teljesítés = az étkezési hónap utolsó napja, fizetési határidő = az azt követő X nap) **könyvelő/jogász által véglegesítendő**, és a validációba utólag beépítendő.
5. **A gomb megnyomásakor normál számla, díjbekérő vagy előlegszámla a helyes dokumentum?** **Ezt a kérdést kifejezetten nem próbáltam magam eldönteni.** Technikai tényként rögzítem: a jelenlegi kód (mindkét provider) kizárólag "normál számla" (`invoice`/`xmlszamla`) kiállítására épül — **díjbekérő vagy előlegszámla kiállítására jelenleg SEMMILYEN kódút nincs**, sem a Billingo, sem a Számlázz.hu providerben. Ha könyvelői döntés alapján a helyes gyakorlat az, hogy a szülő gombnyomása (ami az utalás **előtt** történik) egy díjbekérőt/előlegszámlát generáljon, és csak a tényleges beérkezett utalás után álljon ki normál számla, az egy **jelentős, mindkét providert érintő fejlesztési többletet** jelent (a Számlázz.hu Agent API-ban a díjbekérő/előlegszámla más XML-akció, nem ugyanaz, mint a normál számla). **Ez a döntés alapvetően meghatározza a D. és F. pontban vázolt flow-t, ezért ezt mindenképp könyvelő/jogász véglegesítse, mielőtt az implementáció elkezdődik.**
6. **A fizetési határidő pontos meghatározása** (hány nap, mitől számítva) — a `payment_due_day`/`billingo_due_days`/`szamlazz_hu_due_days` mezők admin-oldalon szabadon állíthatók, tartalmi/jogi korlát nincs a kódban — **könyvelő/jogász által véglegesítendő**.
7. **A szülő gombnyomása kizárólag technikai kiváltó ok** — jogilag az intézmény/önkormányzat marad a számla kibocsátója, a szülő csak egy UI-műveletet indít el. Ez fontos elvi keret, amit a rendszernek (pl. `initiated_by_type = 'parent'` mezővel, D.1) technikailag is dokumentálnia kell, de ez **nem változtat** azon, hogy a jogi felelősség/kibocsátói szerep az intézményé — ez összhangban van azzal, amit a kód ma is tükröz (a számla `institution_id`-hoz, nem `guardian_id`-hoz van elsődlegesen kötve).
8. **Ki jogosult jogilag sztornózni** — technikai javaslatot adtam (H. pont), de hogy ez összhangban van-e a számviteli/aláírási jogosultsági szabályokkal, **könyvelő/jogász által véglegesítendő**.
9. **Mi történik, ha egy havi elszámolást MÓDOSÍTANAK, miután már kiállítottak hozzá számlát** — ezt a kódban **nem találtam kezelve**: a `store()` duplikáció-védelme (ha van eredeti számla, elutasít) azt jelenti, hogy egy statement módosítása után a régi számla **változatlanul** megmarad, és a rendszer nem generál automatikusan sztornó+új számlát. Ez élesen ütközik a felhasználó elvárásával ("statement módosítás → kötelező sztornó + újrakiállítás") — **ez hiányzó funkció, amit a J. pontban jogi/folyamati döntésként is meg kell erősíteni** (pl. mikortól "zárt" egy statement, ki kezdeményezheti a módosítást), mielőtt a technikai megoldást (automatikus sztornó-trigger) megtervezzük.
10. **Túlfizetés automatikus beszámítása** — a kód **jelenleg ténylegesen automatikusan beszámítja** a fel nem használt jóváírásokat (`InstitutionPaymentComponentService::syncAllocationsForPayment()` → `TYPE_UNAPPLIED` → következő statementre alkalmazva) — ez **ellentmond** a felhasználó elvárásának, hogy a túlfizetés NE csökkentse automatikusan a következő havi számlát, ha a szülő személyesen szeretné rendezni. Ez egy **meglévő, aktív viselkedés**, aminek a megváltoztatása (vagy legalább opcionálissá tétele) jelentős, a jelenlegi fizetés-allokációs logikát érintő döntés — **könyvelő/jogász által véglegesítendő**, hogy ez szándékos üzleti szabály-e, vagy módosítandó.
11. **NAV Online Számla jelentés** — ld. I.11, ez a Számlázz.hu-fiók csomagfeltételeitől függ, nem a kódtól; adminisztratív egyeztetést igényel a Számlázz.hu-val/könyvelővel, nem kódmódosítást.

---

## K) Biztonsági kockázatok

1. **IDOR védelem a leendő szülői generáló route-on — TERVEZENDŐ, de van rá bizonyítottan jó minta.** A `ParentInvoicePageService::resolveScope()`/`visibleInvoicesQuery()` mintája (A.12) közvetlenül átvehető: az új generáló végpontnak **ugyanígy** kizárólag a bejelentkezett szülő aktív gondviselő-sorain keresztül elérhető gyermekek statementjeire szabad engednie a generálást — a statement ID-t **soha** nem szabad puszta `whereKey()`-jel, scope nélkül feloldani.
2. **CSRF védelem** — a `web` middleware-csoport (amiben a `routes/parent.php` is van) Laravel-alapból CSRF-védett, tehát ha az új POST route ebbe a csoportba kerül (ahogy a meglévő `parent.monthly-settlements.store` is), ez **automatikusan** biztosított — külön teendő nincs, de implementáció közben ellenőrizendő, hogy a form/JS ténylegesen küldi-e a tokent.
3. **Dupla kattintás / dupla beküldés** — a meglévő `store()` mintában (`lockForUpdate()` + `exists()` ellenőrzés egy tranzakción belül, B.3/A.5) ez már technikailag kezelt lenne, **feltéve hogy** az új szülői metódus ugyanezt a mintát követi. Kliens-oldali (gomb letiltása submit után) védelem **kiegészítésként** javasolt, de nem helyettesítheti a szerver-oldali zárolást.
4. **A `provider` mezőt sose a kliens határozza meg** — ez a meglévő `InstitutionInvoiceStoreRequest` mintája (A.9) szerint már ma is helyesen van kezelve az admin oldalon; az új szülői végpontnak **ugyanígy**, kizárólag az `InstitutionSetting::invoicing_provider`-ből kell olvasnia, sose a kérésből.
5. **A `public`/`local` diszk-eltérés (A.12) önmagában nem IDOR, de adatvédelmi kockázat, ha fordítva térne el** — jelenleg úgy tűnik, hogy inkább funkcionális hiba (letöltés nem működik), nem védelmi rés (mert a `public` diszken valószínűleg nincs is ott a fájl) — de ha valaha bármi PDF a `public` diszkre kerülne, az illetéktelen hozzáférést jelentene (a `public` diszk tartalma jellemzően közvetlen URL-en elérhető). **Javaslat: a PDF-ek végig kizárólag a `local` (privát) diszken maradjanak, streamelt letöltéssel**, ahogy az admin oldali kód ma is teszi.
6. **Számlaszám/`payment_reference` mint fájlnév** — mind a Billingo, mind a Számlázz.hu provider már ma is véd path traversal ellen (`sanitizeForFilePath()`), ezt a mintát az új `payment_reference` mező felhasználásakor (ha fájlnévben szerepelne) is követni kell.
7. **Titkos kulcsok** — `billingo_api_key`, `szamlazz_hu_agent_key`, `cib_secret_key` mind `encrypted` cast + `$hidden` a modellen — ez már ma is helyes, nincs teendő.
8. **Race condition a `payment_reference` egyediségén** — az egyedi generátort a `lockForUpdate()` tranzakción **belül**, DB-szintű `unique` index védelmével kell megírni (ütközés esetén retry, ne csak alkalmazás-szintű ellenőrzés).

---

## L) Tesztterv (MÉG NEM VÉGREHAJTVA — csak terv)

A felhasználó által kért minimum ~18 szcenárió, a jelen felmérés findingjeivel kiegészítve:

1. Szülő a saját statementjéhez sikeresen generál számlát.
2. Szülő NEM tud másik gyermek statementjéhez számlát generálni (IDOR teszt — a `resolveScope()` mintája alapján 404-et vagy 403-at kell kapnia, ez implementáció közben eldöntendő és tesztelendő).
3. 0 Ft-os (vagy negatív) `invoiceable_amount`-ra a generálás elutasításra kerül.
4. Ugyanahhoz a statementhez másodszor nem hozható létre eredeti számla (a meglévő `store()` duplikáció-védelem mintájának megfelelően).
5. Egyidejű (konkurens) kérés ugyanarra a statementre csak EGY számlát eredményez (`lockForUpdate()` teszt — pl. két párhuzamos requesttel).
6. A korábbi tartozás SOHA nem kerül bele az újonnan generált számla összegébe (`invoiceable_amount` vs. `total_payable` regressziós teszt — ez a legfontosabb, mert ez a jelenlegi kód hibás viselkedésének (A.5) explicit javítását ellenőrzi).
7. A túlfizetés nem csökkenti automatikusan az aktuális számlát (ez ütközik a jelenlegi `syncAllocationsForPayment()` viselkedésével — a teszt attól függ, hogyan dönt a J.10 pontban felvetett kérdés).
8. A `payment_reference` egyedi (DB-szintű unique constraint teszt, ütközés-kezeléssel).
9. Admin le tudja tölteni a PDF-et (meglévő funkció regressziós teszt).
10. Szülő a SAJÁT PDF-jét le tudja tölteni, de a másikét nem (a `public`/`local` diszk-hiba javítása utáni regressziós teszt is, ld. A.12/C.5).
11. Admin a megfelelő jogosultsággal sikeresen sztornóz.
12. Admin jogosultság NÉLKÜL 403-at kap sztornózáskor (az új permission-kapu tesztje, H. pont).
13. Szülő sztornózási kísérlete 403-at (vagy egyáltalán nem elérhető route-ot, 404-et) ad.
14. Az eredeti számla VÁLTOZATLAN marad sztornózás után (meglévő viselkedés regressziós teszt).
15. Sztornózáskor létrejön egy külön sztornó-dokumentum (meglévő viselkedés regressziós teszt).
16. Számlázz.hu API-hiba esetén nem marad félkész/inkonzisztens állapot (pl. `PENDING` státuszban ragadt, de a szolgáltatónál mégis kiállított bizonylat — ez különösen fontos teszt, mert a jelenlegi kódban nincs explicit "idempotency key" jellegű védelem a Számlázz.hu hívásban, ld. I.3).
17. Egy sikertelen próbálkozás után történő ismételt generálás NEM hoz létre duplikátumot (összefügg a 16. ponttal és az I.3 nyitott kérdéssel — implementáció előtt tisztázandó, hogyan viselkedik ilyenkor a duplikáció-ellenőrzés egy `FAILED` státuszú korábbi sorral szemben).
18. Statement utólagos módosítása után a régi számla állapota és a rendszer viselkedése a J.9 pontban meghozott döntésnek megfelelően helyes (ha lesz automatikus sztornó-trigger, annak tesztje).
19. **(kiegészítés)** A meglévő `tests/Feature/Finance/InstitutionInvoiceFeatureTest.php` (49 KB) tartalmát a jelen felmérés keretében **nem volt módunk elolvasni** — implementáció előtt mindenképp át kell nézni, nehogy az új flow megsértsen egy már ott tesztelt, jelenlegi (Billingo/admin-oldali) viselkedést.

---

## M) Érintett fájlok listája

### M.1 Ebben a felmérésben TELJES egészében elolvasott, releváns fájlok

```
app/Services/Invoicing/InvoiceProviderInterface.php            (halott kód)
app/Services/Invoicing/InvoiceProviderFactory.php               (halott kód)
app/Services/Invoicing/BillingoInvoiceProvider.php               (halott kód)
app/Services/Invoicing/SzamlazzHuInvoiceProvider.php             (halott kód)
app/Services/Invoicing/ManualInvoiceProvider.php                 (halott kód)
config/integrations.php
config/services.php
app/Models/InstitutionInvoice.php
app/Models/InstitutionPayment.php
app/Models/InstitutionPaymentAllocation.php
app/Models/InstitutionInvoiceSyncRun.php
app/Models/Institution.php
app/Models/InstitutionSetting.php
app/Models/PaymentObligation/MonthlyPaymentStatement.php
app/Models/ParentMonthlySettlementPayment.php
app/Models/ParentMonthlySettlementPaymentItem.php
app/Models/User.php
app/Models/AuditLog.php
app/Services/Finance/Providers/InvoiceProviderInterface.php
app/Services/Finance/Providers/InvoiceProviderPayload.php
app/Services/Finance/Providers/InvoiceProviderResult.php
app/Services/Finance/Providers/ManualInvoiceProvider.php
app/Services/Finance/Providers/SzamlazzHuInvoiceProvider.php
app/Services/Finance/Providers/BillingoInvoiceProvider.php
app/Services/Finance/InstitutionInvoiceService.php
app/Http/Controllers/Dashboard/InstitutionAdmin/Finance/InstitutionInvoiceController.php
app/Http/Controllers/ParentPortal/ParentInvoiceController.php
app/Http/Controllers/ParentPortal/ParentPaymentController.php
app/Http/Controllers/ParentPortal/ParentMonthlySettlementController.php
app/Services/ParentPortal/ParentInvoicePageService.php
app/Services/ParentPortal/ParentMonthlySettlementService.php
resources/views/parent/partials/bank-transfer-box.blade.php
resources/views/parent/invoices/index.blade.php
app/Http/Requests/Dashboard/InstitutionAdmin/Finance/InstitutionInvoiceCancelRequest.php
app/Http/Requests/Dashboard/InstitutionAdmin/Finance/InstitutionInvoiceStoreRequest.php
app/Http/Middleware/CheckRole.php
routes/web.php (Pénzügyek route-csoport)
routes/parent.php (teljes fájl)
database/migrations/2026_07_16_150000_create_institution_invoices_table.php
database/migrations/2026_08_24_090000_split_cancellation_invoices_and_add_billingo_sync_fields.php
```

### M.2 Ebben a felmérésben, egy célzott kutató-lekérdezés (subagent) által feldolgozott, de általam közvetlenül nem soronként ellenőrzött fájlok — a bennük található állítások ezért kicsit alacsonyabb bizonyossággal kezelendők, implementáció előtt érdemes közvetlenül is átnézni:

```
app/Http/Controllers/Dashboard/InstitutionAdmin/Finance/InstitutionPaymentController.php
app/Http/Controllers/Dashboard/InstitutionAdmin/Finance/InstitutionDebtController.php
app/Services/Finance/InstitutionDebtService.php
app/Services/Finance/InstitutionPaymentComponentService.php
app/Services/Finance/BillingoInvoiceSyncService.php
app/Services/ParentPortal/ParentPaymentPageService.php
```

### M.3 Listázva/hivatkozva, de a felmérésben EGYÁLTALÁN NEM olvasott, implementáció előtt mindenképp átnézendő fájlok

```
tests/Feature/Finance/InstitutionInvoiceFeatureTest.php                          (49 KB, a legnagyobb, feltehetően legátfogóbb teszt)
database/migrations/2026_08_19_100000_add_cancellation_fields_to_institution_invoices_table.php
database/migrations/2026_08_23_190000_add_institution_payment_id_to_institution_invoices_table.php
database/migrations/2026_08_27_130000_add_bank_transfer_fields_to_institution_settings_table.php
database/migrations/2026_07_31_120000_create_parent_monthly_settlement_payments_tables.php
database/migrations/2026_08_17_140000_drop_szamlazz_api_key_from_institutions.php
database/migrations/2026_07_15_130000_add_billing_flags_to_institution_settings_table.php
database/migrations/2026_07_16_120000_create_institution_payments_table.php
database/migrations/2026_08_29_091000_create_institution_payment_component_rates_table.php
database/migrations/2026_08_29_094000_create_institution_payment_allocations_table.php
Az intézményi admin "Beállítások → Számlázás és fizetés" controllere/nézete (route: dashboard.institution.settings.invoicing.edit — a controller fájlját nem sikerült ebben a felmérésben azonosítani/elolvasni)
Permission-kezelő admin felület (institution_secretary egyedi jogosultságainak beállítására szolgáló UI, ha létezik ilyen)
A `PaymentObligationCalculatorService`-jellegű, a `total_payable`/`invoiceable_amount`/`previous_balance` mezőket ténylegesen KISZÁMOLÓ szolgáltatás (a `SettlementAmountPresenter` csak formázza ezeket — a tényleges számítás forrása nem volt része ennek a felmérésnek)
```

**Fontos figyelmeztetés:** az implementáció megkezdése előtt mindenképp el kell olvasni legalább a `tests/Feature/Finance/InstitutionInvoiceFeatureTest.php` fájlt és a fent felsorolt, még nem látott migrációkat, mert ezek tartalmazhatnak olyan, jelenleg elvárt viselkedést, amit a jelen felmérés nem tudott figyelembe venni.

---

## N) Javasolt megvalósítási sorrend

1. **J. pont (jogi/könyvelési döntések) lezárása könyvelővel/jogásszal** — mindenekelőtt az 5. pont (normál számla vs. díjbekérő/előlegszámla) és a 10. pont (túlfizetés automatikus beszámítása) kérdése, mert ezek alapjaiban meghatározzák a technikai megoldást.
2. **`InstitutionInvoiceService::store()`/`buildPreview()` javítása**: `total_payable` → `invoiceable_amount` (C.1) — ezt Billingo-ra és manual providerre is le kell tesztelni, hogy semmi ne törjön.
3. **`payment_reference` mező + generátor + unique index** (E.1, E.2) bevezetése, beleértve a `ParentMonthlySettlementService::bankTransferInfo()` frissítését, hogy ezt használja.
4. **Sztornó jogosultsági kapu + kötelező indoklás** (C.2, D.6, H.) bevezetése — ez az admin oldalt módosítja, a meglévő Billingo-folyamatot nem töri.
5. **Audit-log bővítés számla-eseményekre** (D.4).
6. **A szülői "Számla elkészítése" gomb + route + controller + service-metódus** megépítése (D.1, D.2, F. pont) — csak az 1-5. lépések után, mert ezekre épít (helyes összeg, egyedi hivatkozás, audit).
7. **A `public`/`local` diszk-hiba kijavítása** a szülői PDF-letöltésnél (C.5) — ezt érdemes minél előbb, akár a fenti sorrendtől függetlenül, önállóan is javítani, mert jelenlegi éles hibának tűnik.
8. **Admin lista bővítése** (C.7, G.1) az új mezőkkel/kereséssel.
9. **Számlázz.hu provider hiányosságainak pótlása** (I. pont: e-számla flag, teszt/éles mód, timeout, opcionálisan PDF-újratöltés és lekérdező API) — ezek nélkül is működik az alap kiállítás/sztornó, de éles, ÁFA-releváns, hosszú távú használat előtt ajánlott.
10. **Teszt csomag** (L. pont) végrehajtása, beleértve a meglévő `InstitutionInvoiceFeatureTest.php` felülvizsgálatát is.

---

**BIZTONSÁGOSAN MEGVALÓSÍTHATÓ: IGEN** — feltéve, hogy a J. pontban felsorolt jogi/könyvelési kérdések (elsősorban az 5. és a 10.) tisztázásra kerülnek könyvelővel/jogásszal az implementáció megkezdése ELŐTT, és az N. pontban javasolt sorrend betartásra kerül (különös tekintettel arra, hogy a `total_payable`→`invoiceable_amount` javítás és a jogosultsági kapu bevezetése megelőzze a szülői gomb megépítését).

**KÖVETKEZŐ LÉPÉS:** a J. pontban felsorolt nyitott jogi/könyvelési kérdések átbeszélése a könyvelővel/jogásszal — ennek eredménye alapján pontosítható, hogy a szülő gombnyomása normál számlát, díjbekérőt vagy előlegszámlát váltson-e ki, és hogy a túlfizetés automatikus beszámítása maradjon-e a jelenlegi (aktív) viselkedés. Ezután, a döntések birtokában, javaslom az N. pont 2-3. lépésének (a `total_payable`→`invoiceable_amount` javítás és a `payment_reference` bevezetése) elindítását — ezek a legkisebb kockázatú, ugyanakkor a legnagyobb hatású, a meglévő Billingo-folyamatot nem törő módosítások.
