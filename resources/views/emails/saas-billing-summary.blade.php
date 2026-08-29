<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Digifood havi számlázási összesítő</title>
</head>

<body style="
    margin: 0;
    padding: 0;
    background-color: #f3f5f9;
    font-family: Arial, Helvetica, sans-serif;
    color: #2b2f38;
    -webkit-text-size-adjust: 100%;
    -ms-text-size-adjust: 100%;
">

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width: 100%;
        background-color: #f3f5f9;
        margin: 0;
        padding: 0;
    "
>
    <tr>
        <td align="center" style="padding: 36px 15px;">

            {{-- Fő e-mail konténer --}}
            <table
                role="presentation"
                width="680"
                cellspacing="0"
                cellpadding="0"
                border="0"
                style="
                    width: 100%;
                    max-width: 680px;
                    background-color: #ffffff;
                    border-radius: 14px;
                    overflow: hidden;
                    box-shadow: 0 5px 24px rgba(30, 45, 70, 0.08);
                "
            >

                {{-- Fejléc --}}
                <tr>
                    <td
                        align="center"
                        style="
                            padding: 30px 35px 25px 35px;
                            background-color: #ffffff;
                            border-bottom: 1px solid #edf0f5;
                        "
                    >
                        <img
                            src="{{ asset('home/logo.png') }}"
                            alt="Digifood"
                            width="190"
                            style="
                                display: block;
                                width: 190px;
                                max-width: 100%;
                                height: auto;
                                border: 0;
                                outline: none;
                                text-decoration: none;
                                margin: 0 auto;
                            "
                        >
                    </td>
                </tr>

                {{-- Cím --}}
                <tr>
                    <td
                        align="center"
                        style="
                            padding: 22px 35px 0 35px;
                        "
                    >
                        <div
                            style="
                                margin: 0;
                                font-size: 13px;
                                line-height: 20px;
                                font-weight: 600;
                                letter-spacing: 0.4px;
                                color: #8a93a3;
                                text-transform: uppercase;
                            "
                        >
                            Digifood számlázási összesítő
                        </div>

                        <div
                            style="
                                margin-top: 6px;
                                font-size: 20px;
                                line-height: 28px;
                                font-weight: 700;
                                color: #232936;
                            "
                        >
                            {{ $monthLabel }}
                        </div>
                    </td>
                </tr>

                {{-- Tartalom --}}
                <tr>
                    <td
                        style="
                            padding: 26px 30px 10px 30px;
                            font-size: 14px;
                            line-height: 1.6;
                            color: #3d4451;
                        "
                    >
                        <p style="margin: 0 0 18px 0;">
                            Az alábbiakban a(z) <strong>{{ $monthLabel }}</strong> hónapra vonatkozó, aktív étkezőszám
                            (diák + dolgozó) alapján számlázható intézményi összesítő található. Az összeg
                            intézményenként az aktuális aktív étkezőszám és a beállított Ft/fő díj szorzata.
                        </p>

                        <p style="margin: 0 0 22px 0; color: #7c8595; font-size: 13px;">
                            Összesen <strong style="color:#3d4451;">{{ $totalInstitutions }}</strong> intézmény,
                            mindösszesen <strong style="color:#3d4451;">{{ number_format($totalAmount, 0, ',', ' ') }} Ft</strong>
                            számlázható összeg.
                        </p>

                        {{-- Összesítő táblázat --}}
                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                            style="width: 100%; border-collapse: collapse; margin-bottom: 26px;"
                        >
                            <tr>
                                <td style="padding: 8px 10px; font-size: 12px; font-weight: 700; color: #7c8595; text-transform: uppercase; border-bottom: 2px solid #edf0f5;">Intézmény</td>
                                <td style="padding: 8px 10px; font-size: 12px; font-weight: 700; color: #7c8595; text-transform: uppercase; border-bottom: 2px solid #edf0f5;" align="right">Gyerek</td>
                                <td style="padding: 8px 10px; font-size: 12px; font-weight: 700; color: #7c8595; text-transform: uppercase; border-bottom: 2px solid #edf0f5;" align="right">Dolgozó</td>
                                <td style="padding: 8px 10px; font-size: 12px; font-weight: 700; color: #7c8595; text-transform: uppercase; border-bottom: 2px solid #edf0f5;" align="right">Étkező</td>
                                <td style="padding: 8px 10px; font-size: 12px; font-weight: 700; color: #7c8595; text-transform: uppercase; border-bottom: 2px solid #edf0f5;" align="right">Ft / fő</td>
                                <td style="padding: 8px 10px; font-size: 12px; font-weight: 700; color: #7c8595; text-transform: uppercase; border-bottom: 2px solid #edf0f5;" align="right">Számlázható</td>
                            </tr>
                            @forelse($rows as $row)
                                <tr>
                                    <td style="padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f1f3f7;">{{ $row['institution_name'] }}</td>
                                    <td style="padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f1f3f7;" align="right">{{ $row['children_count'] }}</td>
                                    <td style="padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f1f3f7;" align="right">{{ $row['employees_count'] }}</td>
                                    <td style="padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f1f3f7; font-weight: 700;" align="right">{{ $row['eaters_count'] }}</td>
                                    <td style="padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f1f3f7;" align="right">{{ number_format($row['rate'], 0, ',', ' ') }}</td>
                                    <td style="padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f1f3f7; font-weight: 700; color:#d94a16;" align="right">{{ number_format($row['total_amount'], 0, ',', ' ') }} Ft</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 14px 10px; font-size: 13px; color: #7c8595;">
                                        Ebben a hónapban egyetlen intézménynél sincs beállítva számlázási díj.
                                    </td>
                                </tr>
                            @endforelse
                        </table>

                        {{-- Intézményenkénti számlázási adatok (másolható a Számlázz.hu-ba) --}}
                        @if(count($rows) > 0)
                            <div style="font-size: 13px; font-weight: 700; color: #232936; text-transform: uppercase; letter-spacing: 0.3px; margin: 0 0 12px 0;">
                                Intézményi számlázási adatok
                            </div>

                            @foreach($rows as $row)
                                <table
                                    role="presentation"
                                    width="100%"
                                    cellspacing="0"
                                    cellpadding="0"
                                    border="0"
                                    style="width: 100%; border: 1px solid #edf0f5; border-radius: 8px; margin-bottom: 12px;"
                                >
                                    <tr>
                                        <td style="padding: 14px 16px;">
                                            <div style="font-size: 14px; font-weight: 700; color: #232936; margin-bottom: 6px;">
                                                {{ $row['billing_name'] }}
                                            </div>
                                            <div style="font-size: 13px; color: #5b6272; line-height: 1.6;">
                                                Adószám: {{ $row['billing_tax_number'] ?: '—' }}<br>
                                                Cím:
                                                {{ collect([$row['billing_zip'], $row['billing_city'], $row['billing_address']])->filter()->implode(' ') ?: '—' }}
                                                <br>
                                                Aktív étkezők: {{ $row['eaters_count'] }} fő ({{ $row['children_count'] }} diák + {{ $row['employees_count'] }} dolgozó)
                                                &times; {{ number_format($row['rate'], 0, ',', ' ') }} Ft
                                                = <strong>{{ number_format($row['total_amount'], 0, ',', ' ') }} Ft</strong>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            @endforeach
                        @endif

                        {{-- Hiányzó díjszabás figyelmeztetés --}}
                        @if(count($missingRateInstitutions) > 0)
                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                style="width: 100%; background-color: #fff8ec; border: 1px solid #f3e3c0; border-radius: 8px; margin-top: 10px;"
                            >
                                <tr>
                                    <td style="padding: 14px 16px; font-size: 13px; color: #7a5a1e; line-height: 1.6;">
                                        <strong>Nincs beállítva Digifood díj az alábbi aktív intézményeknél</strong>
                                        (ezért nem szerepelnek a fenti összesítőben):<br>
                                        {{ implode(', ', $missingRateInstitutions) }}
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>

                {{-- Elválasztó --}}
                <tr>
                    <td style="padding: 0 30px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="height: 1px; background-color: #edf0f5; font-size: 1px; line-height: 1px;">&nbsp;</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Információs rész --}}
                <tr>
                    <td
                        align="center"
                        style="
                            padding: 20px 40px;
                            font-size: 12px;
                            line-height: 20px;
                            color: #7c8595;
                        "
                    >
                        Ez az összesítő automatikusan készült {{ $generatedAt->format('Y.m.d. H:i') }} időpontban.
                        Nem minősül számlának, kizárólag a Számlázz.hu-s kézi számlázás előkészítését segíti.
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td
                        align="center"
                        style="
                            padding: 20px 35px 26px 35px;
                            background-color: #f8f9fb;
                            border-top: 1px solid #edf0f5;
                        "
                    >
                        <img
                            src="{{ asset('home/logo.png') }}"
                            alt="Digifood"
                            width="95"
                            style="
                                display: block;
                                width: 95px;
                                max-width: 100%;
                                height: auto;
                                border: 0;
                                opacity: 0.75;
                                margin: 0 auto 10px auto;
                            "
                        >

                        <div style="font-size: 11px; line-height: 17px; color: #9aa2af;">
                            Digifood · digitális étkezési és menzakezelő rendszer
                        </div>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
