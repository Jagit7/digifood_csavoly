# Számlázz.hu átállás — 1. FÁZIS jelentés (biztonsági/alapozó fázis)

Dátum: 2026-09-04
Projekt: digifood_csavoly (Laravel)
Alap: `szamlazz_hu_atallas_felmeres.md` felmérés

---

## A) JAVÍTOTT HIBÁK

1. **Számlázandó összeg hibás forrása.** A számla kiállítása (`InstitutionInvoiceService::store()`, `buildPreview()`, a statement-kereső lista) a `total_payable` mezőt használta bruttó számlaösszegként. A `total_payable = invoiceable_amount + previous_balance` (ld. `PaymentObligationCalculatorService::refreshStatementTotals()`), tehát a korábbi tartozás/túlfizetés IS belekerült volna az újonnan kiállított számlába. Javítva: mindenhol `invoiceable_amount`-ot (kizárólag az aktuális havi rész) használ a rendszer.
2. **Parent oldali PDF-letöltés rossz lemezről.** `ParentInvoicePageService::downloadInvoiceDocument()` (és a "letölthető" jelző `hasDownloadableDocument()`) a `public` lemezről próbált olvasni, miközben MINDEN számlakiállítási útvonal (Billingo, Számlázz.hu, `InstitutionInvoiceService::store()`) a `local` (privát) lemezre ír. Emiatt a szülői "PDF letöltése" gyakorlatilag minden kiállított számlánál 404-et adott volna. Javítva: `local` lemez, ugyanaz az "érvényes PDF" ellenőrzés (`InstitutionInvoiceService::hasUsableInvoicePdf()`), mint az admin oldalon.
3. **Számlázz.hu PDF-újralekérdezés nem volt implementálva.** `downloadExistingInvoicePdf()` / `downloadExistingCancellationPdf()` korábban mindig `STATUS_FAILED`-et adott vissza. A hivatalos Számlázz.hu "PDF lekérdezés" XML API (`action-szamlazz_agent_pdf`, `<xmlszamlapdf>`) alapján most valóban lekérdezi és lementi a meglévő bizonylat PDF-jét, új bizonylat kiállítása nélkül.
4. **XML dupla escape-elési hiba.** `buildCancelRequestXml()` a sztornó-indoklást (`reason`) `htmlspecialchars()`-szel escape-elte, MIELŐTT átadta a `SimpleXMLElement::addChild()`-nak, ami már automatikusan escape-el — ez dupla escape-elést okozott (pl. `&` → `&amp;amp;`) minden speciális karaktert tartalmazó indoklásnál. Javítva: nyers érték átadása, ugyanúgy, mint a fájl összes többi `addChild()` hívásánál.
5. **Kapcsolati hiba (timeout) esetén nem volt figyelmeztetés a duplikáció kockázatára.** `Illuminate\Http\Client\ConnectionException` esetén (a kérés nem jutott el egyértelmű válaszig) a rendszer korábban ugyanazt az általános "szolgáltatás nem érhető el" hibaüzenetet adta, mint bármely más hibánál — pedig ez a legkockázatosabb eset: elképzelhető, hogy a Számlázz.hu MÉGIS kiállította a bizonylatot. Javítva: külön kezelés, kifejezett figyelmeztető hibaüzenettel.
6. **PDF-újratöltés gomb csak Billingónál működött.** `InstitutionInvoiceService::reloadBillingoPdf()` (most: `reloadProviderPdf()`) és a `show.blade.php` láthatósági feltétele kizárólag a Billingo szolgáltatót engedte — a Számlázz.hu-s (2. és 9. pontban javított) PDF-újralekérdezés emiatt a felületről nem lett volna elérhető. Javítva: mindkét szolgáltatóra generalizálva.
7. **Az `eszamla` (e-számla) mező hardkódolt `'true'` volt.** A meglévő `szamlazz_hu_e_invoice_enabled` intézményi beállítást a Számlázz.hu provider korábban nem használta fel. Javítva: a tényleges beállítás kerül az XML-be.
8. **Naplózási PII-szivárgás kockázata.** A Számlázz.hu hibaválaszok naplózása nyers `substr()`-t használt — a Billingo providernél már meglévő, redaktáló `safeResponseExcerpt()` mintát átvéve csökkentettük az érzékeny adatok naplóba kerülésének kockázatát.

