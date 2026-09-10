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
                        <div style="margin:0;font-size:13px;line-height:20px;font-weight:600;letter-spacing:0.4px;color:#8a93a3;text-transform:uppercase;">Konyhai étkezési összesítő</div>
                        <div style="margin-top:6px;font-size:20px;line-height:28px;font-weight:700;color:#232936;">{{ $institution->name }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 36px 18px 36px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="padding:0 0 10px 0;font-size:15px;line-height:23px;color:#3d4451;">
                                    <strong>Étkezési nap:</strong> {{ $service_date_label }}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 0 18px 0;font-size:15px;line-height:23px;color:#3d4451;">
                                    <strong>Lemondások lezárva:</strong> {{ $cutoff_label ?? 'Nincs meghatározva' }}
                                </td>
                            </tr>
                        </table>

                        @isset($school_summary)
                            @include('emails.partials.school-kitchen-headcount')
                        @endisset
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef6ff;border-radius:16px;">
                            <tr>
                                <td align="center" style="padding:20px 24px 8px 24px;font-size:13px;line-height:18px;font-weight:700;letter-spacing:0.3px;color:#4f6b95;text-transform:uppercase;">
                                    Összes étkező
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:0 24px 20px 24px;font-size:38px;line-height:44px;font-weight:800;color:#173b6c;">
                                    {{ $stats['daily_eaters'] }} fő
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding:0 24px 20px 24px;font-size:14px;line-height:22px;color:#4f5f77;">
                                    Gyermekek: <strong>{{ $stats['child_daily_eaters'] ?? 0 }}</strong>
                                    &nbsp;·&nbsp;
                                    Dolgozók: <strong>{{ $stats['employee_daily_eaters'] ?? 0 }}</strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 36px 18px 36px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                @if($has_ab_menu)
                                    <td width="33.33%" style="padding:0 6px 12px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f9fb;border-radius:12px;">
                                            <tr><td align="center" style="padding:14px 12px 5px 12px;font-size:12px;font-weight:700;color:#7c8595;text-transform:uppercase;">Gyermek A menü</td></tr>
                                            <tr><td align="center" style="padding:0 12px 14px 12px;font-size:26px;font-weight:800;color:#232936;">{{ $stats['menu_a_count'] }} fő</td></tr>
                                        </table>
                                    </td>
                                    <td width="33.33%" style="padding:0 6px 12px 6px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8f9fb;border-radius:12px;">
                                            <tr><td align="center" style="padding:14px 12px 5px 12px;font-size:12px;font-weight:700;color:#7c8595;text-transform:uppercase;">Gyermek B menü</td></tr>
                                            <tr><td align="center" style="padding:0 12px 14px 12px;font-size:26px;font-weight:800;color:#232936;">{{ $stats['menu_b_count'] }} fő</td></tr>
                                        </table>
                                    </td>
                                    <td width="33.33%" style="padding:0 0 12px 6px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eefaf1;border-radius:12px;">
                                            <tr><td align="center" style="padding:14px 12px 5px 12px;font-size:12px;font-weight:700;color:#5d7c65;text-transform:uppercase;">Diétás összesen</td></tr>
                                            <tr><td align="center" style="padding:0 12px 14px 12px;font-size:26px;font-weight:800;color:#1d6a33;">{{ $stats['dietary_count'] }} fő</td></tr>
                                        </table>
                                    </td>
                                @else
                                    <td style="padding:0 0 12px 0;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eefaf1;border-radius:12px;">
                                            <tr><td align="center" style="padding:14px 12px 5px 12px;font-size:12px;font-weight:700;color:#5d7c65;text-transform:uppercase;">Diétás étkezők</td></tr>
                                            <tr><td align="center" style="padding:0 12px 14px 12px;font-size:26px;font-weight:800;color:#1d6a33;">{{ $stats['dietary_eaters'] }} fő</td></tr>
                                        </table>
                                    </td>
                                @endif
                            </tr>
                        </table>
                        @if(($stats['employee_daily_eaters'] ?? 0) > 0 && $has_ab_menu)
                            <div style="padding-top:8px;font-size:12px;line-height:18px;color:#7c8595;">
                                Az A/B bontás csak a gyermekekre értelmezett. A dolgozói adagok az összes étkező és az étkezéstípus bontás számaiban szerepelnek.
                            </div>
                        @endif
                    </td>
                </tr>
                @if($meal_type_counts->isNotEmpty())
                    <tr>
                        <td style="padding:4px 36px 8px 36px;">
                            <div style="font-size:16px;line-height:24px;font-weight:700;color:#232936;margin-bottom:10px;">Étkezéstípusok</div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #edf0f5;border-radius:12px;overflow:hidden;">
                                @if(($stats['employee_daily_eaters'] ?? 0) > 0)
                                    <tr>
                                        <td style="padding:12px 16px;font-size:12px;line-height:18px;font-weight:700;color:#7c8595;text-transform:uppercase;border-bottom:1px solid #edf0f5;">Étkezéstípus</td>
                                        <td align="right" style="padding:12px 16px;font-size:12px;line-height:18px;font-weight:700;color:#7c8595;text-transform:uppercase;border-bottom:1px solid #edf0f5;">Gyermek</td>
                                        <td align="right" style="padding:12px 16px;font-size:12px;line-height:18px;font-weight:700;color:#7c8595;text-transform:uppercase;border-bottom:1px solid #edf0f5;">Dolgozó</td>
                                        <td align="right" style="padding:12px 16px;font-size:12px;line-height:18px;font-weight:700;color:#7c8595;text-transform:uppercase;border-bottom:1px solid #edf0f5;">Összesen</td>
                                    </tr>
                                    @foreach($meal_type_counts as $mealType)
                                        <tr>
                                            <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">{{ $mealType['name'] }}</td>
                                            <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">{{ $mealType['child_count'] }} fő</td>
                                            <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">{{ $mealType['employee_count'] }} fő</td>
                                            <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;border-bottom:1px solid #edf0f5;">{{ $mealType['count'] }} fő</td>
                                        </tr>
                                    @endforeach
                                @else
                                    @foreach($meal_type_counts as $mealType)
                                        <tr>
                                            <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">{{ $mealType['name'] }}</td>
                                            <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;border-bottom:1px solid #edf0f5;">{{ $mealType['count'] }} fő</td>
                                        </tr>
                                    @endforeach
                                @endif
                            </table>
                        </td>
                    </tr>
                @endif
                @if($dietary_breakdown->isNotEmpty())
                    <tr>
                        <td style="padding:16px 36px 8px 36px;">
                            <div style="font-size:16px;line-height:24px;font-weight:700;color:#232936;margin-bottom:10px;">Érzékenységek</div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #edf0f5;border-radius:12px;overflow:hidden;">
                                @foreach($dietary_breakdown as $restriction)
                                    <tr>
                                        <td style="padding:12px 16px;font-size:14px;line-height:20px;color:#3d4451;border-bottom:1px solid #edf0f5;">{{ $restriction['name'] }}</td>
                                        <td align="right" style="padding:12px 16px;font-size:14px;line-height:20px;font-weight:700;color:#232936;border-bottom:1px solid #edf0f5;">{{ $restriction['count'] }} fő</td>
                                    </tr>
                                @endforeach
                            </table>
                            <div style="padding-top:8px;font-size:12px;line-height:18px;color:#7c8595;">{{ $dietary_note }}</div>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:18px 36px 34px 36px;font-size:13px;line-height:20px;color:#7c8595;">
                        Az összesítő a Digifood központi napi létszámlogikájából készült, a lemondások lezárása utáni állapot alapján.
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
