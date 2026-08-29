<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $institutionName }}</title>
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
                width="640"
                cellspacing="0"
                cellpadding="0"
                border="0"
                style="
                    width: 100%;
                    max-width: 640px;
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

                {{-- Intézmény --}}
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
                            Intézményi tájékoztató
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
                            {{ $institutionName }}
                        </div>
                    </td>
                </tr>

                {{-- Tartalom --}}
                <tr>
                    <td
                        style="
                            padding: 30px 42px 38px 42px;
                            font-size: 15px;
                            line-height: 1.7;
                            color: #3d4451;
                        "
                    >
                        {!! $bodyHtml !!}
                    </td>
                </tr>

                {{-- Elválasztó --}}
                <tr>
                    <td style="padding: 0 42px;">
                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                        >
                            <tr>
                                <td
                                    style="
                                        height: 1px;
                                        background-color: #edf0f5;
                                        font-size: 1px;
                                        line-height: 1px;
                                    "
                                >
                                    &nbsp;
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Információs rész --}}
                <tr>
                    <td
                        align="center"
                        style="
                            padding: 25px 40px;
                            font-size: 13px;
                            line-height: 20px;
                            color: #7c8595;
                        "
                    >
                        Ezt az üzenetet a
                        <strong style="color: #4b5565;">Digifood étkezési rendszer</strong>
                        segítségével küldte az intézmény.
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

                        <div
                            style="
                                font-size: 11px;
                                line-height: 17px;
                                color: #9aa2af;
                            "
                        >
                            Digifood · digitális étkezési és menzakezelő rendszer
                        </div>
                    </td>
                </tr>

            </table>

            {{-- Külső alsó szöveg --}}
            <table
                role="presentation"
                width="640"
                cellspacing="0"
                cellpadding="0"
                border="0"
                style="
                    width: 100%;
                    max-width: 640px;
                "
            >
                <tr>
                    <td
                        align="center"
                        style="
                            padding: 18px 25px 0 25px;
                            font-size: 11px;
                            line-height: 17px;
                            color: #a2a9b5;
                        "
                    >
                        Ez egy automatikusan kézbesített intézményi üzenet.
                        Kérjük, erre az e-mailre csak akkor válaszoljon,
                        ha az intézmény ezt külön kéri.
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>