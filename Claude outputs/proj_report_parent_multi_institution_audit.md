# Szülői portál – több intézményhez tartozó gyermekek audit jelentése

**Dátum:** 2026-09-07
**Projekt:** digifood_csavoly (C:\wamp64\www\digifood_csavoly)
**Vizsgált kérdés:** Egy szülő (Guardian/User) tud-e egyetlen fiókból kezelni több, EGYMÁSTÓL ELTÉRŐ intézményhez (institution_id) tartozó saját gyermeket, és a hozzáférés kizárólag a szülő↔gyermek kapcsolaton alapul-e (nem az intézmény azonosítón).

---

## 1. Találtam-e hibát, ami jelenleg egy intézményre korlátozná a szülőt?

**Nem.** A kódbázis részletes, soronkénti átvizsgálása és a teszt-futtatások alapján a szülői portál **jelenleg helyesen, kizárólag a `guardian` ↔ `child` kapcsolat alapján** engedélyezi a hozzáférést, NEM az intézmény-azonosító alapján. Sehol nem találtam `institutions()->first()`, `currentInstitution`/`selectedInstitution`-alapú, vagy más módon egyetlen intézményre szűkítő logikát a szülői (`ParentPortal`) rétegben.

Ez nem véletlen: a kódban több helyen kifejezett, magyar nyelvű kommentek és egy már meglévő, kiterjedt regressziós tesztfájl (`ParentMultiInstitutionAccessFeatureTest.php`) tanúskodik arról, hogy ezt a problémakört egy korábbi fejlesztési kör már célzottan kezelte és lefedte teszttel.

**Nem kellett tehát alkalmazáskódot javítani.** Az egyetlen változtatás egy ÚJ, a pontosan megadott teszt-forgatókönyvet (Guardian A / Child1+Child2, Guardian B / Child3) lefedő regressziós teszt hozzáadása – lásd 3. és 5. pont.

---

## 2. Mely fájlokat vizsgáltam át (a teljesség igényével)

**Modellek / adatmodell:**
- `app/Models/Guardian.php`, `app/Models/Child.php`, `app/Models/User.php`, `app/Models/Institution.php`

**Middleware / jogosultság:**
- `app/Http/Middleware/EnsureParentAccess.php`
- `app/Http/Middleware/EnsureInstitutionContext.php` (megerősítve: ez KIZÁRÓLAG az admin/intézményi kontextushoz kötött, a szülői route-csoportra nincs alkalmazva)
- `app/Http/Middleware/CheckRole.php`
- `app/Support/AdminInstitutionContext.php` (megerősítve: külön, admin-only mechanizmus, a `dashboard.selected_institution_id` session-kulccsal – nem szivárog be a szülői oldalra)

**Route-ok:**
- `routes/parent.php`, `bootstrap/app.php`

**Controllerek (`app/Http/Controllers/ParentPortal/`):**
- `ParentChildController.php`, `ParentDashboardController.php`, `ParentMealCancellationController.php`, `ParentMenuChoiceController.php`, `ParentMenuController.php`, `ParentMonthlySettlementController.php`, `ParentPaymentController.php`, `ParentInvoiceController.php`, `ParentAccountController.php`, `Auth/ParentAuthenticatedSessionController.php`, `Auth/ParentAccountActivationController.php`

**Service-ek (`app/Services/ParentPortal/`):**
- `ParentGuardianService.php`, `ParentAccountService.php`, `ParentAccountActivationService.php`, `ParentPaymentPageService.php`, `ParentInvoicePageService.php`, `ParentMonthlySettlementService.php`, `ParentInvoiceInitiationService.php`

**Fizetés / számlázás (kiemelt vizsgálat):**
- `app/Services/Finance/InstitutionInvoiceService.php` (`createForParent()`)
- `app/Http/Requests/ParentPortal/StoreParentInvoiceRequest.php`

**Blade nézetek:**
- `resources/views/parent/dashboard.blade.php`, `parent/children/index.blade.php` + `partials/child-overview-card.blade.php`, `parent/meal-cancellations/index.blade.php`, `parent/menu-choices/index.blade.php`, `parent/monthly-settlements/index.blade.php`, `parent/invoices/index.blade.php`

**Adatbázis migrációk:**
- `guardians`, `child_guardian`, `billing_profiles`, `billing_profile_child` tábláinak migrációi (egyedi kényszerek ellenőrzése)

