<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Vonalkódos kártyák - {{ $institution->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            background: #ffffff;
            color: #111827;
        }

        .page-shell {
            width: 190mm;
            margin: 0 auto;
            padding: 8mm 0 12mm;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 10mm;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
        }

        .toolbar a,
        .toolbar button {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #111827;
            border-radius: 8px;
            padding: 8px 14px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .toolbar-title {
            font-size: 14px;
            color: #64748b;
        }

        .print-page {
            width: 190mm;
            min-height: 277mm;
            margin: 0 auto 8mm;
            page-break-after: always;
            break-after: page;
        }

        .print-page:last-child {
            page-break-after: auto;
            break-after: auto;
            margin-bottom: 0;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(2, 85.6mm);
            grid-template-rows: repeat(5, 54mm);
            gap: 4mm;
            justify-content: center;
        }

        .barcode-card {
            width: 85.6mm;
            height: 54mm;
            background: #ffffff;
            border: 0.2mm solid #cbd5e1;
            border-radius: 2.5mm;
            padding: 4mm 4.2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
            break-inside: avoid;
            position: relative;
        }

        .barcode-card::after {
            content: "";
            position: absolute;
            inset: 1.6mm;
            border: 0.2mm dashed #e2e8f0;
            border-radius: 1.8mm;
            pointer-events: none;
        }

        .barcode-card-header,
        .barcode-card-crest,
        .barcode-card-name,
        .barcode-card-group,
        .barcode-svg,
        .barcode-token {
            position: relative;
            z-index: 1;
        }

        .barcode-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
        }

        .barcode-card-logo {
            display: block;
            max-width: 20mm;
            max-height: 7mm;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .barcode-card-crest {
            display: block;
            max-width: 9mm;
            max-height: 7mm;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .barcode-card-name {
            font-weight: 700;
            font-size: 13px;
            line-height: 1.15;
            margin: 1.5mm 0 0.5mm;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .barcode-card-name.is-long {
            font-size: 11px;
        }

        .barcode-card-group {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            text-align: right;
            line-height: 1.15;
            max-width: 30mm;
            flex-shrink: 0;
        }

        .barcode-svg {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 13mm;
            margin-top: 1mm;
        }

        .barcode-svg svg {
            width: 100%;
            height: 13mm;
        }

        .barcode-token {
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            margin-top: 0.5mm;
        }

        .barcode-card-footer {
            position: absolute;
            right: 3mm;
            bottom: 2mm;
            font-size: 7px;
            color: #cbd5e1;
            z-index: 1;
        }

        @media print {
            .page-shell {
                width: auto;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .print-page {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="toolbar">
            <div class="toolbar-title">{{ $institution->name }} · {{ $cards->count() }} kártya · 100%-os nyomtatási méretre optimalizálva</div>
            <div class="toolbar-actions">
                <a href="{{ route('dashboard.institution.children.barcodes.index') }}">Vissza</a>
                <button type="button" onclick="window.print()">Nyomtatás</button>
            </div>
        </div>

        @foreach($cards->chunk(10) as $pageCards)
            <section class="print-page">
                <div class="card-grid">
                    @foreach($pageCards as $card)
                        @php($child = $card['child'])
                        <article class="barcode-card">
                            <div class="barcode-card-header">
                                <img
                                    src="{{ asset('/home/logo.png') }}"
                                    alt="DigiFood"
                                    class="barcode-card-logo"
                                >
                                <img
                                    src="{{ asset('images/szent_angela_cimer.png') }}"
                                    alt="{{ $institution->name }}"
                                    class="barcode-card-crest"
                                >
                                <div class="barcode-card-group">{{ $child->group_name ?: 'Osztály / csoport nincs megadva' }}</div>
                            </div>

                            <div class="barcode-card-name @if(mb_strlen($child->name) > 24) is-long @endif">{{ $child->name }}</div>
                            <div class="barcode-svg">{!! $card['barcode_svg'] !!}</div>
                            <div class="barcode-token">{{ $child->barcode_token }}</div>
                            <div class="barcode-card-footer">DigiFood</div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</body>
</html>