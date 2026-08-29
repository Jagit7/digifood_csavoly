<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gyermeklista - {{ $institution->name }}{{ $selectedGroupName ? ' - ' . $selectedGroupName : '' }}</title>
    @php
        $orientation = count($selectedColumns) > 4 ? 'landscape' : 'portrait';
    @endphp
    <style>
        @page {
            size: A4 {{ $orientation }};
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
            gap: 10px;
            padding: 20px 24px 0;
        }

        .print-toolbar a,
        .print-toolbar button {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }

        .print-toolbar a {
            background: #ffffff;
            color: #111827;
        }

        .print-toolbar button {
            border: 0;
            color: #fff;
            background: #2563eb;
        }

        .sheet {
            width: {{ $orientation === 'landscape' ? '297mm' : '210mm' }};
            min-height: {{ $orientation === 'landscape' ? '210mm' : '297mm' }};
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
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .sheet-subtitle {
            margin: 4px 0;
            font-size: 14px;
        }

        .group-section {
            margin-top: 20px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .group-title {
            font-size: 17px;
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
            padding: 6px 6px;
            font-size: 11px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #f8fafc;
            font-weight: 700;
        }

        .number-col {
            width: 4%;
        }

        .name-col {
            width: 14%;
        }

        .muted {
            color: #64748b;
        }

        .no-data {
            color: #64748b;
            font-style: italic;
            padding: 12px;
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
        <a href="{{ route('dashboard.institution.children.print-list.index') }}">Vissza a beállításokhoz</a>
        <button type="button" onclick="window.print()">Nyomtatás</button>
    </div>

    <div class="sheet">
        @php
            $formatGuardianAddress = function ($guardian) {
                return trim(implode(' ', array_filter([
                    $guardian->postal_code,
                    $guardian->city,
                    trim(implode(' ', array_filter([$guardian->street_name, $guardian->street_type, $guardian->house_number]))),
                    $guardian->floor ? 'em. ' . $guardian->floor : null,
                    $guardian->door ? $guardian->door . '.' : null,
                ])));
            };

            $formatBillingAddress = function ($billingProfile) {
                if (!$billingProfile) {
                    return null;
                }

                return trim(trim(($billingProfile->postal_code ?? '') . ' ' . ($billingProfile->city ?? '')) . ', ' . ($billingProfile->address ?? ''), ' ,');
            };

            $sourceTypeLabel = function ($sourceType) {
                return match ($sourceType) {
                    'school_standard' => 'Iskolai Excel',
                    'school_custom' => 'Egyedi Excel',
                    'kindergarten' => 'Óvodai Excel',
                    default => 'Kézi rögzítés',
                };
            };
        @endphp

        <div class="sheet-header">
            <h1 class="sheet-title">GYERMEKLISTA</h1>
            <p class="sheet-subtitle"><strong>{{ $institution->name }}</strong></p>
            @if($selectedGroupName)
                <p class="sheet-subtitle">{{ $selectedGroupName }} osztály / csoport</p>
            @else
                <p class="sheet-subtitle">Összes osztály / csoport</p>
            @endif
            <p class="sheet-subtitle">Készült: {{ \Illuminate\Support\Carbon::parse($today)->translatedFormat('Y. F j.') }}</p>
        </div>

        @forelse($groupedChildren as $groupName => $groupChildren)
            <section class="group-section">
                @if(!$selectedGroupName)
                    <h2 class="group-title">{{ $groupName }}</h2>
                @endif

                <table>
                    <thead>
                    <tr>
                        <th class="number-col">Sorszám</th>
                        <th class="name-col">Gyermek neve</th>
                        @foreach($selectedColumns as $col)
                            <th>{{ $columnLabels[$col] }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($groupChildren as $index => $child)
                        @php
                            $currentMealSetting = $child->getRelation('currentMealSetting');
                            $latestMealSetting = $child->getRelation('latestMealSetting');
                            $isClosedMealRelationship = !$currentMealSetting && $latestMealSetting?->wasClosedManually() && $latestMealSetting->valid_to !== null;
                            $isUpcomingMealRelationship = !$currentMealSetting && $latestMealSetting && $latestMealSetting->valid_from->toDateString() > $today;
                            $activeDiscountType = $child->discountTypeForDate($today);
                            $billingProfile = $child->billingProfiles->first();
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $child->name }}</strong></td>
                            @foreach($selectedColumns as $col)
                                <td>
                                    @switch($col)
                                        @case('educational_identifier')
                                            {{ $child->educational_identifier ?: '—' }}
                                            @break

                                        @case('group_name')
                                            {{ $child->group_name ?: '—' }}
                                            @break

                                        @case('school_year')
                                            {{ $child->school_year ?: '—' }}
                                            @break

                                        @case('meal_status')
                                            @if($currentMealSetting)
                                                Étkező
                                            @elseif($isUpcomingMealRelationship)
                                                Étkező (ütemezve {{ $latestMealSetting->valid_from->format('Y.m.d.') }})
                                            @elseif($isClosedMealRelationship)
                                                Jogviszony lezárva ({{ $latestMealSetting->valid_to?->format('Y.m.d.') ?? '—' }})
                                            @else
                                                Nem étkező
                                            @endif
                                            @break

                                        @case('meal_package')
                                            @if(!$currentMealSetting)
                                                —
                                            @elseif($currentMealSetting->mode === \App\Models\StudentMealSetting::MODE_INSTITUTION_DEFAULT)
                                                Alapértelmezett – {{ $defaultMealPackage?->name ?? 'nincs aktív csomag' }}
                                            @elseif($currentMealSetting->mode === \App\Models\StudentMealSetting::MODE_PACKAGE)
                                                {{ $currentMealSetting->mealPackage?->name ?? '—' }}
                                            @else
                                                Egyedi – {{ $currentMealSetting->mealTypes->map(fn ($mealType) => $mealType->mealType->name)->implode(', ') ?: '—' }}
                                            @endif
                                            @break

                                        @case('discount')
                                            @if($activeDiscountType)
                                                {{ $activeDiscountType->percentage }}% – {{ $activeDiscountType->name }}
                                            @else
                                                Nincs beállítva
                                            @endif
                                            @break

                                        @case('diet')
                                            {{ $child->dietaryRestrictions->pluck('name')->implode(', ') ?: 'Nincs' }}
                                            @break

                                        @case('guardian_names')
                                            {{ $child->guardians->map(fn ($g) => $g->full_name)->filter()->implode(', ') ?: '—' }}
                                            @break

                                        @case('guardian_phones')
                                            {{ $child->guardians->pluck('phone')->filter()->implode(', ') ?: '—' }}
                                            @break

                                        @case('guardian_emails')
                                            {{ $child->guardians->pluck('email')->filter()->implode(', ') ?: '—' }}
                                            @break

                                        @case('guardian_address')
                                            {{ $child->guardians->map($formatGuardianAddress)->filter()->implode(' | ') ?: '—' }}
                                            @break

                                        @case('billing_address')
                                            {{ $formatBillingAddress($billingProfile) ?: '—' }}
                                            @break

                                        @case('barcode_status')
                                            {{ $child->barcodeStatusLabel() }}
                                            @break

                                        @case('source_type')
                                            {{ $sourceTypeLabel($child->source_type) }}
                                            @break
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <p class="no-data">Nincs a szűrésnek megfelelő aktív gyermek.</p>
        @endforelse
    </div>
</body>
</html>
