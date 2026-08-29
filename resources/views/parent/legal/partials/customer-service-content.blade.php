<h2>Intézményi ügyfélszolgálat</h2>
<p>Az étkezési díjjal, a havi elszámolással, a reklamációval vagy a visszatérítéssel kapcsolatos megkeresések elsődleges címzettje az intézmény.</p>

<p class="mb-1"><strong>{{ $merchant['name'] }}</strong></p>
@if($merchant['address'])
    <p class="mb-1">{{ $merchant['address'] }}</p>
@endif
@if($merchant['email'])
    <p class="mb-1">E-mail: <a href="mailto:{{ $merchant['email'] }}">{{ $merchant['email'] }}</a></p>
@endif
@if($merchant['phone'])
    <p class="mb-1">Telefon: {{ $merchant['phone'] }}</p>
@endif

<h2 class="mt-4">Technikai tájékoztatás</h2>
<p>A Digifood a fizetési folyamat technikai közvetítője. A bankkártyaadatokhoz nem fér hozzá, ezért bankkártyaadat-helyesbítést vagy kártyahitelesítési problémát nem tud kezelni. Ilyen esetben a CIB fizetési felületen megjelent hiba és a kibocsátó bank tájékoztatása az irányadó.</p>