**Meglévő és újonnan futtatott tesztek:**
- `tests/Feature/Parent/*.php` (mind az 5 meglévő fájl), `tests/Feature/Finance/ParentInvoiceInitiationFeatureTest.php`

---

## 3. Mit módosítottam

**Alkalmazáskód: SEMMIT.** Nem volt olyan hiba, amit javítani kellett volna.

**Új fájl (teszt):**
- `tests/Feature/Parent/ParentCrossInstitutionRegressionTest.php` — ez egy vadonatúj regressziós tesztfájl, amely PONTOSAN az elvárt forgatókönyvet fedi le (lásd 5. pont). Ez a fájl már fel van írva a gépedre, ugyanabba a mappába, ahol a többi teszt van.

Semmilyen más fájlt nem írtam felül vagy módosítottam a gépeden.

---

## 4. Mely `institution_id` szűrők maradtak meg – és miért helyesek

Az `institution_id` a kódban **sokszor** előfordul a szülői rétegben is, de minden esetben **az adott GYERMEK saját intézményének** kiválasztására szolgál, nem egy "kiválasztott" vagy "első" intézmény szerinti globális korlátozásra. Néhány kulcsfontosságú, szándékosan megtartott példa:

- **`ParentMealCancellationController::classCancellationMapForChildren()`** — a gyerekeket `institution_id` szerint csoportosítja, hogy egy A intézményben törölt osztály/nap NE hasson ki egy másik intézményben lévő gyermekre. Ez helyes elkülönítés, nem korlátozás.
- **`ParentMenuChoiceController::index()`** és **`ParentMenuController::accessibleInstitutionIds()`** — a menüket/menüválasztást intézményenként csoportosítva jelenítik meg, illetve a HOZZÁFÉRHETŐ intézmény-azonosítók HALMAZÁT (nem egyetlen értéket) származtatják a szülő saját gyermekeiből.
- **`ParentMonthlySettlementService::isPaymentEnabled()` / `bankTransferInfo()`** — ez egy **tudatos biztonsági korlátozás**, nem hiba: ha egy szülő gyermekei EGYSZERRE több intézményhez tartoznak egy adott hónapban, az összevont bankkártyás fizetés / átutalási tájékoztató le van tiltva, mert egy tranzakció csak EGY intézmény bankszámlájára/terminálra irányítható. Ehelyett a felület figyelmeztető üzenetet ad, és intézményenként külön kell rendezni az összeget. Ez a te projektedben (ahol egyébként nincs is bekapcsolva a kártyás fizetés, csak a szamlazz.hu-s számlázás) különösen indokolt védelem, és NEM szabad eltávolítani.
- **`InstitutionInvoiceService::createForParent()`** — az `$institution` paramétert mindig az adott `MonthlyPaymentStatement`/gyermek saját intézményéből származtatja, és egy `abort_if($statement->institution_id !== $institution->id, 403)` védőellenőrzést tartalmaz — ez konzisztencia-védelem, nem hozzáférés-korlátozás.
- **`Child::booted()`** — új gyermek létrehozásakor a saját intézményén belül választ alapértelmezett kedvezménytípust — ugyancsak helyes, gyermek-szintű logika.

Ezeket a szűrőket **nem távolítottam el és nem is szabad eltávolítani**, mert ezek biztosítják az intézményi adatelkülönítést (díjak, menük, étkezési szabályok, bankszámlaszám, számlázási beállítások), amit a feladat kifejezetten megkövetel.

---

## 5. Milyen teszteket készítettem / futtattam

### 5.1 Meglévő, már a projektben lévő tesztek (lefuttatva változtatás nélkül)

