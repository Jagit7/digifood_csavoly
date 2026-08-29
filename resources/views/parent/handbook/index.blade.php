@extends('layouts.parent')

@section('page_title', 'Kézikönyv')

@push('styles')
<style>
    .hb-hero {
        border: 0;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, .18), transparent 34%),
            linear-gradient(135deg, #12344a 0%, #8f3b25 52%, #d94a16 100%);
        color: #fff;
        box-shadow: 0 16px 36px rgba(29, 53, 87, .18);
    }
    .hb-hero .card-body { padding: 1.75rem; }
    .hb-hero-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .hb-hero-label {
        display: block;
        font-size: .78rem;
        opacity: .78;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .hb-hero-value {
        display: block;
        margin-top: .25rem;
        font-size: 1rem;
        font-weight: 600;
    }

    .hb-side{position:sticky;top:15px;}
    .hb-side-card{
        background:#fff;border-radius:14px;box-shadow:0 4px 16px rgba(30,40,80,.07);
        padding:16px;margin-bottom:16px;
    }
    .hb-side-card h6{
        margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;
        color:#868e96;font-weight:700;
    }
    .hb-side-nav a{
        display:flex;align-items:center;gap:9px;padding:7px 9px;border-radius:8px;
        color:#2b2f3a;font-size:13.2px;font-weight:600;margin-bottom:2px;
    }
    .hb-side-nav a:hover{background:#f1f3f5;text-decoration:none;}
    .hb-side-nav a .hb-dot{width:8px;height:8px;border-radius:50%;flex:none;}
    .hb-legend-item{display:flex;align-items:center;gap:8px;font-size:12.4px;margin-bottom:8px;color:#6b7280;}
    .hb-legend-item:last-child{margin-bottom:0;}

    .hb-section{
        scroll-margin-top:15px;
        padding:22px 24px;
        border-bottom:1px solid #e9ecef;
    }
    .hb-section:first-child{padding-top:24px;}
    .hb-section:last-child{border-bottom:none;}
    .hb-section .hb-section-head{display:flex;align-items:flex-start;gap:13px;margin-bottom:14px;}
    .hb-icon-box{
        width:40px;height:40px;border-radius:12px;flex:none;
        display:flex;align-items:center;justify-content:center;font-size:18px;
    }
    .hb-icon-box.green{background:#e6f7f0;color:#2fb380;}
    .hb-icon-box.orange{background:#fff4e0;color:#f59f00;}
    .hb-icon-box.blue{background:#e7f5ff;color:#339af0;}
    .hb-icon-box.purple{background:#f2edfe;color:#845ef7;}
    .hb-icon-box.red{background:#fdeceb;color:#f0483e;}
    .hb-icon-box.gray{background:#f1f3f5;color:#868e96;}
    .hb-section h4{margin:0 0 3px;font-size:16.5px;font-weight:800;}
    .hb-section .hb-section-head p{margin:0;color:#6b7280;font-size:13px;}
    .hb-section .hb-section-body h5{font-size:14px;font-weight:700;margin:16px 0 8px;}
    .hb-section .hb-section-body h5:first-child{margin-top:0;}
    .hb-section .hb-section-body p{margin:0 0 10px;color:#3c4150;}
    .hb-section .hb-section-body ul{margin:0 0 12px;padding-left:20px;color:#3c4150;}
    .hb-section .hb-section-body li{margin-bottom:5px;}

    .hb-callout{
        display:flex;gap:10px;background:#e7f5ff;border-left:3px solid #339af0;
        border-radius:8px;padding:11px 13px;font-size:13.1px;color:#1c4e80;margin:12px 0;
    }
    .hb-callout.warn{background:#fff4e0;border-color:#f59f00;color:#8a5a00;}

    .hb-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11.8px;font-weight:700;}
    .hb-badge.green{background:#e6f7f0;color:#2fb380;}
    .hb-badge.blue{background:#e7f5ff;color:#339af0;}
    .hb-badge.gray{background:#f1f3f5;color:#868e96;}
    .hb-badge.red{background:#fdeceb;color:#f0483e;}

    .hb-mini-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:12px 0 4px;}
    .hb-mini-card{border:1px solid #e9ecef;border-radius:10px;padding:12px 13px;}
    .hb-mini-card .hb-mc-top{display:flex;align-items:center;gap:8px;margin-bottom:5px;}
    .hb-mini-card .hb-mc-dot{width:9px;height:9px;border-radius:50%;flex:none;}
    .hb-mini-card .hb-mc-title{font-weight:700;font-size:13px;}
    .hb-mini-card .hb-mc-text{font-size:12.3px;color:#6b7280;}

    .hb-kv{width:100%;border-collapse:collapse;font-size:13.2px;margin:10px 0;}
    .hb-kv th,.hb-kv td{padding:8px 10px;border-bottom:1px solid #e9ecef;text-align:left;}
    .hb-kv th{width:34%;color:#6b7280;font-weight:600;}

    .hb-status-list{list-style:none;margin:0;padding:0;}
    .hb-status-list li{display:flex;gap:10px;padding:8px 0;border-bottom:1px dashed #e9ecef;}
    .hb-status-list li:last-child{border-bottom:none;}
    .hb-status-dot{width:9px;height:9px;border-radius:50%;margin-top:6px;flex:none;}
    .hb-status-dot.green{background:#2fb380;}
    .hb-status-dot.blue{background:#339af0;}
    .hb-status-dot.orange{background:#f59f00;}
    .hb-status-dot.red{background:#f0483e;}
    .hb-status-list .hb-s-title{font-weight:700;font-size:13.2px;}
    .hb-status-list .hb-s-text{font-size:12.6px;color:#6b7280;}
</style>
@endpush

@section('content')
<div style="margin-bottom:30px;">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Kézikönyv',
        'subtitle' => 'Szülői útmutató – hogyan működik az étkeztetés, a fizetés és a számlázás, és miben tud segíteni az intézmény',
    ])

    <div class="card hb-hero mb-4">
        <div class="card-body">
            <div class="row align-items-center g-4">
                <div class="col-xl-7">
                    <span class="badge bg-light text-dark mb-3">Digifood dokumentáció</span>
                    <h2 class="text-white mb-2">Szülői felület kézikönyve</h2>
                    <p class="mb-0 text-white-50">
                        Ebben az útmutatóban összefoglaljuk, mit láthatsz és mit tudsz saját magad beállítani a szülői
                        felületen - a gyermeked adatlapjától az étkezés lemondásán át a havi fizetésig és a számlákig -,
                        valamint hogy mely kérdésekkel kell az intézmény adminisztrátorához fordulnod.
                    </p>
                </div>
                <div class="col-xl-5">
                    <div class="hb-hero-meta">
                        <div>
                            <span class="hb-hero-label">Mai dátum</span>
                            <span class="hb-hero-value">{{ $pageDate->format('Y. m. d.') }}</span>
                        </div>
                        <div>
                            <span class="hb-hero-label">Intézmény</span>
                            <span class="hb-hero-value">{{ $institutionLabel }}</span>
                        </div>
                        <div>
                            <span class="hb-hero-label">Fejezetek száma</span>
                            <span class="hb-hero-value">8</span>
                        </div>
                        <div>
                            <span class="hb-hero-label">Célközönség</span>
                            <span class="hb-hero-value">Szülő / gondviselő</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <div class="col-lg-3">
            <div class="hb-side">
                <div class="hb-side-card">
                    <h6>Tartalom</h6>
                    <nav class="hb-side-nav">
                        <a href="#hb-gyermekeim"><span class="hb-dot" style="background:#2fb380"></span>Gyermekeim</a>
                        <a href="#hb-etkezesek"><span class="hb-dot" style="background:#339af0"></span>Étkezések, lemondások</a>
                        <a href="#hb-menu"><span class="hb-dot" style="background:#845ef7"></span>Menüválasztás (A/B)</a>
                        <a href="#hb-elszamolas"><span class="hb-dot" style="background:#f0483e"></span>Havi elszámolás, fizetés</a>
                        <a href="#hb-befizetesek"><span class="hb-dot" style="background:#2fb380"></span>Befizetések</a>
                        <a href="#hb-szamlak"><span class="hb-dot" style="background:#f59f00"></span>Számlák</a>
                        <a href="#hb-fiokom"><span class="hb-dot" style="background:#845ef7"></span>Fiókom</a>
                        <a href="#hb-admin"><span class="hb-dot" style="background:#868e96"></span>Mihez kell az admin?</a>
                    </nav>
                </div>

                <div class="hb-side-card">
                    <h6>Állapot-jelvények</h6>
                    <div class="hb-legend-item"><span class="hb-badge green">Étkező</span> aktív, folyamatban lévő étkezés</div>
                    <div class="hb-legend-item"><span class="hb-badge blue">Étkező (ütemezve)</span> jövőben induló étkezés</div>
                    <div class="hb-legend-item"><span class="hb-badge gray">Nem étkező</span> nincs aktív / ütemezett étkezés</div>
                    <div class="hb-legend-item"><span class="hb-badge red">Lezárva</span> a jogviszony lezárult</div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
          <div class="card">
            <div class="card-body p-0">

            <div class="hb-section" id="hb-gyermekeim">
                <div class="hb-section-head">
                    <div class="hb-icon-box green">🧒</div>
                    <div>
                        <h4>Gyermekeim</h4>
                        <p>Kapcsolt gyermekeid listája és részletes adatlapja</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Gyermekeim</strong> menüpont alatt látod az összes hozzád kapcsolt gyermeket - akár több intézményből is, ha egynél több helyre jár a gyermeked, vagy több gyermeked is van. Egy gyermek nevére kattintva megnyílik a részletes adatlapja: étkezési státusz, menücsomag, kedvezmény, allergia/diéta és a kapcsolt intézmény adatai.</p>

                    <div class="hb-callout">
                        <span>ℹ️</span>
                        <span>Ez az oldal <strong>kizárólag megtekintésre</strong> szolgál. A gyermek adatai (kedvezmény, osztály/csoport, engedélyek, kapcsolt intézmény) innen nem módosíthatók - ha valami nem stimmel, az intézmény adminisztrátorát kell megkeresni (lásd a <a href="#hb-admin">Mihez kell az admin?</a> fejezetet).</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-etkezesek">
                <div class="hb-section-head">
                    <div class="hb-icon-box blue">🍽️</div>
                    <div>
                        <h4>Étkezések és lemondások</h4>
                        <p>Napi lemondás, visszaállítás, határidők</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Lemondások</strong> menüpontban heti vagy havi naptár nézetben látod, mely napokon étkezik a gyermeked, és melyik napokra tudod lemondani az étkezést. A lemondás mindig <strong>egy konkrét napra</strong> vonatkozik - egyszerre több gyermeket is kijelölhetsz, ha egy adott napra mindegyiküknél le szeretnéd mondani az étkezést.</p>

                    <h5>Amit fontos tudni</h5>
                    <ul>
                        <li>Minden naphoz tartozik egy <strong>lemondási határidő</strong> (pl. "ma 9:00-ig" vagy "holnap 9:00-ig") - ezt az intézmény állítja be, és a naptárban mindig látod a pontos időpontot.</li>
                        <li>A határidő lejárta után a nap "Határidő lejárt" jelzéssel jelenik meg, és arra a napra már nem tudsz lemondást rögzíteni.</li>
                        <li>Csak azt a lemondást tudod <strong>visszaállítani</strong> (meggondolni magad), amit te magad rögzítettél - az intézmény által rögzített lemondásokat nem.</li>
                        <li>Ha egy nap "Csoportszintű lemondás" jelzéssel jelenik meg, azt az intézmény zárta le az egész osztálynak/csoportnak (pl. kirándulás miatt) - erre a napra egyénileg nincs teendőd.</li>
                    </ul>

                    <div class="hb-callout warn">
                        <span>⚠️</span>
                        <span>Visszatérő, "minden pénteken mondd le" jellegű szabály beállítására jelenleg nincs lehetőség - minden napot egyenként, külön kell lemondanod.</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-menu">
                <div class="hb-section-head">
                    <div class="hb-icon-box purple">📋</div>
                    <div>
                        <h4>Menüválasztás (A/B menü)</h4>
                        <p>Ha az intézmény választható menüt biztosít</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>Néhány intézménynél a napi étkezéshez <strong>A és B menü</strong> közül lehet választani. Ha ez elérhető, a <strong>Menüválasztás</strong> menüpont alatt, intézményenként és gyermekenként csoportosítva látod a választható napokat, a kiválasztási határidővel együtt.</p>

                    <div class="hb-mini-grid">
                        <div class="hb-mini-card">
                            <div class="hb-mc-top"><span class="hb-mc-dot" style="background:#339af0"></span><span class="hb-mc-title">Választási időszak nyitva</span></div>
                            <div class="hb-mc-text">A "Menü A" / "Menü B" gombra kattintva bármikor módosíthatod a választásod a határidőig. Ha nem választasz, a rendszer alapértelmezetten "A" menüt rögzít.</div>
                        </div>
                        <div class="hb-mini-card">
                            <div class="hb-mc-top"><span class="hb-mc-dot" style="background:#868e96"></span><span class="hb-mc-title">Választási időszak lezárva</span></div>
                            <div class="hb-mc-text">A határidő után az adott nap zároltként jelenik meg, a választás már nem módosítható.</div>
                        </div>
                    </div>

                    <div class="hb-callout">
                        <span>ℹ️</span>
                        <span>Diétás étkezésre beállított gyermeknél nincs A/B menüválasztás - ő automatikusan a számára összeállított diétás menüt kapja.</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-elszamolas">
                <div class="hb-section-head">
                    <div class="hb-icon-box red">💳</div>
                    <div>
                        <h4>Havi elszámolás és fizetés</h4>
                        <p>Fizetendő összeg, napi bontás, online kártyás fizetés</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Havi elszámolások</strong> oldalon, hónapról hónapra, gyermekenként és összesítve is látod a ténylegesen fizetendő összeget, valamint egy legördíthető napi bontást ("Napi fizetendő") arról, hogy az adott hónap mely napjaira mennyi étkezési díj került kiszámlázásra.</p>

                    <table class="hb-kv">
                        <tr><th>Fennmaradó összeg</th><td>Az adott hónapból még ki nem egyenlített, fizetendő rész - ez a legfontosabb szám ezen az oldalon.</td></tr>
                        <tr><th>Közös fizetés</th><td>Ha több gyermeked is van, egyetlen fizetéssel egyszerre is rendezheted a tartozásukat.</td></tr>
                        <tr><th>Online (bankkártyás) fizetés</th><td>Az elszámolás oldalról indítható - a rendszer előkészíti a fizetést, majd a kártyás fizetőoldalra irányít.</td></tr>
                    </table>

                    <div class="hb-callout warn">
                        <span>⚠️</span>
                        <span>A Digifood online, bankkártyás fizetési funkciója jelenleg <strong>tesztüzemben</strong> működik, intézményenként eltérő ütemben áll csak be. Ha a fizetés indítása nem elérhető, vagy elakad, az intézmény tud tájékoztatást adni az aktuálisan érvényes befizetési módról.</span>
                    </div>

                    <p>Az elszámolás <strong>összegét, tételeit vagy lezárását</strong> szülőként nem tudod módosítani - ha eltérést tapasztalsz, azt az intézménnyel kell tisztázni.</p>
                </div>
            </div>

            <div class="hb-section" id="hb-befizetesek">
                <div class="hb-section-head">
                    <div class="hb-icon-box green">👛</div>
                    <div>
                        <h4>Befizetések</h4>
                        <p>Fizetési előzmények áttekintése</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Befizetések</strong> oldal az aktuális fizetési kötelezettséget és a korábbi befizetéseid listáját mutatja, gyermekenkénti bontásban - ez egy tisztán áttekintő (csak megtekinthető) felület.</p>

                    <div class="hb-callout">
                        <span>ℹ️</span>
                        <span>Fizetést innen nem lehet indítani - az online fizetés a <a href="#hb-elszamolas">Havi elszámolások</a> oldalról érhető el.</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-szamlak">
                <div class="hb-section-head">
                    <div class="hb-icon-box orange">🧾</div>
                    <div>
                        <h4>Számlák</h4>
                        <p>Kiállított számláid megtekintése és letöltése</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Számlák</strong> menüpont alatt találod a hozzád (vagy a hozzád kapcsolt gyermekhez) kiállított számlákat, évek szerint szűrve. Minden számla PDF formátumban letölthető.</p>

                    <div class="hb-callout warn">
                        <span>⚠️</span>
                        <span>Számlát kiállítani, módosítani vagy stornózni szülőként nem lehet - ez kizárólag az intézmény adminisztrátorának feladata.</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-fiokom">
                <div class="hb-section-head">
                    <div class="hb-icon-box purple">👤</div>
                    <div>
                        <h4>Fiókom</h4>
                        <p>Saját adataid: személyes adatok, lakcím, számlázási adatok, jelszó</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Fiókom</strong> oldalon négy, egymástól függetlenül menthető blokkban szerkesztheted a saját (gondviselői) adataidat - mindegyik blokknak külön "Mentés" gombja van, így az egyik rész módosítása nem befolyásolja a többit.</p>

                    <table class="hb-kv">
                        <tr><th>Személyes adatok</th><td>Név, e-mail cím, telefonszám.</td></tr>
                        <tr><th>Lakcím</th><td>Ország, irányítószám, település, közterület neve/jellege, házszám, emelet, ajtó.</td></tr>
                        <tr><th>Számlázási adatok</th><td>Bekapcsolható "megegyezik a lakcímmel" opció (ekkor a rendszer automatikusan átmásolja a lakcímet), vagy önálló számlázási név, cím és adószám megadása.</td></tr>
                        <tr><th>Biztonság</th><td>Jelszó módosítása - a jelenlegi jelszó megadásával.</td></tr>
                    </table>

                    <div class="hb-callout warn">
                        <span>⚠️</span>
                        <span>A <strong>bankszámla adatok</strong> (számlatulajdonos neve, számlaszám) a szülői felületen nem módosíthatók - ezek megváltoztatásához az intézmény vezetőségéhez kell fordulni.</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-admin">
                <div class="hb-section-head">
                    <div class="hb-icon-box gray">🧭</div>
                    <div>
                        <h4>Mihez kell az intézményi admin?</h4>
                        <p>Amit a szülői felületen nem tudsz módosítani, csak kérni az intézménytől</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>Az alábbi témákban a szülői felület csak megtekintést biztosít - a módosítást az intézmény adminisztrátora (vagy titkársága) tudja elvégezni:</p>

                    <ul class="hb-status-list">
                        <li>
                            <span class="hb-status-dot red"></span>
                            <div>
                                <div class="hb-s-title">Bankszámla adatok</div>
                                <div class="hb-s-text">A számlatulajdonos neve és a számlaszám csak az intézménynél módosítható.</div>
                            </div>
                        </li>
                        <li>
                            <span class="hb-status-dot orange"></span>
                            <div>
                                <div class="hb-s-title">Kedvezmény típusa és mértéke</div>
                                <div class="hb-s-text">A kedvezmény beállítását és a hozzá tartozó igazolások elbírálását az intézmény végzi.</div>
                            </div>
                        </li>
                        <li>
                            <span class="hb-status-dot blue"></span>
                            <div>
                                <div class="hb-s-title">Osztály / csoport, kapcsolt intézmény</div>
                                <div class="hb-s-text">A gyermek besorolása és intézményi hozzárendelése adminisztrátori feladat.</div>
                            </div>
                        </li>
                        <li>
                            <span class="hb-status-dot green"></span>
                            <div>
                                <div class="hb-s-title">Étkezési csomag, menütípus beállítása</div>
                                <div class="hb-s-text">Azt, hogy a gyermek milyen étkezési csomagra/menütípusra van beállítva, az intézmény indítja el és kezeli - szülőként csak az eredményét látod.</div>
                            </div>
                        </li>
                        <li>
                            <span class="hb-status-dot orange"></span>
                            <div>
                                <div class="hb-s-title">Diétás igény, allergia rögzítése</div>
                                <div class="hb-s-text">Új diétás/allergiás igény bejelentését az intézménynél kell kezdeményezni, a szükséges igazolásokkal.</div>
                            </div>
                        </li>
                    </ul>

                    <div class="hb-callout">
                        <span>📞</span>
                        <span>Az intézmény elérhetőségeit (név, cím, telefonszám, e-mail cím) az <a href="{{ route('parent.legal.imprint') }}">Impresszum</a> oldalon találod.</span>
                    </div>
                </div>
            </div>

            </div>
          </div>
        </div>

    </div>

</div>
@endsection
