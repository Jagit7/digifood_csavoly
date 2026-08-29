<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Napi gyermeklista - {{ $institution->name }} - {{ $date->format('Y-m-d') }}</title>
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
            padding: 20px 24px 0;
        }

        .print-toolbar button {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
            color: #fff;
            background: #2563eb;
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
            margin: 0 0 8px;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .sheet-subtitle {
            margin: 4px 0;
            font-size: 15px;
        }

        .group-section {
            margin-top: 20px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .group-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 10px;
            padding: 8px 12px;
            background: #eff6ff;
            border-left: 5px solid #2563eb;
            break-after: avoid;
            page-break-after: avoid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            display: table-header-group;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 8px 6px;
            font-size: 12px;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            text-align: left;
            font-weight: 700;
        }

        .number-col {
            width: 7%;
        }

        .name-col {
            width: 20%;
        }

        .group-col {
            width: 14%;
        }

        .diet-col {
            width: 40%;
        }

        .muted {
            color: #64748b;
        }

        .small {
            font-size: 11px;
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
        @php
            $weekdayLabel = mb_convert_case($date->translatedFormat('l'), MB_CASE_TITLE, 'UTF-8');
            $groupSuffix = $isKindergarten ? 'csoport' : 'osztály';
        @endphp

        <div class="sheet-header">
            <h1 class="sheet-title">NAPI GYERMEKLISTA</h1>
            <p class="sheet-subtitle"><strong>{{ $institution->name }}</strong></p>
            @if($selectedGroupName)
                <p class="sheet-subtitle">{{ $selectedGroupName }} {{ $groupSuffix }}</p>
            @endif
            <p class="sheet-subtitle">{{ $date->translatedFormat('Y. F j.') }}, {{ $weekdayLabel }}</p>
        </div>

        @foreach($groupedRows as $groupName => $groupRows)
            <section class="group-section">
                @if(!$selectedGroupName)
                    <h2 class="group-title">{{ $groupName }}</h2>
                @endif

                <table>
                    <thead>
                    <tr>
                        <th class="number-col">Sorszám</th>
                        <th class="name-col">Gyermek neve</th>
                        <th class="group-col">{{ $isKindergarten ? 'Csoport' : 'Osztály / csoport' }}</th>
                        <th class="diet-col">Diéta</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($groupRows as $index => $row)
                        @php
                            $child = $row['child'];
                            $allergens = $child->dietaryRestrictions->where('type', $allergenType)->pluck('name')->values();
                            $otherRestrictions = $child->dietaryRestrictions->where('type', '!=', $allergenType)->pluck('name')->values();
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $child->name }}</strong>
                                @if($child->educational_identifier)
                                    <div class="small muted">{{ $child->educational_identifier }}</div>
                                @endif
                            </td>
                            <td>{{ $child->group_name ?: 'Nincs megadva' }}</td>
                            <td>
                                @php
                                    $dietLabels = $allergens->concat($otherRestrictions)->values();
                                @endphp
                                @if($dietLabels->isEmpty())
                                    <span class="muted">Nincs</span>
                                @else
                                    {{ $dietLabels->implode(', ') }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>
</body>
</html>
