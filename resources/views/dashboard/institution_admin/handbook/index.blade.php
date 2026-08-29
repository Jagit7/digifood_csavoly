@extends('layouts.superadmin')

@section('title', 'Kézikönyv')

@push('styles')
<style>
    .hb-hero {
        border: 0;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(255, 255, 255, .18), transparent 34%),
            linear-gradient(135deg, #1d3557 0%, #355070 48%, #4a6fa5 100%);
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

    .hb-timeline{list-style:none;margin:14px 0 4px;padding:0;position:relative;}
    .hb-timeline::before{content:"";position:absolute;left:15px;top:6px;bottom:6px;width:2px;background:#e9ecef;}
    .hb-timeline li{position:relative;padding:0 0 20px 42px;}
    .hb-timeline li:last-child{padding-bottom:0;}
    .hb-t-icon{
        position:absolute;left:0;top:0;width:30px;height:30px;border-radius:50%;
        display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:800;
        color:#fff;background:#4c6ef5;box-shadow:0 0 0 4px #fff;
    }
    .hb-t-icon.green{background:#2fb380;}
    .hb-t-icon.orange{background:#f59f00;}
    .hb-t-icon.blue{background:#339af0;}
    .hb-t-icon.gray{background:#868e96;}
    .hb-timeline .hb-t-title{font-weight:700;font-size:13.4px;margin-bottom:2px;}
    .hb-timeline .hb-t-text{color:#6b7280;font-size:12.9px;}

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
    .hb-status-list .hb-s-title{font-weight:700;font-size:13.2px;}
    .hb-status-list .hb-s-text{font-size:12.6px;color:#6b7280;}
</style>
@endpush

@section('content')
<div class="container-fluid">

    @include('layouts.partials.components.ui.page-header', [
        'title' => 'Kézikönyv',
        'subtitle' => 'Intézményi admin útmutató – minden fontos teendő és működési magyarázat egy helyen',
    ])

    <div class="card hb-hero mb-4">
        <div class="card-body">
            <div class="row align-items-center g-4">
                <div class="col-xl-7">
                    <span class="badge bg-light text-dark mb-3">Digifood dokumentáció</span>
                    <h2 class="text-white mb-2">Intézményi admin kézikönyv</h2>
                    <p class="mb-0 text-white-50">
                        {{ $institution->name ?? 'Intézményed' }} adminisztrációjának legfontosabb moduljai: gyermek- és
                        dolgozónyilvántartás, étkeztetés indítása és kezelése, fizetési kötelezettségek, számlázás
                        és étlapkezelés.
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
                            <span class="hb-hero-value">{{ $institution->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="hb-hero-label">Fejezetek száma</span>
                            <span class="hb-hero-value">9</span>
                        </div>
                        <div>
                            <span class="hb-hero-label">Célközönség</span>
                            <span class="hb-hero-value">Intézményi admin</span>
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
                        <a href="#hb-gyermekek"><span class="hb-dot" style="background:#2fb380"></span>Gyermekek kezelése</a>
                        <a href="#hb-dolgozok"><span class="hb-dot" style="background:#f59f00"></span>Dolgozók kezelése</a>
                        <a href="#hb-gondviselok"><span class="hb-dot" style="background:#845ef7"></span>Gondviselők, számlázási cím</a>
                        <a href="#hb-etkeztetes"><span class="hb-dot" style="background:#339af0"></span>Étkeztetés indítása, kezelése</a>
                        <a href="#hb-tanevindulas"><span class="hb-dot" style="background:#4c6ef5"></span>Új tanév indítása</a>
                        <a href="#hb-fizetesi"><span class="hb-dot" style="background:#f0483e"></span>Fizetési kötelezettségek</a>
                        <a href="#hb-szamlazas"><span class="hb-dot" style="background:#2fb380"></span>Számlázás</a>
                        <a href="#hb-etlapok"><span class="hb-dot" style="background:#f59f00"></span>Étlapok kezelése</a>
                        <a href="#hb-ui"><span class="hb-dot" style="background:#868e96"></span>Általános felületi elemek</a>
                    </nav>
                </div>

                <div class="hb-side-card">
                    <h6>Állapot-jelvények</h6>
                    <div class="hb-legend-item"><span class="hb-badge green">Étkező</span> aktív, folyamatban lévő beállítás</div>
                    <div class="hb-legend-item"><span class="hb-badge blue">Étkező (ütemezve)</span> jövőben induló beállítás</div>
                    <div class="hb-legend-item"><span class="hb-badge gray">Nem étkező</span> nincs aktív / ütemezett beállítás</div>
                    <div class="hb-legend-item"><span class="hb-badge red">Inaktív</span> kikapcsolt tétel (pl. étlap)</div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
          <div class="card">
            <div class="card-body p-0">

            <div class="hb-section" id="hb-gyermekek">
                <div class="hb-section-head">
                    <div class="hb-icon-box green">🧒</div>
                    <div>
                        <h4>Gyermekek kezelése</h4>
                        <p>Gyermeklista, keresés, szűrés és a gyermek adatlapja</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Személyek → Gyermeklista</strong> menüpont alatt található a teljes gyermeklista – alapadatok, számlázás, cím, gondviselők és tanulói adatok –, névre vagy oktatási azonosítóra kereshetően, osztály/csoport és állapot (aktív/inaktív) szerint szűrhetően.</p>

                    <div class="hb-callout">
                        <span>ℹ️</span>
                        <span>Az étkezéssel kapcsolatos adatok (menücsomag, kedvezmény, allergia/diéta, rendszeres lemondás, étkezési státusz) <strong>nem itt</strong>, hanem az <strong>Étkező gyermekek</strong> listán találhatók és szűrhetők – ez a lista a tanuló azonosító adataira, gondviselőire és számlázására fókuszál, hogy ne legyen túl széles.</span>
                    </div>

                    <h5>Gyorsstatisztikák és szűrők</h5>
                    <ul>
                        <li>A fejlécben négy statisztika jelenik meg: <strong>Aktív gyermekek</strong>, <strong>Gondviselő nélkül</strong>, <strong>Inaktív gyermekek</strong> és <strong>Még nem ellenőrzött</strong>.</li>
                        <li>A <strong>Hiányosság</strong> szűrő az adatminőségi problémákra szűr: nincs gondviselő, nincs számlázási profil, nincs e-mail-cím, nincs telefonszám, nincs cím – mindegyik opció mellett a találatok száma is látszik.</li>
                        <li>Az <strong>Ellenőrzés</strong> szűrő az „adatellenőrzés” állapotára szűkít (még nem ellenőrzött / már ellenőrzött).</li>
                    </ul>

                    <h5>Amit a listaoldalon látsz</h5>
                    <ul>
                        <li>Egy oldalon <strong>50 sor</strong> jelenik meg lapozás előtt.</li>
                        <li>Az <strong>Oszlopcsoportok</strong> kapcsolókkal (Számlázás, Cím, Gondviselő 1, Gondviselő 2) ki-be kapcsolhatók a táblázat oszlopblokkjai, ha egy adott adatkör most nem fontos – a névoszlop görgetés közben is a helyén marad.</li>
                        <li>A <strong>sárga háttérrel</strong> jelölt cellák azt jelzik, hogy a számlázási cím vagy e-mail eltér a gondviselő saját, nála nyilvántartott adataitól – a cellára húzva a pontos eltérés is megjelenik.</li>
                        <li>A <span style="color:#d68a1f;">⚠️</span> ikon a gondviselő neve mellett lehetséges duplikált gondviselő-rekordra (azonos név és telefon/e-mail egy másik gondviselőnél) figyelmeztet.</li>
                        <li>A név melletti zöld pipa az adatellenőrzés jelölésére/visszavonására szolgál – kattintva rögzíthető, hogy a gyermek adatait valaki átnézte.</li>
                        <li>A lista fölötti gombokkal új gyermek vehető fel, illetve elérhető a vonalkódos kártyák kezelése, a nyomtatható lista és a CSV-export.</li>
                    </ul>

                    <h5>Szerkesztés és visszalépés</h5>
                    <p>A gyermek adatlapjának szerkesztése után, illetve a „Vissza a listához” gombra kattintva a rendszer <strong>megőrzi az előzőleg beállított szűrést és lapszámot</strong> – nem kell újra beállítani a keresést vagy visszalapozni a lista elejére.</p>
                </div>
            </div>

            <div class="hb-section" id="hb-dolgozok">
                <div class="hb-section-head">
                    <div class="hb-icon-box orange">👩‍🍳</div>
                    <div>
                        <h4>Dolgozók kezelése</h4>
                        <p>Munkatársak nyilvántartása és saját étkezési jogosultságuk</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Személyek → Dolgozók</strong> menüpont a gyermeklistához hasonló logikát követ: 50 soros lapozás, valamint saját étkezési beállítás- és lemondás-kezelés minden dolgozóhoz. A szerkesztésből visszalépve itt (a gyermeklistától eltérően) egyelőre nem marad meg automatikusan a korábbi keresés és lapszám.</p>
                    <ul>
                        <li>A dolgozók étkezési jogosultsága ugyanazzal a beállítás-motorral (érvényességi időszakokkal) működik, mint a gyermekeké.</li>
                        <li>A dolgozói lemondáslista (<em>Dolgozói lemondások</em>) külön nézetben, de azonos szűrési és lapozási logikával érhető el.</li>
                    </ul>
                </div>
            </div>

            <div class="hb-section" id="hb-gondviselok">
                <div class="hb-section-head">
                    <div class="hb-icon-box purple">👪</div>
                    <div>
                        <h4>Gondviselők és számlázási cím</h4>
                        <p>Kapcsolattartók, gyermek–gondviselő kapcsolat, számlázási profil</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Személyek → Szülők / gondviselők</strong> alatt kezelhetők a szülők/törvényes képviselők adatai. Egy gyermekhez több gondviselő is tartozhat, és a számlázási profil – amely a ténylegesen a számlán megjelenő nevet, adószámot és címet tárolja – magához a <strong>gondviselőhöz</strong> tartozik, aki azt egy vagy több kapcsolt gyermekéhez rendelheti hozzá.</p>

                    <h5>Gyorsstatisztikák és szűrők</h5>
                    <ul>
                        <li>A fejlécben megjelenő statisztikák: <strong>Aktív gondviselők</strong>, <strong>Hiányzó e-mail</strong>, <strong>Hiányzó telefon</strong>, illetve – csak a számlázást is kezelő adminoknak – <strong>Számlafogadók</strong> (hány gondviselőhöz van gyermekre kijelölt, aktív számlázási profil).</li>
                        <li>Kereshető név, e-mail vagy telefonszám alapján, szűrhető állapotra (aktív/inaktív), és – ha jogosult rá az admin – arra is, hogy van-e kijelölt számlafogadó profilja.</li>
                        <li>Az <strong>„intézményi titkár”</strong> szerepkörű adminok nem látják és nem kezelik a számlázási adatokat: náluk a Számlázás oszlop, a Számlafogadó szűrő és a Számlafogadók statisztika is rejtve marad.</li>
                    </ul>

                    <h5>Amit a listaoldalon látsz</h5>
                    <ul>
                        <li>Soronként a kapcsolt gyermekek (csoportjukkal együtt), az elérhetőség (e-mail, telefon – hiányukra piros felirat figyelmeztet), a jogviszony (<strong>Törvényes képviselő</strong> / <strong>Értesítendő</strong> / egyszerű kapcsolt gondviselő), jogosultság esetén pedig a számlázási állapot jelenik meg.</li>
                        <li>A számlázási állapot „Részletek” gombja egy felugró ablakban mutatja a fizető típusát, a számlázási nevet, adószámot, e-mailt, fizetési módot, számlázási címet, a bankszámla-adatokat és a számlázáshoz kijelölt gyermekeket.</li>
                    </ul>

                    <table class="hb-kv">
                        <tr><th>Elsődleges számlafogadó</th><td>Gyermekenként egyszerre csak egy elsődleges számlafogadó lehet – ha egy gondviselő új gyermeket jelöl ki magának, a korábbi számlafogadó automatikusan lecserélődik ennél a gyermeknél.</td></tr>
                        <tr><th>Számlázási cím forrása</th><td>A számlán a számlázási profil címe jelenik meg, nem szükségszerűen a gondviselő saját, a „Lakcím” blokkban megadott lakcíme.</td></tr>
                    </table>

                    <div class="hb-callout warn">
                        <span>⚠️</span>
                        <span>A gondviselők listáján a szerkesztésből vagy mentésből a „Vissza a gondviselőkhöz” gomb és a „Mégsem” gomb <strong>mindig az alapértelmezett, szűretlen listára</strong> navigál vissza – a keresés és a lapszám itt (ellentétben a gyermeklistával) nem marad meg automatikusan. A gondviselők listája emellett 15, nem 50 soros lapozással működik.</span>
                    </div>

                    <p>A gondviselő szerkesztő oldalán a személyes és lakcímadatok mellett minden kapcsolt gyermekhez külön beállítható a rokonsági fok, valamint hogy a gondviselő törvényes képviselő-e, gyakorol-e szülői felügyeletet, értesítendő hozzátartozó-e, és jogosult-e családi pótlékra. Jogosult admin esetén itt kapcsolható be/szerkeszthető a számlázási profil és jelölhető ki, mely kapcsolt gyermek(ek) számláját kapja a gondviselő.</p>
                </div>
            </div>

            <div class="hb-section" id="hb-etkeztetes">
                <div class="hb-section-head">
                    <div class="hb-icon-box blue">🍽️</div>
                    <div>
                        <h4>Étkeztetés indítása és kezelése</h4>
                        <p>Egyéni és tömeges bekapcsolás, érvényességi időszakok</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Személyek → Étkező gyermekek</strong> menüpont külön kezeli, ki vesz részt az étkeztetésben – a nem étkező gyermek ettől még maradhat aktív intézményi gyermek, a két státusz független egymástól. Az étkezési jogosultságot a rendszer <strong>időszakokban</strong> (érvényesség kezdete – vége) tárolja, gyermekenként/dolgozónként.</p>

                    <h5>Gyorsstatisztikák és szűrők</h5>
                    <ul>
                        <li>A fejlécben: <strong>Étkező gyermekek</strong> (ma érvényes beállítással), <strong>Nem étkező gyermekek</strong>, <strong>Aktív gyermekek</strong> (intézményi státusz) és az <strong>Alapértelmezett csomag</strong> neve, amelyet a rendszer bekapcsoláskor felhasznál.</li>
                        <li>Szűrhető névre/azonosítóra, osztályra/csoportra, <strong>Étkezési státuszra</strong> (étkező/nem étkező), <strong>Intézményi státuszra</strong> (aktív/inaktív), <strong>Allergiára/érzékenységre</strong> (konkrét tételre vagy „bármilyen” allergiára/érzékenységre), valamint <strong>Kedvezményre</strong> – ez utóbbi csak az aktuálisan étkező gyermekek közül szűr.</li>
                    </ul>

                    <h5>Amit a listaoldalon látsz és szerkeszthetsz</h5>
                    <ul>
                        <li>Soronként: oktatási azonosító, osztály, intézményi státusz, étkezési státusz és „érvényes ettől” dátum, aktuális menübeállítás (teljes menü / csomag / egyedi étkezéstípusok), kedvezmény, allergia/érzékenység és rendszeres lemondás.</li>
                        <li>A <strong>kedvezmény</strong> soronként, egy legördülő menüből azonnal módosítható – a kiválasztás mentésekor a form automatikusan elküldődik, külön mentés gomb nélkül.</li>
                        <li>Egyedi soronként is be- vagy kikapcsolható az étkeztetés (dátum megadásával), a tömeges műveletek használata nélkül is.</li>
                    </ul>

                    <div class="hb-mini-grid">
                        <div class="hb-mini-card">
                            <div class="hb-mc-top"><span class="hb-mc-dot" style="background:#339af0"></span><span class="hb-mc-title">Egyéni beállítás</span></div>
                            <div class="hb-mc-text">A gyermek/dolgozó saját adatlapján, a hozzá tartozó Étkezési beállítások oldalon, egyesével – teljes előzménnyel és a jövőbeli dátum megadásának lehetőségével.</div>
                        </div>
                        <div class="hb-mini-card">
                            <div class="hb-mc-top"><span class="hb-mc-dot" style="background:#845ef7"></span><span class="hb-mc-title">Tömeges be- és kikapcsolás</span></div>
                            <div class="hb-mc-text">Személyek → Étkező gyermekek → jelöld ki a kívánt gyermekeket (fejléc-checkboxszal akár mindet), majd add meg a kezdő- vagy az utolsó étkezési napot. A tömeges bekapcsolás mindig a Teljes menü (intézményi alapértelmezett csomag) móddal indítja az étkezést; a tömeges kikapcsolás csak a jelenleg étkezők aktuális időszakát zárja le, rekordtörlés nélkül.</div>
                        </div>
                    </div>

                    <h5>Folyamatos (áthúzódó) jogosultság kezelése</h5>
                    <p>Ha egy gyermeknek volt egy előző tanévről nyitva maradt (végdátum nélküli) beállítása, a rendszer a tömeges és az egyéni bekapcsolásnál is <strong>automatikusan lezárja a régit</strong>, és az új dátumtól indítja az újat – ilyenkor a gyermek nem marad ki a bekapcsolásból, folyamatosan étkezőnek számít.</p>

                    <div class="hb-callout warn">
                        <span>⚠️</span>
                        <span>Ha egy gyermeknél már van jövőbeli dátumra beállított, még el nem indult jogosultsága, a rendszer az „Étkező (ütemezve)” jelvénnyel jelzi ezt – ez nem hibaüzenet, hanem a tervezett indulás megerősítése.</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-tanevindulas">
                <div class="hb-section-head">
                    <div class="hb-icon-box purple">🗓️</div>
                    <div>
                        <h4>Új tanév indítása – lépésről lépésre</h4>
                        <p>Példa: étkeztetés előre beütemezése szeptember 1-jétől</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <ul class="hb-timeline">
                        <li>
                            <span class="hb-t-icon blue">1</span>
                            <div class="hb-t-title">Navigálj a tömeges bekapcsolás oldalra</div>
                            <div class="hb-t-text">Személyek → Étkező gyermekek → Tömeges bekapcsolás</div>
                        </li>
                        <li>
                            <span class="hb-t-icon blue">2</span>
                            <div class="hb-t-title">Válaszd ki az érintett gyermekeket</div>
                            <div class="hb-t-text">Csoport vagy egyedi kijelöléssel jelöld ki, kik kezdjenek el (újra) étkezni.</div>
                        </li>
                        <li>
                            <span class="hb-t-icon orange">3</span>
                            <div class="hb-t-title">Írd felül az érvényesség kezdetét</div>
                            <div class="hb-t-text">Az alapértelmezett dátum a mai nap – ezt cseréld a tanévkezdés dátumára (pl. 09.01.), hogy a nyári hónapokra ne generálódjon étkezési díj.</div>
                        </li>
                        <li>
                            <span class="hb-t-icon green">4</span>
                            <div class="hb-t-title">Mentsd a bekapcsolást</div>
                            <div class="hb-t-text">A rendszer a folyamatosan étkező (áthúzódó) gyermekeknél is helyesen lezárja a régi, és elindítja az új időszakot.</div>
                        </li>
                        <li>
                            <span class="hb-t-icon gray">5</span>
                            <div class="hb-t-title">Ellenőrizd a listát</div>
                            <div class="hb-t-text">A gyermeklistán az érintettek „Étkező (ütemezve)” jelvénnyel jelennek meg a tanévkezdésig, utána automatikusan „Étkező” státuszra váltanak.</div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="hb-section" id="hb-fizetesi">
                <div class="hb-section-head">
                    <div class="hb-icon-box red">💳</div>
                    <div>
                        <h4>Havi fizetési kötelezettségek</h4>
                        <p>Hónapra szűrt díjtételek, keresési hatókör</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <p>A <strong>Fizetési kötelezettségek</strong> lista mindig a jelenleg kiválasztott hónaphoz tartozó tételeket mutatja – ez a fő különbség a gyermeklistához képest, amely az összes gyermeket felsorolja, hónaptól függetlenül.</p>
                    <div class="hb-callout">
                        <span>💡</span>
                        <span>Ha egy keresés (pl. „Juhász”) a gyermeklistán több találatot ad, mint a fizetési kötelezettségek listáján, az azért van, mert utóbbi csak azokra a gyermekekre szűkül, akiknek van díjtétele az adott hónapban.</span>
                    </div>
                </div>
            </div>

            <div class="hb-section" id="hb-szamlazas">
                <div class="hb-section-head">
                    <div class="hb-icon-box green">🧾</div>
                    <div>
                        <h4>Számlázás</h4>
                        <p>Számlakiállítás, szolgáltató-zárolás, sztornó</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <h5>Számlázási szolgáltató</h5>
                    <p>Az intézmény beállításaiban rögzített számlázási szolgáltató (pl. Billingo, Számlázz.hu vagy kézi/egyéb) az „Új számla” űrlapon <strong>nem választható át szabadon</strong> – a mező csak megjeleníti az aktuálisan beállított szolgáltatót, a szerveroldali validáció is ezt kényszeríti ki. Ez szándékos védelem a téves szolgáltatóváltás ellen.</p>

                    <h5>Sztornó (számla stornózása)</h5>
                    <p>Kiállított számla visszavonására a sztornó funkció szolgál, amely a kiválasztott szolgáltatónál a hivatalos sztornó/helyesbítő bizonylatot állítja ki, nem törli a számlát.</p>

                    <h5>Fix (readonly) mezők az űrlapon</h5>
                    <p>A gyermek, gondviselő, hónap és a kapcsolódó kötelezettség adatai az „Új számla” űrlapon <strong>szándékosan nem szerkeszthetők</strong> – ezek a kiválasztott fizetési kötelezettségből töltődnek be. Ténylegesen szerkeszthető mezők: <em>Vevő adatai</em>, valamint a <em>fizetési határidő</em> és <em>fizetési mód</em>.</p>
                </div>
            </div>

            <div class="hb-section" id="hb-etlapok">
                <div class="hb-section-head">
                    <div class="hb-icon-box orange">📄</div>
                    <div>
                        <h4>Étlapok kezelése</h4>
                        <p>Feltöltés, megtekintés, letöltés</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <h5>Heti és diétás étlapok</h5>
                    <p>Az <strong>Étlap &amp; menük</strong> menüpont alatt tölthetők fel a heti és diétás étlapok (PDF vagy kép formátumban).</p>
                    <ul>
                        <li><strong>Megtekintés</strong> – a listából megnyitja az étlap előnézetét: kép esetén közvetlenül, PDF esetén beágyazott nézegetőben.</li>
                        <li><strong>Megnyitás</strong> – a szerkesztő oldalon a feltöltött fájl közvetlenül, új lapon is megnyitható, csere nélkül is ellenőrizhető.</li>
                        <li><strong>Letöltés</strong> – a lista sorvégi ikonjával a fájl letölthető.</li>
                    </ul>

                    <h5>A/B + diétás menü – Excel import és szülői választás</h5>
                    <p>Az A/B menü <strong>nem</strong> PDF/kép feltöltéssel, hanem <strong>Excel/CSV importtal</strong> működik: az <strong>Étlap &amp; menük → A/B + diétás menük</strong> oldalon az „Excel import” gombbal indítható az importálás. A „Minta Excel letöltése” gombbal letölthető a kitöltendő <strong>sablon</strong> (Dátum, A menü, B menü, Diétás menü, Megjegyzés oszlopokkal) – ezt kell kitölteni és visszatölteni. A feltöltés sikeres importálás esetén automatikusan létrehoz egy új, aktív <strong>A/B menütervet</strong> a fájlban szereplő napokkal.</p>

                    <div class="hb-callout warn">
                        <span>⚠️</span>
                        <span>Az importált menüterv önmagában <strong>még nem</strong> jelenik meg a szülőknek választásra – ehhez külön el kell indítani a menüválasztást (lásd lent). Amíg ez nem történik meg, a terv csak az intézményi adminok számára látható.</span>
                    </div>

                    <h5>Menüválasztás indítása</h5>
                    <p>Az <strong>Étlap &amp; menük → Menüválasztások</strong> oldalon, az adott (feltöltött, aktív, legalább egy menünapot tartalmazó) A/B menütervnél a <strong>„Menüválasztás indítása”</strong> gombbal nyitható meg a választás. Ettől a pillanattól a szülők a saját felületükön kiválaszthatják gyermekenként az A vagy B menüt az érintett napokra, a diétás gyermekeknél pedig automatikusan a diétás menü érvényesül, választás nélkül.</p>

                    <table class="hb-kv">
                        <tr><th>Választási határnap</th><td>Az <strong>Intézmény beállítások</strong> oldal „A/B menü választás” blokkjában, egy 1–31 közötti nappal állítható be (alapértelmezés: 20.). A tényleges határidő mindig a menüidőszak (pl. szeptemberi menüterv esetén augusztus) ezen napjának napvégéig (23:59:59) tart – utána a választás automatikusan lezárul.</td></tr>
                        <tr><th>Mikor megy ki az értesítő e-mail</th><td>Kizárólag abban a pillanatban, amikor egy admin egy adott menütervnél megnyomja a „Menüválasztás indítása” gombot – ez tervenként egyszeri, manuális esemény, nincs automatikus emlékeztető vagy időzített újraküldés a határidő közeledtével.</td></tr>
                        <tr><th>Ki kapja</th><td>Minden aktív szülői fiókkal rendelkező gondviselő, akinek van legalább egy aktív, <strong>nem kizárólag diétás</strong> gyermeke az intézményben – a csak diétás menüt kapó gyermek szülője nem kap értesítést, mert neki nincs A/B választása.</td></tr>
                        <tr><th>Az e-mail tartalma</th><td>Az érintett gyermek(ek) neve, a menüidőszak és a választási határidő automatikusan bekerül, valamint egy link a szülői menüválasztó felületre – ezeket nem kell (és nem is lehet) a szövegben megadni.</td></tr>
                    </table>

                    <p>Az e-mail <strong>tárgya és szövege szabadon módosítható</strong>: az <strong>Intézmény beállítások</strong> oldalon, ugyanabban az „A/B menü választás” blokkban található a „Menüválasztási értesítő e-mail tárgya” és „…szövege” mező. Az „Alapértelmezett szöveg visszaállítása” gombbal bármikor visszaállítható az eredeti, gyári szöveg.</p>
                </div>
            </div>

            <div class="hb-section" id="hb-ui">
                <div class="hb-section-head">
                    <div class="hb-icon-box gray">🧭</div>
                    <div>
                        <h4>Általános felületi elemek</h4>
                        <p>Lapozás, szűrésmegőrzés, jelvények</p>
                    </div>
                </div>
                <div class="hb-section-body">
                    <ul class="hb-status-list">
                        <li>
                            <span class="hb-status-dot green"></span>
                            <div>
                                <div class="hb-s-title">50 soros lapozás</div>
                                <div class="hb-s-text">A gyermek-, dolgozó-, étkező- és lemondáslistákon egységesen 50 sor jelenik meg egy oldalon, kompaktabb sortávolsággal. Kivétel a gondviselők listája, amely 15 soros lapozással működik.</div>
                            </div>
                        </li>
                        <li>
                            <span class="hb-status-dot blue"></span>
                            <div>
                                <div class="hb-s-title">Szűrésmegőrző „Vissza a listához”</div>
                                <div class="hb-s-text">A gyermeklistán a szerkesztésből vagy mentésből visszalépve a rendszer megtartja a korábbi keresést, szűrőket és lapszámot. A dolgozó- és a gondviselő-listáknál ez még nem működik – onnan a szerkesztésből mindig az alapértelmezett, szűretlen listára jutsz vissza.</div>
                            </div>
                        </li>
                        <li>
                            <span class="hb-status-dot orange"></span>
                            <div>
                                <div class="hb-s-title">Állapot-jelvények</div>
                                <div class="hb-s-text">Étkező / Étkező (ütemezve) / Nem étkező jelvények az Étkező gyermekek listán jelzik az aktuális étkezési állapotot; az Aktív / Inaktív jelvény a gyermek-, dolgozó- és gondviselő-listákon az intézményi (nem étkezési) állapotot mutatja, egységes színkódolással.</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            </div>
          </div>
        </div>

    </div>

</div>
@endsection
