<?php

namespace App\Support\Finance;

use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionSetting;
use Illuminate\Support\Collection;

class InstitutionFinanceHelpContent
{
    public function build(
        Institution $institution,
        InstitutionSetting $setting,
        ?InstitutionMealPackage $defaultMealPackage,
        Collection $discountTypes,
        array $invoiceProviderOptions,
        array $cardPaymentProviderOptions,
        array $paymentMethodOptions
    ): array {
        $discountNames = $discountTypes->pluck('name')->filter()->values()->all();
        $discountSummary = $discountNames === []
            ? 'A rendszer a gyermekhez rögzített százalékos kedvezményt alkalmazza. Ha nincs kedvezmény hozzárendelve, a havi kimutatás hibát jelez.'
            : 'A rendszer a gyermekhez rögzített százalékos kedvezményt alkalmazza. Az intézménynél jelenleg elérhető aktív kedvezménytípusok: '.implode(', ', $discountNames).'.';

        $invoiceProviderLabel = $this->providerLabel(
            $setting->invoicing_provider,
            $invoiceProviderOptions,
            [InstitutionSetting::INVOICING_PROVIDER_MANUAL => 'Kézi']
        );
        $cardProviderLabel = $this->providerLabel($setting->card_payment_provider, $cardPaymentProviderOptions);
        $invoiceProviderState = $this->invoiceProviderState($setting);
        $defaultPackageText = $defaultMealPackage?->name
            ? 'Az intézmény jelenlegi aktív alapértelmezett menücsomagja: '.$defaultMealPackage->name.'.'
            : 'Jelenleg nincs aktív alapértelmezett menücsomag kijelölve.';

        return [
            'sections' => [
                ['id' => 'folyamat', 'label' => 'A pénzügyi folyamat'],
                ['id' => 'szabalyok', 'label' => 'Részletes szabályok'],
                ['id' => 'tudnivalok', 'label' => 'Fontos tudnivalók'],
                ['id' => 'problemak', 'label' => 'Gyakori problémák'],
                ['id' => 'beallitasok', 'label' => 'Intézményi beállítások'],
            ],
            'timeline_items' => [
                [
                    'step' => '1',
                    'badge' => 'primary',
                    'title' => 'Étkezési alapadatok beállítása',
                    'text' => 'A havi számításhoz a rendszer csak azokat az aktív gyermekeket veszi figyelembe, akikhez van az adott időszakot érintő étkezési beállítás. A napi díjhoz szükség van érvényes étkezési árakra, valamint arra, hogy a gyermek intézményi alapértelmezett, konkrét menücsomagos vagy egyedi étkezési beállítással rendelkezzen. '.$defaultPackageText,
                ],
                [
                    'step' => '2',
                    'badge' => 'success',
                    'title' => 'A számítási időszak kiválasztása',
                    'text' => 'A Fizetési kötelezettségek oldalon hónap szerint készül a számítás. A rendszer a kiválasztott hónap minden napját végignézi, és gyermekenként menti a napi státuszt és a fizetendő összeget.',
                ],
                [
                    'step' => '3',
                    'badge' => 'info',
                    'title' => 'A számlázható étkezési napok meghatározása',
                    'text' => 'A hétvégi napok alapból nem számítanak fizetendő étkezési napnak. Szombat akkor kerül be a számításba, ha külön munkanapként van rögzítve. Az iskolai szünetek és az osztályszintű lemondások szintén 0 Ft-os napot eredményeznek.',
                ],
                [
                    'step' => '4',
                    'badge' => 'warning',
                    'title' => 'Lemondások és kieső napok feldolgozása',
                    'text' => 'A rendszer figyelembe veszi az egyéni lemondásokat, az ismétlődő lemondási szabályokat, az osztályszintű lemondásokat, az iskolai szüneteket és a rögzített munkanapokat. Az egyéni lemondás státusza attól függ, hogy a rögzítés a hónaphoz tartozó határnap előtt vagy után történt.',
                ],
                [
                    'step' => '5',
                    'badge' => 'danger',
                    'title' => 'Napi étkezési díj kiszámítása',
                    'text' => 'A napi alapösszeg az adott napra érvényes, menücsomaghoz vagy egyedi beállításhoz tartozó étkezési típusok áraiból áll össze. Ha bármelyik szükséges ár hiányzik, a nap hibásként jelenik meg, és a havi kimutatásban is hiba keletkezik.',
                ],
                [
                    'step' => '6',
                    'badge' => 'primary',
                    'title' => 'Kedvezmények alkalmazása',
                    'text' => $discountSummary,
                ],
                [
                    'step' => '7',
                    'badge' => 'success',
                    'title' => 'Havi összesítés és ellenőrzés',
                    'text' => 'A napi tételekből összeáll a havi étkezési összeg, majd ehhez hozzáadódnak a korrekciók és a korábbi egyenlegek. A lezárási ellenőrzés külön számolja a hiányzó menücsomagokat, kedvezményeket és árakat.',
                ],
                [
                    'step' => '8',
                    'badge' => 'dark',
                    'title' => 'A hónap lezárása',
                    'text' => 'Lezárni csak hibamentes hónapot lehet. Lezáráskor a tervezet és ellenőrzött státuszú havi kimutatások lezárt állapotba kerülnek, rögzül a lezárás időpontja és a lezáró felhasználó. Lezárt hónap nem számolható újra, és a napi kézi módosítás sem engedett.',
                ],
                [
                    'step' => '9',
                    'badge' => 'warning',
                    'title' => 'A hónap újranyitása',
                    'text' => 'Újranyitni csak lezárt vagy részben lezárt hónapot lehet, indoklás megadásával. Az újranyitás visszaállítja az érintett havi kimutatásokat tervezet állapotba, törli a lezárási adatokat, és naplózza az újranyitás idejét, felhasználóját és okát.',
                ],
                [
                    'step' => '10',
                    'badge' => 'info',
                    'title' => 'Befizetések és tartozások kezelése',
                    'text' => 'A befizetések külön nyilvántartásban rögzíthetők, és kapcsolhatók havi fizetési kötelezettséghez. A Tartozások modul csak azokat a tételeket mutatja, ahol a fennmaradó összeg még pozitív. Ha van már befizetés, de maradt hátralék, a rendszer részben fizetett jelzést is megjelenít.',
                ],
                [
                    'step' => '11',
                    'badge' => 'primary',
                    'title' => 'Számlázás',
                    'text' => 'Számla akkor hozható létre, ha a kötelezettség összege pozitív, még nincs hozzá számla, a számlázás engedélyezett, a kiválasztott szolgáltató beállítása teljes, és a számlázási adatok hiánytalanok. A támogatott szolgáltatók: '.implode(', ', array_unique(array_merge(['Kézi'], array_values($invoiceProviderOptions)))).'. A jelenlegi szolgáltatói állapot: '.$invoiceProviderState,
                ],
                [
                    'step' => '12',
                    'badge' => 'success',
                    'title' => 'Exportálás és ellenőrzés',
                    'text' => 'Az exportok csak kimutatást készítenek, nem módosítják a rendszerben tárolt pénzügyi adatokat. Jelenleg elérhető külön export a fizetési kötelezettségekről, befizetésekről, tartozásokról, számlákról és havi összesítőkről, valamint a havi kötelezettség oldalon egy részletes havi lista export és gyermekenkénti részletező export is.',
                ],
            ],
            'rules' => [
                ['key' => 'children', 'title' => 'Mely gyermekek kerülnek bele a havi számításba?', 'text' => 'A havi számításba azok az aktív gyermekek kerülnek be, akik az adott intézményhez tartoznak, és van olyan étkezési beállításuk, amely időben átfedi a kiválasztott hónapot. A rendszer nem egyszerű névsorból dolgozik, hanem csak azoknak készít havi kimutatást, akiknél ténylegesen van étkezési beállítás.'],
                ['key' => 'package', 'title' => 'Hogyan választja ki a rendszer a gyermek menücsomagját?', 'text' => 'A rendszer mindig a napra érvényes legfrissebb étkezési beállítást használja. Ha a gyermek intézményi alapértelmezett módon étkezik, az aktuális aktív alapértelmezett menücsomagból számol. Ha konkrét menücsomag vagy egyedi tétellista van beállítva, akkor abból áll össze a napi ár.'],
                ['key' => 'daily-price', 'title' => 'Hogyan számítja ki a rendszer a napi árat?', 'text' => 'A napi ár az adott napra érvényes étkezési típusárak összege. A rendszer minden szükséges étkezési típusnál azt az árat keresi, amelynek érvényessége lefedi az adott napot. Ha bármelyik szükséges ár hiányzik, a nap hibásként kerül mentésre.'],
                ['key' => 'price-changes', 'title' => 'Hogyan kezeli az árváltozásokat?', 'text' => 'Árváltozáskor a rendszer nem egyetlen aktuális árat használ, hanem napra pontosan az adott dátumra érvényes árbeállítást. Ezért a korábbi időszakok elszámolása csak akkor marad pontos, ha az új árat új érvényességi időszakkal rögzítik, és a régi ár megmarad a saját időszakára.'],
                ['key' => 'non-billable-days', 'title' => 'Mely napok nem számlázhatók?', 'text' => 'Nem lesz fizetendő a hétvége, az iskolai szünet, az osztályszintű lemondás, az időben rögzített egyéni lemondás, valamint az a nap is, amikor a gyermeknek nincs aktív étkezési beállítása. Szombat csak akkor számít fizetendő napnak, ha külön munkanapként van rögzítve.'],
                ['key' => 'cancellations', 'title' => 'Hogyan működnek az egyéni lemondások?', 'text' => 'A rendszer az aktív egyéni lemondást a gyermekhez és a naphoz kapcsolja. Ha a lemondás a hónaphoz tartozó határnapig beérkezett, a nap időben lemondottként 0 Ft-os lesz. Ha a lemondás később történt, a nap továbbra is fizetendő marad, és szükség esetén jóváírásként külön pénzügyi korrekció készül.'],
                ['key' => 'class-cancellations', 'title' => 'Hogyan működnek az osztálylemondások?', 'text' => 'A rendszer megnézi, hogy a gyermek az adott napon melyik csoporthoz vagy osztályhoz tartozott. Ha arra a csoportra aktív osztályszintű lemondás van rögzítve, a nap külön osztályszintű lemondás státuszt kap, és nem keletkezik rá fizetendő összeg.'],
                ['key' => 'school-breaks', 'title' => 'Hogyan kezeli az iskolai szüneteket?', 'text' => 'A rendszer az intézményhez rögzített szüneti időszakokat napra pontosan figyelembe veszi. Ha a nap szünetre esik, nem lesz belőle fizetendő étkezési nap, még akkor sem, ha egyébként lenne érvényes menü és ár.'],
                ['key' => 'working-saturdays', 'title' => 'Hogyan kezeli a tanítási vagy óvodai szombatokat?', 'text' => 'Szombat alaphelyzetben nem része a fizetős napoknak. Ha azonban az intézmény külön munkanapként rögzíti a szombatot, a rendszer tanítási szombatként kezeli, és a napi díjat ugyanúgy kiszámítja, mint más munkanapon.'],
                ['key' => 'discounts', 'title' => 'Hogyan alkalmazza a kedvezményeket?', 'text' => $discountSummary],
                ['key' => 'missing-price', 'title' => 'Mi történik hiányzó ár esetén?', 'text' => 'Ha az adott naphoz szükséges bármelyik ár hiányzik, a napi tétel hibás lesz, és a havi kimutatásban is megjelenik a hiányzó ár problémája. Ez a lezárási ellenőrzésben is számít, ezért a hónap addig nem zárható le.'],
                ['key' => 'recalculate', 'title' => 'Mit jelent a havi újraszámítás?', 'text' => 'Az újraszámítás a kiválasztott hónap összes nem lezárt havi kimutatását újragenerálja az aktuális adatok alapján. A rendszer új gyermekkimutatásokat is létrehozhat, a meglévő tervezeteket frissítheti, és megtartja azokat a napi sorokat, amelyeket kézzel módosítottak.'],
                ['key' => 'close', 'title' => 'Mit jelent a hónap lezárása?', 'text' => 'A lezárás véglegesíti a tervezet vagy ellenőrzött állapotú havi kimutatásokat. Lezárás után a hónap nem számolható újra, és a napi kézi módosítások sem engedélyezettek, amíg a hónapot újra nem nyitják.'],
                ['key' => 'reopen', 'title' => 'Mikor nyitható újra egy hónap?', 'text' => 'Csak olyan hónap nyitható újra, ahol már van lezárt kimutatás. Az újranyitáshoz indoklás kötelező. A rendszer a lezárt rekordokat visszaállítja tervezet állapotba, és eltárolja az újranyitás adatait.'],
                ['key' => 'debt', 'title' => 'Hogyan keletkezik tartozás?', 'text' => 'A tartozás a havi teljes fizetendő összeg és az ahhoz kapcsolt teljesült befizetések különbsége. A Tartozások modul csak a még pozitív fennmaradó egyenlegű tételeket jeleníti meg, ezért a rendezett kötelezettség onnan eltűnik.'],
                ['key' => 'payment-status', 'title' => 'Hogyan változik a fizetési státusz?', 'text' => 'A befizetésrekordok státusza lehet függőben, teljesült, sikertelen, visszatérítve vagy törölve. A tartozás oldalon ettől függetlenül külön látható, hogy egy tétel fizetési határidő előtt van, ma esedékes, késedelmes vagy részben fizetett.'],
                ['key' => 'invoice-link', 'title' => 'Hogyan kapcsolódik a számla a fizetési kötelezettséghez?', 'text' => 'A számla egy havi fizetési kötelezettséghez kapcsolódik. Új számla csak akkor készülhet, ha ugyanahhoz a kötelezettséghez még nincs számlarekord, az összeg pozitív, és a vevőadatok, valamint a szolgáltatói beállítások hiánytalanok.'],
                ['key' => 'exports', 'title' => 'Mit tartalmaznak az exportok?', 'text' => 'Az exportok a pénzügyi adatok pillanatképét adják vissza. Külön export van a fizetési kötelezettségekről, befizetésekről, tartozásokról, számlákról és havi összesítőről, továbbá a havi kötelezettség oldalon egy gyermek részletező és egy teljes havi lista export is elérhető. Egyik export sem módosítja a rendszerben tárolt adatokat.'],
            ],
            'important_notes' => [
                ['icon' => 'fa-solid fa-tags', 'class' => 'alert-warning', 'title' => 'Az árakat érvényességi idővel kell rögzíteni', 'text' => 'A napi díj mindig az adott napra érvényes árakból számolódik. Ha egy időszakhoz nincs teljes árbeállítás, a havi kimutatás hibát jelez és nem zárható le.'],
                ['icon' => 'fa-solid fa-clock-rotate-left', 'class' => 'alert-info', 'title' => 'A korábbi árakat nem szabad felülírni', 'text' => 'Az elszámolás napra pontos érvényességi idővel dolgozik, ezért árváltozáskor új időszakot kell felvenni. Így a korábbi hónapok újraszámítása is helyes marad.'],
                ['icon' => 'fa-solid fa-ban', 'class' => 'alert-primary', 'title' => 'A lemondás csak a rendszer szabálya szerint csökkenti a fizetendő összeget', 'text' => 'Az időben rögzített lemondás azonnal 0 Ft-os nappá válik. A késői lemondás nem törli a napi díjat, hanem külön jóváírásként jelenhet meg a pénzügyi korrekciók között.'],
                ['icon' => 'fa-solid fa-lock', 'class' => 'alert-danger', 'title' => 'Lezárás előtt minden hibát rendezni kell', 'text' => 'A rendszer lezárás előtt ellenőrzi a hiányzó menücsomagokat, kedvezményeket és árakat. Hibás hónap nem zárható le.'],
                ['icon' => 'fa-solid fa-rotate', 'class' => 'alert-secondary', 'title' => 'Az újraszámítás módosítja a még nem lezárt havi összegeket', 'text' => 'Az újraszámítás a tervezet állapotú havi kimutatásokat frissíti az aktuális adatok alapján. Lezárt hónapot előbb újra kell nyitni.'],
                ['icon' => 'fa-solid fa-file-invoice-dollar', 'class' => 'alert-success', 'title' => 'A számlázás és a kártyás fizetés külön kapcsolható', 'text' => 'Az intézményi beállításokban a számlázás és a kártyás fizetés külön engedélyezhető. A két funkció nem feltételezi egymást.'],
                ['icon' => 'fa-solid fa-screwdriver-wrench', 'class' => 'alert-warning', 'title' => 'Szolgáltatói technikai beállítások nélkül nincs használható integráció', 'text' => 'A számlázási szolgáltató csak akkor használható, ha a szükséges technikai mezők is ki vannak töltve. Ettől függetlenül a Billingo és a Számlázz.hu tényleges API-hívása jelenleg még nincs aktiválva ebben a verzióban.'],
                ['icon' => 'fa-solid fa-file-export', 'class' => 'alert-light', 'title' => 'Az export nem jelent lezárást és nem hoz létre számlát', 'text' => 'Az export kizárólag fájlba menti a kiválasztott adatokat. Nem véglegesíti a hónapot, és nem indít számlázási folyamatot.'],
            ],
            'common_problems' => [
                ['title' => 'Nincs érvényes ár', 'cause' => 'Az adott naphoz szükséges egyik vagy több étkezési típushoz nincs olyan ár, amelynek érvényessége lefedi a napot.', 'solution' => 'Ellenőrizze az érintett étkezési típus árlistáját és az érvényességi időszakokat. Új árat új dátumtartománnyal rögzítsen, ne a régi rekord felülírásával.'],
                ['title' => 'A gyermek nem jelenik meg a havi kimutatásban', 'cause' => 'A gyermek nem aktív, vagy nincs olyan étkezési beállítása, amely átfedi a kiválasztott hónapot.', 'solution' => 'Ellenőrizze a gyermek aktív státuszát és azt, hogy van-e számára időben érvényes étkezési beállítás. Ezután indítson újraszámítást a hónapra.'],
                ['title' => 'A lemondott nap mégis fizetendő', 'cause' => 'A lemondás a rendszer által használt havi határnap után került rögzítésre, ezért a nap késői lemondásnak minősül.', 'solution' => 'Ellenőrizze a lemondás rögzítési idejét, a szolgáltatási napot és az intézmény határnap-beállítását. Késői lemondás esetén a korrekció külön jóváírásként jelenhet meg.'],
                ['title' => 'Egy tanítási szombat nem szerepel a számításban', 'cause' => 'A szombat nincs intézményi munkanapként rögzítve, ezért a rendszer hétvégének tekinti.', 'solution' => 'Ellenőrizze, hogy az adott dátum szerepel-e a munkanapok között. Ha hiányzik, rögzítse, majd számolja újra a hónapot.'],
                ['title' => 'Nem zárható le a hónap', 'cause' => 'Van hiányzó menücsomag, hiányzó kedvezmény, hiányzó ár vagy a számításból kimaradt gyermekkimutatás.', 'solution' => 'Nézze meg a lezárási ellenőrzés kártyát és a hibás sorokat a havi kötelezettségek listájában. A hibák rendezése után újraszámítással frissítse a hónapot, majd próbálja meg újra a lezárást.'],
                ['title' => 'Nem készíthető számla', 'cause' => 'A számlázás nincs engedélyezve, hiányos a szolgáltatói beállítás, hiányosak a számlázási adatok, 0 Ft-os az összeg, vagy már létezik számla ugyanahhoz a kötelezettséghez.', 'solution' => 'Ellenőrizze a számlázási beállításokat, a kiválasztott szolgáltató technikai mezőit, a vevőadatokat és azt, hogy az adott kötelezettséghez tartozik-e már számlarekord.'],
                ['title' => 'A befizetés után még tartozás látható', 'cause' => 'A befizetés nem teljesült státuszú, nem ahhoz a havi kötelezettséghez kapcsolódik, vagy az összeg nem fedezi teljesen a fizetendőt.', 'solution' => 'Ellenőrizze a befizetés rekordját, a kapcsolt gyermeket, a havi kötelezettséget és a befizetés státuszát. A Tartozások modul csak a teljesült befizetéseket veszi figyelembe.'],
            ],
            'institution_settings' => $this->institutionSettings(
                $institution,
                $setting,
                $defaultMealPackage,
                $invoiceProviderLabel,
                $cardProviderLabel,
                $invoiceProviderState
            ),
            'implementation_notes' => [
                'A Billingo és a Számlázz.hu választható szolgáltatóként szerepel, de a tényleges API-hívás jelenleg hibával tér vissza, ezért ezek még nem teljes értékű integrációk.',
                'A havi fizetési kötelezettség rekordban léteznek további státuszmezők is a számlázáshoz és fizetéshez, de a jelenlegi üzemi folyamatokban a lezárás, az egyedi számlarekordok, a befizetésrekordok és a tartozásnézet használata tekinthető ténylegesen megvalósított működésnek.',
                'A számla létrehozása jelenleg nem ellenőrzi külön, hogy a havi kötelezettség lezárt állapotú-e; a tényleges feltétel a pozitív összeg, az egyedi számlahiány, az engedélyezett szolgáltató és a hiánytalan számlázási adat.',
                'Az időben vagy későn rögzített lemondás eldöntéséhez a rendszer jelenleg a havi fizetési határnap beállítását használja.',
            ],
        ];
    }