| Fájl | Eredmény |
|---|---|
| `tests/Feature/Parent/ParentMultiInstitutionAccessFeatureTest.php` | ✅ 7/7 PASS |
| `tests/Feature/Parent/ParentAccountActivationFeatureTest.php` | ✅ 10/10 PASS |
| `tests/Feature/Parent/ParentAccountFeatureTest.php` | ✅ 5/5 PASS |
| `tests/Feature/Parent/ParentMealCancellationFeatureTest.php` | ✅ 12/12 PASS |
| `tests/Feature/Parent/ParentMenuFeatureTest.php` | ✅ 2/2 PASS |
| `tests/Feature/Parent/ParentPortalFeatureTest.php` | ✅ 48/48 PASS |
| `tests/Feature/Finance/ParentInvoiceInitiationFeatureTest.php` (szülői rész) | ✅ 19/19 PASS |
| `tests/Feature/Finance/ParentInvoiceInitiationFeatureTest.php` (admin keresés, 2 teszt) | ⚠️ 2 FAIL – de ez **admin dashboard** hiányzó nézetfájl miatt van (a teszt-futtató környezetben nem másoltam át az admin dashboard teljes nézetkészletét, mivel az kívül esik a szülői portál auditjának hatókörén). **Ez NEM a szülői többintézményes hozzáféréssel kapcsolatos, és nem éles hiba** – az admin oldal ettől függetlenül változatlan maradt. |

**Összesen: 103/105 releváns (szülői) teszt PASS**, a fennmaradó 2 sikertelen teszt egy admin-only funkciót érint, ami a jelen audit hatókörén kívül esik.

### 5.2 Újonnan írt teszt — pontosan a kért forgatókönyv

**Fájl:** `tests/Feature/Parent/ParentCrossInstitutionRegressionTest.php` (felírva a gépedre)

Forgatókönyv, szó szerint a kérésed szerint:
- **Guardian A** → Child1 (Institution 1), Child2 (Institution 2)
- **Guardian B** → Child3 (Institution 2)

| Teszt | Elvárás | Eredmény |
|---|---|---|
| `test_guardian_a_sees_both_own_children_across_institutions_on_dashboard_and_children_page` | A látja Child1-et ÉS Child2-t a vezérlőpulton és a "Gyermekeim" listában | ✅ PASS |
| `test_guardian_a_can_manage_meal_cancellation_for_both_own_children_with_each_institutions_own_rules` | A tud étkezést lemondani Child1-nek ÉS Child2-nek is, mindegyik a SAJÁT intézménye szabályai szerint (eltérő határidőkkel tesztelve) | ✅ PASS |
| `test_guardian_a_sees_monthly_settlements_for_both_own_children` | A látja mindkét gyermek havi elszámolását egyszerre | ✅ PASS |
| `test_guardian_a_cannot_access_guardian_b_child_via_url_manipulation` | A NEM éri el Child3-at (Guardian B gyermeke) URL/ID-manipulációval → 404 | ✅ PASS |
| `test_guardian_a_cannot_cancel_meal_for_guardian_b_child_via_id_manipulation` | A NEM tud étkezést lemondani Child3-nak `child_id` becsempészésével → validációs hiba, 0 rekord létrejötte | ✅ PASS |
| `test_guardian_a_cannot_mix_own_child_with_guardian_b_child_in_one_cancellation_request` | Ha A egy kérésben Child1-et ÉS Child3-at is beküldi, a TELJES kérés elutasításra kerül (a sajátja sem megy át) | ✅ PASS |

**6/6 PASS.**

---

## 6. Összesített PASS/FAIL eredmény

```
Meglévő szülői tesztek:              103 PASS / 2 FAIL (admin-only, hatókörön kívül)
Új célzott regressziós teszt (6 db):   6 PASS / 0 FAIL
────────────────────────────────────────────────────
Összesen (szülői hatókörben):        109 PASS / 0 FAIL
```

A pontosan kért forgatókönyv (Guardian A lát mindent, Guardian B gyermekéhez A-nak nincs hozzáférése, sem URL-lel, sem ID-manipulációval) **teljes mértékben teljesül a jelenlegi kódban.**

---

## 7. Teljes lista a módosított/új fájlokról – mit tölts fel

**Csak EGY fájlt kell feltölteni**, ez már a helyén van a gépeden (nem kell külön másolnod):

```
tests/Feature/Parent/ParentCrossInstitutionRegressionTest.php
```

Ez a fájl **kizárólag tesztkód**, önmagában nem futtatódik le éles környezetben (csak `php artisan test` / CI során), így akár egyből fel is töltheted a szerverre a többi fájl mellé kockázat nélkül. Semmilyen más fájl (controller, service, model, view, migráció) nem változott.

**Javasolt következő lépés éles oldalon:** ha van CI/teszt-futtatás a szerveren, futtasd le a `php artisan test tests/Feature/Parent` parancsot a feltöltés után, hogy a saját adatbázis-konfigurációddal is megerősítsd az eredményt.