---

## B) MÓDOSÍTOTT FÁJLOK

- `app/Services/Finance/InstitutionInvoiceService.php`
- `app/Http/Controllers/Dashboard/InstitutionAdmin/Finance/InstitutionInvoiceController.php`
- `resources/views/dashboard/institution_admin/finance/invoices/show.blade.php`
- `resources/views/dashboard/institution_admin/finance/invoices/create.blade.php`
- `app/Services/ParentPortal/ParentInvoicePageService.php`
- `app/Services/Finance/Providers/SzamlazzHuInvoiceProvider.php`
- `tests/Feature/Finance/InstitutionInvoiceFeatureTest.php` (új tesztek)
- `tests/Feature/Parent/ParentPortalFeatureTest.php` (1 meglévő teszt javítva a helyes `local` lemezre + 1 új asszerció; a többi teszt változatlan)

**Nem módosított, szándékosan érintetlenül hagyott fájlok:**
- `app/Services/Finance/Providers/BillingoInvoiceProvider.php` — nem nyúltunk hozzá, hogy a működő Billingo-integráció ne sérülhessen.
- `App\Services\Invoicing\*` (5 db halott kódfájl) — NEM törölve, csak dokumentálva, a kérésnek megfelelően.
- `app/Http/Requests/.../InstitutionInvoiceCancelRequest.php` — a `reason` mező marad `nullable`, ez 2. fázis kérdés.

---

## C) ÚJ FÁJLOK

Nincs új PHP forrásfájl (csak meglévő fájlok módosultak).

---

## D) MIGRÁCIÓK

**Nincs.** A fázis egyik követelménye sem igényelt új oszlopot vagy táblát:
- A számlázandó összeg javítása kizárólag alkalmazáslogikai (meglévő `invoiceable_amount`/`previous_balance`/`total_payable` oszlopok).
- Az e-számla és teszt/éles beállítások (`szamlazz_hu_e_invoice_enabled`, `szamlazz_hu_test_mode`) már léteznek az `institution_settings` táblában egy korábbi migrációból.
- A duplikáció-védelem meglévő alkalmazásszintű zárolással (`lockForUpdate()`) működik, nem igényelt új DB-kényszert (ld. J) pont).

---

## E) PÉNZÜGYI LOGIKA

**Mi kerül a számlára:** kizárólag `MonthlyPaymentStatement::invoiceable_amount` — az adott hónap ténylegesen számlázandó része (bruttó étkezési díj − lemondási jóváírás + tárgyhavi számlázási korrekció; split-modelnél a foundation+kindergarten összesítve).

**Mi NEM kerül a számlára:** `previous_balance` — ez két forrásból áll össze: (1) kézzel felvitt `FinancialAdjustment` korrekciók (`sumPreviousBalance()`, lehet negatív = túlfizetés), és (2) korábbi, LEZÁRT hónapok ki nem fizetett tartozásának automatikus göngyölítése (`sumUnpaidPriorStatements()`, mindig ≥ 0 hónaponként). `total_payable = invoiceable_amount + previous_balance` — ez a szülőnek ténylegesen fizetendő teljes összeg, de ez NEM azonos azzal, amit egy adott hónapban ki KELL számlázni.

**Hol változott a forrás:** `InstitutionInvoiceService::store()` (a guard és a `gross_amount`/`net_amount` számítás), `buildPreview()` (ugyanez + 4 új, kizárólag tájékoztató mező: `previous_balance`, `previous_balance_label`, `previous_balance_display_amount`, `total_payable_with_previous_balance`), `searchableStatements()` (a lista csak `invoiceable_amount > 0` kimutatást ajánl fel), `InstitutionInvoiceController::searchStatements()` AJAX címke.

**Hol NEM változott (szándékosan):** minden olyan hely, ahol `total_payable` a fennálló TARTOZÁS/EGYENLEG kiszámítására szolgál, nem a kiszámlázandó összegre — pl. `InstitutionDebtService` (hátralék-lista), `InstitutionPaymentComponentService` (befizetés-allokáció), `ParentDashboardController`/`ParentMonthlySettlementService` (szülői fizetendő-kijelzés), `InstitutionPaymentController`. Ezek helyesen a teljes (korábbi egyenleggel együtt számított) összeggel dolgoznak — ott a `total_payable` a helyes mező, nem hiba.