    private function institutionSettings(
        Institution $institution,
        InstitutionSetting $setting,
        ?InstitutionMealPackage $defaultMealPackage,
        string $invoiceProviderLabel,
        string $cardProviderLabel,
        string $invoiceProviderState
    ): array {
        $billingDueDay = (int) ($setting->payment_due_day ?: $institution->billing_payment_due_days ?: 5);

        return [
            'cards' => [
                ['label' => 'Lemondások havi határnapja', 'value' => $billingDueDay.'. nap', 'state' => 'ok', 'note' => 'A rendszer ezzel a hónapon belüli nappal dönti el, hogy a lemondás időben vagy késve érkezett.'],
                ['label' => 'Számlázás', 'value' => $setting->invoicing_enabled ? 'Engedélyezve' : 'Kikapcsolva', 'state' => $setting->invoicing_enabled ? 'ok' : 'warning', 'note' => $setting->invoicing_enabled ? 'A számlázási modul használható, ha a szolgáltatói beállítások is teljesek.' : 'A számlakészítés jelenleg nincs bekapcsolva ennél az intézménynél.'],
                ['label' => 'Számlázási szolgáltató', 'value' => $invoiceProviderLabel, 'state' => $setting->invoicing_enabled && !$setting->invoicing_provider ? 'warning' : 'ok', 'note' => $invoiceProviderState],
                ['label' => 'Kártyás fizetés', 'value' => $setting->card_payment_enabled ? 'Engedélyezve' : 'Kikapcsolva', 'state' => $setting->card_payment_enabled ? 'ok' : 'warning', 'note' => $setting->card_payment_enabled ? 'A kiválasztott szolgáltató az intézményi beállításokban látható.' : 'Online kártyás fizetés jelenleg nincs bekapcsolva.'],
                ['label' => 'Fizetési szolgáltató', 'value' => $cardProviderLabel, 'state' => $setting->card_payment_enabled && !$setting->card_payment_provider ? 'warning' : 'ok', 'note' => $setting->card_payment_enabled ? 'Üzemmód: '.($setting->card_payment_test_mode ? 'teszt' : 'éles').'.' : 'A szolgáltató csak bekapcsolt kártyás fizetés esetén használható.'],
                ['label' => 'Aktív alapértelmezett menücsomag', 'value' => $defaultMealPackage?->name ?: 'Nincs kijelölve', 'state' => $defaultMealPackage ? 'ok' : 'warning', 'note' => $defaultMealPackage ? 'Az intézményi alapértelmezett étkezési módot használó gyermekek ebből a csomagból számolódnak.' : 'Alapértelmezett mód esetén a hiányzó menücsomag hibát okozhat a havi számításban.'],
            ],
            'links' => [
                ['label' => 'Általános beállítások megnyitása', 'url' => route('dashboard.institution.settings.edit')],
                ['label' => 'Számlázási beállítások megnyitása', 'url' => route('dashboard.institution.settings.invoicing.edit')],
            ],
            'security_note' => 'Az oldalon érzékeny adat, API-kulcs vagy titkos kulcs nem jelenik meg. Csak az látható, hogy a szükséges beállítási elemek rendelkezésre állnak-e.',
        ];
    }

