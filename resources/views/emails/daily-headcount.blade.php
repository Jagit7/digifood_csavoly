<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject }}</title>
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
                        <div style="margin:0;font-size:13px;line-height:20px;font-weight:600;letter-spacing:0.4px;color:#8a93a3;text-transform:uppercase;">Napi étkezési létszám</div>
                        <div style="margin-top:6px;font-size:20px;line-height:28px;font-weight:700;color:#232936;">{{ $institution->name }}</div>
                        <div style="margin-top:4px;font-size:16px;line-height:24px;font-weight:600;color:#3d4451;">{{ $group_name }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 36px 8px 36px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="padding:0 0 22px 0;font-size:15px;line-height:23px;color:#3d4451;">
                                    Tisztelt Címzett!<br>
                                    A(z) <strong>{{ $group_name }}</strong> {{ $date_label }}-i étkező gyermekeinek névsora az alábbi.
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td>
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eefaf1;border-radius:12px;">
                                        <tr><td align="center" style="padding:16px 12px 4px 12px;font-size:12px;font-weight:700;color:#5d7c65;text-transform:uppercase;">Étkező gyermekek száma</td></tr>
                                        <tr><td align="center" style="padding:0 12px 18px 12px;font-size:32px;font-weight:800;color:#1d6a33;">{{ $eaters_count }} fő</td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 36px 8px 36px;">
                        <div style="font-size:16px;line-height:24px;font-weight:700;color:#232936;margin-bottom:10px;">Étkező gyermekek névsora (ABC sorrendben)</div>
                        @if($eaters->isEmpty())
                            <div style="font-size:14px;line-height:20px;color:#7c8595;">A mai napra nincs étkező gyermek.</div>
                        @else
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #edf0f5;border-radius:12px;overflow:hidden;">
                                @foreach($eaters as $index => $eaterName)
                                    <tr style="background-color:{{ $index % 2 === 0 ? '#ffffff' : '#f8f9fb' }};">
                                        <td width="40" align="center" style="padding:10px 4px;font-size:13px;line-height:19px;font-weight:700;color:#8a93a3;border-bottom:1px solid #edf0f5;">{{ $index + 1 }}.</td>
                                        <td style="padding:10px 16px 10px 6px;font-size:14px;line-height:19px;color:#3d4451;border-bottom:1px solid #edf0f5;">{{ $eaterName }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 36px 34px 36px;font-size:13px;line-height:20px;color:#7c8595;">
                        Ez az összesítő a Digifood napi étkezési/lemondási adatai alapján készült, a „Napi működés → Mai létszám” oldalon látható állapot szerint.
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:20px 35px 26px 35px;background-color:#f8f9fb;border-top:1px solid #edf0f5;">
                        <img src="{{ asset('home/logo.png') }}" alt="Digifood" width="95" style="display:block;width:95px;max-width:100%;height:auto;border:0;opacity:0.75;margin:0 auto 10px auto;">
                        <div style="font-size:11px;line-height:17px;color:#9aa2af;">Digifood · digitális étkezési és menzakezelő rendszer</div>
                    </td>
                </tr>
            </table>

            <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;">
                <tr>
                    <td align="center" style="padding:18px 25px 0 25px;font-size:11px;line-height:17px;color:#a2a9b5;">
                        Ez egy automatikusan előkészített rendszerüzenet. Kérjük, erre az e-mailre ne válaszoljon. Kérdés esetén forduljon az intézményhez.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