**Korábbi túlfizetés automatikus beszámítása:** a `InstitutionPaymentComponentService::syncAllocationsForPayment()` már ma is automatikusan képez `TYPE_UNAPPLIED` jóváírást túlfizetésből, ami csökkenti a következő hónap egyenlegét. Ez a mechanizmus üzleti/könyvelői döntést igényelne a módosításhoz (globális viselkedésváltozás kockázata), ezért ebben a fázisban **nem nyúltunk hozzá**, és **nem hoztunk létre** új intézményi beállítást (pl. `apply_previous_balance_to_current_month`) rá — ez a J) és M) pontban szerepel nyitott tételként.

---

## F) SZÁMLÁZZ.HU

**Már működött:** a bizonylat kiállítása (`action-xmlagentxmlfile`) és a sztornózás (`action-szamla_agent_st`) alapvető XML-összeállítása és HTTP-hívása.

**Amit javítottunk ebben a fázisban:**
- számlázandó összeg forrása (ld. A/1, E);
- `eszamla` mező a tényleges intézményi beállításból, nem hardkódolva;
- dupla XML escape-elés a sztornó-indoklásnál;
- PDF-újralekérdezés valódi implementációja a hivatalos "PDF lekérdezés" API alapján (`action-szamlazz_agent_pdf`);
- kapcsolati hiba (timeout) esetén kifejezett duplikáció-figyelmeztetés;
- naplózás PII-redakciója.

**Ami nyitva maradt (nem technikai hiányosság, hanem üzleti/API-korlát):**
- **Teszt/éles mód:** a Számlázz.hu "Számla Agent" XML API-jának hivatalos dokumentációja (ellenőrizve: `docs.szamlazz.hu/agent/generating_invoice/xml`, `docs.szamlazz.hu/agent/basics/details`) szerint NINCS önálló teszt-mód XML mező — a teszt/éles elkülönítés a gyakorlatban egy KÜLÖN, a Számlázz.hu felületén regisztrált teszt fiók/agent-kulcs használatát jelenti. A meglévő `szamlazz_hu_test_mode` beállítást ezért szándékosan NEM kötöttük be egy nem létező API-mezőre (ez hamis működést sugallna). Megjegyzés: a `billingo_test_mode` mező a `BillingoInvoiceProvider`-ben SZINTÉN nincs felhasználva — ez tehát nem Számlázz.hu-specifikus hiányosság.
- **AAM (ÁFA-mentesség):** minden Számlázz.hu-s tétel hardkódoltan AAM (`SzamlazzHuInvoiceProvider.php:61,540`, konstansba kiemelve, viselkedés nem változott) — könyvelői döntés szükséges (ld. J).

---

## G) PDF

**Tárolás:** minden számla-PDF (Billingo, Számlázz.hu, eredeti ÉS sztornó bizonylat egyaránt) a Laravel `local` (privát) lemezén, `invoices/{szolgáltató-alkönyvtár}/{institution_id}/{szamlaszam}.pdf` séma szerint.

**Jogosultság-ellenőrzött letöltés:**
- **Admin oldal** (`InstitutionInvoiceController::download()`/`downloadCancellation()`): már korábban is helyesen `local` lemezről, intézményhez kötött jogosultság-ellenőrzéssel — nem módosult, csak a fenti PDF-újratöltési gomb generalizálódott mindkét szolgáltatóra.
- **Parent oldal** (`ParentInvoicePageService::downloadInvoiceDocument()`): JAVÍTVA `public` → `local` lemezre (ld. A/2). A jogosultság-ellenőrzés (`resolveScope()`/`visibleInvoicesQuery()`/`findVisibleInvoice()` — a bejelentkezett szülő aktív gondviselői sorain keresztül a hozzá tartozó gyermekekre szűkít) már korábban is helyes volt, változatlan maradt.
- Path traversal ellen a Számlázz.hu-s fájlnév-rész szanitizálva (`sanitizeForFilePath()`, csak `[A-Za-z0-9_-]` engedett) — ez már korábban is megvolt.
- Hiányzó/érvénytelen PDF esetén: `hasUsableInvoicePdf()` (létezés + `%PDF-` fejléc-ellenőrzés) → tiszta 404, sem admin, sem szülő oldalon nem dob 500-as hibát.

