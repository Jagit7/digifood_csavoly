<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Digifood - Meghívás adminisztrátori hozzáférésre</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f5f9;font-family:Arial,Helvetica,sans-serif;color:#2b2f38;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f3f5f9;margin:0;padding:0;">
    <tr>
        <td align="center" style="padding:36px 15px;">
            <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background-color:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 5px 24px rgba(30,45,70,0.08);">
                <tr>
                    <td align="center" style="padding:30px 35px 25px 35px;background-color:#ffffff;border-bottom:1px solid #edf0f5;">
                        <img src="{{ asset('home/logo.png') }}" alt="Digifood" width="190" style="display:block;width:190px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;margin:0 auto;">
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:22px 35px 0 35px;">
                        <div style="margin:0;font-size:13px;line-height:20px;font-weight:600;letter-spacing:0.4px;color:#8a93a3;text-transform:uppercase;">
                            Adminisztrátori hozzáférés meghívó
                        </div>
                        <div style="margin-top:6px;font-size:20px;line-height:28px;font-weight:700;color:#232936;">
                            {{ $institutionName }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:30px 42px 38px 42px;font-size:15px;line-height:1.7;color:#3d4451;">
                        <p style="margin:0 0 16px 0;">Tisztelt {{ $recipientName }}!</p>

                        <p style="margin:0 0 16px 0;">
                            Önt a(z) <strong>{{ $institutionName }}</strong> intézményhez <strong>{{ $roleLabel }}</strong> szerepkörű Digifood hozzáféréssel hívták meg.
                        </p>

                        <p style="margin:0 0 24px 0;">
                            A fiók létrehozásához és saját jelszava beállításához kattintson az alábbi gombra.
                        </p>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px 0;">
                            <tr>
                                <td align="center" bgcolor="#d94a16" style="border-radius:999px;">
                                    <a href="{{ $activationUrl }}" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;">
                                        Meghívás elfogadása
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div style="margin:0 0 20px 0;padding:18px 20px;border-radius:12px;background-color:#f8fafc;border:1px solid #e2e8f0;color:#475569;">
                            <div style="font-size:14px;font-weight:700;color:#1e293b;margin-bottom:6px;">
                                Fontos tudnivalók
                            </div>

                            <div style="font-size:14px;line-height:22px;">
                                A meghívó link {{ $expiresAt->format('Y. m. d. H:i') }}-ig érvényes.
                            </div>

                            <div style="font-size:14px;line-height:22px;">
                                Ha nem Ön kezdeményezte, vagy nem várta ezt a meghívást, kérjük, hagyja figyelmen kívül ezt az üzenetet.
                            </div>
                        </div>

                        <p style="margin:0 0 8px 0;font-size:13px;color:#64748b;">
                            Ha a gomb nem működik, kérjük, másolja be az alábbi linket a böngészőjébe:
                        </p>

                        <p style="margin:0;word-break:break-all;font-size:13px;line-height:20px;">
                            <a href="{{ $activationUrl }}" style="color:#d94a16;text-decoration:underline;">
                                {{ $activationUrl }}
                            </a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 42px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="height:1px;background-color:#edf0f5;font-size:1px;line-height:1px;">
                                    &nbsp;
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:25px 40px;font-size:13px;line-height:20px;color:#7c8595;">
                        Ezt az üzenetet a
                        <strong style="color:#4b5565;">Digifood étkezési rendszer</strong>
                        készítette elő.
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:20px 35px 26px 35px;background-color:#f8f9fb;border-top:1px solid #edf0f5;">
                        <img src="{{ asset('home/logo.png') }}" alt="Digifood" width="95" style="display:block;width:95px;max-width:100%;height:auto;border:0;opacity:0.75;margin:0 auto 10px auto;">

                        <div style="font-size:11px;line-height:17px;color:#9aa2af;">
                            Digifood · digitális étkezési és menzakezelő rendszer
                        </div>
                    </td>
                </tr>
            </table>

            <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;">
                <tr>
                    <td align="center" style="padding:18px 25px 0 25px;font-size:11px;line-height:17px;color:#a2a9b5;">
                        Ez egy automatikusan előkészített rendszerüzenet. Kérjük, erre az e-mailre ne válaszoljon. Kérdés esetén forduljon a rendszergazdához.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
