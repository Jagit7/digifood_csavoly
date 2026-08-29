<p>Ez a tájékoztató a bankkártyás fizetés indítása előtt elfogadott, a CIB Bank Zrt. felé történő adattovábbításról szól. A bankkártyaadatok kezelésének és a kártyás fizetés általános biztonságának szabályait a <a href="{{ route($routePrefix . '.card-payment') }}">Bankkártyás fizetési tájékoztató</a> tartalmazza.</p>

<h2>Ki kezeli az Ön személyes adatait?</h2>
<p>Az adatkezelő {{ $merchant['name'] }}
    @if($merchant['address'])
        ({{ $merchant['address'] }})
    @endif
    @if($merchant['phone'])
        , telefon: {{ $merchant['phone'] }}
    @endif
    .
</p>

<h2>Milyen adatokat, milyen célból, milyen jogalapon és mennyi ideig kerülnek kezelésre az Ön személyes adatai?</h2>
<p>Kezelt adatok köre: az Ön neve, számlázási címe, e-mail címe, telefonszáma.</p>
<p>Az adatkezelés célja: a kibocsátó bank által végzett biztonságosabb ügyfél-hitelesítés.</p>
<p>Az adatkezelés jogalapja: az Ön hozzájárulása.</p>
<p>Az adatkezelés időtartama: személyes adatok esetében a 3DS authentikáció erejéig, ezt követően törlésre kerülnek.</p>

<h2>Milyen jogok illetik meg Önt?</h2>
<p>Az Európai Parlamentnek és a Tanácsnak a természetes személyeknek a személyes adatok kezelése tekintetében történő védelméről és az ilyen adatok szabad áramlásáról, valamint a 95/46/EK irányelv hatályon kívül helyezéséről szóló 2016/679/EU rendelete (GDPR) alapján Ön</p>
<ul>
    <li>az adatkezeléshez adott hozzájárulását bármikor visszavonhatja, azonban a hozzájárulás visszavonása nem érinti a hozzájáruláson alapuló, a visszavonás előtti adatkezelés jogszerűségét. {{ $merchant['name'] }} a hozzájárulás visszavonását követően is kezelheti a személyes adatokat jogi kötelezettségei teljesítése vagy jogos érdekei érvényesítése céljából, ha az érdek érvényesítése a személyes adatok védelméhez fűződő jog korlátozásával arányban áll;</li>
    <li>kérelmezheti az Önre vonatkozó személyes adatokhoz való hozzáférést, tájékoztatást kérve {{ $merchant['name'] }}-nél kezelt adatokról. A tájékoztatás, illetve a kezelt adatok másolatának megadása ingyenes. Az Ön által kért további másolatokért az adminisztratív költségen alapuló, ésszerű mértékű díj számítható fel;</li>
    <li>kérelmezheti az Önre vonatkozó személyes adatok helyesbítését, törlését vagy kezelésük korlátozását;</li>
    <li>kérheti, hogy {{ $merchant['name'] }} ismertesse, milyen címzetteket tájékoztatott az adatok helyesbítéséről, törléséről vagy az adatkezelés korlátozásáról.</li>
</ul>

<h2>Kik az Ön személyes adatainak a címzettjei?</h2>
<p>A fentiek szerint kezelt személyes adatok címzettje a CIB Bank Zrt., mint a bankkártyás fizetési szolgáltatás nyújtója. {{ $merchant['name'] }} ezen adatok kezeléséhez jelenleg nem vesz igénybe további adatfeldolgozót.</p>

<h2>Mi történik, ha Ön nem járul hozzá a fenti pontban felsorolt személyes adatainak kezeléséhez?</h2>
<p>Amennyiben Ön nem járul hozzá az adatok továbbításához, a fizetési folyamat az alábbi lesz: a kereskedő átirányítja Önt a CIB Bank oldalára, ahol megadja kártyájának adatait (kártyaszám, lejárati dátum, érvényesítési kód). Ezt követően a CIB Bank átirányítja Önt a kártyáját kibocsátó bank oldalára, ahol azonosításon kell átesnie (pl. mobiltelefonra küldött kód beírása). Amennyiben az azonosítás sikeres, a fizetés megtörténik, és Ön erről értesítést kap. Ezt követően automatikusan visszairányításra kerül a kereskedői felületre. Vagyis a hozzájárulás hiánya a fizetés lebonyolítását nem akadályozza, kizárólag az Ön adatainak előzetes átadására nincs mód a gyorsabb, előre kitöltött hitelesítés érdekében.</p>

<h2>Milyen jogorvoslati lehetőségek állnak az Ön rendelkezésére?</h2>
<p>Az adatkezelés jogszerűsége kapcsán a Nemzeti Adatvédelmi és Információszabadság Hatóság (1055 Budapest, Falk Miksa utca 9-11., postacím: 1363 Budapest, Pf.: 9., honlap: <a href="https://www.naih.hu" target="_blank" rel="noopener">www.naih.hu</a>, telefon: +36 (1) 391-1400, fax: +36 (1) 391-1410, központi e-mail cím: <a href="mailto:ugyfelszolgalat@naih.hu">ugyfelszolgalat@naih.hu</a>) eljárását kezdeményezheti, illetőleg a bírósághoz fordulhat. Javasoljuk, hogy mielőtt a Nemzeti Adatvédelmi és Információszabadság Hatósághoz vagy a bírósághoz fordulna, keresse meg az Ügyfélszolgálatot.</p>

<h2>Miként érhető el az adatvédelmi tisztviselő?</h2>
<p>{{ $merchant['name'] }} önálló adatvédelmi tisztviselőt jelenleg nem nevezett ki. Az adatkezeléssel kapcsolatos kérdéseivel forduljon az <a href="{{ route($routePrefix . '.customer-service') }}">Ügyfélszolgálathoz</a>.</p>

<h2>Nyilatkozat</h2>
<p>A bankkártyás fizetés indítása előtt az alábbi nyilatkozat elfogadása szükséges:</p>
<blockquote>
    <p>Kijelentem, hogy az adatkezeléshez kapcsolódó tájékoztatást megértettem és tudomásul vettem. Ezennel önkéntesen és megfelelő tájékoztatás birtokában hozzájárulok ahhoz, hogy {{ $merchant['name'] }} az önkéntesen megadott személyes adataimat a fenti tájékoztatóban meghatározott célból továbbítsa a CIB Bank Zrt. részére.</p>
</blockquote>