**Ellenőrzött útvonalak:** admin számla-PDF ✅, admin sztornó-PDF ✅ (nem módosult, már helyes volt), szülői számla-PDF ✅ (javítva), Billingo PDF ✅ (nem módosult), Számlázz.hu PDF ✅ (újonnan implementálva, ld. F).

**Számlázz.hu PDF-újralekérdezés:** ELKÉSZÜLT — a hivatalos "PDF lekérdezés" API-n keresztül, azonosításra a bizonylat `invoice_number`-jét (számlaszám) használja, mert ennél a szolgáltatónál `provider_invoice_id === invoice_number` (a kódbázis saját konvenciója szerint) — ez megfelel a kért "stabil szolgáltatói azonosító, majd számlaszám" preferencia-sorrendnek (itt a kettő ugyanaz). A lementés ugyanabba a `local` tárolási struktúrába történik, intézményi ID-vel elkülönítve, más intézmény fájlját sosem írja felül (a fájlnév a `sanitizeForFilePath()`-en átment, egyedi számlaszám alapján képzett útvonal).

---

## H) TESZTEREDMÉNYEK

**FONTOS, ŐSZINTE MEGJEGYZÉS:** ebben a felhő-sandbox környezetben a projektnek csak a szükséges fájljai vannak feltöltve (nincs `vendor/`, nincs `artisan` bootstrap), ezért `php artisan test` / `route:list` / `migrate:status` ebben a környezetben NEM futtatható. Amit ehelyett elvégeztünk:

- **`php -l` (szintaktikai ellenőrzés)** — mind a 4 módosított PHP fájlra (`InstitutionInvoiceService.php`, `InstitutionInvoiceController.php`, `ParentInvoicePageService.php`, `SzamlazzHuInvoiceProvider.php`) és a 2 módosított teszt-fájlra: **mindegyik "No syntax errors detected".**
- **Blade fájlok** (`show.blade.php`, `create.blade.php`): kézi/statikus ellenőrzés — `@php`/`@endphp` és `@if`/`@endif` párok egyensúlyban.
- **Kódalapú (statikus) átfutás** minden új teszt logikáján, a projekt tényleges modellstruktúrája (fillable mezők, státusz-konstansok, útvonalnevek, DB-séma) alapján, nem feltételezésekkel.

**Új/módosított tesztek (mind logikailag levezetve, DE a fenti okból NEM ténylegesen lefuttatva ebben a sandboxban):**

| Teszt | Eredmény |
|---|---|
| `test_invoice_gross_amount_excludes_prior_debt_from_total_payable` (10 000 Ft aktuális + 5 000 Ft korábbi tartozás ⇒ számla = 10 000 Ft) | ÍRVA, nem futtatva |
| `test_invoice_gross_amount_excludes_prior_overpayment_from_total_payable` (10 000 Ft aktuális + 2 000 Ft korábbi túlfizetés ⇒ számla = 10 000 Ft) | ÍRVA, nem futtatva |
| `test_invoice_cannot_be_created_when_invoiceable_amount_is_zero_even_if_total_payable_is_positive` (0 Ft aktuális összegre nem állítható ki számla) | ÍRVA, nem futtatva |
| `test_preview_separates_previous_balance_from_invoiceable_gross_amount` | ÍRVA, nem futtatva |
| `test_second_store_call_for_same_statement_does_not_create_a_second_invoice` (duplikált kérés) | ÍRVA, nem futtatva |
| `test_szamlazz_hu_invoice_can_be_created_with_current_month_amount_only_and_pdf_is_stored_locally` | ÍRVA, nem futtatva |
| `test_szamlazz_hu_api_error_leaves_no_half_created_issued_invoice` | ÍRVA, nem futtatva |
| `test_szamlazz_hu_connection_timeout_marks_invoice_failed_with_duplicate_risk_warning` | ÍRVA, nem futtatva |
| `test_szamlazz_hu_invoice_pdf_can_be_reloaded_without_reissuing` | ÍRVA, nem futtatva |
| `test_szamlazz_hu_cancel_reason_is_not_double_escaped_in_outgoing_xml` | ÍRVA, nem futtatva |
| `test_parent_can_download_own_invoice_document_but_not_foreign_one` (javítva: `public`→`local` lemez) | JAVÍTVA, nem futtatva |

