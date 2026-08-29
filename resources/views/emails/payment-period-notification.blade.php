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
                        <div style="margin:0;font-size:13px;line-height:20px;font-weight:600;letter-spacing:0.4px;color:#8a93a3;text-transform:uppercase;">Fizetési időszak értesítés</div>
                        <div style="margin-top:6px;font-size:20px;line-height:28px;font-weight:700;color:#232936;">{{ $institution->name }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 36px 18px 36px;font-size:15px;line-height:24px;color:#3d4451;">
                        {!! nl2br(e($custom_body_text)) !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 36px 18px 36px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef6ff;border-radius:16px;">
                            <tr>
                                <td align="center" style="padding:20px 24px 8px 24px;font-size:13px;line-height:18px;font-weight:700;letter-spacing:0.3px;color:#4f6b95;text-transform:uppercase;">
                                    Fizetendő
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:0 24px 20px 24px;font-size:38px;line-height:44px;font-weight:800;color:#173b6c;">
                                    {{ $amount_label }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 36px 8px 36px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #edf0f5;border-radius:12px;overflow:hidden;">
                            <tr>
                                <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">Fizetendő összeg</td>
                                <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;border-bottom:1px solid #edf0f5;">{{ $amount_label }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">Fizetési hónap</td>
                                <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;border-bottom:1px solid #edf0f5;">{{ $payment_period_label }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">Étkezési időszak</td>
                                <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;border-bottom:1px solid #edf0f5;">{{ $meal_period_label }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">Jóváírási időszak</td>
                                <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;border-bottom:1px solid #edf0f5;">{{ $credit_period_label }}</td>
                            </tr>
                            <tr>
                                <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;">Fizetési határidő</td>
                                <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;">{{ $due_date_label }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:18px 36px 30px 36px;">
                        <a href="{{ $payment_url }}" style="display:inline-block;padding:14px 24px;border-radius:10px;background:#886CC0;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">
                            Havi elszámolás megtekintése
                        </a>
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
