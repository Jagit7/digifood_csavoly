<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Diétás lista - {{ $institution->name }} - {{ $date->format('Y-m-d') }}</title>
    <style>
        @page {
            size: A4;
            margin: 14mm;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            margin: 0;
            font-size: 13px;
        }

        .page {
            max-width: 190mm;
            margin: 0 auto;
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }

        .toolbar button {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        .meta {
            margin-bottom: 24px;
            font-size: 15px;
        }

        .group {
            margin-bottom: 24px;
        }

        .group-title {
            font-size: 18px;
            font-weight: 700;
            padding-bottom: 8px;
            margin-bottom: 12px;
            border-bottom: 2px solid #dbe4f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #dbe4f0;
            padding: 10px 8px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .child-name {
            font-size: 15px;
            font-weight: 700;
        }

        .muted {
            color: #64748b;
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="toolbar">
            <button type="button" onclick="window.print()">Nyomtatás</button>
        </div>

        <h1>Diétás étkezők listája</h1>
        <div class="meta">
            <strong>{{ $institution->name }}</strong><br>
            {{ $date->translatedFormat('Y. m. d.') }} · {{ mb_convert_case($date->translatedFormat('l'), MB_CASE_TITLE, 'UTF-8') }}
        </div>

        @forelse($groupedRows as $groupName => $rows)
            <section class="group">
                <div class="group-title">{{ $groupName }}</div>
                <table>
                    <thead>
                    <tr>
                        <th>Név</th>
                        <th>Diéta</th>
                        <th>Menücsomag</th>
                        <th>Napi menü</th>
                        <th>Megjegyzés</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>
                                <div class="child-name">{{ $row['display_name'] }}</div>
                                @if($row['display_identifier'])
                                    <div class="muted">{{ $row['display_identifier'] }}</div>
                                @endif
                            </td>
                            <td>{{ $row['diet_names']->implode(', ') }}</td>
                            <td>{{ $row['menu_package'] }}</td>
                            <td>{{ $row['daily_menu'] ?: 'Nem meghatározható' }}</td>
                            <td>{{ $row['notes']->implode(' · ') ?: '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <p class="muted">A kiválasztott napra nincs diétás étkező.</p>
        @endforelse
    </div>
</body>
</html>