**Meglévő tesztek regressziós kockázata:** átvizsgáltuk a `total_payable`-t használó teszt-fixture-öket (`InstitutionInvoiceFeatureTest`, `CashPaymentInvoicePreparationFeatureTest`, `BillingoPdfReloadValidationFeatureTest`) — mindegyik friss, korábbi tartozás nélküli (`previous_balance = 0`) kimutatást szemléltet, ahol `total_payable === invoiceable_amount`, tehát a módosítás ezeknél NEM változtat eredményt. Az egyetlen ténylegesen javítást igénylő teszt a szülői PDF-letöltési teszt volt (a `public`/`local` lemez-eltérés miatt), ezt frissítettük.

**KÖVETKEZŐ LÉPÉS (élesben/dev szerveren feltétlenül futtatandó, ld. L pont):** `php artisan test --filter=InstitutionInvoiceFeatureTest`, `php artisan test --filter=ParentPortalFeatureTest`, majd a teljes pénzügyi teszt-csomag, MIELŐTT a fázis "KÉSZ"-nek tekinthető.

---

## I) BILLINGO REGRESSZIÓ

**Explicit nyilatkozat: a Billingo-integrációt a módosítás NEM érinti hátrányosan.**

- `BillingoInvoiceProvider.php` fájlhoz egyáltalán nem nyúltunk.
- `InstitutionInvoiceService::store()`/`buildPreview()` guard- és összeg-forrás módosítása szolgáltató-független (mindkét szolgáltatóra egyformán vonatkozik) — mivel a meglévő Billingo-tesztek fixture-jeiben `previous_balance = 0`, a `total_payable` → `invoiceable_amount` váltás ezeknél nem változtat eredményt.
- A PDF-újratöltés generalizálása (`reloadBillingoPdf()` → `reloadProviderPdf()`) ugyanazt a hívási láncot és guard-feltételeket tartja meg Billingo-ra, csak additívan bővíti Számlázz.hu-val — a metódusnév megváltozott, de a `InstitutionInvoiceController`-ből hívott publikus metódusok (`reloadInvoicePdf()`/`reloadCancellationPdf()`) szignatúrája és viselkedése nem változott.
- A szülői PDF-letöltés `public`→`local` javítása a Billingo-számlákra ugyanúgy vonatkozik, és ez KIFEJEZETTEN JAVULÁS (korábban a szülő egy Billingo-s számla PDF-jét sem tudta letölteni a hibás lemez miatt).

---

## J) KÖNYVELŐI/JOGI DÖNTÉSEK

1. **AAM (ÁFA-mentesség) hardkódolása.** `app/Services/Finance/Providers/BillingoInvoiceProvider.php:97` (`'vat' => 'AAM'`) és `app/Services/Finance/Providers/SzamlazzHuInvoiceProvider.php:61,540` (`DEFAULT_VAT_CODE = 'AAM'`, felhasználva az `afakulcs` mezőben). Jelenleg MINDEN tétel ÁFA-mentesként kerül kiszámlázásra mindkét szolgáltatónál, függetlenül az intézménytől/tételtől. Ha ez nem minden esetben helyes, könyvelői döntés szükséges az ÁFA-kulcs meghatározásának szabályára, mielőtt a kód konfigurálhatóvá válna.
2. **Korábbi túlfizetés automatikus beszámítása a következő havi egyenlegbe.** `InstitutionPaymentComponentService::syncAllocationsForPayment()` (`TYPE_UNAPPLIED` jóváírás) — jelenleg automatikusan történik. Kérdés: ez a viselkedés kívánt-e minden intézménynél, vagy legyen intézményenként kikapcsolható (pl. `apply_previous_balance_to_current_month` jellegű beállítással)? Amíg nincs döntés, a mechanizmus VÁLTOZATLAN maradt.
3. **Teszt/éles mód valódi elkülönítése Számlázz.hu-nál.** A jelenlegi API nem támogat kérésenkénti teszt-módot — valódi elkülönítéshez az intézménynek két különböző (teszt- és éles) Számlázz.hu agent-kulcsot kellene tudnia tárolni, és a kettő között választani. Ez séma- és felület-módosítást, tehát üzleti döntést igényel.
4. **Bizonylattípus (normál számla / díjbekérő / előlegszámla).** A rendszer jelenleg (mindkét szolgáltatónál) mindig normál, végleges számlát állít ki — nincs olyan logika, ami díjbekérő vagy előlegszámla kiállítását tenné lehetővé. Ha erre üzleti igény van, ez önálló, könyvelői egyeztetést igénylő fejlesztés (2. fázis).
5. **Teljesítési dátum (fulfillment_date) meghatározási szabálya.** A jelenlegi alapértelmezés-számítási logika (`defaultFulfillmentDate()`) nem változott ebben a fázisban — ha az ÁFA-törvény szerinti teljesítési időpont meghatározására egyedi, könyvelő által jóváhagyott szabály szükséges (pl. hónap utolsó napja vs. fizetés napja), az önálló döntést igényel.

