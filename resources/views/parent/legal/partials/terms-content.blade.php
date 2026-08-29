<p><strong>{{ $merchant['name'] }}</strong> a Digifood rendszerben teszi elérhetővé az étkezési elszámolások megtekintését és a fennmaradó térítési díjak rendezését.</p>

<h2>1. A Digifood szerepe</h2>
<p>A Digifood intézményi ügyintézési és elszámolási felület. Az étkezési díj jogalapját, összegét és a fizetési kötelezettséget minden esetben az intézmény által rögzített adatok határozzák meg.</p>

<h2>2. A havi elszámolás alapja</h2>
<p>A havi fizetendő összeg az adott időszak étkezési napjai, a gyermek étkezési csomagja, a kedvezmények, a korábbi lemondások jóváírásai, valamint az intézményi pénzügyi korrekciók alapján számolódik ki.</p>

<h2>3. Fizetési módok</h2>
<p>Az intézmény döntése szerint a díjak rögzített befizetésként vagy online bankkártyás fizetéssel rendezhetők. Az online kártyás fizetés a CIB Bank fizetőoldalán történik.</p>

<h2>4. Bankkártyás fizetés</h2>
<p>A kártyás fizetés kezdeményezésekor a Digifood csak a fizetéshez szükséges kereskedői és tranzakciós adatokat továbbítja. A kártyaadatok megadása kizárólag a CIB Bank oldalán történik.</p>

<h2>5. Több gyermek közös rendezése</h2>
<p>Ha ugyanahhoz a szülői fiókhoz több gyermek kapcsolódik ugyanabban az intézményben, a fennmaradó összeg egyetlen közös online fizetéssel is rendezhető.</p>

<h2>6. Intézményi adatok</h2>
<p>A kereskedő, a {{ $merchant['name'] }} székhelyének országa és országkódja: {{ $merchant['country'] }}.</p>
@if($merchant['address'])
    <p>Székhely: {{ $merchant['address'] }}</p>
@endif
@if($merchant['tax_number'])
    <p>Adószám: {{ $merchant['tax_number'] }}</p>
@endif

<h2>7. Kapcsolat és panaszkezelés</h2>
<p>Az elszámolással, a fizetendő összeggel, reklamációval vagy visszatérítéssel kapcsolatos ügyekben az intézmény ügyfélszolgálata jár el.</p>