    private function invoiceProviderState(InstitutionSetting $setting): string
    {
        if (! $setting->invoicing_enabled) {
            return 'A számlázás ki van kapcsolva.';
        }

        return match ($setting->invoicing_provider) {
            InstitutionSetting::INVOICING_PROVIDER_MANUAL => 'Kézi számlázás van kiválasztva, ezért számlaszám kézzel rögzíthető.',
            InstitutionSetting::INVOICING_PROVIDER_BILLINGO => ! $setting->hasBillingoApiKey() || ! filled($setting->billingo_document_block_id)
                ? 'A Billingo beállítása hiányos.'
                : 'A Billingo technikai mezői ki vannak töltve, de a tényleges API-integráció még nincs aktiválva.',
            InstitutionSetting::INVOICING_PROVIDER_SZAMLAZZ_HU => ! $setting->hasSzamlazzHuAgentKey() || ! filled($setting->szamlazz_hu_invoice_prefix)
                ? 'A Számlázz.hu beállítása hiányos.'
                : 'A Számlázz.hu technikai mezői ki vannak töltve, de a tényleges API-integráció még nincs aktiválva.',
            default => 'Nincs számlázási szolgáltató kiválasztva.',
        };
    }

    private function providerLabel(?string $provider, array $options, array $extra = []): string
    {
        if (! filled($provider)) {
            return 'Nincs kiválasztva';
        }

        return $extra[$provider] ?? $options[$provider] ?? (string) $provider;
    }
}
