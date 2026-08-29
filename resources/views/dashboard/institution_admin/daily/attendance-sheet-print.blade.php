<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jelenléti ív - {{ $institution->name }} - {{ $date->format('Y-m-d') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            background: #f3f4f6;
        }

        .print-toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 24px 0;
        }

        .print-toolbar button {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
            color: #fff;
            background: #0f766e;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto 24px;
            background: #fff;
            padding: 14mm 14mm 12mm;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }

        .sheet-header {
            border-bottom: 2px solid #d1d5db;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .sheet-title {
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 6px;
        }

        .sheet-subtitle {
            font-size: 15px;
            margin: 0;
        }

        .group-section {
            margin-top: 20px;
        }

        .group-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 10px;
            padding: 8px 12px;
            background: #eff6ff;
            border-left: 5px solid #2563eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 8px 6px;
            font-size: 13px;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            text-align: left;
            font-weight: 700;
        }

        .name-col {
            width: 26%;
        }

        .group-col {
            width: 14%;
        }

        .time-col {
            width: 12%;
        }

        .check-col {
            width: 8%;
            text-align: center;
        }

        .note-col {
            width: 20%;
        }

        .blank-line {
            display: block;
            width: 100%;
            min-height: 22px;
            border-bottom: 1px solid #94a3b8;
        }

        .blank-box {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 1.5px solid #64748b;
            border-radius: 3px;
        }

        @media print {
            body {
                background: #fff;
            }

            .print-toolbar {
                display: none !important;
            }

            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button type="button" onclick="window.print()">Nyomtatás</button>
    </div>

    <div class="sheet">
        <div class="sheet-header">
            <h1 class="sheet-title">Óvodai jelenléti ív</h1>
            <p class="sheet-subtitle"><strong>Intézmény:</strong> {{ $institution->name }}</p>
            <p class="sheet-subtitle"><strong>Dátum:</strong> {{ $date->translatedFormat('Y. m. d.') }} ({{ mb_convert_case($date->translatedFormat('l'), MB_CASE_TITLE, 'UTF-8') }})</p>
        </div>

        @foreach($groupedRows as $groupName => $groupRows)
            <section class="group-section">
                <h2 class="group-title">{{ $groupName }}</h2>

                <table>
                    <thead>
                    <tr>
                        <th class="name-col">Gyermek neve</th>
                        <th class="group-col">Csoport</th>
                        <th class="time-col">Érkezés</th>
                        <th class="time-col">Távozás</th>
                        <th class="check-col">Jelen</th>
                        <th class="check-col">Hiányzik</th>
                        <th class="note-col">Megjegyzés</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($groupRows as $row)
                        <tr>
                            <td>{{ $row['child']->name }}</td>
                            <td>{{ $row['child']->group_name ?: '—' }}</td>
                            <td><span class="blank-line"></span></td>
                            <td><span class="blank-line"></span></td>
                            <td class="check-col"><span class="blank-box"></span></td>
                            <td class="check-col"><span class="blank-box"></span></td>
                            <td><span class="blank-line"></span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>
</body>
</html>
