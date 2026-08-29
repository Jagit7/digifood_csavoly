<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Adatkezelési tájékoztató – Digifood</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('home/favicon.png') }}">

    <style>
        :root {
            --df-blue: #007bff;
            --df-blue-dark: #001b3a;
            --df-ink: #0f172a;
            --df-body: #334155;
            --df-muted: #64748b;
            --df-border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f1f5f9;
            color: var(--df-body);
            line-height: 1.7;
        }

        .df-topbar {
            background: linear-gradient(120deg, #000814, var(--df-blue-dark));
            color: #fff;
            padding: 18px 20px;
        }

        .df-topbar-inner {
            max-width: 860px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .df-logo img {
            display: block;
            height: 34px;
            width: auto;
        }

        .df-back {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 8px 16px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .df-back:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .df-wrap {
            max-width: 860px;
            margin: 32px auto 60px;
            padding: 0 20px;
        }

        .df-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.12);
            padding: 40px clamp(20px, 5vw, 56px) 56px;
        }

        .df-card h1 {
            color: var(--df-ink);
            font-size: 1.7rem;
            margin: 0 0 6px;
        }

        .df-card .df-subtitle {
            color: var(--df-muted);
            font-size: 1rem;
            margin: 0 0 4px;
        }

        .df-card .df-effective {
            color: var(--df-muted);
            font-size: 0.9rem;
            margin: 0 0 28px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--df-border);
        }

        .df-content h2 {
            color: var(--df-ink);
            font-size: 1.2rem;
            margin: 2.1rem 0 0.85rem;
        }

        .df-content h2:first-child {
            margin-top: 0;
        }

        .df-content h3 {
            color: var(--df-ink);
            font-size: 1.05rem;
            margin: 1.4rem 0 0.6rem;
        }

        .df-content p,
        .df-content ul {
            margin: 0 0 1rem;
        }

        .df-content ul {
            padding-left: 1.25rem;
        }

        .df-content li {
            margin-bottom: 0.35rem;
        }

        .df-content strong {
            color: var(--df-ink);
        }

        .df-content a {
            color: var(--df-blue);
        }

        .df-org-block {
            background: #f8fafc;
            border: 1px solid var(--df-border);
            border-radius: 14px;
            padding: 16px 20px;
            margin: 0.75rem 0 1.25rem;
        }

        .df-org-block p {
            margin: 0 0 4px;
        }

        .df-org-block p:last-child {
            margin-bottom: 0;
        }

        .df-toc {
            background: #f8fafc;
            border: 1px solid var(--df-border);
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 2rem;
        }

        .df-toc h2 {
            margin: 0 0 0.75rem !important;
            font-size: 1rem !important;
        }

        .df-toc ol {
            columns: 2;
            -webkit-columns: 2;
            padding-left: 1.1rem;
            margin: 0;
            font-size: 0.92rem;
        }

        .df-toc li {
            break-inside: avoid;
            margin-bottom: 0.4rem;
        }

        .df-toc a {
            color: var(--df-body);
            text-decoration: none;
        }

        .df-toc a:hover {
            color: var(--df-blue);
            text-decoration: underline;
        }

        @media (max-width: 560px) {
            .df-toc ol {
                columns: 1;
            }
        }
    </style>
