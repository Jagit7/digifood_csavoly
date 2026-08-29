@extends('layouts.superadmin')

@section('title', 'Fizetési kötelezettségek – nyomtatható lista')

@php
    $periodLabel = $period->translatedFormat('Y. F');
    $groupColumnLabel = $institution->isKindergarten() ? 'Csoport' : 'Osztály';
    // Nem törhető szóköz az ezres tagolásban és a "Ft" előtt, hogy egy
    // összeg (pl. "26 840 Ft") soha ne törjön szét sortöréskor.
    $formatFt = fn ($amount) => number_format($amount, 0, ',', "\u{00A0}") . "\u{00A0}Ft";
@endphp

@push('styles')
<style>
    /* Fix oszlopszélességek és sortörés a fejlécben is - így a hosszú,
       hónapnévvel kezdődő fejlécek (pl. "2026. szeptemberi étkezések")
       sem feszítik szét a táblázatot, hanem két sorba törnek. Ugyanezek
       a szabályok élőben és nyomtatáskor is érvényesek, hogy a kettő
       ne térjen el egymástól. */
    .print-table { table-layout: fixed; width: 100%; font-size: .85rem; }
    .print-table th, .print-table td {
        white-space: normal;
        overflow-wrap: normal;
        word-break: normal;
        vertical-align: middle;
    }
    .print-table th { font-weight: 700; line-height: 1.25; font-size: .72rem; }
    .print-table tbody tr:nth-child(even) td { background: #f2f0fb; }
    .print-table tbody tr:nth-child(odd) td { background: #ffffff; }
    /* Biztonsági háló: a Név/Csoport oszlopok adatcelláiban (nem a
       fejlécben!) egy kivételesen hosszú, szóköz nélküli szó (pl. egy
       hosszú csoportnév) inkább törjön, mintsem hogy kilógjon és
       átfedje a szomszédos oszlopot. */
    .print-table td:nth-child(2), .print-table td:nth-child(3) {
        overflow-wrap: anywhere;
    }
    .print-table th:nth-child(1) { width:3%; }
    .print-table th:nth-child(2) { width:15%; }
    .print-table th:nth-child(3) { width:15%; }
    .print-table th:nth-child(4) { width:5%; }
    .print-table th:nth-child(5) { width:9%; }
    .print-table th:nth-child(6) { width:9%; }
    .print-table th:nth-child(7) { width:8%; }
    .print-table th:nth-child(8) { width:8%; }
    .print-table th:nth-child(9) { width:8%; }
    .print-table th:nth-child(10) { width:9%; }
    .print-table th:nth-child(11) { width:11%; }
    @page { size: A4 landscape; margin: 12mm; }
    @media print {
        #main-wrapper > .nav-header, #main-wrapper > .header, #main-wrapper > .deznav, .footer,
        .no-print, #preloader { display:none !important; }
        html, body, #main-wrapper, .content-body, .content-body > .container-fluid {
            width:100% !important;
            max-width:none !important;
            min-width:0 !important;
            margin:0 !important;
            padding-left:0 !important;
            padding-right:0 !important;
        }
        .content-body { padding-top:0 !important; }
        .card { box-shadow:none !important; border:1px solid #ddd; }
        .print-table-wrap {
            display:block !important;
            width:100% !important;
            max-width:none !important;
            overflow:visible !important;
        }
        .print-table {
            width:100% !important;
            min-width:0 !important;
            font-size:10pt;
        }
        .print-table th, .print-table td {
            padding:.6rem .6rem !important;
        }
        .print-table th {
            font-size:8pt !important;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h2 class="mb-1">Fizetési kötelezettségek – teljes lista</h2>
            <p class="text-muted mb-0">
                {{ $institution->name }} · {{ ucfirst($periodLabel) }}
            </p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('dashboard.institution.payment-obligations.index', ['month' => $period->format('Y-m')]) }}"
               class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Vissza a listához
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Nyomtatás
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-3">
            @if($statements->count())
                <div class="print-table-wrap">
                    <table class="table table-bordered align-middle print-table mb-0">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Név</th>
                            <th>{{ $groupColumnLabel }}</th>
                            <th>Étk. napok</th>
                            <th>Havi étkezés</th>
                            <th>Jóváírás</th>
                            <th>Korrekció</th>
                            <th>Előírás</th>
                            <th>Egyenleg</th>
                            <th>Nettó fizetendő</th>
                            <th>Bruttó fizetendő</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($statements as $statement)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $statement->child->name }}</strong></td>
                                <td>{{ $statement->child->group_name ?: '—' }}</td>
                                <td>{{ $statement->days->where('payable_amount', '>', 0)->count() }}</td>
                                <td>{{ $formatFt($statement->meal_amount) }}</td>
                                <td>{{ $formatFt($statement->previous_cancellation_credit) }}</td>
                                <td>{{ $formatFt($statement->billing_adjustment_amount) }}</td>
                                <td>{{ $formatFt($statement->invoiceable_amount) }}</td>
                                <td>{{ $formatFt($statement->previous_balance) }}</td>
                                <td><strong>{{ $formatFt($statement->total_payable) }}</strong></td>
                                <td><strong>{{ $formatFt($institutionSetting->grossAmount($statement->total_payable)) }}</strong></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                @include('layouts.partials.components.ui.empty-state', [
                    'icon' => 'fa-solid fa-file-invoice-dollar',
                    'title' => 'Ehhez a hónaphoz még nincs kimutatás',
                    'text' => 'Indíts újraszámítást a listaoldalon, majd térj vissza ide a nyomtatáshoz.',
                ])
            @endif
        </div>
    </div>
</div>
@endsection
