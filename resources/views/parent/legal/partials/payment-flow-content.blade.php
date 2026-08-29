<ol>
    <li>A szülő megnyitja a havi elszámolást, ahol a fizetendő összeg kizárólag szerveroldali adatokból számolódik.</li>
    <li>A Digifood létrehozza a fizetési kezdeményezést és a CIB által használt tranzakciós azonosítót.</li>
    <li>A rendszer a CIB Bank kereskedői végpontja felé elküldi a dokumentáció szerinti kezdeményező üzenetet.</li>
    <li>Sikeres banki válasz után a felhasználó a CIB Bank fizetőoldalára kerül át.</li>
    <li>A bankkártyaadatok megadása kizárólag a CIB felületén történik.</li>
    <li>A CIB Bank feldolgozza a tranzakciót, szükség esetén további ügyfélhitelesítést kér.</li>
    <li>A böngésző visszatér a Digifood oldalára.</li>
    <li>A Digifood szerveroldalon lekérdezi és lezárja a tranzakció állapotát, majd megjeleníti a TRID, ANUM, RC, RT és AMO adatokat tartalmazó eredményt.</li>
    <li>Sikeres lezárás esetén a befizetés könyvelődik, és az intézményi admin felületen is visszakereshetővé válik.</li>
</ol>

<p>A fizetési összeg, a kapcsolódó kötelezettség és a tranzakció tulajdonosa minden esetben a Digifood belső nyilvántartásából kerül meghatározásra.</p>
