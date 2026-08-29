<p class="mb-1"><strong>{{ $merchant['name'] }}</strong></p>
@if($merchant['institution_name'] && $merchant['institution_name'] !== $merchant['name'])
    <p class="mb-1">{{ $merchant['institution_name'] }}</p>
@endif
@if($merchant['address'])
    <p class="mb-1">{{ $merchant['address'] }}</p>
@endif
@if($merchant['tax_number'])
    <p class="mb-1">Adószám: {{ $merchant['tax_number'] }}</p>
@endif
@if($merchant['company_registration_number'])
    <p class="mb-1">Cégjegyzékszám: {{ $merchant['company_registration_number'] }}</p>
@endif
@if($merchant['om_identifier'])
    <p class="mb-1">OM azonosító: {{ $merchant['om_identifier'] }}</p>
@endif
@if($merchant['email'])
    <p class="mb-1">E-mail: <a href="mailto:{{ $merchant['email'] }}">{{ $merchant['email'] }}</a></p>
@endif
@if($merchant['phone'])
    <p class="mb-1">Telefon: {{ $merchant['phone'] }}</p>
@endif
<p class="mb-0">Ország: {{ $merchant['country'] }}</p>
