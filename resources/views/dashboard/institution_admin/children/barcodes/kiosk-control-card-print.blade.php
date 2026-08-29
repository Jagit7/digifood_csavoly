<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Kioszk vezérlő kártya - {{ $institution->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        * { box-sizing: border-box; }

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

        .card-wrap {
            display: flex;
            justify-content: center;
        }

        .barcode-card {
            width: 85.6mm;
            height: 54mm;
            background: #ffffff;
            border: 0.3mm solid #0f172a;
            border-radius: 2.5mm;
            padding: 4mm 4.2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .barcode-card::after {
            content: "";
            position: absolute;
            inset: 1.6mm;
            border: 0.2mm dashed #cbd5e1;
            border-radius: 1.8mm;
            pointer-events: none;
        }

        .barcode-card > * {
            position: relative;
            z-index: 1;
        }

        .barcode-card-logo {
            display: block;
            max-width: 32mm;
            max-height: 10mm;
            object-fit: contain;
        }

        .barcode-card-institution {
            font-size: 9px;
            line-height: 1.25;
            color: #475569;
            margin-top: 1.2mm;
        }

        .barcode-card-name {
            font-weight: 700;
            font-size: 15px;
            line-height: 1.12;
            margin: 2.8mm 0 1.2mm;
            color: #0f172a;
        }

        .barcode-card-role {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #b45309;
            min-height: 4mm;
        }

        .barcode-svg {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 16mm;
            margin-top: 1.5mm;
        }

        .barcode-svg svg {
            width: 100%;
            height: 15mm;
        }

        .barcode-token {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            margin-top: 1mm;
        }

        .barcode-card-footer {
            text-align: right;
            font-size: 8px;
            color: #94a3b8;
        }

        .hint {
            width: 85.6mm;
            margin: 6mm auto 0;
            font-size: 11px;
            color: #64748b;
            text-align: center;
        }

        @media print {
            .page-shell {
                width: auto;
                padding: 0;
            }

            .toolbar,
            .hint {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="toolbar">
            <div class="toolbar-title">{{ $institution->name }} · kioszk vezérlő kártya</div>
            <div class="toolbar-actions">
                <a href="{{ route('dashboard.institution.children.barcodes.kiosk.edit') }}">Vissza</a>
                <button type="button" onclick="window.print()">Nyomtatás</button>
            </div>
        </div>

        <section class="card-wrap">
            <article class="barcode-card">
                <div>
                    <img src="{{ asset('/home/logo.png') }}" alt="DigiFood" class="barcode-card-logo">
                    <div class="barcode-card-institution">{{ $institution->name }}</div>
                </div>
                <div class="barcode-card-name">Kioszk vezérlő kártya</div>
                <div class="barcode-card-role">AKTIVÁLÁS / LEZÁRÁS</div>
                <div class="barcode-svg">{!! $barcodeSvg !!}</div>
                <div class="barcode-token">{{ $token }}</div>
                <div class="barcode-card-footer">DigiFood</div>
            </article>
        </section>

        <p class="hint">Tartsd biztonságos helyen - ezzel a kártyával bárki aktiválhatja/lezárhatja a kioszkot.</p>
    </div>
</body>
</html>
