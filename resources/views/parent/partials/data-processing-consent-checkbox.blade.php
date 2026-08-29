{{-- Bankkártyás fizetés indítása előtti, banki előírás szerint kötelező
     adatkezelési hozzájárulási checkbox. A szöveg a CIB Bank által megadott
     "Nyilatkozat" verbatim szövege, kizárólag a kereskedő nevének
     behelyettesítésével. Elvárt változók: $merchantName (string),
     $dataProcessingRoute (a tájékoztató oldal route neve), opcionálisan
     $consentId (egyedi HTML id, ha egy oldalon több ilyen checkbox is
     szerepelne). --}}
@php
    $consentFieldId = 'data-processing-consent-' . ($consentId ?? 'default');
@endphp
<div class="form-check mt-3 text-start">
    <input
        class="form-check-input @error('data_processing_consent') is-invalid @enderror"
        type="checkbox"
        name="data_processing_consent"
        value="1"
        id="{{ $consentFieldId }}"
        required
        {{ old('data_processing_consent') ? 'checked' : '' }}
    >
    <label class="form-check-label small" for="{{ $consentFieldId }}">
        Kijelentem, hogy az <a href="{{ route($dataProcessingRoute) }}" target="_blank" rel="noopener">adatkezeléshez kapcsolódó tájékoztatást</a> megértettem és tudomásul vettem. Ezennel önkéntesen és megfelelő tájékoztatás birtokában hozzájárulok ahhoz, hogy {{ $merchantName }} az önkéntesen megadott személyes adataimat a tájékoztatóban meghatározott célból továbbítsa a CIB Bank Zrt. részére.
    </label>
    @error('data_processing_consent')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
