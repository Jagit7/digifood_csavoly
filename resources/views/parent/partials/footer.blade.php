@php
    // Felhasználói kérés: ha egyetlen kapcsolt intézménynél sincs
    // bekapcsolva a kártyás fizetés, se a CIB logó, se a kifejezetten
    // a CIB-es online kártyás fizetéshez kötődő tájékoztatók ne
    // jelenjenek meg a footerben (ld. a tartalmukat: mindegyik konkrétan
    // a CIB Bank online fizetési folyamatáról szól).
    $footerAnyCardPaymentEnabled = auth()->user()?->guardians()
        ->where('active', true)
        ->with('institution.setting')
        ->get()
        ->pluck('institution')
        ->filter()
        ->contains(fn ($institution) => (bool) ($institution->setting?->card_payment_enabled ?? false)) ?? false;
@endphp

<div class="footer border-top bg-white">
    <div class="copyright py-3">
        <div class="container-fluid">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
                <div class="d-flex flex-column gap-2">
                    <p class="mb-0">Minden jog fenntartva! © Digifood {{ date('Y') }}</p>
                    @if($footerAnyCardPaymentEnabled)
                        <div>
                            <img src="{{ asset('images/cib/cib-card-logos-hu.png') }}" alt="CIB Bank és kártyalogók" style="max-width:min(100%, 280px); height:auto;">
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2 small">
                    <a href="{{ route('parent.legal.terms-of-service') }}" class="text-primary">ÁSZF</a>
                    <span class="text-muted">|</span>
                    <a href="{{ route('parent.legal.terms') }}" class="text-primary">Étkezési és fizetési feltételek</a>
                    <span class="text-muted">|</span>
                    @if($footerAnyCardPaymentEnabled)
                        <a href="{{ route('parent.legal.card-payment') }}" class="text-primary">Bankkártyás fizetési tájékoztató</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('parent.legal.card-payment-faq') }}" class="text-primary">CIB GYFK</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('parent.legal.data-processing') }}" class="text-primary">Adatkezelés (online fizetés)</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('parent.legal.payment-flow') }}" class="text-primary">Fizetési folyamat</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('parent.legal.complaints') }}" class="text-primary">Reklamáció és visszatérítés</a>
                        <span class="text-muted">|</span>
                        <a href="{{ route('parent.legal.customer-service') }}" class="text-primary">Ügyfélszolgálat</a>
                        <span class="text-muted">|</span>
                    @endif
                    <a href="{{ route('parent.legal.imprint') }}" class="text-primary">Impresszum</a>
                    <span class="text-muted">|</span>
                    <a href="{{ route('legal.privacy') }}" class="text-primary">Adatkezelési tájékoztató</a>
                </div>
            </div>
        </div>
    </div>
</div>
