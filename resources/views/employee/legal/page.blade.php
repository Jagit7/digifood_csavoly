@extends('layouts.employee')

@section('page_title', $title)

@push('styles')
    <style>
        .df-legal-page .df-legal-shell {
            max-width: 920px;
            margin: 0 auto;
        }

        .df-legal-page .df-legal-card,
        .df-legal-page .df-legal-note,
        .df-legal-page .df-legal-merchant {
            border: 0;
            border-radius: 1.5rem;
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
        }

        .df-legal-page .df-legal-content {
            color: #334155;
            font-size: 1rem;
            line-height: 1.75;
        }

        .df-legal-page .df-legal-content h2,
        .df-legal-page .df-legal-content h3 {
            color: #0f172a;
            margin-top: 2rem;
            margin-bottom: 0.85rem;
        }

        .df-legal-page .df-legal-content h2:first-child,
        .df-legal-page .df-legal-content h3:first-child {
            margin-top: 0;
        }

        .df-legal-page .df-legal-content p,
        .df-legal-page .df-legal-content ul,
        .df-legal-page .df-legal-content ol {
            margin-bottom: 1rem;
        }

        .df-legal-page .df-legal-content ul,
        .df-legal-page .df-legal-content ol {
            padding-left: 1.25rem;
        }

        .df-legal-page .df-legal-content strong {
            color: #0f172a;
        }

        .df-legal-page .df-legal-meta {
            color: #64748b;
        }

        .df-legal-page .df-legal-links a {
            text-decoration: none;
        }

        .df-legal-page .df-legal-logo {
            max-width: min(100%, 320px);
            height: auto;
        }
    </style>
@endpush

@section('content')
    <div class="df-legal-page">
        <div class="df-legal-shell">
            @include('layouts.partials.components.ui.page-header', [
                'title' => $title,
                'subtitle' => $subtitle,
                'buttons' => [
                    [
                        'url' => $backUrl,
                        'text' => 'Vissza',
                        'icon' => 'fa-solid fa-arrow-left',
                        'class' => 'btn btn-outline-primary',
                    ],
                ],
            ])

            <div class="card df-legal-note mb-4">
                <div class="card-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-4 d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger border" style="width:3rem;height:3rem;flex:0 0 3rem;">
                                    <i class="fa-solid fa-credit-card"></i>
                                </div>
                                <div>
                                    <h5 class="mb-2">CIB online fizetés és kereskedői adatok</h5>
                                    <p class="mb-2 text-muted">A kártyaadatok megadása kizárólag a CIB Bank biztonságos fizetőoldalán történik. A Digifood nem kér be, nem továbbít és nem tárol bankkártyaadatokat.</p>
                                    <div class="small text-body-secondary">A kereskedő, a {{ $merchant['name'] }} székhelyének országa és országkódja: {{ $merchant['country'] }}.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5 text-lg-end">
                            <img src="{{ asset('images/cib/cib-card-logos-hu.png') }}" alt="CIB Bank és elfogadott kártyák logói" class="df-legal-logo">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card df-legal-merchant mb-4">
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h5 class="mb-3">Kereskedő</h5>
                            <div class="df-legal-meta small text-uppercase fw-semibold mb-2">Hivatalos adatok</div>
                            <div class="fw-semibold">{{ $merchant['name'] }}</div>
                            @if($merchant['institution_name'] && $merchant['institution_name'] !== $merchant['name'])
                                <div class="text-muted">{{ $merchant['institution_name'] }}</div>
                            @endif
                            @if($merchant['address'])
                                <div class="mt-2">{{ $merchant['address'] }}</div>
                            @endif
                            @if($merchant['tax_number'])
                                <div>Adószám: {{ $merchant['tax_number'] }}</div>
                            @endif
                            @if($merchant['company_registration_number'])
                                <div>Cégjegyzékszám: {{ $merchant['company_registration_number'] }}</div>
                            @endif
                            @if($merchant['om_identifier'])
                                <div>OM azonosító: {{ $merchant['om_identifier'] }}</div>
                            @endif
                            <div>Ország: {{ $merchant['country'] }}</div>
                        </div>
                        <div class="col-lg-6">
                            <h5 class="mb-3">Kapcsolódó oldalak</h5>
                            <div class="df-legal-links d-flex flex-wrap gap-2">
                                @foreach($legalLinks as $link)
                                    <a href="{{ $link['route'] }}" class="btn btn-outline-primary btn-sm">{{ $link['label'] }}</a>
                                @endforeach
                            </div>
                            @if($merchant['email'] || $merchant['phone'])
                                <div class="mt-3">
                                    <div class="df-legal-meta small text-uppercase fw-semibold mb-2">Elérhetőség</div>
                                    @if($merchant['email'])
                                        <div>E-mail: <a href="mailto:{{ $merchant['email'] }}">{{ $merchant['email'] }}</a></div>
                                    @endif
                                    @if($merchant['phone'])
                                        <div>Telefon: {{ $merchant['phone'] }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card df-legal-card">
                <div class="card-body p-4 p-lg-5">
                    <div class="df-legal-content">
                        @include($contentView)
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
