@extends('layouts.superadmin')

@section('title', 'Beléptető kioszk')

@section('content')
<div class="container-fluid">
    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Beléptető kioszk',
        'subtitle' => 'Korlátozott vonalkódos beléptető hozzáférés kezelése',
        'buttons' => [
            [
                'text' => 'Vissza a vonalkódokhoz',
                'url' => route('dashboard.institution.children.barcodes.index'),
                'icon' => 'fa-solid fa-arrow-left',
                'class' => 'btn btn-light',
            ],
        ],
    ])

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Kioszk hozzáférés</h4>
                </div>
                <div class="card-body">
                    @if(! $setting->barcodeEntryEnabled())
                        <div class="alert alert-warning mb-0">
                            A vonalkódos beléptető modul nem része az intézmény előfizetésének. A vonalkód-generálás és kártyanyomtatás továbbra is használható.
                        </div>
                    @else
                        <form method="POST" action="{{ route('dashboard.institution.children.barcodes.kiosk.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">Kioszk bejelentkezési e-mail</label>
                                <input type="text" class="form-control" value="{{ $kioskUser?->email ?? 'A mentés után jön létre.' }}" readonly>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="password">Kioszk jelszó</label>
                                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="password_confirmation">Jelszó megerősítése</label>
                                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="admin_pin">Admin PIN-kód</label>
                                    <input id="admin_pin" type="password" name="admin_pin" class="form-control @error('admin_pin') is-invalid @enderror" inputmode="numeric" required>
                                    @error('admin_pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="admin_pin_confirmation">PIN-kód megerősítése</label>
                                    <input id="admin_pin_confirmation" type="password" name="admin_pin_confirmation" class="form-control" inputmode="numeric" required>
                                </div>
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $kioskUser?->is_active ?? true))>
                                <label class="form-check-label" for="is_active">Kioszk hozzáférés aktív</label>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-floppy-disk me-1"></i>Hozzáférés mentése
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Állapot</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Előfizetési modul</div>
                        <div class="fw-semibold">{{ $setting->barcodeEntryEnabled() ? 'Engedélyezve' : 'Nincs engedélyezve' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Kioszk felhasználó</div>
                        <div class="fw-semibold">{{ $kioskUser?->name ?? 'Még nincs létrehozva' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Hozzáférés állapota</div>
                        <div class="fw-semibold">{{ $kioskUser?->is_active ? 'Aktív' : 'Inaktív' }}</div>
                    </div>
                    @if(session('kiosk_email') || $generatedPassword)
                        <div class="alert alert-info mb-0">
                            <div><strong>E-mail:</strong> {{ session('kiosk_email', $kioskUser?->email) }}</div>
                            @if($generatedPassword)
                                <div><strong>Új jelszó:</strong> {{ $generatedPassword }}</div>
                            @endif
                            <div class="mt-2 small">A kioszkot külön böngészőablakban vagy külön eszközön, ezzel a hozzáféréssel kell megnyitni.</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Vezérlő kártya (aktiválás / lezárás vonalkóddal)</h4>
                </div>
                <div class="card-body">
                    @if($setting->hasKioskControlCard())
                        <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
                            <div class="bg-light border rounded p-2">
                                {!! $controlCardSvg !!}
                            </div>
                            <div>
                                <div class="text-muted small">Legutóbb generálva</div>
                                <div class="fw-semibold">{{ $setting->barcode_kiosk_control_generated_at?->timezone(config('app.timezone'))->format('Y.m.d. H:i') }}</div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('dashboard.institution.children.barcodes.kiosk.control-card.print') }}" class="btn btn-primary" target="_blank" rel="noopener">
                                <i class="fa-solid fa-print me-1"></i>Kártya nyomtatása
                            </a>
                            <form method="POST" action="{{ route('dashboard.institution.children.barcodes.kiosk.control-card.regenerate') }}" onsubmit="return confirm('Biztosan újragenerálod? A korábban kinyomtatott kártya ezután nem fog működni.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fa-solid fa-rotate me-1"></i>Újragenerálás
                                </button>
                            </form>
                        </div>
                    @else
                        <p class="text-muted">Ehhez az intézményhez még nincs vezérlő kártya generálva. Ez a kártya (nem tartozik diákhoz vagy dolgozóhoz) fogja lehetővé tenni, hogy a kioszkot a vonalkód-olvasóval, érintés és gépelés nélkül lehessen aktiválni/lezárni.</p>
                        <form method="POST" action="{{ route('dashboard.institution.children.barcodes.kiosk.control-card.generate') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-barcode me-1"></i>Vezérlő kártya generálása
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">Böngésző-kötés</h4>
                </div>
                <div class="card-body">
                    @if($setting->hasKioskDevice())
                        <div class="mb-3">
                            <div class="text-muted small">Kötve</div>
                            <div class="fw-semibold">{{ $setting->barcode_kiosk_device_bound_at?->timezone(config('app.timezone'))->format('Y.m.d. H:i') }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Legutóbb használva</div>
                            <div class="fw-semibold">{{ $setting->barcode_kiosk_device_last_used_at?->timezone(config('app.timezone'))->format('Y.m.d. H:i') }}</div>
                        </div>
                        <p class="text-muted small">A kioszk gépének böngészője kötve van - nem kell naponta bejelentkezni. Ha lecserélitek a gépet, előbb válaszd le, majd az új gépen jelentkezz be egyszer a kioszk e-mail címmel és jelszóval.</p>
                        <form method="POST" action="{{ route('dashboard.institution.children.barcodes.kiosk.device.unbind') }}" onsubmit="return confirm('Biztosan leválasztod? A kioszk gépén a következő betöltéskor újra be kell majd jelentkezni e-mail címmel és jelszóval.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="fa-solid fa-link-slash me-1"></i>Leválasztás
                            </button>
                        </form>
                    @else
                        <p class="text-muted">Ehhez az intézményhez még nincs böngésző kötve. A kioszk gépén jelentkezz be egyszer, billentyűzettel, a fenti kioszk e-mail címmel és jelszóval - ez a böngésző ezután automatikusan felismeri magát.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="fa-solid fa-circle-info me-1 text-primary"></i>Hogyan működik?
                    </h4>
                </div>
                <div class="card-body">
                    <ol class="ps-3 mb-3">
                        <li class="mb-2">
                            <strong>Első beüzemelés (egyszeri, billentyűzettel):</strong>
                            jelentkezz be a kioszk gépének böngészőjében a fenti kioszk e-mail címmel és jelszóval.
                        </li>
                        <li class="mb-2">
                            <strong>Vezérlő kártya:</strong>
                            generáld le és nyomtasd ki (balra) - ezt tartsd biztonságos helyen, mert bárki, aki beolvassa, aktiválhatja/lezárhatja a kioszkot.
                        </li>
                        <li class="mb-2">
                            <strong>Böngésző-kötés</strong> -
                            az első bejelentkezés után a kioszk böngészője automatikusan felismeri saját magát, így naponta nem kell újra e-mailt/jelszót gépelni.
                        </li>
                        <li class="mb-2">
                            <strong>Aktiválás / lezárás beolvasással</strong> -
                            a vezérlő kártya beolvasásával lehet elindítani és leállítani a kioszkot, érintőképernyő és billentyűzet nélkül.
                        </li>
                        <li>
                            <strong>Teljes képernyő</strong> -
                            az első vonalkód-beolvasáskor a kioszk automatikusan teljes képernyőre vált. A legmegbízhatóbb módszer, ha a kioszk gépén a böngészőt kiosk-módban indítjátok (pl. Chrome-nál a <code>--kiosk</code> kapcsolóval) - ezt egyszer, a beüzemeléskor érdemes beállítani.
                        </li>
                    </ol>
                    <div class="text-muted small mb-0">A fizetési/számlázási működést a kioszk egyik funkciója sem érinti.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