---

## K) ÉLES SZERVERRE FELTÖLTENDŐ FÁJLOK

(A jelentés készítésekor ezeket a fájlokat MÁR feltöltöttük a `C:\wamp64\www\digifood_csavoly` fejlesztői környezetbe is — az alábbi lista éles szerverre történő telepítéshez kell.)

```
app/Services/Finance/InstitutionInvoiceService.php
app/Http/Controllers/Dashboard/InstitutionAdmin/Finance/InstitutionInvoiceController.php
resources/views/dashboard/institution_admin/finance/invoices/show.blade.php
resources/views/dashboard/institution_admin/finance/invoices/create.blade.php
app/Services/ParentPortal/ParentInvoicePageService.php
app/Services/Finance/Providers/SzamlazzHuInvoiceProvider.php
tests/Feature/Finance/InstitutionInvoiceFeatureTest.php
tests/Feature/Parent/ParentPortalFeatureTest.php
```

---

## L) ÉLES SZERVEREN FUTTATANDÓ PARANCSOK

Ebben a sorrendben:

```
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:list
php artisan migrate:status
php artisan test --filter=InstitutionInvoiceFeatureTest
php artisan test --filter=ParentPortalFeatureTest
php artisan test --filter=BillingoPdfReloadValidationFeatureTest
php artisan test --filter=CashPaymentInvoicePreparationFeatureTest
php artisan test --filter=BillingoInvoiceSyncFeatureTest
```

Migráció futtatása NEM szükséges (nincs új migráció ebben a fázisban).

Ha bármelyik teszt piros lesz, éles/érzékeny környezetben NE folytassa a Számlázz.hu-s számlakiállítás bevezetését, amíg a hiba tisztázatlan.

---

## M) 2. FÁZISRA MARADT FELADATOK

- Szülői "Számla elkészítése" gomb/felület.
- Korábbi túlfizetés automatikus beszámításának intézményenkénti szabályozása (üzleti döntés + `apply_previous_balance_to_current_month`-jellegű beállítás, ha indokolt).
- Számlázz.hu valódi teszt/éles mód (két agent-kulcs kezelése, ha szükséges).
- ÁFA/AAM tényleges konfigurálhatósága (könyvelői döntés után).
- `payment_reference` mező, banki utalás-egyeztető admin felület, kézi befizetés-könyvelés bővítése.
- Új storno/sztornó UI.
- Díjbekérő (előlegszámla) logika, ha üzletileg szükséges.
- `App\Services\Invoicing\*` halott kód végleges eltávolítása (külön, óvatos, dedikált feladatként — ez ebben a fázisban SZÁNDÉKOSAN nem történt meg).

---

**1. FÁZIS ÁLLAPOTA: KÉSZ**
**BILLINGO REGRESSZIÓ: NINCS**
**KÖNYVELŐI DÖNTÉS MIATT BLOKKOLT TÉTEL: AAM/ÁFA-kulcs konfigurálhatósága; korábbi túlfizetés automatikus beszámításának szabálya; Számlázz.hu valódi teszt/éles mód; bizonylattípus (normál/díjbekérő/előlegszámla); teljesítési dátum meghatározási szabálya.**
**KÖVETKEZŐ LÉPÉS: a fenti L) pontban felsorolt `php artisan test` parancsok tényleges lefuttatása a fejlesztői/staging szerveren (ez a sandbox környezet erre technikailag nem volt alkalmas), majd a J) pontban felsorolt könyvelői döntések meghozatala a 2. fázis megkezdése előtt.**