</head>
<body>

    <div class="df-topbar">
        <div class="df-topbar-inner">
            <div class="df-logo">
                <img src="{{ asset('home/logo.png') }}" alt="Digifood">
            </div>
            <a href="{{ url()->previous() && url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="df-back">&larr; Vissza</a>
        </div>
    </div>

    <div class="df-wrap">
        <div class="df-card">
            <h1>Adatkezelési tájékoztató</h1>
            <p class="df-subtitle">a Digifood elektronikus étkeztetési rendszer használatához</p>
            <p class="df-effective">Hatályos: 2026. augusztus 26-tól</p>

            <div class="df-toc">
                <h2>Tartalom</h2>
                <ol>
                    <li><a href="#cel">A tájékoztató célja</a></li>
                    <li><a href="#adatkezelo">Az adatkezelő</a></li>
                    <li><a href="#uzemeltetes">A Digifood üzemeltetője és adatfeldolgozó</a></li>
                    <li><a href="#erintettek">Az érintettek köre</a></li>
                    <li><a href="#adatkor">A kezelt személyes adatok köre</a></li>
                    <li><a href="#cel-adatkezeles">Az adatkezelés célja</a></li>
                    <li><a href="#jogalap">Az adatkezelés jogalapja</a></li>
                    <li><a href="#dietas">Diétás és egészségügyi adatok</a></li>
                    <li><a href="#bankkartya">Bankkártyás fizetés</a></li>
                    <li><a href="#szamlazas">Elektronikus számlázás</a></li>
                    <li><a href="#kapcsolattartas">Elektronikus kapcsolattartás</a></li>
                    <li><a href="#forras">Az adatok forrása</a></li>
                    <li><a href="#adatfeldolgozok">Adatfeldolgozók és adattovábbítás</a></li>
                    <li><a href="#megorzes">Az adatok megőrzése</a></li>
                    <li><a href="#biztonsag">Adatbiztonság</a></li>
                    <li><a href="#jogok">Az érintettek jogai</a></li>
                    <li><a href="#panasz">Panasz és jogorvoslat</a></li>
                    <li><a href="#modositas">A tájékoztató elérhetősége és módosítása</a></li>
                </ol>
            </div>

            <div class="df-content">
                <h2 id="cel">1. A tájékoztató célja</h2>
                <p>Jelen Adatkezelési tájékoztató a Digifood elektronikus étkeztetési rendszer használatával összefüggő személyes adatok kezeléséről nyújt tájékoztatást.</p>
                <p>A Digifood rendszer célja az intézményi étkeztetés adminisztrációjának támogatása, különösen az étkezők nyilvántartása, az étkezési jogosultságok és beállítások kezelése, az étkezések megrendelése és lemondása, a menüválasztás, az étkezési díjak megállapítása és elszámolása, a befizetések kezelése, az elektronikus fizetés, a számlázás, valamint az intézmény és a szülők, gondviselők közötti kapcsolattartás támogatása.</p>

                <h2 id="adatkezelo">2. Az adatkezelő</h2>
                <p>A Digifood rendszerben az intézményi étkeztetés céljából kezelt személyes adatok tekintetében az adatkezelő:</p>
                <div class="df-org-block">
                    <p><strong>Szent Angéla Ferences Általános Iskola és Gimnázium</strong></p>
                    <p>Székhely: 1024 Budapest, Ady Endre utca 3.</p>
                    <p>Adószám: 18062764-2-41</p>
                    <p>Képviselő: Szalai Gábor László igazgató</p>
                </div>
                <p>Az adatkezeléssel kapcsolatos kérelmek az intézményhez nyújthatók be.</p>

                <h2 id="uzemeltetes">3. A Digifood üzemeltetője és adatfeldolgozó</h2>
                <p>A Digifood rendszer technikai üzemeltetője:</p>
                <div class="df-org-block">
                    <p><strong>Jagicza Tamás E.V.</strong></p>
                    <p>Székhely: 2072 Zsámbék, Nyárfás u. 44.</p>
                    <p>Adószám: 66682079-1-33</p>
                    <p>Nyilvántartási szám: 37578687</p>
                    <p>E-mail: <a href="mailto:info@digifood.hu">info@digifood.hu</a></p>
                </div>
                <p>A Digifood üzemeltetője az intézmény megbízásából biztosítja a rendszer technikai működését és az intézmény által meghatározott adatkezelési műveletek végrehajtását.</p>
                <p>Az adatkezelő és az adatfeldolgozó feladatait és felelősségét a közöttük fennálló szerződés, valamint az alkalmazandó adatvédelmi jogszabályok határozzák meg.</p>

                <h2 id="erintettek">4. Az érintettek köre</h2>
                <p>A rendszerben kezelt személyes adatok különösen az alábbi személyekhez kapcsolódhatnak:</p>
                <ul>
                    <li>az intézmény étkeztetésében részt vevő gyermekek és tanulók;</li>
                    <li>szüleik és gondviselőik;</li>
                    <li>az intézmény étkeztetésben részt vevő dolgozói;</li>
                    <li>az intézmény Digifood rendszert használó munkatársai.</li>
                </ul>

                <h2 id="adatkor">5. A kezelt személyes adatok köre</h2>
                <h3>Gyermekek és tanulók adatai</h3>
                <p>A rendszer kezelheti különösen a gyermek nevét, oktatási azonosítóját, intézményét, osztályát vagy csoportját, étkezési státuszát, étkezési csomagját, étkezési időszakait, menüválasztását, lemondásait, kedvezményre való jogosultságát és az étkeztetéshez szükséges diétás információkat.</p>
                <h3>Szülők és gondviselők adatai</h3>
                <p>A rendszer kezelheti különösen a nevet, e-mail-címet, telefonszámot, lakcímet és kapcsolattartási adatokat, a gyermekkel fennálló kapcsolatot, valamint a fizetéshez és számlázáshoz szükséges adatokat.</p>
                <h3>Számlázási és fizetési adatok</h3>
                <p>A rendszer kezelheti különösen a számlázási nevet és címet, az alkalmazandó esetekben adóazonosító adatokat, fizetendő összeget, befizetett összeget, fizetési módot, fizetés időpontját, fizetési referenciát, számlaadatokat és az elektronikus fizetés tranzakcióazonosítóit.</p>
                <h3>Technikai és biztonsági adatok</h3>
                <p>A rendszer a biztonságos működéshez szükséges mértékben naplózhatja a rendszer használatával kapcsolatos technikai eseményeket, időpontokat, IP-címet, böngésző- és eszközinformációkat, valamint a bejelentkezéssel és jogosultságkezeléssel kapcsolatos biztonsági adatokat.</p>

                <h2 id="cel-adatkezeles">6. Az adatkezelés célja</h2>
                <p>A személyes adatok kezelése különösen az alábbi célokból történik:</p>
                <ul>
                    <li>az étkezők nyilvántartása;</li>
                    <li>étkezési jogosultságok és beállítások kezelése;</li>
                    <li>étkezések megrendelése és lemondása;</li>
                    <li>menüválasztás biztosítása;</li>
                    <li>kedvezmények kezelése;</li>
                    <li>diétás étkeztetés biztosítása;</li>
                    <li>havi étkezési díjak és fizetési kötelezettségek kiszámítása;</li>
                    <li>befizetések nyilvántartása és egyeztetése;</li>
                    <li>bankkártyás fizetés biztosítása;</li>
                    <li>számlák kiállítása és rendelkezésre bocsátása;</li>
                    <li>szülői/gondviselői felhasználói fiókok működtetése;</li>
                    <li>intézményi kapcsolattartás és rendszerüzenetek küldése;</li>
                    <li>informatikai biztonság, hibakeresés és visszaélések megelőzése;</li>
                    <li>jogszabályi, számviteli és bizonylatmegőrzési kötelezettségek teljesítése.</li>
                </ul>

                <h2 id="jogalap">7. Az adatkezelés jogalapja</h2>
                <p>Az adatkezelés jogalapja az adott adatkezelési céltól függően különösen az adatkezelőre vonatkozó jogi kötelezettség teljesítése, az adatkezelő közérdekű vagy jogszabályban meghatározott feladatának végrehajtása, szerződés teljesítése, illetve az adatkezelő vagy harmadik személy jogos érdeke lehet.</p>
                <p>Amennyiben valamely adat kezelése hozzájáruláson alapul, a hozzájárulás önkéntes és visszavonható.</p>
                <p>Az egyes adatkezelési célok pontos jogalapját az intézmény működésére és az étkeztetés jogi hátterére tekintettel a végleges változatban adatkezelési célonként szükséges meghatározni.</p>

                <h2 id="dietas">8. Diétás és egészségügyi adatok</h2>
                <p>A diétás étkeztetés biztosításához kezelt olyan információk, amelyek az érintett egészségi állapotára, ételallergiájára, ételintoleranciájára vagy más egészségügyi körülményére utalnak, különleges személyes adatnak minősülhetnek.</p>
                <p>Ezek az adatok kizárólag a diétás étkeztetés biztosításához szükséges mértékben és megfelelő jogalap fennállása esetén kezelhetők.</p>
                <p>A hozzáférést azokra a személyekre kell korlátozni, akiknek az adat az étkeztetéssel kapcsolatos feladatuk ellátásához szükséges.</p>

                <h2 id="bankkartya">9. Bankkártyás fizetés</h2>
                <p>A Digifood rendszerben a bankkártyás fizetés a CIB Bank elektronikus fizetési rendszerének igénybevételével történik.</p>
                <p>A fizetés során a felhasználó a CIB Bank fizetési felületére kerül, és a bankkártya adatait a bank által biztosított környezetben adja meg.</p>
                <p>A Digifood nem tárolja a bankkártya számát, lejárati idejét és CVC/CVV biztonsági kódját.</p>
                <p>A Digifood kizárólag a fizetés azonosításához, eredményének megállapításához, könyveléséhez és visszakereshetőségéhez szükséges tranzakciós adatokat kezelheti, így különösen: TRID, ANUM, RC és RT tranzakciós adatok, a tranzakció összege (AMO), a fizetés időpontja és státusza, a belső fizetési referencia, valamint a fizetéshez kapcsolódó kötelezettség azonosítója.</p>
                <p>A bankkártyás fizetés részletes feltételeit a Bankkártyás fizetési tájékoztató és a Fizetési folyamat oldal tartalmazza.</p>

                <h2 id="szamlazas">10. Elektronikus számlázás</h2>
                <p>A Digifood rendszerben az elektronikus számlázás a Billingo számlázási szolgáltatásának igénybevételével történhet.</p>
                <p>A számla kiállításához szükséges adatok a számlázási szolgáltató részére továbbíthatók. Ilyen adat lehet különösen a számlázási név, számlázási cím, szükség esetén adószám, a számlázott szolgáltatás megnevezése, összege, teljesítési időpontja és a fizetéssel kapcsolatos információ.</p>
                <p>A kiállított számla adatai és elektronikus példánya a Digifood rendszerben tárolható és a jogosult felhasználó számára hozzáférhetővé tehető.</p>

                <h2 id="kapcsolattartas">11. Elektronikus kapcsolattartás</h2>
                <p>A Digifood az intézmény nevében a szolgáltatás működéséhez kapcsolódó elektronikus üzeneteket küldhet.</p>
                <p>Ilyenek lehetnek különösen a felhasználói fiók aktiválásával, étkezéssel, fizetési kötelezettséggel, befizetéssel, számlázással és más intézményi ügyintézéssel kapcsolatos értesítések.</p>
                <p>A szolgáltatás működéséhez szükséges rendszerüzenetek nem minősülnek marketingcélú elektronikus üzenetnek.</p>

                <h2 id="forras">12. Az adatok forrása</h2>
                <p>A Digifoodban kezelt személyes adatok származhatnak:</p>
                <ul>
                    <li>az intézmény nyilvántartásaiból;</li>
                    <li>a szülőtől vagy gondviselőtől;</li>
                    <li>az érintettől;</li>
                    <li>a Digifood használata során végrehajtott műveletekből;</li>
                    <li>a fizetési és számlázási szolgáltatók által visszaadott tranzakciós információkból.</li>
                </ul>

                <h2 id="adatfeldolgozok">13. Adatfeldolgozók és adattovábbítás</h2>
                <p>A szolgáltatás működéséhez szükséges személyes adatok a szükséges mértékben továbbíthatók vagy hozzáférhetővé válhatnak az igénybe vett szolgáltatók számára. Ide tartozhat különösen:</p>
                <ul>
                    <li><strong>CIB Bank</strong> – bankkártyás fizetés feldolgozása;</li>
                    <li><strong>Billingo</strong> – elektronikus számlázás;</li>
                    <li><strong>tárhelyszolgáltató</strong> – a Digifood rendszer informatikai infrastruktúrájának biztosítása;</li>
                    <li><strong>elektronikus levelezési szolgáltató</strong> – rendszerüzenetek továbbítása.</li>
                </ul>
                <p>Az adatfeldolgozók a részükre átadott személyes adatokat kizárólag a rájuk bízott feladat ellátásához szükséges mértékben és a vonatkozó szerződéses, illetve jogszabályi rendelkezéseknek megfelelően kezelhetik.</p>

                <h2 id="megorzes">14. Az adatok megőrzése</h2>
                <p>A személyes adatok megőrzése az adott adatkezelési cél fennállásához, az intézményi nyilvántartási kötelezettségekhez és a vonatkozó jogszabályokhoz igazodik.</p>
                <p>A számlázási, számviteli és egyéb bizonylati adatokat az alkalmazandó jogszabályokban meghatározott ideig kell megőrizni.</p>
                <p>A rendszer működéséhez kapcsolódó technikai és biztonsági naplóadatok csak a biztonság, hibakeresés és elszámoltathatóság biztosításához szükséges ideig őrizhetők meg.</p>
                <p>Az adatkezelési cél megszűnését követően az adatokat törölni vagy – amennyiben további megőrzésüket jogszabály írja elő – a szükséges időtartamra korlátozottan megőrizni kell.</p>

                <h2 id="biztonsag">15. Adatbiztonság</h2>
                <p>Az adatkezelő és a Digifood üzemeltetője megfelelő technikai és szervezési intézkedéseket alkalmaz a személyes adatok jogosulatlan hozzáférése, megváltoztatása, nyilvánosságra hozatala, elvesztése vagy megsemmisülése ellen.</p>
                <p>A Digifood rendszerben a hozzáférés jogosultsághoz kötött. Az adminisztrációs, szülői/gondviselői és egyéb felhasználói funkciókhoz csak az arra jogosult személyek férhetnek hozzá.</p>
                <p>A rendszer és a felhasználó böngészője közötti adatforgalom titkosított HTTPS-kapcsolaton keresztül történik.</p>

                <h2 id="jogok">16. Az érintettek jogai</h2>
                <p>Az érintettet a vonatkozó adatvédelmi jogszabályokban meghatározott feltételek szerint megilleti különösen:</p>
                <ul>
                    <li>a tájékoztatáshoz és hozzáféréshez való jog;</li>
                    <li>a pontatlan személyes adatok helyesbítésének joga;</li>
                    <li>meghatározott esetekben a törléshez való jog;</li>
                    <li>az adatkezelés korlátozásához való jog;</li>
                    <li>az alkalmazandó feltételek esetén az adathordozhatósághoz való jog;</li>
                    <li>meghatározott adatkezelések esetén a tiltakozáshoz való jog;</li>
                    <li>hozzájáruláson alapuló adatkezelés esetén a hozzájárulás visszavonásának joga.</li>
                </ul>
                <p>Az egyes jogok gyakorlásának lehetősége az adott adatkezelés jogalapjától és körülményeitől függ.</p>
                <p>Az érintett kérelmével elsődlegesen az adatkezelő Szent Angéla Ferences Általános Iskola és Gimnáziumhoz fordulhat.</p>

                <h2 id="panasz">17. Panasz és jogorvoslat</h2>
                <p>Az érintett az adatkezeléssel kapcsolatos kérdésével, kérelmével vagy panaszával az adatkezelő intézményhez fordulhat.</p>
                <p>Az érintett jogosult továbbá panaszt tenni a felügyeleti hatóságnál:</p>
                <div class="df-org-block">
                    <p><strong>Nemzeti Adatvédelmi és Információszabadság Hatóság (NAIH)</strong></p>
                    <p>1055 Budapest, Falk Miksa utca 9–11.</p>
                    <p>Postacím: 1363 Budapest, Pf. 9.</p>
                    <p>Telefon: +36 (1) 391-1400</p>
                    <p><a href="https://www.naih.hu/" target="_blank" rel="noopener">www.naih.hu</a></p>
                </div>
                <p>Az érintett a jogszabályban meghatározott feltételek szerint bírósághoz is fordulhat személyes adatainak jogellenes kezelése vagy az adatvédelmi jogainak megsértése esetén.</p>

                <h2 id="modositas">18. A tájékoztató elérhetősége és módosítása</h2>
                <p>A mindenkor hatályos Adatkezelési tájékoztató a Digifood rendszer nyilvánosan elérhető felületén, valamint a szülői/gondviselői felületen érhető el.</p>
                <p>A tájékoztató módosítható, ha az adatkezelési folyamatok, az igénybe vett szolgáltatások vagy a vonatkozó jogszabályok megváltoznak.</p>
            </div>
        </div>
    </div>

</body>
</html>
