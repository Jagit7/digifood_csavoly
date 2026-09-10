<div style="font-size:20px;line-height:28px;font-weight:700;margin:0 0 16px;">
    Napi konyhai létszám – {{ $service_date->format('Y.m.d.') }}
</div>
<div style="background:#eef6ff;padding:18px;margin-bottom:20px;border-radius:12px;line-height:26px;">
    <strong>ÖSSZESÍTÉS – GYERMEKEK</strong><br>
    Teljes étkezői létszám: <strong>{{ $school_summary['total'] }} fő</strong><br>
    Hiányzó / lemondott: <strong>{{ $school_summary['absent'] }} fő</strong><br>
    Ténylegesen étkezik: <strong>{{ $school_summary['eating'] }} fő</strong>
</div>
<p style="font-size:13px;line-height:20px;color:#5d6878;">
    A létszám az adott napra étkezési beállítással rendelkező gyermekeket jelenti,
    gyermekenként egyszer. A hiányzó / lemondott létszám a rendszerben rögzített
    érvényes lemondásokból származik. Az étkezéstípusonkénti adagok és a dolgozók
    külön bontása lejjebb található.
</p>
@foreach($school_summary['classes'] as $class)
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom:16px;border:1px solid #dfe5ee;border-radius:8px;">
        <tr>
            <td style="padding:16px;font-size:14px;line-height:24px;">
                <h3 style="margin:0 0 8px;font-size:18px;color:#173b6c;">{{ $class['name'] }}</h3>
                Teljes étkezői létszám: <strong>{{ $class['total'] }} fő</strong><br>
                Hiányzó / lemondott: <strong>{{ $class['absent'] }} fő</strong><br>
                Ténylegesen étkezik: <strong>{{ $class['eating'] }} fő</strong>
                <p style="margin:12px 0 4px;font-weight:700;">Hiányzó / lemondott gyermekek:</p>
                @if($class['absent_children']->isEmpty())
                    <div>Nincs hiányzó / lemondott gyermek.</div>
                @else
                    <ul style="margin:0;padding-left:22px;">
                        @foreach($class['absent_children'] as $child)
                            <li>{{ $child['name'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </td>
        </tr>
    </table>
@endforeach
